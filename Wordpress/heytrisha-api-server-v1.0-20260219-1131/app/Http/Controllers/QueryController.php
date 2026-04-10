<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SQLGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NEW SECURE Query Controller
 * 
 * This controller:
 * 1. Gets OpenAI key from site record (encrypted in database)
 * 2. Uses OpenAI to generate SQL from user question
 * 3. Sends SQL to WordPress site for execution
 * 4. Returns formatted results to user
 * 
 * NO direct database access - all queries executed by WordPress
 */
class QueryController extends Controller
{
    protected $sqlGenerator;

    public function __construct(SQLGeneratorService $sqlGenerator)
    {
        $this->sqlGenerator = $sqlGenerator;
    }

    /**
     * Process user query
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function process(Request $request)
    {
        // Get site from middleware (injected by ApiKeyMiddleware)
        $site = $request->get('site');

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found in request'
            ], 500);
        }

        // Validate request
        $validator = \Validator::make($request->all(), [
            'question' => 'required|string|min:3',
            'context' => 'nullable|string',
            'schema' => 'nullable|array', // Accept schema from plugin
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $question = $request->input('question');
        $context = $request->input('context', 'woocommerce');
        $schema = $request->input('schema'); // Get schema from request

        try {
            // Step 1: Get database schema (from request or fetch from WordPress)
            if (!$schema || empty($schema)) {
                // Fallback: Get schema from WordPress if not provided
                $schema = $this->getSchemaFromWordPress($site);
                
                if (!$schema) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to get database schema. Please provide schema in request or ensure WordPress is accessible.'
                    ], 500);
                }
            }

            // Step 2: Use OpenAI to generate SQL (using OpenAI key from API site database)
            $openaiKey = $site->getOpenAIKey();

            if (!$openaiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI API key not configured for this site'
                ], 500);
            }

            $sqlResponse = $this->sqlGenerator->queryChatGPTForSQL(
                $question,
                $schema,
                $openaiKey
            );

            if (isset($sqlResponse['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate SQL: ' . $sqlResponse['error']
                ], 500);
            }

            $sql = $sqlResponse['query'] ?? null;

            if (empty($sql)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SQL query generated'
                ], 500);
            }

            // Step 3: Increment query count
            $site->incrementQueryCount();

            // Step 4: Return SQL only (plugin will execute it locally)
            return response()->json([
                'success' => true,
                'sql' => $sql,
                'message' => 'SQL query generated successfully. Execute this query on your WordPress database.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Query processing failed', [
                'site_url' => $site->site_url,
                'question' => $question,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Query processing failed: ' . $e->getMessage()
            ], 500);
        }
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
            return "I couldn't find any results for: " . $question;
        }

        if ($count === 1) {
            return "I found 1 result for your question.";
        }

        return "I found {$count} results for your question.";
    }
}



