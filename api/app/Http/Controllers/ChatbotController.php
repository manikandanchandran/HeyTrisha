<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI;

class ChatbotController extends Controller
{
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
    private function queryChatGPTForSql($userQuery, $schema)
    {
        $schemaStr = collect($schema)
            ->map(fn ($columns, $table) => "Table: $table, Columns: " . implode(', ', $columns))
            ->implode("\n");

        $prompt = <<<EOD
Database Schema:
$schemaStr

User Query: $userQuery

Output only the SQL query. Do not include explanations, context, or any other text. Strictly return the SQL query itself.
EOD;

        try {
            $response = OpenAI::client(env('OPENAI_API_KEY'))->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a SQL query assistant. Your task is to generate SQL queries.'],
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
            if (str_starts_with(strtolower($sqlQuery), 'select')) {
                $result = DB::select($sqlQuery);
                return $result ?: ['message' => 'No results found.'];
            } else {
                $rowsAffected = DB::statement($sqlQuery);
                return ['status' => 'success', 'rows_affected' => $rowsAffected];
            }
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

            // Fetch schema
            $schema = $this->getDbSchema();
            if (isset($schema['error'])) {
                return response()->json(['error' => $schema['error']], 500);
            }

            // Generate SQL query
            $sqlQuery = $this->queryChatGPTForSql($userQuery, $schema);
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
