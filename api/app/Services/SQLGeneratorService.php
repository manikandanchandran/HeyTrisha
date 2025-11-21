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

class SQLGeneratorService
{
    public function queryChatGPTForSQL($userQuery, $schema)
    {
        $schemaStr = '';
        foreach ($schema as $table => $columns) {
            $schemaStr .= "Table: `$table` (Columns: " . implode(', ', $columns) . ")\n";
        }

        $prompt = "
        You are an AI that generates SQL queries based on the given database schema.

        Database Schema:
        $schemaStr

        User Query: \"$userQuery\"

        Write the correct SQL query for the above user request.
        - **Only return the raw SQL query**.  
        - **Do NOT include explanations, context, or formatting**.  
        ";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an AI that generates correct MySQL queries.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500
            ]);

            $openAIResponse = $response->json();

            // Log the full response for debugging
            Log::info("OpenAI Response: " . json_encode($openAIResponse));

            // Check if the response contains the expected data
            if (!isset($openAIResponse['choices'][0]['message']['content'])) {
                Log::error("OpenAI response format is invalid or content is missing");
                return ['error' => 'OpenAI response format is invalid or incomplete'];
            }

            // Extract the SQL query
            $sqlQuery = trim($openAIResponse['choices'][0]['message']['content']);
            return ['query' => $sqlQuery];

        } catch (\Exception $e) {
            Log::error("OpenAI API Error: " . $e->getMessage());
            return ['error' => "OpenAI API request failed: " . $e->getMessage()];
        }
    }
}
