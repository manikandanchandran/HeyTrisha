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

use OpenAI;

class OpenAiService
{
    /**
     * @param string $openaiKey  The API key supplied by the WordPress plugin (never from .env).
     */
    public function generateWordPressRequest($userQuery, $schema, string $openaiKey)
{
    if (empty($openaiKey)) {
        throw new \Exception('OpenAI API Key is not configured. Please set it in the HeyTrisha plugin settings and save.');
    }

    // Generate the schema string
    $schemaStr = collect($schema)->map(function ($columns, $table) {
        return "Table: $table, Columns: " . implode(', ', $columns);
    })->implode("\n");

    // Construct the prompt
    $prompt = "
    You are an API assistant that helps generate WordPress REST API requests.
    
    SECURITY & PRIVACY RULES (ABSOLUTE):
    - Never request, output, or expose passwords, API keys, secrets, tokens, session data, or payment instrument details.
    - Do not generate requests that retrieve or update credentials or payment tokens.
    - If the user asks for anything sensitive (passwords, API keys, saved cards), return a safe request that fetches only analytics/aggregates or return an endpoint that yields no sensitive data.

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
        // Make the API request to OpenAI's chat model using the plugin-supplied key
        $response = OpenAI::client($openaiKey)->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You output only valid JSON. Never expose secrets or payment data.'],
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
