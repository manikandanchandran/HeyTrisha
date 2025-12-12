<?php

// Fetching Working Code 02/01/2025 12:00 PM

// namespace App\Services;

// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;

// class SQLGeneratorService
// {
//     public function queryChatGPTForSQL($userQuery, $schema)
//     {
//         // ✅ Build schema string dynamically
//         $schemaStr = '';
//         foreach ($schema as $table => $columns) {
//             $schemaStr .= "Table: `$table` (Columns: " . implode(', ', $columns) . ")\n";
//         }

//         // ✅ Updated Prompt (No hardcoded JSON)
//         $prompt = "
//         You are an AI that generates SQL queries based on the given database schema.

//         Database Schema:
//         $schemaStr

//         User Query: \"$userQuery\"

//         Write the correct SQL query for the above user request.
//         - **Only return the raw SQL query**.  
//         - **Do NOT include explanations, context, or formatting**.  
//         - **Do NOT wrap the response in JSON**.  
//         ";

//         try {
//             $response = Http::withHeaders([
//                 'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
//                 'Content-Type' => 'application/json'
//             ])->post('https://api.openai.com/v1/chat/completions', [
//                 'model' => 'gpt-4',
//                 'messages' => [
//                     ['role' => 'system', 'content' => 'You are an AI that generates correct MySQL queries.'],
//                     ['role' => 'user', 'content' => $prompt],
//                 ],
//                 'max_tokens' => 500
//             ]);
        
//             $openAIResponse = $response->json();
        
//             // ✅ Log the complete OpenAI response
//             Log::info("OpenAI API Response: " . json_encode($openAIResponse));
        
//             // ✅ Validate if 'choices' exists and has content
//             if (!isset($openAIResponse['choices'][0]['message']['content'])) {
//                 Log::error("Invalid OpenAI response: " . json_encode($openAIResponse));
//                 return ['error' => 'Invalid OpenAI response format'];
//             }
        
//             $sqlQuery = trim($openAIResponse['choices'][0]['message']['content']);
        
//             // ✅ Check if SQL query starts with valid SQL keywords
//             if (!preg_match('/^(SELECT|INSERT|UPDATE|DELETE)/i', $sqlQuery)) {
//                 Log::error("Invalid SQL query generated: " . $sqlQuery);
//                 return ['error' => 'Generated SQL query is invalid'];
//             }
        
//             return ['query' => $sqlQuery];
        
//         } catch (\Exception $e) {
//             Log::error("OpenAI API Error: " . $e->getMessage());
//             return ['error' => "OpenAI API request failed: " . $e->getMessage()];
//         }
        
//     }
// }

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\WordPressConfigService;

class SQLGeneratorService
{
    protected $configService;

    public function __construct(WordPressConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * ✅ Generate SQL query using OpenAI NLP
     * Sends FULL user input + FULL database schema to OpenAI
     * OpenAI uses NLP to understand the query and generate appropriate SQL
     * 
     * @param string $userQuery The natural language query from user
     * @param array $schema Complete database schema (all tables, all columns)
     * @return array Contains 'query' (SQL) or 'error'
     */
    public function queryChatGPTForSQL($userQuery, $schema)
    {
        Log::info("🔍 Starting NLP SQL Generation");
        Log::info("📝 User Query: " . $userQuery);
        Log::info("📊 Schema: " . count($schema) . " tables");

        // Build compact schema string - most efficient format
        // Format: table(column1,column2,...) - minimal tokens
        $schemaStr = "";
        $tableList = [];
        
        foreach ($schema as $table => $columns) {
            // Compact format: table(column1,column2,...)
            $schemaStr .= "$table(" . implode(',', $columns) . ")\n";
            $tableList[] = $table;
        }

        // Pure NLP prompt - no assumptions, no hints, just facts
        $prompt = "Generate MySQL SELECT query.\n\n" .
                  "User request: \"$userQuery\"\n\n" .
                  "Database has " . count($tableList) . " tables.\n\n" .
                  "Schema:\n$schemaStr\n" .
                  "Generate SQL using ONLY tables/columns from schema above.\n" .
                  "SQL:";

        try {
            $apiKey = $this->configService->getOpenAIApiKey();
            if (!$apiKey) {
                Log::error("OpenAI API Key is missing!");
                return ['error' => 'OpenAI API Key is missing. Please configure it in the WordPress admin settings.'];
            }

            // Use gpt-3.5-turbo for better rate limits (1M tokens/min vs 10K for gpt-4)
            // Still excellent for SQL generation and handles NLP well
            $model = 'gpt-3.5-turbo';
            
            // Calculate approximate token count (rough estimate: 1 token ≈ 4 characters)
            $estimatedTokens = strlen($prompt) / 4;
            Log::info("📊 Estimated prompt tokens: ~" . round($estimatedTokens));
            
            // Check if prompt is too large for model context window
            // gpt-3.5-turbo has 16,385 token context, we need to leave room for response
            if ($estimatedTokens > 14000) {
                Log::error("❌ Prompt too large (" . round($estimatedTokens) . " tokens). Maximum is ~14,000 tokens.");
                return [
                    'error' => 'The database schema is too large for this query. Please try a more specific query. ' .
                              'For example: "Show posts from last week" instead of "Show all posts".'
                ];
            }
            
            if ($estimatedTokens > 10000) {
                Log::warning("⚠️ Prompt is large (" . round($estimatedTokens) . " tokens). Consider more specific queries.");
            }
            
            // Adjust max_tokens based on model
            $maxTokens = 300;
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert SQL developer. Analyze the user\'s natural language request and the database schema provided. Generate a precise MySQL SELECT query. Use ONLY table and column names from the schema. Return ONLY the SQL query - no explanations, no markdown, no code blocks.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => 0.1 // Low temperature for consistent, accurate SQL
            ]);

            // Check if HTTP request failed
            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error("OpenAI API HTTP Error: " . $response->status() . " - " . $errorBody);
                return ['error' => 'OpenAI API request failed: ' . $errorBody];
            }

            $openAIResponse = $response->json();

            // Log the full response for debugging
            Log::info("OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

            // Check for API errors in response
            if (isset($openAIResponse['error'])) {
                $error = $openAIResponse['error'];
                $errorCode = $error['code'] ?? 'unknown';
                $errorMsg = $error['message'] ?? json_encode($error);
                
                Log::error("OpenAI API Error in response: " . $errorMsg);
                
                // Handle rate limit errors specifically
                if ($errorCode === 'rate_limit_exceeded' || strpos($errorMsg, 'rate_limit') !== false || strpos($errorMsg, 'TPM') !== false) {
                    Log::error("Rate limit exceeded. Estimated tokens: ~" . round(strlen($prompt) / 4));
                    return [
                        'error' => 'Request too large for OpenAI API. The query requires too many tokens. ' .
                                  'Please try a more specific query (e.g., "Show sales for last month" instead of "Show all sales data"). ' .
                                  'Or wait a moment and try again.'
                    ];
                }
                
                // Handle token limit errors
                if (strpos($errorMsg, 'too large') !== false || strpos($errorMsg, 'tokens') !== false) {
                    return [
                        'error' => 'Request too large for OpenAI. Please try a more specific query. ' .
                                  'For example: "Show sales for last 30 days" instead of "Show all sales data".'
                    ];
                }
                
                return ['error' => 'OpenAI API error: ' . $errorMsg];
            }

            // Check if the response structure is valid
            if (!isset($openAIResponse['choices'])) {
                Log::error("OpenAI response missing 'choices': " . json_encode($openAIResponse));
                return ['error' => 'OpenAI response format is invalid: missing choices array'];
            }

            if (empty($openAIResponse['choices'])) {
                Log::error("OpenAI response has empty choices array");
                return ['error' => 'OpenAI response format is invalid: empty choices array'];
            }

            // Check if the response contains the expected data
            if (!isset($openAIResponse['choices'][0]['message']['content'])) {
                Log::error("OpenAI response format is invalid or content is missing. Full response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));
                return ['error' => 'OpenAI response format is invalid or incomplete'];
            }

            // Extract the SQL query
            $sqlQuery = trim($openAIResponse['choices'][0]['message']['content']);
            
            // Remove markdown code blocks if present
            $sqlQuery = preg_replace('/^```sql\s*/i', '', $sqlQuery);
            $sqlQuery = preg_replace('/^```\s*/i', '', $sqlQuery);
            $sqlQuery = preg_replace('/\s*```$/i', '', $sqlQuery);
            $sqlQuery = trim($sqlQuery);
            
            // Validate it's actually SQL
            if (empty($sqlQuery)) {
                Log::error("Generated SQL query is empty");
                return ['error' => 'Generated SQL query is empty'];
            }

            Log::info("Generated SQL Query: " . $sqlQuery);
            return ['query' => $sqlQuery];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("OpenAI API Connection Error: " . $e->getMessage());
            return ['error' => 'Failed to connect to OpenAI API. Please check your internet connection.'];
        } catch (\Exception $e) {
            Log::error("OpenAI API Error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return ['error' => "OpenAI API request failed: " . $e->getMessage()];
        }
    }
}
