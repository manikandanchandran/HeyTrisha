<?php

// namespace App\Services;

// use OpenAI\Laravel\Facades\OpenAI;

// class OpenAiService
// {
//     public function generateWordPressRequest($userQuery, $schema)
//     {
//         $schemaStr = collect($schema)->map(function ($columns, $table) {
//             return "Table: $table, Columns: " . implode(', ', $columns);
//         })->implode("\n");

//         $prompt = "
//         WordPress Database Schema:
//         $schemaStr

//         User Query: $userQuery

//         Generate the appropriate WordPress REST API endpoint and payload. Return the endpoint, method (POST/GET/PUT/DELETE), and JSON payload for the request.
//         ";

//         $response = OpenAI::completions()->create([
//             'model' => 'gpt-4',
//             'prompt' => $prompt,
//             'max_tokens' => 200,
//         ]);

//         return $response['choices'][0]['text'];
//     }
// }

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiService
{
    public function generateWordPressRequest($userQuery, $schema)
{
    // Generate the schema string
    $schemaStr = collect($schema)->map(function ($columns, $table) {
        return "Table: $table, Columns: " . implode(', ', $columns);
    })->implode("\n");

    // Construct the prompt
    $prompt = "
    You are an API assistant that helps generate WordPress REST API requests.

    WordPress Database Schema:
    $schemaStr

    User Query: \"$userQuery\"

    Generate the appropriate WordPress REST API endpoint and payload.
    ### Return the response in **valid JSON format ONLY**.
    DO NOT include explanations or extra text.
    The JSON format should be:
    {
        \"method\": \"GET\",
        \"endpoint\": \"/wp-json/wc/v3/products\",
        \"payload\": { \"key\": \"value\" }  // optional, only for POST or PUT
    }
    ";

    try {
        // Make the API request to OpenAI's chat model
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an API assistant that outputs only valid JSON responses.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 200,
        ]);

        // Check if response contains valid data
        if (isset($response->choices[0]->message->content)) {
            $messageContent = $response->choices[0]->message->content;

            // Return the message content
            return $messageContent;
        } else {
            // Handle missing response content
            error_log('No valid response from OpenAI');
            throw new \Exception('No valid response from OpenAI');
        }
    } catch (\Exception $e) {
        // Log the error message
        error_log('Error connecting to OpenAI: ' . $e->getMessage());
        throw new \Exception("Error connecting to OpenAI: " . $e->getMessage());
    }
}


}
