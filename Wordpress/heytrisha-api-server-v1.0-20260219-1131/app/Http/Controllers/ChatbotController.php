<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WordPressConfigService;
use App\Support\OpenAiKeyResolver;
use App\Support\ChatErrorMessages;
use App\Support\SearchSynonyms;
use App\Support\SqlReadOnlyValidator;
use OpenAI;

class ChatbotController extends Controller
{
    protected $configService;

    public function __construct(WordPressConfigService $configService)
    {
        $this->configService = $configService;
    }
    /**
     * Fetch the database schema.
     */
    public function getDbSchema()
        {
            try {
                $tables = DB::select('SHOW TABLES');
                $schema = [];

                foreach ($tables as $table) {
                    $tableName = array_values((array)$table)[0];
                    $columns = DB::select("DESCRIBE $tableName");
                    $schema[$tableName] = array_map(fn($col) => $col->Field, $columns);
                }

                return $schema;
            } catch (\Exception $e) {
                Log::error("Schema Fetch Error: {$e->getMessage()}");
                return ['error' => $e->getMessage()];
            }
        }


    /**
     * Generate SQL query using ChatGPT.
     */
    private function queryChatGPTForSql($userQuery, $schema, string $openaiKey = '')
    {
        $schemaStr = collect($schema)
            ->map(fn ($columns, $table) => "Table: $table, Columns: " . implode(', ', $columns))
            ->implode("\n");

        $prompt = <<<EOD
Database Schema:
$schemaStr

User Query: $userQuery

SECURITY & PRIVACY RULES:
- Only generate MySQL SELECT queries.
- Never query or reference sensitive tables/columns such as: users, usermeta, options, woocommerce_payment_tokens, woocommerce_payment_tokenmeta, woocommerce_api_keys, woocommerce_sessions, user_pass, password, secret, token, api_key.
- If the user asks for secrets/passwords/cards, return a safe analytics query (aggregates) or a SELECT that returns 0 rows.

Output only the SQL query. Do not include explanations, context, or any other text. Strictly return the SQL query itself.
EOD;

        try {
            $apiKey = $openaiKey;
            if (empty($apiKey)) {
                Log::error("OpenAI API Key is missing!");
                return ['error' => 'OpenAI API Key is missing. Please configure it in the HeyTrisha plugin settings and save.'];
            }
            
            $response = OpenAI::client($apiKey)->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You generate safe MySQL SELECT queries for analytics. Never return secrets or access sensitive tables. Output SQL only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $sqlQuery = trim($response['choices'][0]['message']['content']);
            $sqlQuery = str_replace(['```sql', '```'], '', $sqlQuery);
            return $sqlQuery;
        } catch (\Exception $e) {
            Log::error("OpenAI Query Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Execute SQL query and fetch results.
     */
    private function executeSqlQuery($sqlQuery)
    {
        try {
            $validation = SqlReadOnlyValidator::validate($sqlQuery);
            if (!$validation['valid']) {
                return ['error' => ChatErrorMessages::fromSqlValidation($validation)];
            }

            // Defense-in-depth: block obvious sensitive tables.
            $normalized = strtolower(trim($sqlQuery));
            $blocked = [' users', ' usermeta', ' options', ' woocommerce_payment_tokens', ' woocommerce_payment_tokenmeta', ' woocommerce_api_keys', ' woocommerce_sessions'];
            foreach ($blocked as $marker) {
                if (strpos($normalized, $marker) !== false) {
                    return ['error' => ChatErrorMessages::sensitiveTable()];
                }
            }

            $result = DB::select($sqlQuery);
            if ($result === []) {
                return ['message' => ChatErrorMessages::emptyQueryResults('', $sqlQuery)];
            }
            return $result;
        } catch (\Exception $e) {
            Log::error("SQL Execution Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Handle user queries: generate SQL, execute it, and return results.
     */
    public function processQuery(Request $request)
    {
        try {
            $userQuery = $request->input('query');
            if (!$userQuery) {
                return response()->json(['error' => 'No query provided'], 400);
            }

            // Resolve OpenAI key from plugin header or site DB (never .env)
            $site = $request->get('site');
            $openaiKey = $site ? OpenAiKeyResolver::forPluginRequest($request, $site) : null;
            if (empty($openaiKey)) {
                return response()->json(['error' => 'OpenAI API Key is not configured. Please set it in the HeyTrisha plugin settings and save.'], 500);
            }

            // Fetch schema
            $schema = $this->getDbSchema();
            if (isset($schema['error'])) {
                return response()->json(['error' => $schema['error']], 500);
            }

            // Generate SQL query
            $sqlQuery = $this->queryChatGPTForSql($userQuery, $schema, $openaiKey);
            if (isset($sqlQuery['error'])) {
                return response()->json(['error' => $sqlQuery['error']], 500);
            }

            // Execute SQL query
            $result = $this->executeSqlQuery($sqlQuery);
            return response()->json(['reply' => $result]);
        } catch (\Exception $e) {
            Log::error("Process Query Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
