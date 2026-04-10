<?php

// namespace App\Http\Controllers;

// use App\Services\OpenAiService;
// use App\Services\WordPressApiService;
// use Illuminate\Http\Request;

// class WordPressApiController extends Controller
// {
//     protected $openAiService;
//     protected $wordpressApiService;

//     public function __construct(OpenAiService $openAiService, WordPressApiService $wordpressApiService)
//     {
//         $this->openAiService = $openAiService;
//         $this->wordpressApiService = $wordpressApiService;
//     }

//     public function handleQuery(Request $request)
//     {
//         $userQuery = $request->input('query');

//         if (empty($userQuery)) {
//             return response()->json(['error' => 'No query provided'], 400);
//         }

//         try {
//             // Example WordPress schema
//             $schema = [
//                 'posts' => ['id', 'title', 'content', 'status'],
//                 'products' => ['id', 'name', 'price', 'stock_status'],
//             ];

//             // Generate API request using OpenAI
//             $apiDetails = $this->openAiService->generateWordPressRequest($userQuery, $schema);
//             $apiDetails = json_decode($apiDetails, true);

//             // Send API request
//             $response = $this->wordpressApiService->sendRequest(
//                 $apiDetails['method'],
//                 $apiDetails['endpoint'],
//                 $apiDetails['payload'] ?? []
//             );

//             return response()->json(['reply' => $response]);

//         } catch (\Exception $e) {
//             return response()->json(['error' => $e->getMessage()], 500);
//         }
//     }
// }


namespace App\Http\Controllers;

use App\Services\OpenAiService;
use App\Services\WordPressApiService;
use Illuminate\Http\Request;

class WordPressApiController extends Controller
{
    protected $openAiService;
    protected $wordpressApiService;

    public function __construct(OpenAiService $openAiService, WordPressApiService $wordpressApiService)
    {
        $this->openAiService = $openAiService;
        $this->wordpressApiService = $wordpressApiService;
    }

    public function handleQuery(Request $request)
    {
        $userQuery = $request->input('query');

        if (empty($userQuery)) {
            return response()->json(['error' => 'No query provided'], 400);
        }

        try {
            // Example WordPress schema
            $schema = [
                'posts' => ['id', 'title', 'content', 'status'],
                'products' => ['id', 'name', 'price', 'stock_status'],
            ];

            // Generate API request using OpenAI
            $apiDetails = $this->openAiService->generateWordPressRequest($userQuery, $schema);
            if (!$apiDetails) {
                return response()->json(['error' => 'Failed to generate API request'], 500);
            }

            $apiDetails = json_decode($apiDetails, true);
            if (!isset($apiDetails['method'], $apiDetails['endpoint'])) {
                return response()->json(['error' => 'Invalid API response format'], 500);
            }

            // Send API request
            $response = $this->wordpressApiService->sendRequest(
                $apiDetails['method'],
                $apiDetails['endpoint'],
                $apiDetails['payload'] ?? []
            );

            return response()->json(['reply' => $response]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
