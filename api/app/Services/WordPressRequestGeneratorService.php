<?php

// namespace App\Services;

// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;

// class WordPressRequestGeneratorService
// {
//     public function generateWordPressRequest($userQuery)
//     {
//         $prompt = "
//         You are an AI assistant that generates WordPress REST API requests based on user input.

//         **Rules:**
//         - **Return ONLY valid JSON output**.
//         - **DO NOT** include any explanation, formatting, or comments.
//         - **Generate requests for the WordPress REST API (WooCommerce included)**.
//         - The JSON response **MUST** follow this structure:
//         ```json
//         {
//             \"method\": \"GET or POST or PUT or DELETE\",
//             \"endpoint\": \"WordPress REST API endpoint\",
//             \"payload\": { \"key\": \"value\" }  // Only for POST or PUT
//         }
//         ```
        
//         **User Query:** \"$userQuery\"
//         ";

//         try {
//             $apiKey = env('OPENAI_API_KEY');
//             if (!$apiKey) {
//                 Log::error("🚨 OpenAI API Key is missing!");
//                 return ['error' => 'OpenAI API Key is missing. Please set it in the .env file.'];
//             }

//             // ✅ Make request to OpenAI API
//             $response = Http::withHeaders([
//                 'Authorization' => 'Bearer ' . $apiKey,
//                 'Content-Type' => 'application/json'
//             ])->post('https://api.openai.com/v1/chat/completions', [
//                 'model' => 'gpt-4',
//                 'messages' => [
//                     ['role' => 'system', 'content' => 'You must return only JSON output in the correct format.'],
//                     ['role' => 'user', 'content' => $prompt],
//                 ],
//                 'max_tokens' => 200
//             ]);

//             // ✅ Log full OpenAI response
//             $openAIResponse = $response->json();
//             Log::info("🔍 Full OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

//             // ✅ Ensure OpenAI response has correct structure
//             if (!isset($openAIResponse['choices'][0]['message']['content'])) {
//                 Log::error("⚠️ OpenAI response is missing 'choices' or 'message' key.");
//                 return ['error' => 'OpenAI response format is invalid or incomplete'];
//             }

//             // ✅ Extract OpenAI response
//             $apiRequestRaw = $openAIResponse['choices'][0]['message']['content'];
//             Log::info("📢 Raw OpenAI Content: " . $apiRequestRaw);

//             // ✅ Validate JSON
//             $apiRequest = json_decode($apiRequestRaw, true);

//             if (json_last_error() !== JSON_ERROR_NONE) {
//                 Log::error("❌ JSON decoding failed: " . json_last_error_msg());
//                 return ['error' => 'Invalid JSON response from OpenAI: ' . json_last_error_msg()];
//             }

//             return $apiRequest;
//         } catch (\Exception $e) {
//             Log::error("🚨 OpenAI API Error: " . $e->getMessage());
//             return ['error' => "OpenAI API request failed: " . $e->getMessage()];
//         }
//     }
// }


// namespace App\Services;

// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;

// class WordPressRequestGeneratorService
// {
//     public function generateWordPressRequest($userQuery)
//     {
//         $prompt = "
//         You are an AI assistant that generates WordPress REST API requests based on user input.

//         **Rules:**
//         - **Return ONLY valid JSON output**.
//         - **DO NOT** include any explanation, formatting, or comments.
//         - **Generate requests for the WordPress REST API (WooCommerce included)**.
//         - **Identify if the request is for a post, product, or category**.
//         - The JSON response **MUST** follow this structure:
//         ```json
//         {
//             \"method\": \"GET or POST or PUT or DELETE\",
//             \"endpoint\": \"WordPress REST API endpoint\",
//             \"payload\": { \"key\": \"value\" }  // Only for POST or PUT
//         }
//         ```

//         **Examples:**
//         - \"Add a post titled 'My Journey' with content 'Life is beautiful' under category 'Travel'\"
//         - \"Create a WooCommerce product named 'Laptop' priced at 1200 with category 'Electronics'\"
//         - \"Add a category named 'Technology' for blog posts\"

//         **User Query:** \"$userQuery\"
//         ";

//         try {
//             $apiKey = env('OPENAI_API_KEY');
//             if (!$apiKey) {
//                 Log::error("🚨 OpenAI API Key is missing!");
//                 return ['error' => 'OpenAI API Key is missing. Please set it in the .env file.'];
//             }

//             // ✅ Send request to ChatGPT API
//             $response = Http::withHeaders([
//                 'Authorization' => 'Bearer ' . $apiKey,
//                 'Content-Type' => 'application/json'
//             ])->post('https://api.openai.com/v1/chat/completions', [
//                 'model' => 'gpt-4',
//                 'messages' => [
//                     ['role' => 'system', 'content' => 'You must return only JSON output in the correct format.'],
//                     ['role' => 'user', 'content' => $prompt],
//                 ],
//                 'max_tokens' => 300
//             ]);

//             // ✅ Log full OpenAI response
//             $openAIResponse = $response->json();
//             Log::info("🔍 Full OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

//             // ✅ Validate OpenAI response structure
//             if (!isset($openAIResponse['choices'][0]['message']['content'])) {
//                 Log::error("⚠️ OpenAI response is missing 'choices' or 'message' key.");
//                 return ['error' => 'OpenAI response format is invalid or incomplete'];
//             }

//             // ✅ Extract API request JSON
//             // $apiRequestRaw = $openAIResponse['choices'][0]['message']['content'];
//             // Log::info("📢 Raw OpenAI Content: " . $apiRequestRaw);

//             // // ✅ Validate JSON
//             // $apiRequest = json_decode($apiRequestRaw, true);
//             // if (json_last_error() !== JSON_ERROR_NONE) {
//             //     Log::error("❌ JSON decoding failed: " . json_last_error_msg());
//             //     return ['error' => 'Invalid JSON response from OpenAI: ' . json_last_error_msg()];
//             // }

//             $apiRequestRaw = trim($openAIResponse['choices'][0]['message']['content']);
//             Log::info("📢 Raw OpenAI Content (Trimmed): " . $apiRequestRaw);

//             // Decode the response and check for errors
//             $apiRequest = json_decode($apiRequestRaw, true);
//             if (json_last_error() !== JSON_ERROR_NONE) {
//                 Log::error("❌ JSON decoding failed: " . json_last_error_msg());
//                 return ['error' => 'Invalid JSON response from OpenAI: ' . json_last_error_msg()];
//             }

//             // Log and check the structure of the decoded response
//             Log::info("📢 API Request Structure: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

//             // Validate if the 'method' and 'endpoint' keys exist in the decoded response
//             if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
//                 Log::error("⚠️ Missing 'method' or 'endpoint' in OpenAI response. Raw response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));
//                 return ['error' => 'Invalid API request structure: Missing method or endpoint.'];
//             }

//             // Now continue to the WordPress API request
//             return $this->executeWordPressRequest($apiRequest);

//         } catch (\Exception $e) {
//             Log::error("🚨 OpenAI API Error: " . $e->getMessage());
//             return ['error' => "OpenAI API request failed: " . $e->getMessage()];
//         }
//     }

//     /**
//      * ✅ Execute WordPress API Request with Authentication
//      */
//     // private function executeWordPressRequest($apiRequest)
//     // {
//     //     try {
//     //         $apiUrl = env('WORDPRESS_API_URL') . $apiRequest['endpoint'];
//     //         $method = strtoupper($apiRequest['method']);
//     //         $payload = $apiRequest['payload'] ?? [];

//     //         $authUser = env('WORDPRESS_API_USER');
//     //         $authPass = env('WORDPRESS_API_PASSWORD');

//     //         if (!$authUser || !$authPass) {
//     //             Log::error("🚨 WordPress API credentials are missing!");
//     //             return ['error' => 'WordPress API credentials are missing.'];
//     //         }

//     //         Log::info("🔍 Sending request to: $apiUrl");
//     //         Log::info("🔑 Using authentication: $authUser / $authPass");
//     //         Log::info("📩 Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

//     //         $response = Http::withBasicAuth($authUser, $authPass)
//     //             ->withHeaders(['Content-Type' => 'application/json'])
//     //             ->{$method}($apiUrl, $payload);

//     //         $responseData = $response->json();
//     //         Log::info("✅ WordPress API Response: " . json_encode($responseData, JSON_PRETTY_PRINT));

//     //         if ($response->failed()) {
//     //             Log::error("❌ API request failed: " . json_encode($responseData));
//     //             return ['error' => 'WordPress API request failed', 'details' => $responseData];
//     //         }

//     //         return ['success' => true, 'response' => $responseData];

//     //     } catch (\Exception $e) {
//     //         Log::error("🚨 WordPress API Execution Error: " . $e->getMessage());
//     //         return ['error' => "WordPress API execution failed: " . $e->getMessage()];
//     //     }
//     // }

//     private function executeWordPressRequest($apiRequest)
//     {
//         try {
//             // Ensure 'method' and 'endpoint' keys exist in the request
//             if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
//                 Log::error("⚠️ Missing 'method' or 'endpoint' in API request.");
//                 return ['error' => 'Missing method or endpoint in API request.'];
//             }

//             // $apiUrl = env('WORDPRESS_API_URL') . $apiRequest['endpoint'];
//             $apiUrl = rtrim(env('WORDPRESS_API_URL'), '/') . '/' . ltrim($apiRequest['endpoint'], '/');
//             Log::info("🔍 Final API URL: $apiUrl");
//             $method = strtoupper($apiRequest['method']); // Ensure the method is in uppercase (POST, GET, etc.)
//             $payload = $apiRequest['payload'] ?? [];  // Default to an empty array if no payload is provided

//             // Your existing WordPress API credentials and logging
//             $authUser = env('WORDPRESS_API_USER');
//             $authPass = env('WORDPRESS_API_PASSWORD');

//             if (!$authUser || !$authPass) {
//                 Log::error("🚨 WordPress API credentials are missing!");
//                 return ['error' => 'WordPress API credentials are missing.'];
//             }

//             Log::info("🔍 Sending request to: $apiUrl");
//             Log::info("🔑 Using authentication: $authUser / $authPass");
//             Log::info("📩 Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
            

//             // Send the request based on the HTTP method (GET, POST, PUT, etc.)
//             // $response = Http::withBasicAuth($authUser, $authPass)
//             //     ->withHeaders(['Content-Type' => 'application/json'])
//             //     ->{$method}($apiUrl, $payload);

//             $response = Http::withBasicAuth($authUser, $authPass)
//                 ->withHeaders(['Content-Type' => 'application/json'])
//                 ->timeout(60)  // Increase timeout to 60 seconds
//                 ->{$method}($apiUrl, $payload);

//             // Ensure $responseData is always defined
//             if ($response->failed()) {
//                 $responseData = $response->json();  // Ensure $responseData is assigned in case of failure
//                 Log::error("❌ API request failed: " . json_encode($responseData));
//                 return ['error' => 'WordPress API request failed', 'details' => $responseData];
//             }

//             // Successfully processed response
//             $responseData = $response->json();
//             Log::info("✅ WordPress API Response: " . json_encode($responseData, JSON_PRETTY_PRINT));

//             Log::info("🔍 Request Details: Method: {$apiRequest['method']}, Endpoint: {$apiRequest['endpoint']}, Payload: " . json_encode($apiRequest['payload'], JSON_PRETTY_PRINT));
            
//             // Ensure we log the API response
//             // $responseData = $response->json();
//             // Log::info("✅ WordPress API Response: " . json_encode($responseData, JSON_PRETTY_PRINT));

//             // Check if the response was successful
//             if ($response->failed()) {
//                 Log::error("❌ API request failed: " . json_encode($responseData));
//                 return ['error' => 'WordPress API request failed', 'details' => $responseData];
//             }

//             // Proceed with further processing
//             Log::info("Post created successfully with ID: " . $responseData['id']);


//             return ['success' => true, 'response' => $responseData];

//         } catch (\Exception $e) {
//             Log::error("🚨 WordPress API Execution Error: " . $e->getMessage());
//             return ['error' => "WordPress API execution failed: " . $e->getMessage()];
//         }
//     }

// }


// namespace App\Services;

// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;

// class WordPressRequestGeneratorService
// {
//     public function generateWordPressRequest($userQuery)
//     {
//         $prompt = "
//         You are an AI assistant that generates WordPress REST API requests based on user input.

//         **Rules:**
//         - **Return ONLY valid JSON output**.
//         - **DO NOT** include any explanation, formatting, or comments.
//         - **Generate requests for the WordPress REST API (WooCommerce included)**.
//         - **Identify if the request is for a post, product, or category**.
//         - The JSON response **MUST** follow this structure:
//         ```json
//         {
//             \"method\": \"GET or POST or PUT or DELETE\",
//             \"endpoint\": \"WordPress REST API endpoint\",
//             \"payload\": { \"key\": \"value\" }  // Only for POST or PUT
//         }
//         ```

//         **User Query:** \"$userQuery\"";

//         try {
//             $apiKey = env('OPENAI_API_KEY');
//             if (!$apiKey) {
//                 Log::error("🚨 OpenAI API Key is missing!");
//                 return ['error' => 'OpenAI API Key is missing. Please set it in the .env file.'];
//             }

//             // ✅ Send request to ChatGPT API
//             $response = Http::withHeaders([
//                 'Authorization' => 'Bearer ' . $apiKey,
//                 'Content-Type' => 'application/json'
//             ])->post('https://api.openai.com/v1/chat/completions', [
//                 'model' => 'gpt-4',
//                 'messages' => [
//                     ['role' => 'system', 'content' => 'You must return only JSON output in the correct format.'],
//                     ['role' => 'user', 'content' => $prompt],
//                 ],
//                 'max_tokens' => 300
//             ]);

//             // ✅ Log full OpenAI response
//             $openAIResponse = $response->json();
//             Log::info("🔍 Full OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

//             // ✅ Validate OpenAI response structure
//             if (!isset($openAIResponse['choices'][0]['message']['content'])) {
//                 Log::error("⚠️ OpenAI response is missing 'choices' or 'message' key.");
//                 return ['error' => 'OpenAI response format is invalid or incomplete'];
//             }

//             // ✅ Extract API request JSON
//             $apiRequestRaw = trim($openAIResponse['choices'][0]['message']['content']);
//             Log::info("📢 Raw OpenAI Content (Trimmed): " . $apiRequestRaw);

//             // ✅ Decode the JSON
//             $apiRequest = json_decode($apiRequestRaw, true);
//             if (json_last_error() !== JSON_ERROR_NONE) {
//                 Log::error("❌ JSON decoding failed: " . json_last_error_msg());
//                 return ['error' => 'Invalid JSON response from OpenAI: ' . json_last_error_msg()];
//             }

//             // ✅ Log the decoded API request to verify its structure
//             Log::info("📢 API Request Structure: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

//             // ✅ Ensure 'method' and 'endpoint' are valid before proceeding
//             if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
//                 Log::error("⚠️ Missing 'method' or 'endpoint' in API request.");
//                 return ['error' => 'Missing method or endpoint in API request.'];
//             }

//             // ✅ Fix the endpoint URL (add /wp-json if missing)
//             if (strpos($apiRequest['endpoint'], '/wp/') === 0 && strpos($apiRequest['endpoint'], '/wp-json') !== 0) {
//                 // Prepend '/wp-json' if missing
//                 $apiRequest['endpoint'] = '/wp-json' . $apiRequest['endpoint'];
//                 Log::info("📢 Fixed API Request Endpoint: " . $apiRequest['endpoint']);
//             }

//             // ✅ Log the API request details before making the request
//             Log::info("🔍 Request Details: Method: {$apiRequest['method']}, Endpoint: {$apiRequest['endpoint']}, Payload: " . json_encode($apiRequest['payload'], JSON_PRETTY_PRINT));

//             // ✅ Now execute the WordPress request
//             $response = $this->executeWordPressRequest($apiRequest);

//             // ✅ Return the response
//             return $response;

//         } catch (\Exception $e) {
//             Log::error("🚨 OpenAI API Error: " . $e->getMessage());
//             return ['error' => "OpenAI API request failed: " . $e->getMessage()];
//         }
//     }

//     /**
//      * ✅ Execute WordPress API Request with Authentication
//      */
//     private function executeWordPressRequest($apiRequest)
//     {
//         try {
//             // Ensure 'method' and 'endpoint' are valid **before** the request
//             if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
//                 Log::error("⚠️ Missing 'method' or 'endpoint' in API request.");
//                 return ['error' => 'Missing method or endpoint in API request.'];
//             }

//             // Build the full URL (fix for extra slashes)
//             $apiUrl = rtrim(env('WORDPRESS_API_URL'), '/') . '/' . ltrim($apiRequest['endpoint'], '/');
//             Log::info("🔍 Final API URL: $apiUrl");

//             // Get method (POST, GET, etc.) and payload
//             $method = strtoupper($apiRequest['method']); // Ensure it's uppercase (POST, GET, etc.)
//             $payload = $apiRequest['payload'] ?? []; // Default to empty array if no payload

//             // WordPress API credentials
//             $authUser = env('WORDPRESS_API_USER');
//             $authPass = env('WORDPRESS_API_PASSWORD');

//             if (!$authUser || !$authPass) {
//                 Log::error("🚨 WordPress API credentials are missing!");
//                 return ['error' => 'WordPress API credentials are missing.'];
//             }

//             Log::info("🔍 Sending request to: $apiUrl");
//             Log::info("🔑 Using authentication: $authUser / $authPass");
//             Log::info("📩 Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

//             // Send the request based on the method (POST, GET, etc.)
//             $response = Http::withBasicAuth($authUser, $authPass)
//                 ->withHeaders(['Content-Type' => 'application/json'])
//                 ->{$method}($apiUrl, $payload);

//             // Ensure the response data is available
//             if ($response->failed()) {
//                 $responseData = $response->json(); // Ensure response data is assigned in case of failure
//                 Log::error("❌ API request failed: " . json_encode($responseData));
//                 return ['error' => 'WordPress API request failed', 'details' => $responseData];
//             }

//             // Successfully processed response
//             $responseData = $response->json();
//             Log::info("✅ WordPress API Response: " . json_encode($responseData, JSON_PRETTY_PRINT));

//             return ['success' => true, 'response' => $responseData];

//         } catch (\Exception $e) {
//             Log::error("🚨 WordPress API Execution Error: " . $e->getMessage());
//             return ['error' => "WordPress API execution failed: " . $e->getMessage()];
//         }
//     }
// }


namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressRequestGeneratorService
{
    // public function generateWordPressRequest($userQuery)
    // {
    //     $prompt = "
    //     You are an AI assistant that generates WordPress REST API requests based on user input.

    //     **Rules:**
    //     - **Return ONLY valid JSON output**.
    //     - **DO NOT** include any explanation, formatting, or comments.
    //     - **Generate requests for the WordPress REST API (WooCommerce included)**.
    //     - The JSON response **MUST** follow this structure:
    //     ```json
    //     {
    //         \"method\": \"GET or POST or PUT or DELETE\",
    //         \"endpoint\": \"WordPress REST API endpoint\",
    //         \"payload\": { \"key\": \"value\" }  // Only for POST or PUT
    //     }
    //     ```

    //     **User Query:** \"$userQuery\"
    //     ";

    //     try {
    //         // Get OpenAI API key
    //         $apiKey = env('OPENAI_API_KEY');
    //         if (!$apiKey) {
    //             Log::error("🚨 OpenAI API Key is missing!");
    //             return ['error' => 'OpenAI API Key is missing. Please set it in the .env file.'];
    //         }

    //         // Send request to OpenAI API
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $apiKey,
    //             'Content-Type' => 'application/json'
    //         ])->post('https://api.openai.com/v1/chat/completions', [
    //             'model' => 'gpt-4',
    //             'messages' => [
    //                 ['role' => 'system', 'content' => 'You must return only JSON output in the correct format.'],
    //                 ['role' => 'user', 'content' => $prompt],
    //             ],
    //             'max_tokens' => 200
    //         ]);

    //         // Log full OpenAI response
    //         $openAIResponse = $response->json();
    //         Log::info("🔍 Full OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

    //         // Ensure OpenAI response has the correct structure
    //         if (!isset($openAIResponse['choices'][0]['message']['content'])) {
    //             Log::error("⚠️ OpenAI response is missing 'choices' or 'message' key.");
    //             return ['error' => 'OpenAI response format is invalid or incomplete'];
    //         }

    //         // Extract the raw API request from OpenAI
    //         $apiRequestRaw = $openAIResponse['choices'][0]['message']['content'];
    //         Log::info("📢 Raw OpenAI Content: " . $apiRequestRaw);

    //         // Decode the API request
    //         $apiRequest = json_decode($apiRequestRaw, true);
    //         if (json_last_error() !== JSON_ERROR_NONE) {
    //             Log::error("❌ JSON decoding failed: " . json_last_error_msg());
    //             return ['error' => 'Invalid JSON response from OpenAI: ' . json_last_error_msg()];
    //         }

    //         // Log decoded API request
    //         Log::info("📢 Decoded API Request: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

    //         // Ensure the required fields exist
    //         if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
    //             Log::error("⚠️ Missing 'method' or 'endpoint' in API request.");
    //             return ['error' => 'Missing method or endpoint in API request.'];
    //         }

    //         // Fix endpoint if needed
    //         if (strpos($apiRequest['endpoint'], '/wp/') === 0 && strpos($apiRequest['endpoint'], '/wp-json') !== 0) {
    //             $apiRequest['endpoint'] = '/wp-json' . $apiRequest['endpoint'];
    //             Log::info("📢 Fixed API Request Endpoint: " . $apiRequest['endpoint']);
    //         }

    //         // Log the API request details before sending
    //         Log::info("🔍 Request Details: Method: {$apiRequest['method']}, Endpoint: {$apiRequest['endpoint']}, Payload: " . json_encode($apiRequest['payload'], JSON_PRETTY_PRINT));

    //         // Send the API request to WordPress
    //         return $this->executeWordPressRequest($apiRequest);

    //     } catch (\Exception $e) {
    //         Log::error("🚨 OpenAI API Error: " . $e->getMessage());
    //         return ['error' => "OpenAI API request failed: " . $e->getMessage()];
    //     }
    // }

    public function generateWordPressRequest($userQuery)
    {
        $prompt = "
        You are an AI assistant that generates WordPress REST API requests based on user input.

        **Rules:**
        - **Return ONLY valid JSON output**.
        - **DO NOT** include any explanation, formatting, or comments.
        - **Generate requests for the WordPress REST API (WooCommerce included)**.
        - The JSON response **MUST** follow this structure:
        ```json
        {
            \"method\": \"GET or POST or PUT or DELETE\",
            \"endpoint\": \"WordPress REST API endpoint\",
            \"payload\": { \"key\": \"value\" }  // Only for POST or PUT
        }
        ```

        **User Query:** \"$userQuery\"
        ";

        try {
            // ✅ Get OpenAI API key
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                Log::error("🚨 OpenAI API Key is missing!");
                return ['error' => 'OpenAI API Key is missing. Please set it in the .env file.'];
            }

            // ✅ Send request to OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You must return only JSON output in the correct format.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 200
            ]);

            // ✅ Log full OpenAI response
            $openAIResponse = $response->json();
            Log::info("🔍 Full OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

            // ✅ Validate OpenAI response structure
            if (!isset($openAIResponse['choices'][0]['message']['content'])) {
                Log::error("⚠️ OpenAI response is missing 'choices' or 'message' key.");
                return ['error' => 'OpenAI response format is invalid or incomplete'];
            }

            // ✅ Extract OpenAI response content
            $apiRequestRaw = $openAIResponse['choices'][0]['message']['content'];
            Log::info("📢 Raw OpenAI Content: " . $apiRequestRaw);

            // ✅ Decode JSON
            $apiRequest = json_decode($apiRequestRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("❌ JSON decoding failed: " . json_last_error_msg());
                return ['error' => 'Invalid JSON response from OpenAI: ' . json_last_error_msg()];
            }

            // ✅ Log decoded API request
            Log::info("📢 Decoded API Request: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

            // ✅ Ensure 'method' and 'endpoint' exist
            if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                Log::error("❌ Invalid API Request Structure. Missing 'method' or 'endpoint'.");
                Log::error("🛠 Debugging API Request: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

                return [
                    'error' => 'Invalid API request structure. Missing method or endpoint.',
                    'details' => $apiRequest
                ];
            }

            // ✅ Fix endpoint if needed (Ensure it includes '/wp-json')
            if (strpos($apiRequest['endpoint'], '/wp/') === 0 && strpos($apiRequest['endpoint'], '/wp-json') !== 0) {
                $apiRequest['endpoint'] = '/wp-json' . $apiRequest['endpoint'];
                Log::info("📢 Fixed API Request Endpoint: " . $apiRequest['endpoint']);
            }

            // ✅ Log final API request before returning
            Log::info("🔍 Final API Request: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

            // ✅ Return structured response instead of executing it inside this function
            return [
                'method' => $apiRequest['method'],
                'endpoint' => $apiRequest['endpoint'],
                'payload' => $apiRequest['payload'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error("🚨 OpenAI API Error: " . $e->getMessage());
            return ['error' => "OpenAI API request failed: " . $e->getMessage()];
        }
    }


    /**
     * ✅ Execute WordPress API Request with Authentication
     */
    private function executeWordPressRequest($apiRequest)
    {
        try {
            // Log the request before executing
            Log::info("🔍 Executing API Request: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

            // Check if method and endpoint are set
            if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                Log::error("⚠️ Missing 'method' or 'endpoint' in API request.");
                return ['error' => 'Missing method or endpoint in API request.'];
            }

            // Build final API URL
            $apiUrl = rtrim(env('WORDPRESS_API_URL'), '/') . '/' . ltrim($apiRequest['endpoint'], '/');
            Log::info("🔍 Final API URL: $apiUrl");

            // Get method (POST, GET, etc.) and payload
            $method = strtoupper($apiRequest['method']); // Ensure it's uppercase
            $payload = $apiRequest['payload'] ?? []; // Default to empty array if no payload

            // Authentication credentials
            $authUser = env('WORDPRESS_API_USER');
            $authPass = env('WORDPRESS_API_PASSWORD');
            if (!$authUser || !$authPass) {
                Log::error("🚨 WordPress API credentials are missing!");
                return ['error' => 'WordPress API credentials are missing.'];
            }

            Log::info("🔍 Sending request to: $apiUrl");
            Log::info("🔑 Using authentication: $authUser / $authPass");
            Log::info("📩 Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

            // Send the API request based on method (POST, GET, etc.)
            $response = Http::withBasicAuth($authUser, $authPass)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->{$method}($apiUrl, $payload);

            // Log the response
            Log::info("🔍 WordPress API Response Status: " . $response->status());
            Log::info("🔍 WordPress API Response Body: " . json_encode($response->json(), JSON_PRETTY_PRINT));

            // Check if the request failed
            if ($response->failed()) {
                Log::error("❌ API request failed with status code " . $response->status());
                Log::error("❌ Response Body: " . json_encode($response->json(), JSON_PRETTY_PRINT));
                return ['error' => 'WordPress API request failed', 'details' => $response->json()];
            }

            // Success, process the response
            $responseData = $response->json();
            Log::info("✅ WordPress API Response (Successful): " . json_encode($responseData, JSON_PRETTY_PRINT));

            return ['success' => true, 'response' => $responseData];

        } catch (\Exception $e) {
            Log::error("🚨 WordPress API Execution Error: " . $e->getMessage());
            return ['error' => "WordPress API execution failed: " . $e->getMessage()];
        }
    }
}
