<?php

// Fetching Working Code 02/01/2025 12:00 PM

// namespace App\Http\Controllers;

// use App\Services\SQLGeneratorService;
// use App\Services\MySQLService;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;

// class NLPController extends Controller
// {
//     protected $sqlGenerator;
//     protected $mysqlService;

//     public function __construct(SQLGeneratorService $sqlGenerator, MySQLService $mysqlService)
//     {
//         $this->sqlGenerator = $sqlGenerator;
//         $this->mysqlService = $mysqlService;
//     }

//     public function handleQuery(Request $request)
//     {
//         $userQuery = $request->input('query');

//         try {
//             // ✅ Get the database schema from MySQLService
//             $schema = $this->mysqlService->getCompactSchema();

//             // ✅ Generate the SQL query using OpenAI
//             $queryResponse = $this->sqlGenerator->queryChatGPTForSQL($userQuery, $schema);

//             // ✅ Ensure OpenAI returned a valid query
//             if (isset($queryResponse['error'])) {
//                 return response()->json(['success' => false, 'message' => $queryResponse['error']], 500);
//             }

//             $sqlQuery = $queryResponse['query'];

//             // ✅ Execute the SQL query
//             $result = $this->mysqlService->executeSQLQuery($sqlQuery);

//             // ✅ Return the result in JSON format
//             return response()->json([
//                 'success' => true,
//                 'data' => $result,
//                 'query' => $sqlQuery
//             ]);
//         } catch (\Exception $e) {
//             Log::error("Error handling user query: " . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ]);
//         }
//     }
// }

// Working Fine code 2nd version

namespace App\Http\Controllers;

use App\Services\SQLGeneratorService;
use App\Services\MySQLService;
use App\Services\WordPressApiService;
use App\Services\WordPressRequestGeneratorService; // ✅ Add new service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NLPController extends Controller
{
    protected $sqlGenerator;
    protected $mysqlService;
    protected $wordpressApiService;
    protected $wordpressRequestGeneratorService;

    public function __construct(
        SQLGeneratorService $sqlGenerator,
        MySQLService $mysqlService,
        WordPressApiService $wordpressApiService,
        WordPressRequestGeneratorService $wordpressRequestGeneratorService // ✅ Inject new service
    ) {
        $this->sqlGenerator = $sqlGenerator;
        $this->mysqlService = $mysqlService;
        $this->wordpressApiService = $wordpressApiService;
        $this->wordpressRequestGeneratorService = $wordpressRequestGeneratorService;
    }

    public function handleQuery(Request $request)
    {
        $userQuery = $request->input('query');

        try {
            // ✅ If the query is a fetch operation, use MySQL database
            if ($this->isFetchOperation($userQuery)) {
                $schema = $this->mysqlService->getCompactSchema();
                $queryResponse = $this->sqlGenerator->queryChatGPTForSQL($userQuery, $schema);

                if (isset($queryResponse['error'])) {
                    return response()->json(['success' => false, 'message' => $queryResponse['error']], 500);
                }

                $sqlQuery = $queryResponse['query'];
                $result = $this->mysqlService->executeSQLQuery($sqlQuery);
                return response()->json(['success' => true, 'data' => $result]);
            } else {
                // ✅ If it's an Insert, Update, or Delete, use WordPress API
                // $apiRequest = $this->wordpressRequestGeneratorService->generateWordPressRequest($userQuery);

                // if (isset($apiRequest['error'])) {
                //     return response()->json(['success' => false, 'message' => $apiRequest['error']], 500);
                // }

                // $response = $this->wordpressApiService->sendRequest(
                //     $apiRequest['method'],
                //     $apiRequest['endpoint'],
                //     $apiRequest['payload'] ?? []
                // );

                // return response()->json(['success' => true, 'data' => $response]);

                $apiRequest = $this->wordpressRequestGeneratorService->generateWordPressRequest($userQuery);
                
                // ✅ Step 1: Check if the API request is valid before using it
                if (!is_array($apiRequest) || !isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                    Log::error("❌ Invalid API Request Structure. Missing 'method' or 'endpoint'.");
                    Log::error("🛠 Debugging: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid API request structure. Missing method or endpoint.'
                    ], 500);
                }

                Log::info("📢 Sending WordPress API Request: Method: {$apiRequest['method']}, Endpoint: {$apiRequest['endpoint']}");

                // ✅ Step 2: Now safely send the request
                $response = $this->wordpressApiService->sendRequest(
                    $apiRequest['method'],
                    $apiRequest['endpoint'],
                    $apiRequest['payload'] ?? []
                );

                return response()->json(['success' => true, 'data' => $response]);


            }
        } catch (\Exception $e) {
            Log::error("🚨 Error handling user query: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ✅ Detects Fetch operations (SELECT queries)
    private function isFetchOperation($query)
    {
        return preg_match('/\b(show|list|fetch|get|view|display|select)\b/i', $query);
    }
}
