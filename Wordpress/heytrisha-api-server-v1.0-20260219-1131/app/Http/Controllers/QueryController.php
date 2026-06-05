<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\CompareInsightsService;
use App\Services\GlobalInsightsService;
use App\Services\HybridKnowledgeService;
use App\Services\HybridAnswerRouterService;
use App\Services\SQLGeneratorService;
use App\Services\SpecificationIngestService;
use App\Support\ChatErrorMessages;
use App\Support\OpenAiKeyResolver;
use App\Support\SqlReadOnlyValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NEW SECURE Query Controller
 *
 * This controller:
 * 1. Gets OpenAI key from plugin header (preferred) or site record — not from .env
 * 2. Retrieves top-k specification chunks (when specification mode is active)
 * 3. Uses OpenAI to generate SQL constrained by the effective schema + spec context
 * 4. Returns SQL to WordPress plugin for local execution
 *
 * NO direct database access — all queries executed by WordPress.
 */
class QueryController extends Controller
{
    protected SQLGeneratorService        $sqlGenerator;
    protected SpecificationIngestService $specService;
    protected HybridAnswerRouterService  $router;
    protected GlobalInsightsService      $globalInsights;
    protected CompareInsightsService     $compareInsights;
    protected HybridKnowledgeService     $hybridKnowledge;

    public function __construct(
        SQLGeneratorService        $sqlGenerator,
        SpecificationIngestService $specService,
        HybridAnswerRouterService  $router,
        GlobalInsightsService      $globalInsights,
        CompareInsightsService     $compareInsights,
        HybridKnowledgeService     $hybridKnowledge
    ) {
        $this->sqlGenerator = $sqlGenerator;
        $this->specService  = $specService;
        $this->router          = $router;
        $this->globalInsights  = $globalInsights;
        $this->compareInsights = $compareInsights;
        $this->hybridKnowledge  = $hybridKnowledge;
    }

    /**
     * Process user query.
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function process(Request $request)
    {
        // Site is injected by ApiKeyMiddleware
        $site = $request->get('site');

        if (!$site) {
            return response()->json(['success' => false, 'message' => 'Site not found in request'], 500);
        }

        // Validate request
        $validator = \Validator::make($request->all(), [
            'question'              => 'required|string|min:3',
            'context'               => 'nullable|string',
            'schema'                => 'nullable|array',
            'specification_active'  => 'nullable|boolean',
            'specification_version' => 'nullable|string',
            'uploaded_schema_enforced' => 'nullable|boolean',
            'mode'                          => 'nullable|string|in:auto,local,global,compare',
            'global_mode'                   => 'nullable|string|in:evidence_grounded,model_only,both',
            'hybrid_architecture_enabled'   => 'nullable|boolean',
            'chat_id'                       => 'nullable|integer',
            'conversation_history'          => 'nullable|array',
            'conversation_history.*.role'   => 'required_with:conversation_history|string|in:user,assistant',
            'conversation_history.*.content'=> 'required_with:conversation_history|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 400);
        }

        $question             = $request->input('question');
        $blockedIntent        = ChatErrorMessages::blockedIntentFromQuery((string) $question);
        if ($blockedIntent !== null) {
            return response()->json([
                'success' => false,
                'message' => $blockedIntent,
            ], 400);
        }

        $schema               = $request->input('schema');
        $specificationActive  = (bool) $request->input('specification_active', false);
        $mode                     = $request->input('mode', 'auto');
        $globalMode               = $request->input('global_mode', 'both');
        $hybridArchitectureEnabled = filter_var(
            $request->input('hybrid_architecture_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $conversationHistory = $this->normalizeConversationHistory(
            $request->input('conversation_history', [])
        );

        try {
            // Step 1: OpenAI key — plugin header first, then API DB (never .env on this path)
            $openaiKey = OpenAiKeyResolver::forPluginRequest($request, $site);

            if (!$openaiKey) {
                return response()->json([
                    'success' => false,
                    'message' => ChatErrorMessages::openAiNotConfigured(),
                ], 500);
            }

            // Step 2: Route request (local/global/compare/hybrid)
            $route = $this->router->route($question, $mode, $hybridArchitectureEnabled);
            $type  = $route['type'] ?? 'local';

            $questionWithContext = $this->augmentQuestionWithConversationHistory($question, $conversationHistory);

            // Global-only: no schema needed
            if ($type === 'global') {
                $global = $this->globalInsights->getGlobalInsights($questionWithContext, $openaiKey, (string) $globalMode);

                $site->incrementQueryCount();

                return response()->json([
                    'success'        => true,
                    'type'           => 'global',
                    'answer'         => $global['answer'] ?? '',
                    'global_sources' => $global['sources'] ?? [],
                    'message'        => $global['answer'] ?? '',
                    'global_mode_used' => $global['mode_used'] ?? '',
                ]);
            }

            // Step 3: Get schema (from request or fallback to WordPress) — only for local/compare
            if (!$schema || empty($schema)) {
                $schema = $this->getSchemaFromWordPress($site);

                if (!$schema) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to get database schema.',
                    ], 500);
                }
            }

            // Step 4: Build specification context when active
            $specificationContext = null;

            if ($specificationActive) {
                $specificationContext = $this->buildSpecificationContext(
                    $site, $question, $openaiKey
                );
            }

            // Step 5: Generate SQL (includes prior turns for follow-up questions)
            $sqlResponse = $this->sqlGenerator->queryChatGPTForSQL(
                $question,
                $schema,
                $openaiKey,
                $specificationContext,
                $conversationHistory
            );

            // Handle conversational refusal from spec-constrained generation
            if (isset($sqlResponse['refusal'])) {
                return response()->json([
                    'success' => true,
                    'type'    => 'conversation',
                    'message' => $sqlResponse['refusal'],
                ]);
            }

            if (isset($sqlResponse['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => ChatErrorMessages::fromOpenAiError((string) $sqlResponse['error']),
                ], 500);
            }

            $sql = $sqlResponse['query'] ?? null;

            if (empty($sql)) {
                return response()->json([
                    'success' => false,
                    'message' => ChatErrorMessages::sqlGenerationFailed(),
                ], 500);
            }

            $sqlValidation = SqlReadOnlyValidator::validate($sql);
            if (!$sqlValidation['valid']) {
                Log::warning('QueryController: generated SQL failed read-only validation', [
                    'error'    => $sqlValidation['error'],
                    'question' => $question,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => ChatErrorMessages::fromSqlValidation($sqlValidation),
                ], 400);
            }

            // Step 6: Post-generation table allowlist — schema is the source of truth
            $allowedTables = $this->allowedTableIdentifiersFromSchema($schema);
            if (!empty($allowedTables)) {
                $violation = $this->detectForbiddenTables($sql, $allowedTables);
                if ($violation) {
                    Log::warning('QueryController: SQL references table outside effective schema', [
                        'table'    => $violation,
                        'question' => $question,
                    ]);
                    return response()->json([
                        'success' => true,
                        'type'    => 'conversation',
                        'message' => ChatErrorMessages::schemaRestriction(),
                    ]);
                }
            }

            // Compare / general hybrid: local SQL on WP → global retrieval (with local context) → synthesis
            if ($type === 'compare' || $type === 'hybrid') {
                $localData = $this->executeQueryOnWordPress($site, $sql) ?? [];

                $globalQuestion = $this->augmentQuestionWithLocalContext($questionWithContext, is_array($localData) ? $localData : []);
                $global         = $this->globalInsights->getGlobalInsights($globalQuestion, $openaiKey, (string) $globalMode);

                if ($type === 'compare') {
                    $hybridOut = $this->compareInsights->compare(
                        $questionWithContext,
                        is_array($localData) ? $localData : [],
                        (string) ($global['answer'] ?? ''),
                        is_array($global['sources'] ?? null) ? $global['sources'] : [],
                        $openaiKey
                    );
                    $site->incrementQueryCount();

                    return response()->json([
                        'success'            => true,
                        'type'               => 'compare',
                        'hybrid_route_reason'=> $route['reason'] ?? '',
                        'sql'                => $sql,
                        'local_sql'          => $sql,
                        'local_data'         => is_array($localData) ? $localData : [],
                        'answer'             => $hybridOut['answer'] ?? '',
                        'comparison'         => $hybridOut['comparison'] ?? [],
                        'global_sources'     => $global['sources'] ?? [],
                        'message'            => $hybridOut['answer'] ?? '',
                        'global_mode_used'   => $global['mode_used'] ?? '',
                    ]);
                }

                $knowledge = $this->hybridKnowledge->synthesize(
                    $questionWithContext,
                    is_array($localData) ? $localData : [],
                    (string) ($global['answer'] ?? ''),
                    is_array($global['sources'] ?? null) ? $global['sources'] : [],
                    $openaiKey
                );

                $site->incrementQueryCount();

                return response()->json([
                    'success'             => true,
                    'type'                => 'hybrid',
                    'hybrid_route_reason' => $route['reason'] ?? '',
                    'sql'                 => $sql,
                    'local_sql'           => $sql,
                    'local_data'          => is_array($localData) ? $localData : [],
                    'answer'              => $knowledge['answer'] ?? '',
                    'hybrid_insights'     => $knowledge['insights'] ?? [],
                    'global_sources'      => $global['sources'] ?? [],
                    'message'             => $knowledge['answer'] ?? '',
                    'global_mode_used'    => $global['mode_used'] ?? '',
                ]);
            }

            // Local mode (default): keep original behavior, add type/local_sql for hybrid clients
            $site->incrementQueryCount();

            return response()->json([
                'success'    => true,
                'type'       => 'local',
                'sql'        => $sql,
                'local_sql'  => $sql,
                'message'    => 'SQL query generated successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Query processing failed', [
                'site_url' => $site->site_url,
                'question' => $question,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => ChatErrorMessages::fromException($e),
            ], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Specification context builder
    // -----------------------------------------------------------------------

    /**
     * Retrieve top-k spec chunks for the question and assemble context for the prompt.
     *
     * @return array{chunks: string[], rules_summary: string, allowed_table_names: string[]}
     */
    private function buildSpecificationContext(Site $site, string $question, string $openaiKey): array
    {
        try {
            $chunks       = $this->specService->retrieveChunks($site, $question, $openaiKey, 5);
            $rulesSummary = $this->specService->getRulesSummary($site);
            $allowlist    = $this->specService->getAllowlist($site);

            // Build list of all allowed full table names (for post-check)
            $allowedNames = [];
            if ($allowlist) {
                foreach (array_keys($allowlist) as $suffix) {
                    $allowedNames[] = $suffix; // suffix without WP prefix
                }
            }

            return [
                'chunks'              => $chunks,
                'rules_summary'       => $rulesSummary,
                'allowed_table_names' => $allowedNames,
                'allowlist'           => $allowlist ?? [],
            ];
        } catch (\Exception $e) {
            Log::warning('QueryController: spec context build failed — proceeding without spec', [
                'error' => $e->getMessage(),
            ]);
            return ['chunks' => [], 'rules_summary' => '', 'allowed_table_names' => [], 'allowlist' => []];
        }
    }

    /**
     * Allowed table identifiers derived from the effective schema (full names + stripped suffixes).
     * Used so generated SQL cannot reference tables omitted from the schema sent to the model.
     *
     * @param  array<string, mixed> $schema
     * @return list<string>
     */
    private function normalizeTableIdentifier(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[\s\-]+/', '_', $name);

        return (string) preg_replace('/[^a-z0-9_]/', '', $name);
    }

    private function allowedTableIdentifiersFromSchema(array $schema): array
    {
        $out = [];
        foreach (array_keys($schema) as $table) {
            if ($table === '_heytrisha_spec_error' || !\is_string($table) || $table === '') {
                continue;
            }
            $out[] = $table;
            $out[] = $this->normalizeTableIdentifier($table);
            $suffix = preg_replace('/^wp\w*_\d+_/', '', $table);
            $suffix = preg_replace('/^wp_/', '', (string) $suffix);
            if ($suffix !== '' && strcasecmp($suffix, $table) !== 0) {
                $out[] = $suffix;
                $out[] = $this->normalizeTableIdentifier($suffix);
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    // -----------------------------------------------------------------------
    // Table allowlist post-check
    // -----------------------------------------------------------------------

    /**
     * Scan generated SQL for FROM / JOIN table references and check against allowed suffixes.
     * Returns the first disallowed table name found, or null if all are allowed.
     */
    private function detectForbiddenTables(string $sql, array $allowedSuffixes): ?string
    {
        if (empty($allowedSuffixes)) {
            return null;
        }

        // Extract table identifiers after FROM and JOIN (word or backtick-quoted names)
        preg_match_all('/\b(?:FROM|JOIN)\s+(?:`([^`]+)`|(\w+))/i', $sql, $matches);
        $tables = [];
        foreach ($matches[1] as $i => $quoted) {
            $tables[] = $quoted !== '' ? $quoted : ($matches[2][$i] ?? '');
        }

        $normalizedAllowed = array_map(
            fn (string $id) => $this->normalizeTableIdentifier($id),
            $allowedSuffixes
        );

        foreach ($tables as $table) {
            if ($table === '') {
                continue;
            }

            // Strip any numeric WP prefix (e.g. wp53_5_wc_orders → wc_orders)
            $suffix = preg_replace('/^wp\w*_\d+_/', '', $table);
            $suffix = preg_replace('/^wp_/', '', $suffix);

            $candidates = [
                $this->normalizeTableIdentifier($table),
                $this->normalizeTableIdentifier($suffix),
            ];

            $found = false;
            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                if (in_array($candidate, $normalizedAllowed, true)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return $table;
            }
        }

        return null;
    }

    /**
     * @param mixed $raw
     * @return array<int, array{role: string, content: string}>
     */
    private function normalizeConversationHistory($raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $role = strtolower(trim((string) ($turn['role'] ?? '')));
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = trim(strip_tags((string) ($turn['content'] ?? '')));
            if ($content === '') {
                continue;
            }
            if (strlen($content) > 800) {
                $content = substr($content, 0, 800) . '…';
            }
            $out[] = ['role' => $role, 'content' => $content];
        }

        $max = 20;
        if (count($out) > $max) {
            $out = array_slice($out, -$max);
        }

        return $out;
    }

    /**
     * Prepend prior turns so follow-ups ("show more", "those products") resolve correctly.
     *
     * @param array<int, array{role: string, content: string}> $history
     */
    private function augmentQuestionWithConversationHistory(string $question, array $history): string
    {
        if ($history === []) {
            return $question;
        }

        $lines = ["Prior conversation (use for context; answer the CURRENT question at the end):"];
        foreach ($history as $turn) {
            $label = $turn['role'] === 'user' ? 'User' : 'Assistant';
            $lines[] = "{$label}: {$turn['content']}";
        }
        $lines[] = '';
        $lines[] = "CURRENT question: {$question}";

        return implode("\n", $lines);
    }

    /**
     * Append a compact JSON snapshot of local rows so retrieval/synthesis can target real product/category names.
     *
     * @param array<int, mixed> $localData
     */
    private function augmentQuestionWithLocalContext(string $question, array $localData): string
    {
        $json = json_encode($localData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return $question;
        }
        if (strlen($json) > 4000) {
            $json = substr($json, 0, 4000) . '…';
        }

        return $question . "\n\n--- STORE PRODUCT DATA (from merchant database; use for identity, names, categories, attributes) ---\n" . $json;
    }

    /**
     * Get database schema from WordPress
     *
     * @param Site $site
     * @return array|null
     */
    protected function getSchemaFromWordPress(Site $site)
    {
        try {
            $response = Http::withHeaders([
                'X-HeyTrisha-API-Key' => $site->api_key_hash, // Send hash for verification
            ])->timeout(30)->get($site->site_url . '/wp-json/heytrisha/v1/schema');

            if ($response->failed()) {
                Log::error('Failed to get schema from WordPress', [
                    'site_url' => $site->site_url,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (!isset($data['success']) || !$data['success']) {
                Log::error('WordPress returned error for schema', [
                    'site_url' => $site->site_url,
                    'data' => $data
                ]);
                return null;
            }

            return $data['tables'] ?? [];

        } catch (\Exception $e) {
            Log::error('Exception getting schema from WordPress', [
                'site_url' => $site->site_url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Execute SQL query on WordPress
     *
     * @param Site $site
     * @param string $sql
     * @return array|null
     */
    protected function executeQueryOnWordPress(Site $site, $sql)
    {
        $validation = SqlReadOnlyValidator::validate($sql);
        if (!$validation['valid']) {
            Log::warning('QueryController: refused to send non-read-only SQL to WordPress', [
                'error'    => $validation['error'],
                'site_url' => $site->site_url,
            ]);
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-HeyTrisha-API-Key' => $site->api_key_hash,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($site->site_url . '/wp-json/heytrisha/v1/execute-sql', [
                'sql' => $sql,
                'max_limit' => 1000,
            ]);

            if ($response->failed()) {
                Log::error('Failed to execute query on WordPress', [
                    'site_url' => $site->site_url,
                    'sql' => $sql,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (!isset($data['success']) || !$data['success']) {
                Log::error('WordPress returned error for query', [
                    'site_url' => $site->site_url,
                    'sql' => $sql,
                    'data' => $data
                ]);
                return null;
            }

            return $data['data'] ?? [];

        } catch (\Exception $e) {
            Log::error('Exception executing query on WordPress', [
                'site_url' => $site->site_url,
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Format answer from data
     *
     * @param string $question
     * @param array $data
     * @return string
     */
    protected function formatAnswer($question, $data)
    {
        // Simple formatting - can be enhanced with OpenAI later
        $count = count($data);

        if ($count === 0) {
            return ChatErrorMessages::emptyQueryResults($question);
        }

        if ($count === 1) {
            return "I found 1 result for your question.";
        }

        return "I found {$count} results for your question.";
    }
}



