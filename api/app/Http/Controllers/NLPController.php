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
use App\Services\PostProductSearchService; // ✅ Add search service
use App\Services\WordPressConfigService; // ✅ Add config service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NLPController extends Controller
{
    protected $sqlGenerator;
    protected $mysqlService;
    protected $wordpressApiService;
    protected $wordpressRequestGeneratorService;
    protected $postProductSearchService;
    protected $configService;

    public function __construct(
        SQLGeneratorService $sqlGenerator,
        MySQLService $mysqlService,
        WordPressApiService $wordpressApiService,
        WordPressRequestGeneratorService $wordpressRequestGeneratorService,
        PostProductSearchService $postProductSearchService,
        WordPressConfigService $configService // ✅ Inject config service
    ) {
        $this->sqlGenerator = $sqlGenerator;
        $this->mysqlService = $mysqlService;
        $this->wordpressApiService = $wordpressApiService;
        $this->wordpressRequestGeneratorService = $wordpressRequestGeneratorService;
        $this->postProductSearchService = $postProductSearchService;
        $this->configService = $configService;
    }

    public function handleQuery(Request $request)
    {
        $userQuery = $request->input('query');
        $isConfirmed = $request->input('confirmed', false);
        $confirmationData = $request->input('confirmation_data', null);

        try {
            // ✅ If this is a confirmed edit, proceed directly
            if ($isConfirmed && $confirmationData) {
                return $this->executeConfirmedEdit($confirmationData);
            }

            // ✅ Check for capability questions FIRST (before fetch operations)
            // This prevents questions like "What you can do?" from being treated as data queries
            if ($this->isCapabilityQuestion($userQuery)) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => $this->getHelpfulResponse($userQuery)
                ]);
            }

            // ✅ If the query is a fetch operation, use NLP with OpenAI
            if ($this->isFetchOperation($userQuery)) {
                Log::info("🤖 NLP Flow: Detected fetch operation");
                
                // Check if OpenAI API key is configured (from WordPress database)
                try {
                    $openaiKey = $this->configService->getOpenAIApiKey();
                    if (empty($openaiKey)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'OpenAI API Key is not configured. Please set it in the Hey Trisha Chatbot settings page.'
                        ], 500);
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error getting OpenAI API key: " . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Configuration error. Please check your WordPress settings.'
                    ], 500);
                }

                // ✅ Step 1: Get intelligent database schema
                // Analyzes query to include relevant tables (reduces tokens while maintaining NLP)
                Log::info("📊 Step 1: Fetching intelligent database schema...");
                try {
                    $schema = $this->mysqlService->getCompactSchema($userQuery);
                    
                    if (isset($schema['error'])) {
                        return response()->json([
                            'success' => false,
                            'message' => $schema['error']
                        ], 500);
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error fetching schema: " . $e->getMessage());
                    Log::error("❌ Stack trace: " . $e->getTraceAsString());
                    return response()->json([
                        'success' => false,
                        'message' => 'Database connection error. Please check your database settings.'
                    ], 500);
                }

                // ✅ Step 2: Send FULL user input + FULL schema to OpenAI
                // OpenAI uses NLP to understand the query and generate SQL
                Log::info("🧠 Step 2: Sending to OpenAI for NLP SQL generation...");
                $queryResponse = $this->sqlGenerator->queryChatGPTForSQL($userQuery, $schema);

                if (isset($queryResponse['error'])) {
                    Log::error("SQL Generation Error: " . $queryResponse['error']);
                    return response()->json([
                        'success' => false,
                        'message' => $queryResponse['error']
                    ], 500);
                }

                if (!isset($queryResponse['query'])) {
                    Log::error("SQL Generation returned no query: " . json_encode($queryResponse));
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to generate SQL query. Please try rephrasing your request.'
                    ], 500);
                }

                // ✅ Step 3: Get the generated SQL query from OpenAI
                $sqlQuery = $queryResponse['query'];
                Log::info("✅ Step 3: OpenAI generated SQL: " . $sqlQuery);

                // ✅ Step 4: Execute the SQL query locally on our database
                Log::info("💾 Step 4: Executing SQL query locally...");
                $result = $this->mysqlService->executeSQLQuery($sqlQuery);
                
                if (isset($result['error'])) {
                    Log::error("SQL Execution Error: " . $result['error']);
                    return response()->json([
                        'success' => false,
                        'message' => $result['error'],
                        'sql_query' => $sqlQuery // Include SQL for debugging
                    ], 500);
                }

                // ✅ Step 5: Return results to frontend
                Log::info("✅ Step 5: Returning results to frontend (" . count($result) . " rows)");
                return response()->json([
                    'success' => true, 
                    'data' => $result,
                    'sql_query' => $sqlQuery // Include SQL for transparency
                ]);
            } else {
                // ✅ Check if this is an edit operation by name
                $editInfo = $this->detectEditByName($userQuery);
                
                if ($editInfo && isset($editInfo['name'])) {
                    // ✅ Search for the post/product by name
                    $itemType = $editInfo['type'] ?? 'both';
                    $foundItem = $this->postProductSearchService->searchByName($editInfo['name'], $itemType);
                    
                    if (!$foundItem) {
                        return response()->json([
                            'success' => false,
                            'message' => "No {$itemType} found with the name '{$editInfo['name']}'.",
                            'requires_confirmation' => false
                        ]);
                    }

                    // ✅ Generate the API request with the found ID
                    $modifiedQuery = $this->replaceNameWithId($userQuery, $editInfo['name'], $foundItem['id'], $foundItem['type']);
                    $apiRequest = $this->wordpressRequestGeneratorService->generateWordPressRequest($modifiedQuery);
                    
                    if (isset($apiRequest['error'])) {
                        return response()->json(['success' => false, 'message' => $apiRequest['error']], 500);
                    }

                    // ✅ Return confirmation request
                    $itemName = $foundItem['type'] === 'post' ? $foundItem['title'] : $foundItem['name'];
                    return response()->json([
                        'success' => true,
                        'requires_confirmation' => true,
                        'confirmation_message' => "I found a {$foundItem['type']} named '{$itemName}' (ID: {$foundItem['id']}). Do you want to proceed with the edit?",
                        'confirmation_data' => [
                            'item_id' => $foundItem['id'],
                            'item_name' => $itemName,
                            'item_type' => $foundItem['type'],
                            'api_request' => $apiRequest,
                            'original_query' => $userQuery
                        ],
                        'data' => [
                            'found_item' => $foundItem,
                            'preview' => "This will {$apiRequest['method']} the {$foundItem['type']} '{$itemName}'"
                        ]
                    ]);
                }

                    // ✅ Check if this is a WordPress API operation (create, update, delete)
                if ($this->isWordPressApiOperation($userQuery)) {
                    // ✅ Standard flow for ID-based or create operations
                    $apiRequest = $this->wordpressRequestGeneratorService->generateWordPressRequest($userQuery);
                    
                    if (!is_array($apiRequest) || !isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                        Log::error("❌ Invalid API Request Structure. Missing 'method' or 'endpoint'.");
                        Log::error("🛠 Debugging: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid API request structure. Missing method or endpoint.'
                        ], 500);
                    }

                    Log::info("📢 Sending WordPress API Request: Method: {$apiRequest['method']}, Endpoint: {$apiRequest['endpoint']}");

                    $response = $this->wordpressApiService->sendRequest(
                        $apiRequest['method'],
                        $apiRequest['endpoint'],
                        $apiRequest['payload'] ?? []
                    );

                    return response()->json(['success' => true, 'data' => $response]);
                } else {
                    // ✅ Unrecognized query - return helpful message
                    return response()->json([
                        'success' => true,
                        'data' => null,
                        'message' => $this->getHelpfulResponse($userQuery)
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("🚨 Error handling user query: " . $e->getMessage());
            Log::error("🚨 Stack trace: " . $e->getTraceAsString());
            
            // Return user-friendly error message
            $errorMessage = "Sorry, I encountered an error processing your request. ";
            
            // Provide more specific error messages for common issues
            if (strpos($e->getMessage(), 'Connection') !== false || strpos($e->getMessage(), 'timeout') !== false) {
                $errorMessage .= "The database connection timed out. Please try again.";
            } elseif (strpos($e->getMessage(), 'OpenAI') !== false) {
                $errorMessage .= "There was an issue with the AI service. Please check your OpenAI API key configuration.";
            } elseif (strpos($e->getMessage(), 'WordPress') !== false) {
                $errorMessage .= "There was an issue connecting to WordPress. Please check your WordPress API settings.";
            } else {
                $errorMessage .= "Please try rephrasing your request or contact support if the issue persists.";
            }
            
            return response()->json([
                'success' => false, 
                'message' => $errorMessage
            ], 500);
        }
    }

    /**
     * Execute a confirmed edit operation
     */
    private function executeConfirmedEdit($confirmationData)
    {
        try {
            $apiRequest = $confirmationData['api_request'];
            
            if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid confirmation data.'
                ], 500);
            }

            Log::info("✅ Executing confirmed edit: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

            $response = $this->wordpressApiService->sendRequest(
                $apiRequest['method'],
                $apiRequest['endpoint'],
                $apiRequest['payload'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => "Successfully edited {$confirmationData['item_type']} '{$confirmationData['item_name']}'"
            ]);
        } catch (\Exception $e) {
            Log::error("🚨 Error executing confirmed edit: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Detect if the query is an edit operation by name
     */
    private function detectEditByName($query)
    {
        // Check for edit/update operations
        if (!preg_match('/\b(edit|update|modify|change|alter)\b/i', $query)) {
            return null;
        }

        // Check if query contains an ID (numeric) - if so, it's not a name-based edit
        if (preg_match('/\b(id|ID)\s*[:\-]?\s*\d+\b/i', $query) || preg_match('/\b\d+\b.*\b(edit|update|modify|change|alter)\b/i', $query)) {
            return null;
        }

        // Try to extract name from common patterns
        $patterns = [
            // "edit post named 'X'"
            '/edit\s+(?:post|product)\s+(?:named|called|titled)\s+["\']([^"\']+)["\']/i',
            // "edit the post 'X'"
            '/edit\s+(?:the\s+)?(post|product)\s+["\']([^"\']+)["\']/i',
            // "update post 'X'"
            '/update\s+(?:the\s+)?(post|product)\s+["\']([^"\']+)["\']/i',
            // "edit 'X' post"
            '/edit\s+["\']([^"\']+)["\']\s+(post|product)/i',
            // "edit post X" (without quotes, but not a number)
            '/edit\s+(?:the\s+)?(post|product)\s+([a-zA-Z][a-zA-Z0-9\s]+?)(?:\s+with|\s+to|\s+and|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                $type = 'both';
                $name = null;

                // Determine type and name from matches
                if (count($matches) >= 3) {
                    // Pattern has both type and name
                    if (in_array(strtolower($matches[1]), ['post', 'product'])) {
                        $type = strtolower($matches[1]);
                        $name = $matches[2];
                    } else if (in_array(strtolower($matches[2]), ['post', 'product'])) {
                        $type = strtolower($matches[2]);
                        $name = $matches[1];
                    } else {
                        $name = $matches[1] ?? $matches[2];
                    }
                } else if (count($matches) >= 2) {
                    $name = $matches[1];
                }

                // Clean up the name
                if ($name) {
                    $name = trim($name);
                    // Remove common words that might be captured
                    $name = preg_replace('/\s+(with|to|and|the|a|an)\s+/i', ' ', $name);
                    $name = trim($name);
                    
                    // Make sure it's not just a number
                    if (!is_numeric($name) && strlen($name) > 0) {
                        return [
                            'name' => $name,
                            'type' => $type
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Replace name with ID in the query
     */
    private function replaceNameWithId($query, $name, $id, $type)
    {
        // Replace the name with ID in the query
        $modifiedQuery = str_ireplace(
            ["'{$name}'", "\"{$name}\"", "{$name}"],
            ["{$id}", "{$id}", "{$id}"],
            $query
        );
        
        // Also try to replace patterns like "post named X" with "post ID X"
        $modifiedQuery = preg_replace(
            "/\b(post|product)\s+(?:named|called|titled)\s+[\"']?{$name}[\"']?/i",
            "{$type} ID {$id}",
            $modifiedQuery
        );

        return $modifiedQuery;
    }

    // ✅ Detects questions about bot capabilities (should NOT be treated as fetch operations)
    private function isCapabilityQuestion($query)
    {
        $lowerQuery = strtolower(trim($query));
        
        // Patterns that indicate questions about capabilities
        $capabilityPatterns = [
            '/^what\s+(can|do|are)\s+(you|i)\s+(do|help|assist)/i',  // "What can you do?", "What do you do?"
            '/^what\s+(are|is)\s+(your|you)\s+(capabilities|features|functions|abilities)/i',  // "What are your capabilities?"
            '/^how\s+(can|do)\s+(you|i)\s+(help|assist)/i',  // "How can you help?"
            '/^(tell|show)\s+(me\s+)?(what\s+)?(can\s+)?(you\s+)?(do|help)/i',  // "Tell me what you can do"
            '/^(what|how)\s+(you\s+)?(can\s+)?(do|help)/i',  // "What you can do?", "How you can help?"
            '/^can\s+you\s+(help|do|assist)/i',  // "Can you help?"
            '/^(what|how)\s+are\s+you/i',  // "What are you?", "How are you?"
        ];
        
        foreach ($capabilityPatterns as $pattern) {
            if (preg_match($pattern, $lowerQuery)) {
                return true;
            }
        }
        
        return false;
    }

    // ✅ Detects Fetch operations (SELECT queries)
    private function isFetchOperation($query)
    {
        // Check for explicit fetch keywords (excluding "what" when it's about capabilities)
        $fetchKeywords = '/\b(show|list|fetch|get|view|display|select|give|provide|retrieve|find|search|see|tell|which|how many|count|sum|total|sales|revenue|orders|products|posts|users|customers|data|information|details|report)\b/i';
        
        // Check for question patterns that indicate data requests (excluding capability questions)
        $questionPattern = '/\b(can you|could you|please|i need|i want|show me|give me|get me|tell me|what is|what are|how many|how much)\b/i';
        
        // Check for data-related terms
        $dataTerms = '/\b(data|information|details|report|statistics|stats|summary|overview|all|every|each)\b/i';
        
        // Exclude capability questions from fetch operations
        if ($this->isCapabilityQuestion($query)) {
            return false;
        }
        
        // If query contains fetch keywords OR (question pattern AND data terms), it's a fetch operation
        if (preg_match($fetchKeywords, $query)) {
            return true;
        }
        
        // Check for question patterns combined with data terms (but not capability questions)
        if (preg_match($questionPattern, $query) && preg_match($dataTerms, $query)) {
            return true;
        }
        
        // Check for "what" questions about data (not capabilities)
        if (preg_match('/\bwhat\b/i', $query)) {
            // Only treat as fetch if it's asking about data, not capabilities
            if (preg_match('/\bwhat\s+(is|are|was|were)\s+(the|a|an|my|our|this|that|these|those)/i', $query) ||
                preg_match('/\bwhat\s+(is|are)\s+(in|from|of)\s+(the|my|our|this|that)/i', $query) ||
                preg_match('/\bwhat\s+(data|information|details|report|statistics|stats|summary|overview)\b/i', $query)) {
                return true;
            }
        }
        
        // Check for sales/revenue/orders specific queries
        if (preg_match('/\b(sales|revenue|income|profit|orders|transactions|earnings|revenue|income)\b/i', $query)) {
            return true;
        }
        
        return false;
    }

    // ✅ Detects WordPress API operations (create, update, delete)
    private function isWordPressApiOperation($query)
    {
        // Check for create, update, delete, edit operations
        $operationPattern = '/\b(create|add|new|insert|update|edit|modify|change|delete|remove|alter|publish|draft)\b/i';
        
        // Check for WordPress/WooCommerce specific terms
        $wpTermsPattern = '/\b(post|product|page|category|tag|order|customer|user|woocommerce|wordpress)\b/i';
        
        // Check for ID references (indicates specific operation)
        $idPattern = '/\b(id|ID)\s*[:\-]?\s*\d+\b/i';
        
        // Check for property assignments (indicates update/create)
        $propertyPattern = '/\b(with|set|to|as|price|title|content|name|status)\s*[:\-]?\s*["\']?[^"\']+["\']?/i';
        
        return preg_match($operationPattern, $query) && 
               (preg_match($wpTermsPattern, $query) || preg_match($idPattern, $query) || preg_match($propertyPattern, $query));
    }

    // ✅ Returns helpful response for unrecognized queries
    private function getHelpfulResponse($query)
    {
        $lowerQuery = strtolower($query);
        
        // Greetings
        if (preg_match('/\b(hi|hello|hey|greetings|good morning|good afternoon|good evening)\b/i', $query)) {
            return "Hello! I'm Hey Trisha, your WordPress assistant. I'm here to help you manage your WordPress site. " .
                   "You can ask me to show posts, products, or other data from your database. " .
                   "I can also help you edit posts or products by name or ID, and create new content. " .
                   "Try asking me something like 'Show me the last 10 posts' or 'Edit post named Your Post Title' and I'll help you right away!";
        }
        
        // Questions about capabilities
        if (preg_match('/\b(what|how|can you|help|capabilities|features)\b/i', $query)) {
            return "I can help you manage your WordPress site in many ways! " .
                   "I can view data from your database - just ask me things like 'Show me the last 10 posts', 'List all products', or 'Get all users' and I'll fetch that information for you. " .
                   "I can also edit your content - you can say 'Edit post named Your Post Title', 'Update product Laptop with price 1200', or 'Edit post ID 123'. " .
                   "And I can create new content too - try 'Create a new post titled Hello World' or 'Add a product named Widget priced at 50'. " .
                   "Just ask me in natural language and I'll understand what you need!";
        }
        
        // Default helpful message
        return "I'm not sure how to help with that specific request, but I'm here to assist you! " .
               "I can help you view data from your WordPress site - just ask me to show posts, list products, or get information about users. " .
               "I can also edit your content - you can edit posts or products by name or ID. " .
               "And I can create new posts or products for you. " .
               "Try rephrasing your request in a different way, or ask me 'What can you do?' and I'll explain all my capabilities!";
    }
}
