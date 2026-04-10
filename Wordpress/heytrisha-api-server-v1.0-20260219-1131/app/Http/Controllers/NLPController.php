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
    
    /**
     * Load WordPress security filter class if available
     * @return bool True if class is loaded and available
     */
    private function loadSecurityFilter() {
        // Use global namespace to prevent Laravel autoloader issues
        $securityFilterClass = '\\HeyTrisha_Security_Filter';
        
        // If already loaded, return true
        if (class_exists($securityFilterClass)) {
            return true;
        }
        
        // Try multiple path calculation methods for reliability
        $possible_paths = array();
        
        // Method 1: Calculate from current file location
        // NLPController.php is at: plugin/api/app/Http/Controllers/NLPController.php
        // We need: plugin/includes/class-heytrisha-security-filter.php
        // So: go up 4 levels from Controllers to plugin root
        $current_dir = __DIR__; // Controllers directory
        $plugin_root = dirname(dirname(dirname(dirname($current_dir))));
        $possible_paths[] = $plugin_root . '/includes/class-heytrisha-security-filter.php';
        
        // Method 2: Use ABSPATH if defined (WordPress root)
        if (defined('ABSPATH')) {
            $plugins_dir = ABSPATH . 'wp-content/plugins/';
            
            // Try exact name first
            $possible_paths[] = $plugins_dir . 'heytrisha-woo/includes/class-heytrisha-security-filter.php';
            
            // Try versioned directories using glob
            $versioned_dirs = glob($plugins_dir . 'heytrisha-woo-v*');
            if (!empty($versioned_dirs)) {
                foreach ($versioned_dirs as $versioned_dir) {
                    if (is_dir($versioned_dir)) {
                        $possible_paths[] = $versioned_dir . '/includes/class-heytrisha-security-filter.php';
                    }
                }
            }
        }
        
        // Method 3: Try to find plugin directory by looking for main plugin file
        // Go up from Controllers: Controllers -> Http -> app -> api -> plugin
        $plugin_base = dirname(dirname(dirname(dirname(__DIR__))));
        
        // Check if we're in the right place (should have api directory)
        if (file_exists($plugin_base . '/api') || file_exists($plugin_base . '/heytrisha-woo.php')) {
            $possible_paths[] = $plugin_base . '/includes/class-heytrisha-security-filter.php';
        }
        
        // Method 4: Try to find by searching for the main plugin file
        // Look in parent directories for heytrisha-woo.php
        $search_dir = dirname(dirname(dirname(dirname(__DIR__))));
        $max_levels = 3;
        $level = 0;
        while ($level < $max_levels && $search_dir !== '/' && $search_dir !== '') {
            if (file_exists($search_dir . '/heytrisha-woo.php')) {
                $possible_paths[] = $search_dir . '/includes/class-heytrisha-security-filter.php';
                break;
            }
            $search_dir = dirname($search_dir);
            $level++;
        }
        
        // Remove duplicates and empty paths
        $possible_paths = array_unique(array_filter($possible_paths));
        
        // Try each path until we find the file
        foreach ($possible_paths as $security_filter_path) {
            // Normalize path
            $security_filter_path = str_replace('\\', '/', $security_filter_path);
            
            if (file_exists($security_filter_path) && is_readable($security_filter_path)) {
                try {
                    // Define LARAVEL_START to allow loading from Laravel context
                    if (!defined('LARAVEL_START')) {
                        define('LARAVEL_START', microtime(true));
                    }
                    
                    // Suppress errors during require to prevent fatal errors
                    $old_error_reporting = error_reporting();
                    error_reporting($old_error_reporting & ~E_WARNING & ~E_NOTICE);
                    
                    require_once $security_filter_path;
                    
                    error_reporting($old_error_reporting);
                    
                    // Verify class exists and has required methods (use global namespace)
                    $securityFilterClass = '\\HeyTrisha_Security_Filter';
                    if (class_exists($securityFilterClass)) {
                        // Check if required methods exist
                        $required_methods = array('is_sensitive_query', 'is_sensitive_sql', 'filter_sensitive_results', 'get_rejection_message');
                        $all_methods_exist = true;
                        foreach ($required_methods as $method) {
                            if (!method_exists($securityFilterClass, $method)) {
                                $all_methods_exist = false;
                                Log::warning("Security filter class loaded but method '{$method}' not found");
                                break;
                            }
                        }
                        
                        if ($all_methods_exist) {
                            return true;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to load security filter from ' . $security_filter_path . ': ' . $e->getMessage());
                    continue;
                } catch (\Error $e) {
                    Log::warning('Fatal error loading security filter from ' . $security_filter_path . ': ' . $e->getMessage());
                    continue;
                }
            }
        }
        
        return false;
    }

    public function handleQuery(Request $request)
    {
        // ✅ Try multiple methods to get the query (Laravel Request can have data in different places)
        $userQuery = null;
        
        // Method 1: Try request->request->get() (POST data bag)
        if ($request->request->has('query')) {
            $userQuery = $request->request->get('query');
        }
        
        // Method 2: Try input() (works for form data and JSON, checks multiple sources)
        if (empty($userQuery)) {
            $userQuery = $request->input('query');
        }
        
        // Method 3: Try json() if it's JSON request
        if (empty($userQuery) && $request->isJson()) {
            $jsonData = $request->json()->all();
            $userQuery = isset($jsonData['query']) ? $jsonData['query'] : null;
        }
        
        // Method 4: Try all() and check for query
        if (empty($userQuery)) {
            $allData = $request->all();
            $userQuery = isset($allData['query']) ? $allData['query'] : null;
        }
        
        // Method 5: Try get() (for query parameters)
        if (empty($userQuery)) {
            $userQuery = $request->get('query');
        }
        
        // Method 6: Try request() helper
        if (empty($userQuery)) {
            $userQuery = request('query');
        }
        
        // Method 7: Try direct access to request bag
        if (empty($userQuery) && method_exists($request, 'get')) {
            $userQuery = $request->get('query');
        }
        
        // Log what we received for debugging
        Log::info("📥 Request Debug - request->request->get('query'): " . var_export($request->request->get('query'), true));
        Log::info("📥 Request Debug - input('query'): " . var_export($request->input('query'), true));
        Log::info("📥 Request Debug - all(): " . json_encode($request->all()));
        Log::info("📥 Request Debug - request->request->all(): " . json_encode($request->request->all()));
        Log::info("📥 Request Debug - json()->all(): " . ($request->isJson() ? json_encode($request->json()->all()) : 'not JSON'));
        Log::info("📥 Request Debug - Content-Type: " . ($request->header('Content-Type') ?? 'not set'));
        Log::info("📥 Request Debug - Method: " . $request->method());
        
        $isConfirmed = $request->input('confirmed', false);
        $confirmationData = $request->input('confirmation_data', null);
        
        // ✅ Validate query is not empty
        if (empty($userQuery) || !is_string($userQuery) || trim($userQuery) === '') {
            Log::warning("⚠️ Empty or invalid query received - userQuery: " . var_export($userQuery, true));
            Log::warning("⚠️ Request data dump: " . json_encode([
                'input_query' => $request->input('query'),
                'all' => $request->all(),
                'json' => $request->isJson() ? $request->json()->all() : null,
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
            ]));
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid query.'
            ], 400);
        }
        
        // ✅ Normalize query (trim whitespace)
        $userQuery = trim($userQuery);
        
        Log::info("📥 Received query: '{$userQuery}'");

        try {
            // 🚨 CRITICAL SECURITY: Check for sensitive data queries FIRST
            // Load WordPress security filter if available
            $securityFilterLoaded = false;
            $isSensitiveQuery = false;
            
            try {
                // Use global namespace class name to prevent Laravel autoloader issues
                $securityFilterClass = '\\HeyTrisha_Security_Filter';
                
                if ($this->loadSecurityFilter() && class_exists($securityFilterClass)) {
                    $securityFilterLoaded = true;
                    // Verify the method exists before calling
                    if (method_exists($securityFilterClass, 'is_sensitive_query')) {
                        // Use call_user_func to avoid namespace resolution issues
                        $isSensitiveQuery = call_user_func(array($securityFilterClass, 'is_sensitive_query'), $userQuery);
                        Log::info("🔒 Security Filter Check - Query: '{$userQuery}' | IsSensitive: " . ($isSensitiveQuery ? 'true' : 'false'));
                        
                        if ($isSensitiveQuery) {
                            // Verify get_rejection_message exists
                            $rejection_msg = method_exists($securityFilterClass, 'get_rejection_message') 
                                ? call_user_func(array($securityFilterClass, 'get_rejection_message'))
                                : "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.";
                            
                            Log::warning("🚨 BLOCKED sensitive query: '{$userQuery}'");
                            
                            return response()->json([
                                'success' => true,
                                'data' => null,
                                'message' => $rejection_msg,
                                'sql_query' => null
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // If security filter fails, log but continue processing
                Log::warning('Security filter error: ' . $e->getMessage());
                // Continue with normal processing
            } catch (\Error $e) {
                // Catch fatal errors too
                Log::warning('Security filter fatal error: ' . $e->getMessage());
                // Continue with normal processing
            }
            
            // ✅ Fallback security check if security filter class failed to load
            // This ensures we still block sensitive queries even if the class can't be loaded
            if (!$securityFilterLoaded) {
                $fallbackSensitivePatterns = array(
                    '/\b(password|passwd|pwd|pass|credentials)\b/i',
                    '/\b[\w\.-]+@[\w\.-]+\.\w+\s+(password|credentials)\b/i',
                    '/\b(password|credentials)\s+(for|of|to)\s+[\w\.-]+@[\w\.-]+\.\w+\b/i',
                    '/\b(give|get|show|tell|provide|share)\s+(.*\s+)?(password|credentials)\b/i',
                    '/\bcan\s+(you\s+)?(give|get|show|tell|provide|share)\s+(.*\s+)?(password|credentials)\b/i',
                    '/\buser\s+credentials\b/i',
                    '/\bcredentials\s+(for|of|to)\s+.*@.*\./i',
                );
                
                foreach ($fallbackSensitivePatterns as $pattern) {
                    if (preg_match($pattern, strtolower($userQuery))) {
                        Log::warning("🚨 BLOCKED sensitive query (fallback): '{$userQuery}'");
                        return response()->json([
                            'success' => true,
                            'data' => null,
                            'message' => "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.",
                            'sql_query' => null
                        ]);
                    }
                }
            }
            
            // ✅ If this is a confirmed edit, proceed directly
            if ($isConfirmed && $confirmationData) {
                return $this->executeConfirmedEdit($confirmationData);
            }

            // ✅ Check for capability questions FIRST (before fetch operations)
            // This prevents questions like "What you can do?" from being treated as data queries
            $isCapability = $this->isCapabilityQuestion($userQuery);
            
            // ✅ Check for creative/suggestion queries (SEO keywords, recommendations, etc.)
            // These should NOT be treated as pure fetch operations
            $isCreativeQuery = $this->isCreativeQuery($userQuery);
            
            $isFetch = false;
            if (!$isCreativeQuery) {
                $isFetch = $this->isFetchOperation($userQuery);
                
                // ✅ Aggressive fallback: If query has data terms and question words, treat as fetch
                if (!$isFetch && !$isCapability) {
                    $lowerQuery = strtolower(trim($userQuery));
                    $hasDataTerms = preg_match('/\b(data|information|details|report|statistics|stats|summary|product|item|order|transaction|sale|customer|user|post|page|category|tag|revenue|income|profit|earnings|sales|orders|transactions)\b/i', $lowerQuery);
                    $hasQuestionWords = preg_match('/\b(can|could|what|how|when|where|who|which|give|get|show|tell|list|find|provide|retrieve)\b/i', $lowerQuery);
                    
                    if ($hasDataTerms && $hasQuestionWords) {
                        Log::info("🔄 Aggressive detection: Treating as fetch operation - '{$userQuery}'");
                        $isFetch = true;
                    }
                }
            }
            
            Log::info("🔍 Query Analysis - Query: '{$userQuery}' | IsCapability: " . ($isCapability ? 'true' : 'false') . " | IsFetch: " . ($isFetch ? 'true' : 'false') . " | IsCreative: " . ($isCreativeQuery ? 'true' : 'false'));
            
            if ($isCapability) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => $this->getHelpfulResponse($userQuery)
                ]);
            }

            // ✅ Handle creative queries (SEO keywords, recommendations, suggestions, etc.)
            if ($isCreativeQuery) {
                return $this->handleCreativeQuery($userQuery);
            }

            // ✅ If the query is a fetch operation, use NLP with OpenAI
            if ($isFetch) {
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
                    // Return user-friendly error message (already formatted in SQLGeneratorService)
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

                // 🚨 CRITICAL SECURITY: Validate SQL for sensitive data access
                try {
                    // Use global namespace class name to prevent Laravel autoloader issues
                    $securityFilterClass = '\\HeyTrisha_Security_Filter';
                    
                    if ($this->loadSecurityFilter() && class_exists($securityFilterClass)) {
                        // Verify the method exists before calling
                        if (method_exists($securityFilterClass, 'is_sensitive_sql')) {
                            // Use call_user_func to avoid namespace resolution issues
                            $isSensitiveSQL = call_user_func(array($securityFilterClass, 'is_sensitive_sql'), $sqlQuery);
                            if ($isSensitiveSQL) {
                                Log::warning('🚨 Blocked SQL query attempting to access sensitive data');
                                // Verify get_rejection_message exists
                                $rejection_msg = method_exists($securityFilterClass, 'get_rejection_message') 
                                    ? call_user_func(array($securityFilterClass, 'get_rejection_message'))
                                    : "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.";
                                
                                return response()->json([
                                    'success' => true,
                                    'data' => null,
                                    'message' => $rejection_msg,
                                    'sql_query' => null
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // If security filter fails, log but continue processing
                    Log::warning('Security filter SQL validation error: ' . $e->getMessage());
                    // Continue with normal processing
                } catch (\Error $e) {
                    // Catch fatal errors too
                    Log::warning('Security filter SQL validation fatal error: ' . $e->getMessage());
                    // Continue with normal processing
                }

                // ✅ Step 4: Execute the SQL query locally on our database
                Log::info("💾 Step 4: Executing SQL query locally...");
                $result = $this->mysqlService->executeSQLQuery($sqlQuery);
                
                if (isset($result['error'])) {
                    Log::error("SQL Execution Error: " . $result['error']);
                    
                    // ✅ Get raw error message if available (for better error detection)
                    $rawError = $result['raw_error'] ?? $result['error'];
                    $userFriendlyError = $result['error'];
                    
                    // ✅ Check if this is a table/column error and if it's a customer/order query, try legacy fallback
                    // Check both raw error and user-friendly error message
                    $isTableNotFound = strpos($rawError, "doesn't exist") !== false || 
                                      strpos($rawError, "Base table or view not found") !== false ||
                                      (strpos($rawError, "Table") !== false && strpos($rawError, "not found") !== false) ||
                                      strpos($userFriendlyError, "data table was not found") !== false;
                    
                    $isColumnError = strpos($rawError, "Unknown column") !== false ||
                                    strpos($rawError, "Column not found") !== false ||
                                    strpos($userFriendlyError, "issue with the data structure") !== false;
                    
                    $queryLower = strtolower($userQuery);
                    $isCustomerQuery = strpos($queryLower, 'customer') !== false || 
                                      strpos($queryLower, 'order') !== false;
                    
                    $isProductQuery = strpos($queryLower, 'product') !== false || 
                                     strpos($queryLower, 'selling') !== false ||
                                     strpos($queryLower, 'sold') !== false;
                    
                    // Check if this is a quantity column error for product queries
                    $isQuantityColumnError = $isColumnError && $isProductQuery && 
                                           (strpos($rawError, 'quantity') !== false || 
                                            strpos($rawError, 'qty') !== false ||
                                            strpos($rawError, 'product_qty') !== false);
                    
                    // Check if query uses HPOS tables (wc_orders, wc_order_stats, etc.)
                    $usesHPOS = $this->isHPOSQuery($sqlQuery);
                    
                    Log::info("🔍 Error Analysis - TableNotFound: " . ($isTableNotFound ? 'YES' : 'NO') . 
                             " | ColumnError: " . ($isColumnError ? 'YES' : 'NO') . 
                             " | QuantityColumnError: " . ($isQuantityColumnError ? 'YES' : 'NO') .
                             " | IsCustomerQuery: " . ($isCustomerQuery ? 'YES' : 'NO') . 
                             " | IsProductQuery: " . ($isProductQuery ? 'YES' : 'NO') .
                             " | UsesHPOS: " . ($usesHPOS ? 'YES' : 'NO') . 
                             " | RawError: " . substr($rawError, 0, 100));
                    
                    // ✅ If quantity column error for product queries, try fallback using order_items/order_itemmeta
                    if ($isQuantityColumnError) {
                        Log::info("🔄 Quantity column error detected for product query, trying fallback with order_items...");
                        Log::info("🔄 Raw Error: " . $rawError);
                        $fallbackResult = $this->tryOrderItemsQuantityQuery($userQuery, $sqlQuery);
                        
                        if ($fallbackResult !== null && !isset($fallbackResult['error']) && !empty($fallbackResult['data'])) {
                            Log::info("✅ Order items quantity query found " . count($fallbackResult['data']) . " results!");
                            $result = $fallbackResult['data'];
                            $sqlQuery = $fallbackResult['sql_query'] ?? $sqlQuery;
                            // Continue processing with fallback results (skip the error return)
                        } else {
                            // If fallback also failed, return the original error
                            Log::warning("⚠️ Order items quantity query fallback also failed");
                            if (isset($fallbackResult['error'])) {
                                Log::warning("⚠️ Fallback error: " . $fallbackResult['error']);
                            }
                            return response()->json([
                                'success' => false,
                                'message' => $userFriendlyError
                            ], 500);
                        }
                    }
                    // If table/column error and it's a customer/order query using HPOS, try legacy fallback
                    elseif (($isTableNotFound || $isColumnError) && $isCustomerQuery && $usesHPOS) {
                        Log::info("🔄 Table/column error detected for customer/order query, trying legacy fallback...");
                        Log::info("🔄 Raw Error: " . $rawError);
                        $fallbackResult = $this->tryLegacyCustomerQuery($userQuery, $sqlQuery);
                        
                        if ($fallbackResult !== null && !isset($fallbackResult['error']) && !empty($fallbackResult['data'])) {
                            Log::info("✅ Legacy customer query found " . count($fallbackResult['data']) . " results!");
                            $result = $fallbackResult['data'];
                            $sqlQuery = $fallbackResult['sql_query'] ?? $sqlQuery;
                            // Continue processing with fallback results (skip the error return)
                        } else {
                            // If fallback also failed, return the original error
                            Log::warning("⚠️ Legacy customer query fallback also failed");
                            if (isset($fallbackResult['error'])) {
                                Log::warning("⚠️ Fallback error: " . $fallbackResult['error']);
                            }
                            return response()->json([
                                'success' => false,
                                'message' => $userFriendlyError
                            ], 500);
                        }
                    } else {
                        // Return user-friendly error message
                        return response()->json([
                            'success' => false,
                            'message' => $userFriendlyError
                        ], 500);
                    }
                }

                // ✅ Step 5: Check if result is valid array before processing
                if (!is_array($result)) {
                    Log::error("❌ Result is not an array: " . gettype($result));
                    return response()->json([
                        'success' => false,
                        'message' => "Invalid data format returned from database.",
                        'sql_query' => $sqlQuery
                    ], 500);
                }
                
                // ✅ Check if result is empty OR contains only a message (no actual data rows)
                // MySQLService returns ["message" => "No matching records found"] when empty
                // This is NOT empty() but has no actual data rows
                $hasDataRows = false;
                $noDataMessage = null;
                
                if (isset($result['message']) && count($result) === 1) {
                    // Result has only a "message" key - this means no data found
                    $noDataMessage = $result['message'];
                    $hasDataRows = false;
                } elseif (empty($result)) {
                    // Truly empty array
                    $noDataMessage = "I couldn't find any data matching your request.";
                    $hasDataRows = false;
                } else {
                    // Check if result has numeric keys (actual data rows)
                    // Data rows will have numeric keys (0, 1, 2...) or be indexed arrays
                    $hasDataRows = false;
                    foreach ($result as $key => $value) {
                        if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                            $hasDataRows = true;
                            break;
                        }
                    }
                    
                    // If no numeric keys found, check if it's a single row with data
                    if (!$hasDataRows && isset($result[0])) {
                        $hasDataRows = true;
                    }
                }
                
                if (!$hasDataRows) {
                    // No data rows found - check if this is an order query that might need legacy table fallback
                    $queryLower = strtolower($userQuery);
                    // Handle typos like "orderes" -> "orders"
                    $queryLower = preg_replace('/\borderes?\b/i', 'orders', $queryLower);
                    $isOrderQuery = strpos($queryLower, 'order') !== false;
                    
                    // ✅ CRITICAL: If order query returned 0 results and used HPOS tables, try legacy posts/postmeta tables
                    if ($isOrderQuery && $this->isHPOSQuery($sqlQuery)) {
                        Log::info("🔄 Order query returned 0 results with HPOS tables, trying legacy posts/postmeta tables...");
                        $fallbackResult = $this->tryLegacyOrderQuery($userQuery, $sqlQuery);
                        
                        if ($fallbackResult !== null) {
                            // Fallback query found results!
                            if (isset($fallbackResult['error'])) {
                                Log::warning("⚠️ Legacy order query failed: " . $fallbackResult['error']);
                            } elseif (!empty($fallbackResult['data'])) {
                                Log::info("✅ Legacy order query found " . count($fallbackResult['data']) . " results!");
                                // Use the fallback results
                                $result = $fallbackResult['data'];
                                $hasDataRows = true;
                                $sqlQuery = $fallbackResult['sql_query'] ?? $sqlQuery;
                                // Continue processing with fallback results
                            }
                        }
                    }
                    
                    // If still no data rows after fallback attempt, return error message
                    if (!$hasDataRows) {
                        $message = $noDataMessage ?: "I couldn't find any data matching your request. Please check if the data exists or try rephrasing your question.";
                        Log::info("ℹ️ No data rows found in result. Message: " . $message);
                        Log::info("ℹ️ SQL Query: " . $sqlQuery);
                        Log::info("ℹ️ User Query: " . $userQuery);
                        Log::info("ℹ️ Result structure: " . json_encode($result));
                        
                        // ✅ For order queries, provide more helpful message
                        if ($isOrderQuery) {
                            $message = "I couldn't find any orders matching your request. This could mean:\n" .
                                       "1. There are no orders in your database yet\n" .
                                       "2. The orders are stored in a different table than expected\n" .
                                       "3. The query needs to be rephrased\n\n" .
                                       "SQL Query attempted: " . $sqlQuery;
                            Log::warning("⚠️ Order query returned no results. SQL: " . $sqlQuery);
                        }
                        
                        return response()->json([
                            'success' => true,
                            'data' => [],
                            'message' => $message,
                            'sql_query' => $sqlQuery
                        ]);
                    }
                }

                // ✅ Step 6: Post-process results to add product names if product_id is present
                $result = $this->addProductNamesToResults($result);
                
                // 🚨 CRITICAL SECURITY: Filter sensitive columns from results
                try {
                    // Use global namespace class name to prevent Laravel autoloader issues
                    $securityFilterClass = '\\HeyTrisha_Security_Filter';
                    
                    if ($this->loadSecurityFilter() && class_exists($securityFilterClass)) {
                        // Verify the method exists before calling
                        if (method_exists($securityFilterClass, 'filter_sensitive_results')) {
                            // Use call_user_func to avoid namespace resolution issues
                            $result = call_user_func(array($securityFilterClass, 'filter_sensitive_results'), $result);
                            Log::info('✅ Filtered results for sensitive data');
                        }
                    }
                } catch (\Exception $e) {
                    // If security filter fails, log but continue processing
                    Log::warning('Security filter result filtering error: ' . $e->getMessage());
                    // Continue with unfiltered results (better than failing completely)
                } catch (\Error $e) {
                    // Catch fatal errors too
                    Log::warning('Security filter result filtering fatal error: ' . $e->getMessage());
                    // Continue with unfiltered results (better than failing completely)
                }
                
                // ✅ Step 7: Analyze results and generate human-friendly response
                try {
                    $analysis = $this->analyzeResultsAndGenerateResponse($userQuery, $result, $sqlQuery);
                } catch (\Exception $e) {
                    Log::error("⚠️ Error in analysis: " . $e->getMessage());
                    // Fallback to simple message if analysis fails
                    $analysis = [
                        'message' => "I found " . count($result) . " result" . (count($result) > 1 ? 's' : '') . " for your query.",
                        'analysis' => null
                    ];
                }
                
                // ✅ Step 8: Return results to frontend with analysis
                Log::info("✅ Step 8: Returning results to frontend (" . count($result) . " rows)");
                
                // Ensure message is always set (use analysis message or fallback)
                $message = $analysis['message'] ?? "Here's what I found:";
                
                return response()->json([
                    'success' => true, 
                    'data' => $result,
                    'message' => $message, // Human-friendly response
                    'analysis' => $analysis['analysis'] ?? null, // Detailed analysis
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
                    Log::warning("⚠️ Unrecognized query - no patterns matched: '{$userQuery}'");
                    Log::warning("⚠️ Query details - IsCapability: " . ($isCapability ? 'true' : 'false') . " | IsFetch: " . ($isFetch ? 'true' : 'false') . " | IsWordPressApi: " . ($this->isWordPressApiOperation($userQuery) ? 'true' : 'false'));
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
        // IMPORTANT: These should NOT match action requests like "Can you make a post"
        $capabilityPatterns = [
            '/^what\s+(can|do|are)\s+(you|i)\s+(do|help|assist)(\s+me)?\s*$/i',  // "What can you do?", "What do you do?"
            '/^what\s+(are|is)\s+(your|you)\s+(capabilities|features|functions|abilities)/i',  // "What are your capabilities?"
            '/^how\s+(can|do)\s+(you|i)\s+(help|assist)(\s+me)?\s*$/i',  // "How can you help?"
            '/^(tell|show)\s+(me\s+)?(what\s+)?(can\s+)?(you\s+)?(do|help)(\s+me)?\s*$/i',  // "Tell me what you can do"
            '/^(what|how)\s+(you\s+)?(can\s+)?(do|help)(\s+me)?\s*$/i',  // "What you can do?", "How you can help?"
            '/^can\s+you\s+(help|do|assist)(\s+me)?\s*$/i',  // "Can you help?" (but NOT "Can you make a post")
            '/^(what|how)\s+are\s+you(\s+doing)?\s*$/i',  // "What are you?", "How are you?"
        ];
        
        foreach ($capabilityPatterns as $pattern) {
            if (preg_match($pattern, $lowerQuery)) {
                // Additional check: if the query contains action verbs or WordPress terms, it's NOT a capability question
                // This prevents "Can you make a post" from being treated as a capability question
                if (preg_match('/\b(make|create|add|new|insert|update|edit|modify|change|delete|remove|post|product|page|category|tag|order)\b/i', $lowerQuery)) {
                    return false; // It's an action request, not a capability question
                }
                return true;
            }
        }
        
        return false;
    }

    // ✅ Detects Fetch operations (SELECT queries)
    private function isFetchOperation($query)
    {
        $lowerQuery = strtolower(trim($query));
        
        // Exclude capability questions from fetch operations FIRST
        if ($this->isCapabilityQuestion($query)) {
            return false;
        }
        
        // Exclude creative/suggestion queries from fetch operations
        // These should be handled separately by handleCreativeQuery
        if ($this->isCreativeQuery($query)) {
            return false;
        }
        
        // Check for explicit fetch keywords (excluding "what" when it's about capabilities)
        $fetchKeywords = '/\b(show|list|fetch|get|view|display|select|give|provide|retrieve|find|search|see|tell|share|which|how many|count|sum|total|sales|revenue|orders|products|posts|users|customers|data|information|details|report|selling|sold|popular|best|top|most|least|highest|lowest|average|avg|maximum|minimum|max|min|transaction|transactions)\b/i';
        
        // Check for question patterns that indicate data requests
        $questionPattern = '/\b(can you|could you|please|i need|i want|show me|give me|get me|tell me|what is|what are|how many|how much|who|when|where)\b/i';
        
        // Check for data-related terms (expanded list)
        $dataTerms = '/\b(data|information|details|report|statistics|stats|summary|overview|all|every|each|product|item|order|transaction|transactions|sale|sales|customer|customers|user|users|post|posts|page|pages|category|categories|tag|tags|revenue|income|profit|earnings)\b/i';
        
        // Check for time-related terms (often part of analytical queries)
        $timeTerms = '/\b(last|this|next|previous|yesterday|today|tomorrow|week|month|year|january|february|march|april|may|june|july|august|september|october|november|december|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i';
        
        // Check for comparative/superlative queries (most, best, top, etc.)
        $comparativePattern = '/\b(most|best|top|worst|least|highest|lowest|biggest|smallest|largest|fastest|slowest|newest|oldest|latest|earliest)\b/i';
        
        // ✅ PRIMARY CHECK: If query contains fetch keywords, it's a fetch operation
        if (preg_match($fetchKeywords, $lowerQuery)) {
            Log::info("✅ Fetch detected by keywords: '{$query}'");
            return true;
        }
        
        // ✅ SECONDARY CHECK: Question pattern + data terms = fetch operation
        if (preg_match($questionPattern, $lowerQuery) && preg_match($dataTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by question + data terms: '{$query}'");
            return true;
        }
        
        // ✅ TERTIARY CHECK: Question pattern + time terms = likely fetch operation (analytical query)
        if (preg_match($questionPattern, $lowerQuery) && preg_match($timeTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by question + time terms: '{$query}'");
            return true;
        }
        
        // ✅ FOURTH CHECK: Comparative/superlative + data terms = fetch operation
        if (preg_match($comparativePattern, $lowerQuery) && preg_match($dataTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by comparative + data terms: '{$query}'");
            return true;
        }
        
        // ✅ FIFTH CHECK: Data terms + time terms = fetch operation (analytical query)
        if (preg_match($dataTerms, $lowerQuery) && preg_match($timeTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by data + time terms: '{$query}'");
            return true;
        }
        
        // ✅ SIXTH CHECK: "what" questions about data (not capabilities)
        if (preg_match('/\bwhat\b/i', $lowerQuery)) {
            // Only treat as fetch if it's asking about data, not capabilities
            if (preg_match('/\bwhat\s+(is|are|was|were)\s+(the|a|an|my|our|this|that|these|those)/i', $lowerQuery) ||
                preg_match('/\bwhat\s+(is|are)\s+(in|from|of)\s+(the|my|our|this|that)/i', $lowerQuery) ||
                preg_match('/\bwhat\s+(data|information|details|report|statistics|stats|summary|overview)\b/i', $lowerQuery)) {
                Log::info("✅ Fetch detected by 'what' question: '{$query}'");
                return true;
            }
        }
        
        // ✅ SEVENTH CHECK: Sales/revenue/orders/transactions specific queries
        if (preg_match('/\b(sales|revenue|income|profit|orders|transactions|earnings|selling|sold)\b/i', $lowerQuery)) {
            Log::info("✅ Fetch detected by sales/revenue terms: '{$query}'");
            return true;
        }
        
        // ✅ EIGHTH CHECK: "give me" or "show me" patterns (common fetch requests)
        if (preg_match('/\b(give|show|get|tell|find|list)\s+(me\s+)?(the\s+)?(most|best|top|all|every|last|this|next)\b/i', $lowerQuery)) {
            Log::info("✅ Fetch detected by 'give/show me' pattern: '{$query}'");
            return true;
        }
        
        // ✅ NINTH CHECK: "can you" + action verb + data term = fetch operation
        if (preg_match('/\bcan\s+(you\s+)?(give|get|show|tell|provide|fetch|retrieve|find|list)\b/i', $lowerQuery) && preg_match($dataTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by 'can you' + action + data: '{$query}'");
            return true;
        }
        
        Log::info("❌ Query NOT detected as fetch operation: '{$query}'");
        return false;
    }

    // ✅ Detects creative/suggestion queries (SEO keywords, recommendations, etc.)
    // These queries should use OpenAI directly, not SQL generation
    private function isCreativeQuery($query)
    {
        $lowerQuery = strtolower(trim($query));
        
        // Check for creative/suggestion keywords
        $creativeKeywords = '/\b(suggest|recommend|recommendation|idea|ideas|advice|advise|tip|tips|strategy|strategies|improve|improvement|optimize|optimization|SEO|seo|keyword|keywords|meta|description|title|tagline|slogan|content|copy|write|draft|create|generate|brainstorm|help|assist|guide)\b/i';
        
        // Check for patterns like "suggest SEO keywords", "recommend products", etc.
        if (preg_match($creativeKeywords, $lowerQuery)) {
            Log::info("✅ Creative query detected: '{$query}'");
            return true;
        }
        
        // Check for "can you suggest/recommend" patterns
        if (preg_match('/\bcan\s+(you\s+)?(suggest|recommend|give|provide|help|assist)\b/i', $lowerQuery)) {
            Log::info("✅ Creative query detected (can you suggest/recommend): '{$query}'");
            return true;
        }
        
        return false;
    }

    // ✅ Handles creative queries by using OpenAI directly (with optional data context)
    private function handleCreativeQuery($userQuery)
    {
        Log::info("🎨 Handling creative query: '{$userQuery}'");
        
        try {
            // Check if OpenAI API key is configured
            $openaiKey = $this->configService->getOpenAIApiKey();
            if (empty($openaiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI API Key is not configured. Please set it in the Hey Trisha Chatbot settings page.'
                ], 500);
            }
            
            // Check if the query needs data first (e.g., "suggest SEO keywords for my most selling products")
            // Extract data requirements from the query
            $needsData = false;
            $dataQuery = null;
            
            // Patterns that indicate we need to fetch data first
            // Match patterns like "for my most selling products", "about the best products", etc.
            if (preg_match('/\b(for|of|about)\s+(my|the|our|this|that|these|those|your)?\s*(most|best|top|popular|selling|sold|recent|latest|newest|oldest)\s+(product|products|item|items|order|orders|post|posts|page|pages)\b/i', strtolower($userQuery), $matches)) {
                $needsData = true;
                // Extract the data query part
                $dataQuery = "Show me the " . (isset($matches[3]) ? $matches[3] . ' ' : '') . (isset($matches[4]) ? $matches[4] : '');
            } elseif (preg_match('/\b(most|best|top|popular|selling|sold|recent|latest|newest|oldest)\s+(product|products|item|items|order|orders|post|posts|page|pages)\b/i', strtolower($userQuery), $matches)) {
                // Match patterns like "most selling products" directly
                $needsData = true;
                $dataQuery = "Show me the " . $matches[1] . ' ' . $matches[2];
            }
            
            $contextData = null;
            $contextMessage = '';
            
            // If we need data, fetch it first
            if ($needsData && $dataQuery) {
                Log::info("📊 Creative query needs data context, fetching: '{$dataQuery}'");
                
                try {
                    // Get schema
                    $schema = $this->mysqlService->getCompactSchema($dataQuery);
                    
                    if (!isset($schema['error'])) {
                        // Generate SQL
                        $queryResponse = $this->sqlGenerator->queryChatGPTForSQL($dataQuery, $schema);
                        
                        if (!isset($queryResponse['error']) && isset($queryResponse['query'])) {
                            // Execute SQL
                            $result = $this->mysqlService->executeSQLQuery($queryResponse['query']);
                            
                            if (!isset($result['error']) && is_array($result) && !empty($result)) {
                                // ✅ CRITICAL: Add product names to results before using them
                                $result = $this->addProductNamesToResults($result);
                                
                                $contextData = $result;
                                $contextMessage = "Based on your " . (count($result) > 1 ? count($result) . ' items' : 'item') . ":\n\n";
                                
                                // For SEO keyword requests, include more detailed product information
                                if (stripos($userQuery, 'SEO') !== false || stripos($userQuery, 'keyword') !== false) {
                                    $contextMessage .= "PRODUCT DETAILS:\n";
                                    // Use ALL available products - no hardcoded limit
                                    // Extract limit from user query if specified
                                    $productLimit = null;
                                    $queryLower = strtolower($userQuery);
                                    if (preg_match('/\b(\d+)\s+(product|item)/i', $queryLower, $matches)) {
                                        $productLimit = (int)$matches[1];
                                    } elseif (preg_match('/\b(top|first|last|latest)\s+(\d+)/i', $queryLower, $matches)) {
                                        $productLimit = (int)$matches[2];
                                    }
                                    
                                    foreach ($result as $idx => $item) {
                                        // Only limit if explicitly requested in query
                                        if ($productLimit !== null && $idx >= $productLimit) break;
                                        $itemArray = is_object($item) ? (array)$item : $item;
                                        
                                        // Extract product name (check multiple possible field names)
                                        $productName = $itemArray['product_name'] ?? 
                                                      $itemArray['post_title'] ?? 
                                                      $itemArray['name'] ?? 
                                                      $itemArray['title'] ?? 
                                                      $itemArray['Product Name'] ?? 
                                                      $itemArray['Product'] ?? 
                                                      null;
                                        
                                        // If still no product name, try to fetch it using product_id
                                        if (empty($productName)) {
                                            $productId = $itemArray['product_id'] ?? 
                                                        $itemArray['Product ID'] ?? 
                                                        $itemArray['product'] ?? 
                                                        $itemArray['id'] ?? 
                                                        null;
                                            
                                            if ($productId && $productId > 0) {
                                                $productName = $this->getProductNameById($productId);
                                            }
                                        }
                                        
                                        // If still no name, use fallback
                                        if (empty($productName)) {
                                            $productName = 'Product ' . ($idx + 1);
                                        }
                                        
                                        // Extract product description or other details
                                        $description = $itemArray['description'] ?? 
                                                      $itemArray['post_excerpt'] ?? 
                                                      $itemArray['short_description'] ?? 
                                                      $itemArray['Description'] ?? 
                                                      '';
                                        
                                        // Get total sold or other metrics
                                        $totalSold = $itemArray['total_sold'] ?? 
                                                    $itemArray['Total Sold'] ?? 
                                                    $itemArray['quantity'] ?? 
                                                    $itemArray['Quantity'] ?? 
                                                    '';
                                        
                                        $contextMessage .= "- " . $productName;
                                        if (!empty($totalSold)) {
                                            $contextMessage .= " (Sold: " . $totalSold . ")";
                                        }
                                        if (!empty($description)) {
                                            $contextMessage .= ": " . substr(strip_tags($description), 0, 150);
                                        }
                                        $contextMessage .= "\n";
                                    }
                                    $contextMessage .= "\n";
                                }
                                
                                // Create a summary of the data
                                $dataSummary = $this->prepareDataSummary($result, $userQuery);
                                $contextMessage .= $dataSummary;
                            } else {
                                // ⚠️ CRITICAL: If data fetch failed or returned no results, return error
                                // Don't generate fake data - user wants real data from their database
                                Log::warning("⚠️ Data fetch failed or returned no results for creative query context");
                                if (isset($result['error'])) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => "I couldn't retrieve the data needed to answer your question. " . $result['error']
                                    ], 500);
                                } else {
                                    return response()->json([
                                        'success' => false,
                                        'message' => "I couldn't find any data matching your request. Please check if the data exists in your database."
                                    ], 500);
                                }
                            }
                        } else {
                            // SQL generation failed
                            Log::warning("⚠️ SQL generation failed for creative query context");
                            return response()->json([
                                'success' => false,
                                'message' => "I couldn't generate a query to retrieve the data. Please try rephrasing your question."
                            ], 500);
                        }
                    } else {
                        // Schema fetch failed
                        Log::warning("⚠️ Schema fetch failed for creative query context");
                        return response()->json([
                            'success' => false,
                            'message' => "I couldn't access your database schema. Please check your database connection."
                        ], 500);
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error fetching data for creative query context: " . $e->getMessage());
                    // Return error instead of continuing with fake data
                    return response()->json([
                        'success' => false,
                        'message' => "I encountered an error while trying to retrieve data from your database: " . $e->getMessage()
                    ], 500);
                }
            }
            
            // ⚠️ CRITICAL: If we don't have data context, we cannot provide accurate suggestions
            // Return error instead of generating fake data
            if (!$contextData || !$contextMessage) {
                Log::warning("⚠️ No data context available for creative query - cannot provide accurate suggestions");
                return response()->json([
                    'success' => false,
                    'message' => "I need access to your actual database data to provide accurate suggestions. I couldn't retrieve the necessary data from your database. Please ensure your database is accessible and contains the relevant information."
                ], 500);
            }
            
            // Build prompt for creative response
            $prompt = "You are a helpful AI assistant for a WordPress/WooCommerce website. " .
                     "The user is asking for creative help, suggestions, or recommendations.\n\n" .
                     "User's Request: \"$userQuery\"\n\n" .
                     "⚠️⚠️⚠️ CRITICAL: You MUST use ONLY the actual data provided below. DO NOT invent, guess, or use generic examples.\n\n";
            
            $prompt .= $contextMessage . "\n\n";
            $prompt .= "⚠️⚠️⚠️ CRITICAL INSTRUCTIONS:\n" .
                      "- Use ONLY the ACTUAL product names, descriptions, and data from the information provided above\n" .
                      "- DO NOT create, invent, or guess any product names or data\n" .
                      "- DO NOT use generic examples like 'Blue Velvet Sofa', 'Smartphone Gimbal', 'Organic Matcha', 'Leather Backpack', 'Plant Stand' unless they appear in the data above\n" .
                      "- If the data above shows specific products, use ONLY those products\n" .
                      "- If the request is about SEO keywords:\n" .
                      "  * Use the ACTUAL product names from the data provided above\n" .
                      "  * Generate specific, relevant SEO keywords based on the ACTUAL products shown in the data\n" .
                      "  * Include product-specific keywords, category keywords, and long-tail keywords\n" .
                      "  * For each product mentioned in the data, suggest 3-5 specific SEO keywords\n" .
                      "  * Example: If the data shows product 'Wireless Bluetooth Headphones', suggest keywords like 'wireless bluetooth headphones', 'noise cancelling headphones', 'bluetooth earbuds', etc.\n" .
                      "  * DO NOT use generic placeholders or invented product names\n" .
                      "- If the request is about recommendations, provide clear, useful recommendations based on the ACTUAL data provided\n" .
                      "- Use natural, conversational language\n" .
                      "- Be concise but informative\n" .
                      "- ⚠️⚠️⚠️ REMEMBER: This is an analytical tool - all suggestions must be based on REAL data from the database, not generic examples\n\n" .
                      "IMPORTANT: Return ONLY the response text - no markdown, no code blocks, no JSON. Just plain, friendly text.\n\n" .
                      "Your Response:";
            
            // Call OpenAI for creative response
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $openaiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful AI assistant that provides creative suggestions, recommendations, and advice for WordPress/WooCommerce websites. ⚠️ CRITICAL: You MUST use ONLY the actual data provided by the user from their database. DO NOT invent, guess, or use generic examples. All suggestions must be based on REAL data from the user\'s database.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 1000,
                'temperature' => 0.8 // Higher temperature for more creative responses
            ]);
            
            if ($response->successful()) {
                $openAIResponse = $response->json();
                $creativeText = $openAIResponse['choices'][0]['message']['content'] ?? null;
                
                if ($creativeText) {
                    // Clean up the response
                    $creativeText = trim($creativeText);
                    $creativeText = preg_replace('/^```[\w]*\n?/', '', $creativeText);
                    $creativeText = preg_replace('/\n?```$/', '', $creativeText);
                    $creativeText = trim($creativeText);
                    
                    Log::info("✅ Generated creative response: " . substr($creativeText, 0, 100) . "...");
                    
                    return response()->json([
                        'success' => true,
                        'data' => $contextData, // Include context data if available
                        'message' => $creativeText
                    ]);
                }
            }
            
            // Fallback if OpenAI fails
            return response()->json([
                'success' => false,
                'message' => 'I encountered an issue generating a creative response. Please try again or rephrase your request.'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error("❌ Error handling creative query: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'I encountered an error processing your creative request. Please try again or rephrase your question.'
            ], 500);
        }
    }

    // ✅ Detects WordPress API operations (create, update, delete)
    private function isWordPressApiOperation($query)
    {
        // Check for create, update, delete, edit operations
        // Added "make" and other common verbs for creating content
        $operationPattern = '/\b(create|make|add|new|insert|update|edit|modify|change|delete|remove|alter|publish|draft|build|generate)\b/i';
        
        // Check for WordPress/WooCommerce specific terms
        $wpTermsPattern = '/\b(post|product|page|category|tag|order|customer|user|woocommerce|wordpress)\b/i';
        
        // Check for ID references (indicates specific operation)
        $idPattern = '/\b(id|ID)\s*[:\-]?\s*\d+\b/i';
        
        // Check for property assignments (indicates update/create)
        // Enhanced to catch "Title Hello World" pattern
        $propertyPattern = '/\b(with|set|to|as|price|title|content|name|status|titled|called|named)\s*[:\-]?\s*["\']?[^"\']+["\']?/i';
        
        // Also check for direct property patterns like "Title Hello World" (without "with/set/to")
        $directPropertyPattern = '/\b(title|content|name|price|status)\s+["\']?[^"\']+["\']?/i';
        
        return preg_match($operationPattern, $query) && 
               (preg_match($wpTermsPattern, $query) || preg_match($idPattern, $query) || preg_match($propertyPattern, $query) || preg_match($directPropertyPattern, $query));
    }

    // ✅ Returns helpful response for unrecognized queries
    /**
     * ✅ Post-process results to add product names if product_id is present
     * Fetches product names from WordPress posts table when product_id is in results
     */
    private function addProductNamesToResults($result)
    {
        // Check if result is an array and has product_id
        if (!is_array($result) || empty($result)) {
            return $result;
        }
        
        // Check if any row has product_id but no product name
        $needsProductNames = false;
        $productIds = [];
        
        foreach ($result as $row) {
            if (is_object($row)) {
                $row = (array)$row;
            }
            
            // Check for product_id in various possible field names
            $productId = $row['product_id'] ?? $row['Product ID'] ?? $row['product'] ?? $row['id'] ?? null;
            
            // Only add if we have a product_id and no product name
            if ($productId && !isset($row['product_name']) && !isset($row['post_title']) && !isset($row['name']) && !isset($row['Product Name'])) {
                $needsProductNames = true;
                $productIds[] = $productId;
            }
        }
        
        if (!$needsProductNames || empty($productIds)) {
            return $result;
        }
        
        // Fetch product names from database
        try {
            $productIds = array_unique($productIds);
            $productIdsStr = implode(',', array_map('intval', $productIds));
            
            // Get posts table name from config
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts table name (auto-detect site ID if needed)
            $postsTable = 'wp_posts';
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Auto-detect site ID from table names if config returned site ID 1
            $hasMultisitePattern = false;
            foreach ($allTableNames as $tableName) {
                if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                    $hasMultisitePattern = true;
                    break;
                }
            }
            
            if ($hasMultisitePattern || $isMultisite) {
                // If site ID is 1, try to detect actual site ID
                if ($currentSiteId == 1) {
                    $siteIdCounts = [];
                    foreach ($allTableNames as $tableName) {
                        if (preg_match('/^wp\d+_(\d+)_/', $tableName, $matches)) {
                            $siteId = (int)$matches[1];
                            if ($siteId > 0) {
                                $siteIdCounts[$siteId] = ($siteIdCounts[$siteId] ?? 0) + 1;
                            }
                        }
                    }
                    if (!empty($siteIdCounts)) {
                        arsort($siteIdCounts);
                        $currentSiteId = array_key_first($siteIdCounts);
                    }
                }
                
                // Find posts table for current site ID
                foreach ($allTableNames as $tableName) {
                    if (preg_match('/^wp\d+_' . preg_quote($currentSiteId, '/') . '_posts$/', $tableName)) {
                        $postsTable = $tableName;
                        break;
                    }
                }
            }
            
            // Fetch product names from posts table
            $products = \Illuminate\Support\Facades\DB::select(
                "SELECT ID, post_title FROM `{$postsTable}` WHERE ID IN ({$productIdsStr}) AND post_type = 'product'"
            );
            
            // Create a map of product_id => product_name
            $productNameMap = [];
            foreach ($products as $product) {
                $productNameMap[$product->ID] = $product->post_title;
            }
            
            // Add product names to results
            foreach ($result as &$row) {
                if (is_object($row)) {
                    $row = (array)$row;
                }
                
                // Check for product_id in various possible field names
                $productId = $row['product_id'] ?? $row['Product ID'] ?? $row['product'] ?? $row['id'] ?? null;
                
                if ($productId && isset($productNameMap[$productId])) {
                    $row['product_name'] = $productNameMap[$productId];
                    // Also set Product Name for consistency
                    $row['Product Name'] = $productNameMap[$productId];
                }
            }
            
            Log::info("✅ Added product names for " . count($productNameMap) . " products");
        } catch (\Exception $e) {
            Log::warning("⚠️ Could not fetch product names: " . $e->getMessage());
            // Continue without product names if fetch fails
        }
        
        return $result;
    }
    
    /**
     * ✅ Get product name by product ID (helper method)
     * Fetches product name from database for a single product ID
     */
    private function getProductNameById($productId)
    {
        if (empty($productId) || $productId <= 0) {
            return null;
        }
        
        try {
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts table name using the same method as addProductNamesToResults
            $postsTable = 'wp_posts';
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            $hasMultisitePattern = false;
            foreach ($allTableNames as $tableName) {
                if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                    $hasMultisitePattern = true;
                    break;
                }
            }
            
            if ($hasMultisitePattern || $isMultisite) {
                if ($currentSiteId == 1) {
                    $siteIdCounts = [];
                    foreach ($allTableNames as $tableName) {
                        if (preg_match('/^wp\d+_(\d+)_/', $tableName, $matches)) {
                            $siteId = (int)$matches[1];
                            if ($siteId > 0) {
                                $siteIdCounts[$siteId] = ($siteIdCounts[$siteId] ?? 0) + 1;
                            }
                        }
                    }
                    if (!empty($siteIdCounts)) {
                        arsort($siteIdCounts);
                        $currentSiteId = array_key_first($siteIdCounts);
                    }
                }
                
                foreach ($allTableNames as $tableName) {
                    if (preg_match('/^wp\d+_' . preg_quote($currentSiteId, '/') . '_posts$/', $tableName)) {
                        $postsTable = $tableName;
                        break;
                    }
                }
            } else {
                foreach ($allTableNames as $tableName) {
                    if (preg_match('/^wp\d*_posts$/', $tableName)) {
                        $postsTable = $tableName;
                        break;
                    }
                }
            }
            
            // Fetch product name from posts table
            $product = \Illuminate\Support\Facades\DB::selectOne(
                "SELECT post_title FROM `{$postsTable}` WHERE ID = ? AND post_type = 'product'",
                [$productId]
            );
            
            if ($product && isset($product->post_title)) {
                return $product->post_title;
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Could not fetch product name for ID {$productId}: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * ✅ Analyze SQL results and generate human-friendly, analytical response
     * Uses OpenAI to analyze data and provide insights in conversational format
     */
    private function analyzeResultsAndGenerateResponse($userQuery, $result, $sqlQuery)
    {
        try {
            // Validate result is a proper array
            if (!is_array($result)) {
                Log::warning("⚠️ Result is not an array: " . gettype($result));
                return [
                    'message' => "I couldn't process the results. Please try again.",
                    'analysis' => null
                ];
            }
            
            // If result is empty or has error message, return simple message
            if (empty($result) || (isset($result['message']) && !isset($result[0]))) {
                $message = isset($result['message']) ? $result['message'] : "I couldn't find any data matching your request.";
                return [
                    'message' => $message,
                    'analysis' => null
                ];
            }
            
            // Check if result contains error key (shouldn't happen here, but safety check)
            if (isset($result['error'])) {
                Log::warning("⚠️ Result contains error: " . $result['error']);
                return [
                    'message' => $result['error'],
                    'analysis' => null
                ];
            }
            
            // Prepare data summary for OpenAI
            $dataSummary = $this->prepareDataSummary($result, $userQuery);
            
            // Get OpenAI API key
            $apiKey = $this->configService->getOpenAIApiKey();
            if (!$apiKey) {
                // Fallback to simple summary if no API key
                return $this->generateSimpleSummary($userQuery, $result);
            }
            
            // Build prompt for analysis
            $prompt = "You are a helpful AI assistant analyzing WordPress/WooCommerce data. Your role is to provide clear, friendly, and insightful responses.\n\n" .
                     "User's Question: \"$userQuery\"\n\n" .
                     "Data Retrieved:\n$dataSummary\n\n" .
                     "⚠️⚠️⚠️ CRITICAL INSTRUCTIONS:\n" .
                     "- Use ONLY the actual data provided above - DO NOT invent, guess, or create placeholder data\n" .
                     "- If the data shows customer names, use those EXACT names - DO NOT use placeholders like '[Customer 2]', '[Customer 3]'\n" .
                     "- If the data shows 5 customers, list all 5 with their actual names from the data\n" .
                     "- If the data shows product names, use those EXACT names - DO NOT create generic examples\n" .
                     "- All information must come from the data provided above\n\n" .
                     "Your Task:\n" .
                     "1. Analyze the data and understand what the user is asking\n" .
                     "2. Provide a friendly, conversational response that answers their question\n" .
                     "3. Include key insights and numbers in a natural, human way\n" .
                     "4. If showing specific items (customers, products, orders, etc.), mention them by their ACTUAL names from the data\n" .
                     "5. For customer queries: List each customer with their actual name, email, and other details from the data\n" .
                     "6. For analytical queries (totals, counts, trends), provide context and insights\n" .
                     "7. Be concise but informative - don't just list numbers, explain what they mean\n" .
                     "8. Use friendly, conversational language - like you're explaining to a colleague\n\n" .
                     "Response Guidelines:\n" .
                     "- Start with a friendly acknowledgment of their question\n" .
                     "- Present the key findings clearly\n" .
                     "- ⚠️⚠️⚠️ CRITICAL: Use the ACTUAL count from the data - if the data shows 1 customer, say '1 customer', NOT '5 customers'\n" .
                     "- Use natural language (e.g., 'I found 1 customer' if data shows 1, 'I found 5 customers' if data shows 5)\n" .
                     "- If showing a list of customers/products, list ALL of them with their ACTUAL names from the data\n" .
                     "- For customer lists: Show each customer's actual name, email, and relevant details from the data\n" .
                     "- If the data shows fewer customers than requested (e.g., asked for 5 but only 1 exists), mention the actual number found\n" .
                     "- DO NOT use placeholders like '[Customer 2]', '[Product Name]' - use the ACTUAL names from the data\n" .
                     "- DO NOT claim to have found more customers than are actually in the data\n" .
                     "- For totals/amounts, format numbers nicely (e.g., '$1,234.56' not '1234.56')\n" .
                     "- End with a helpful note if relevant\n\n" .
                     "IMPORTANT: Return ONLY the response text - no markdown, no code blocks, no JSON. Just plain, friendly text.\n\n" .
                     "Your Response:";
            
            // Call OpenAI for analysis
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful AI assistant that analyzes data and provides friendly, conversational responses. ⚠️ CRITICAL: You MUST use ONLY the actual data provided from the user\'s database. DO NOT invent, guess, or create placeholder data. All information must come from the actual database results provided.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]);
            
            if ($response->successful()) {
                $openAIResponse = $response->json();
                $analysisText = $openAIResponse['choices'][0]['message']['content'] ?? null;
                
                if ($analysisText) {
                    // Clean up the response (remove markdown if any)
                    $analysisText = trim($analysisText);
                    $analysisText = preg_replace('/^```[\w]*\n?/', '', $analysisText);
                    $analysisText = preg_replace('/\n?```$/', '', $analysisText);
                    $analysisText = trim($analysisText);
                    
                    Log::info("✅ Generated friendly response: " . substr($analysisText, 0, 100) . "...");
                    
                    return [
                        'message' => $analysisText,
                        'analysis' => $dataSummary
                    ];
                }
            }
            
            // Fallback to simple summary if OpenAI fails
            return $this->generateSimpleSummary($userQuery, $result);
            
        } catch (\Exception $e) {
            Log::warning("⚠️ Error generating analysis: " . $e->getMessage());
            // Fallback to simple summary
            return $this->generateSimpleSummary($userQuery, $result);
        }
    }
    
    /**
     * ✅ Prepare data summary for OpenAI analysis
     */
    private function prepareDataSummary($result, $userQuery = null)
    {
        // Validate input
        if (!is_array($result)) {
            Log::warning("⚠️ prepareDataSummary: Result is not an array");
            return "Invalid data format.";
        }
        
        // Check for error messages
        if (isset($result['error']) || (isset($result['message']) && !isset($result[0]))) {
            return "No data found.";
        }
        
        if (empty($result)) {
            return "No data found.";
        }
        
        // Ensure result is a numeric array (not associative with error keys)
        $numericArray = array_values($result);
        if (empty($numericArray)) {
            return "No data found.";
        }
        
        // Use ALL available data - no hardcoded limits
        // Extract any limit from user query, otherwise use all available records
        $queryLower = strtolower($userQuery ?? '');
        $isCustomerQuery = strpos($queryLower, 'customer') !== false;
        
        // Try to extract a number from the query (e.g., "5 customers", "10 products")
        $maxRecords = null;
        if (preg_match('/\b(\d+)\s+(customer|product|order|item|record|result)/i', $queryLower, $matches)) {
            $maxRecords = (int)$matches[1];
        } elseif (preg_match('/\b(lastest|latest|last|first|top)\s+(\d+)/i', $queryLower, $matches)) {
            $maxRecords = (int)$matches[2];
        }
        
        // If no limit specified in query, use ALL available data
        // Only limit if explicitly requested by user
        if ($maxRecords === null) {
            $maxRecords = count($numericArray); // Use all available records
        } else {
            $maxRecords = min($maxRecords, count($numericArray)); // Don't exceed available data
        }
        
        $sampleData = array_slice($numericArray, 0, $maxRecords);
        
        $summary = "Total records: " . count($result) . "\n\n";
        
        if (count($sampleData) > 0) {
            $summary .= ($isCustomerQuery ? "Customer data (all " . count($sampleData) . " records):\n" : "Sample data:\n");
            foreach ($sampleData as $index => $row) {
                if (is_object($row)) {
                    $row = (array)$row;
                }
                
                // For customer queries, use more descriptive labels with actual customer name
                if ($isCustomerQuery) {
                    $customerName = $row['customer_name'] ?? $row['Customer Name'] ?? $row['display_name'] ?? $row['name'] ?? 'Unknown';
                    $summary .= "Customer " . ($index + 1) . " - " . $customerName . ":\n";
                } else {
                    $summary .= "Record " . ($index + 1) . ":\n";
                }
                
                foreach ($row as $key => $value) {
                    // ✅ Format values intelligently based on field type
                    if (is_numeric($value)) {
                        $keyLower = strtolower($key);
                        
                        // ✅ IDs should NEVER be formatted (keep as integers)
                        if (strpos($keyLower, 'id') !== false || strpos($keyLower, '_id') !== false || $key === 'ID') {
                            $value = (int)$value; // Keep as integer, no formatting
                        }
                        // ✅ Counts, quantities should be integers (no decimals)
                        elseif (strpos($keyLower, 'count') !== false || strpos($keyLower, 'quantity') !== false || 
                                strpos($keyLower, 'qty') !== false || strpos($keyLower, 'total_items') !== false ||
                                strpos($keyLower, 'num_') !== false) {
                            $value = (int)$value; // Integer, no decimals
                        }
                        // ✅ Currency/price/amount fields - format with 2 decimals ONLY if it has decimals
                        elseif (strpos($keyLower, 'price') !== false || strpos($keyLower, 'amount') !== false || 
                                strpos($keyLower, 'total') !== false || strpos($keyLower, 'revenue') !== false ||
                                strpos($keyLower, 'sales') !== false || strpos($keyLower, 'cost') !== false ||
                                strpos($keyLower, 'value') !== false) {
                            // Only format if it's a decimal number
                            if (is_float($value) || strpos((string)$value, '.') !== false) {
                                $value = number_format((float)$value, 2, '.', ','); // e.g., 1,234.56
                            } else {
                                $value = number_format((float)$value, 2, '.', ','); // e.g., 1,234.00
                            }
                        }
                        // ✅ Large integers (non-currency) - format with commas but no decimals
                        elseif (abs($value) >= 1000) {
                            $value = number_format((int)$value, 0, '.', ','); // e.g., 1,234
                        }
                        // ✅ Small numbers/percentages - keep as-is
                        else {
                            $value = $value; // Keep original value
                        }
                    }
                    $summary .= "  - " . ucwords(str_replace('_', ' ', $key)) . ": " . $value . "\n";
                }
                $summary .= "\n";
            }
            
            if (count($result) > $maxRecords) {
                $summary .= "... and " . (count($result) - $maxRecords) . " more records.\n";
            }
        }
        
        return $summary;
    }
    
    /**
     * ✅ Get user query from context (helper for prepareDataSummary)
     */
    private function getUserQuery()
    {
        // Try to get from request if available
        try {
            $request = request();
            if ($request && $request->has('query')) {
                return $request->input('query');
            }
        } catch (\Exception $e) {
            // Ignore if request not available
        }
        return null;
    }
    
    /**
     * ✅ Generate simple summary when OpenAI is not available
     * Uses actual database data - no hardcoded values
     */
    private function generateSimpleSummary($userQuery, $result)
    {
        if (empty($result) || (is_array($result) && isset($result['message']))) {
            $message = is_array($result) && isset($result['message']) ? $result['message'] : "I couldn't find any data matching your request.";
            return [
                'message' => $message,
                'analysis' => null
            ];
        }
        
        $count = count($result);
        $queryLower = strtolower($userQuery);
        
        // Generate friendly message based on query type using ACTUAL database data
        $firstRow = is_object($result[0]) ? (array)$result[0] : $result[0];
        
        // ✅ Check for COUNT queries (single row, single column with count/total/number)
        if ($count === 1 && is_array($firstRow)) {
            $keys = array_keys($firstRow);
            $values = array_values($firstRow);
            
            // If single column result, it's likely a COUNT or aggregate query
            if (count($keys) === 1) {
                $keyName = strtolower($keys[0]);
                $value = $values[0];
                
                // ✅ Count queries - show as integer (using actual database value)
                if (strpos($keyName, 'count') !== false || strpos($keyName, 'total') !== false || 
                    strpos($keyName, 'number') !== false || strpos($keyName, 'num_') !== false) {
                    $displayValue = is_numeric($value) ? number_format((int)$value, 0) : $value;
                    $friendlyKey = str_replace('_', ' ', $keys[0]);
                    return [
                        'message' => "I found the answer to your query. The " . $friendlyKey . " is " . $displayValue . ".",
                        'analysis' => null
                    ];
                }
                
                // ✅ Sum/revenue/amount queries - show with currency formatting (using actual database value)
                if (strpos($keyName, 'sum') !== false || strpos($keyName, 'revenue') !== false || 
                    strpos($keyName, 'sales') !== false || strpos($keyName, 'amount') !== false ||
                    strpos($keyName, 'value') !== false || strpos($keyName, 'price') !== false) {
                    $displayValue = is_numeric($value) ? '$' . number_format((float)$value, 2) : $value;
                    $friendlyKey = str_replace('_', ' ', $keys[0]);
                    return [
                        'message' => "I found the total for you. The " . $friendlyKey . " is " . $displayValue . ".",
                        'analysis' => null
                    ];
                }
            }
        }
        
        // ✅ For queries with specific total/sum/value columns (using actual database values)
        if (strpos($queryLower, 'total') !== false || strpos($queryLower, 'sum') !== false || 
            strpos($queryLower, 'revenue') !== false || strpos($queryLower, 'sales') !== false) {
            $totalKey = null;
            foreach ($firstRow as $key => $value) {
                $keyLower = strtolower($key);
                if (strpos($keyLower, 'total') !== false || strpos($keyLower, 'sum') !== false || 
                    strpos($keyLower, 'value') !== false || strpos($keyLower, 'amount') !== false ||
                    strpos($keyLower, 'revenue') !== false || strpos($keyLower, 'sales') !== false) {
                    $totalKey = $key;
                    break;
                }
            }
            
            if ($totalKey && isset($firstRow[$totalKey])) {
                $value = $firstRow[$totalKey];
                $keyLower = strtolower($totalKey);
                
                // Format based on field type (using actual database value)
                if (strpos($keyLower, 'count') !== false) {
                    $displayValue = is_numeric($value) ? number_format((int)$value, 0) : $value;
                } else {
                    $displayValue = is_numeric($value) ? '$' . number_format((float)$value, 2) : $value;
                }
                
                return [
                    'message' => "I found the total you're looking for. The " . str_replace('_', ' ', $totalKey) . " is " . $displayValue . ".",
                    'analysis' => null
                ];
            }
        }
        
        // ✅ For customer queries - extract actual customer names from database results
        if (strpos($queryLower, 'customer') !== false && $count > 0) {
            $customerNames = [];
            $maxCustomers = min($count, 5); // Show up to 5 customers
            
            for ($i = 0; $i < $maxCustomers; $i++) {
                $row = is_object($result[$i]) ? (array)$result[$i] : $result[$i];
                $customerName = $row['customer_name'] ?? $row['Customer Name'] ?? $row['display_name'] ?? $row['name'] ?? null;
                if ($customerName) {
                    $customerNames[] = $customerName;
                }
            }
            
            if (!empty($customerNames)) {
                $namesList = implode(', ', $customerNames);
                if ($count > $maxCustomers) {
                    $message = "I found " . $count . " customers. Here are the first " . $maxCustomers . ": " . $namesList . ", and " . ($count - $maxCustomers) . " more.";
                } else {
                    $message = "I found " . $count . " customer" . ($count > 1 ? 's' : '') . ": " . $namesList . ".";
                }
                return [
                    'message' => $message,
                    'analysis' => null
                ];
            }
        }
        
        // ✅ For product queries - extract actual product names from database results
        if ((strpos($queryLower, 'product') !== false || strpos($queryLower, 'selling') !== false) && $count > 0) {
            $productNames = [];
            $maxProducts = min($count, 5); // Show up to 5 products
            
            for ($i = 0; $i < $maxProducts; $i++) {
                $row = is_object($result[$i]) ? (array)$result[$i] : $result[$i];
                $productName = $row['product_name'] ?? $row['Product Name'] ?? $row['post_title'] ?? $row['name'] ?? $row['title'] ?? null;
                if ($productName) {
                    $productNames[] = $productName;
                }
            }
            
            if (!empty($productNames)) {
                $namesList = implode(', ', $productNames);
                if ($count > $maxProducts) {
                    $message = "I found " . $count . " products. Here are the top " . $maxProducts . ": " . $namesList . ", and " . ($count - $maxProducts) . " more.";
                } else {
                    $message = "I found " . $count . " product" . ($count > 1 ? 's' : '') . ": " . $namesList . ".";
                }
                return [
                    'message' => $message,
                    'analysis' => null
                ];
            }
        }
        
        // Dynamic message based on actual count - no hardcoded thresholds
        $message = "I found " . $count . " result" . ($count > 1 ? 's' : '') . " for your query. ";
        
        // Dynamic message based on actual count - no hardcoded thresholds
        if ($count > 0) {
            $message .= "Here are the details:";
        } else {
            $message .= "No results found.";
        }
        
        return [
            'message' => $message,
            'analysis' => null
        ];
    }
    
    /**
     * ✅ Check if SQL query uses HPOS tables (wc_orders, wc_order_stats)
     * Returns true if query uses HPOS tables, false otherwise
     */
    private function isHPOSQuery($sqlQuery)
    {
        $sqlLower = strtolower($sqlQuery);
        // Check for HPOS table patterns (including wp_wc_* prefixed versions)
        $hposPatterns = [
            '/\bwc_orders\b/',
            '/\bwc_order_stats\b/',
            '/\bwc_order_product_lookup\b/',
            '/\bwp_wc_orders\b/',  // Prefixed version
            '/\bwp_wc_order_stats\b/',  // Prefixed version
            '/\bwp_wc_order_product_lookup\b/',  // Prefixed version
        ];
        
        foreach ($hposPatterns as $pattern) {
            if (preg_match($pattern, $sqlLower)) {
                Log::info("🔍 Detected HPOS table in query: " . $pattern);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ✅ Try legacy customer query using wp_posts and wp_users tables
     * This handles customer queries when HPOS tables don't exist
     * 
     * @param string $userQuery Original user query
     * @param string $originalSqlQuery Original SQL query that failed
     * @return array|null Returns result array with 'data' and 'sql_query' if successful, null if should not try, or array with 'error' if failed
     */
    private function tryLegacyCustomerQuery($userQuery, $originalSqlQuery)
    {
        try {
            Log::info("🔄 Attempting legacy customer query fallback for: " . $userQuery);
            
            // Get WordPress config to find table names
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts and users table names
            $postsTable = $this->detectPostsTableName($currentSiteId, $isMultisite);
            if (!$postsTable) {
                Log::warning("⚠️ Could not detect posts table name for legacy customer query");
                return null;
            }
            
            // Detect users table name
            $usersTable = $this->detectUsersTableName($currentSiteId, $isMultisite);
            if (!$usersTable) {
                Log::warning("⚠️ Could not detect users table name for legacy customer query");
                return null;
            }
            
            Log::info("📋 Using legacy tables: {$postsTable}, {$usersTable}");
            
            // Extract LIMIT from original query or user query - NO HARDCODED DEFAULTS
            // Use NLP to extract the number from the user's query, or use all available data
            $limit = null; // No default - will be determined from query or actual data
            
            // First, try to extract from SQL query
            if (preg_match('/\bLIMIT\s+(\d+)\b/i', $originalSqlQuery, $limitMatches)) {
                $limit = (int)$limitMatches[1];
            }
            // Extract number from user query if "last N customers" or "latest N customers" or "latestest ordered N customers"
            if (preg_match('/\b(lastest|latest|latestest)\s+(\d+)\s+(ordered\s+)?(new\s+)?customers?\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[2];
            } elseif (preg_match('/\b(lastest|latest|last)\s+(\d+)\s+(new\s+)?customers?\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[2];
            } elseif (preg_match('/\b(\d+)\s+(ordered\s+)?(new\s+)?customers?\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[1];
            }
            
            // If no limit found in query, don't set a default - let the query return all available data
            // The LIMIT will only be added if explicitly requested by the user
            
            // Check if query is asking for "new customers" (recently registered) vs "latest customers" (recent orders)
            $queryLower = strtolower($userQuery);
            $isNewCustomerQuery = (strpos($queryLower, 'new customer') !== false || 
                                 strpos($queryLower, 'newly registered') !== false ||
                                 strpos($queryLower, 'recent customer') !== false) &&
                                 strpos($queryLower, 'ordered') === false; // "new customers" not "new ordered customers"
            
            // "latestest ordered" or "latest ordered" means customers with recent orders, not new registrations
            $isOrderedCustomerQuery = strpos($queryLower, 'ordered') !== false || 
                                     strpos($queryLower, 'latestest ordered') !== false ||
                                     strpos($queryLower, 'latest ordered') !== false;
            
            if ($isNewCustomerQuery && !$isOrderedCustomerQuery) {
                // For "new customers", order by user_registered DESC (most recently registered users)
                // Include all users, not just those with orders
                Log::info("🆕 Detected 'new customers' query - ordering by registration date");
                $legacySql = "SELECT u.ID as customer_id, u.display_name as customer_name, u.user_email as customer_email, " .
                            "u.user_login, u.user_registered, " .
                            "COUNT(DISTINCT p.ID) as total_orders, " .
                            "MAX(p.post_date) as last_order_date " .
                            "FROM `{$usersTable}` u " .
                            "LEFT JOIN `{$postsTable}` p ON p.post_author = u.ID AND p.post_type = 'shop_order' " .
                            "GROUP BY u.ID, u.display_name, u.user_email, u.user_login, u.user_registered " .
                            "ORDER BY u.user_registered DESC" .
                            ($limit !== null ? " LIMIT {$limit}" : "");
            } else {
                // For "latest customers" or "latestest ordered customers" (customers with recent orders), order by last_order_date DESC
                // Only include customers who have placed orders
                // Use a more comprehensive approach: check both post_author and _customer_user meta
                // This ensures we catch all customers who have placed orders, even if post_author doesn't match
                Log::info("📅 Detected 'latest/ordered customers' query - ordering by last order date");
                
                // First, try the standard query using post_author
                $legacySql = "SELECT u.ID as customer_id, u.display_name as customer_name, u.user_email as customer_email, " .
                            "u.user_login, u.user_registered, " .
                            "COUNT(DISTINCT p.ID) as total_orders, " .
                            "MAX(p.post_date) as last_order_date " .
                            "FROM `{$usersTable}` u " .
                            "INNER JOIN `{$postsTable}` p ON p.post_author = u.ID AND p.post_type = 'shop_order' " .
                            "GROUP BY u.ID, u.display_name, u.user_email, u.user_login, u.user_registered " .
                            "ORDER BY last_order_date DESC" .
                            ($limit !== null ? " LIMIT {$limit}" : "");
                
                // Execute and check if we got enough results
                Log::info("💾 Executing legacy customer query (method 1 - post_author): " . $legacySql);
                $legacyResult = $this->mysqlService->executeSQLQuery($legacySql);
                
                // If we got fewer results than requested, try alternative method using _customer_user meta
                if (!isset($legacyResult['error'])) {
                    $resultCount = is_array($legacyResult) ? count($legacyResult) : 0;
                    $dataRows = [];
                    foreach ($legacyResult as $key => $value) {
                        if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                            $dataRows[] = $value;
                        }
                    }
                    $actualCount = count($dataRows);
                    
                    if ($actualCount < $limit && $actualCount > 0) {
                        Log::info("📊 Method 1 returned {$actualCount} customer(s), trying alternative method using _customer_user meta...");
                        
                        // Try alternative query using _customer_user meta key
                        // This catches customers where the order's customer_user meta matches the user ID
                        // Construct postmeta table name from posts table name
                        $metaTable = str_replace('_posts', '_postmeta', $postsTable);
                        
                        if ($metaTable && $metaTable !== $postsTable) {
                            $alternativeSql = "SELECT DISTINCT u.ID as customer_id, u.display_name as customer_name, u.user_email as customer_email, " .
                                            "u.user_login, u.user_registered, " .
                                            "COUNT(DISTINCT p.ID) as total_orders, " .
                                            "MAX(p.post_date) as last_order_date " .
                                            "FROM `{$usersTable}` u " .
                                            "INNER JOIN `{$metaTable}` pm ON pm.meta_value = u.ID AND pm.meta_key = '_customer_user' " .
                                            "INNER JOIN `{$postsTable}` p ON p.ID = pm.post_id AND p.post_type = 'shop_order' " .
                                            "GROUP BY u.ID, u.display_name, u.user_email, u.user_login, u.user_registered " .
                                            "ORDER BY last_order_date DESC" .
                                            ($limit !== null ? " LIMIT {$limit}" : "");
                            
                            Log::info("💾 Executing alternative customer query (method 2 - _customer_user meta): " . $alternativeSql);
                            $alternativeResult = $this->mysqlService->executeSQLQuery($alternativeSql);
                            
                            if (!isset($alternativeResult['error'])) {
                                $altDataRows = [];
                                foreach ($alternativeResult as $key => $value) {
                                    if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                                        $altDataRows[] = $value;
                                    }
                                }
                                $altCount = count($altDataRows);
                                
                                if ($altCount > $actualCount) {
                                    Log::info("✅ Alternative method found {$altCount} customer(s) vs {$actualCount} from method 1 - using alternative results");
                                    $legacyResult = $alternativeResult;
                                } else {
                                    Log::info("ℹ️ Alternative method found {$altCount} customer(s) - same or fewer than method 1, keeping original results");
                                }
                            }
                        }
                    }
                }
            }
            
            // If query wasn't executed yet (for "new customers" queries), execute it now
            if (!isset($legacyResult)) {
                Log::info("💾 Executing legacy customer query: " . $legacySql);
                Log::info("📊 Query parameters - Limit: {$limit}, IsNewCustomerQuery: " . ($isNewCustomerQuery ? 'YES' : 'NO'));
                
                // Execute the legacy query
                $legacyResult = $this->mysqlService->executeSQLQuery($legacySql);
            }
            
            // Log the number of results returned
            if (isset($legacyResult) && !isset($legacyResult['error'])) {
                $resultCount = is_array($legacyResult) ? count($legacyResult) : 0;
                Log::info("📊 Legacy customer query returned {$resultCount} rows (requested: {$limit})");
                
                // If we got fewer results than requested, log a warning
                if ($resultCount < $limit && $resultCount > 0) {
                    Log::warning("⚠️ Query returned only {$resultCount} customer(s) but {$limit} were requested. This may indicate there are only {$resultCount} customer(s) with orders in the database.");
                }
            }
            
            if (isset($legacyResult['error'])) {
                Log::warning("⚠️ Legacy customer query execution failed: " . $legacyResult['error']);
                
                // Try an even simpler query - check if it's a "new customers" query
                $queryLower = strtolower($userQuery);
                $isNewCustomerQuery = strpos($queryLower, 'new customer') !== false || 
                                     strpos($queryLower, 'newly registered') !== false ||
                                     strpos($queryLower, 'recent customer') !== false;
                
                if ($isNewCustomerQuery) {
                    // For "new customers", just get most recently registered users
                    Log::info("🔄 Trying simpler new customer query - ordering by registration date...");
                    $simpleSql = "SELECT u.ID as customer_id, u.display_name as customer_name, u.user_email as customer_email, " .
                                "u.user_login, u.user_registered, " .
                                "COUNT(DISTINCT p.ID) as total_orders, " .
                                "MAX(p.post_date) as last_order_date " .
                                "FROM `{$usersTable}` u " .
                                "LEFT JOIN `{$postsTable}` p ON p.post_author = u.ID AND p.post_type = 'shop_order' " .
                                "GROUP BY u.ID, u.display_name, u.user_email, u.user_login, u.user_registered " .
                                "ORDER BY u.user_registered DESC" .
                                ($limit !== null ? " LIMIT {$limit}" : "");
                } else {
                    // For "latest customers", get users with recent orders
                    // Remove DISTINCT as GROUP BY already ensures distinct customers
                    Log::info("🔄 Trying simpler customer query - getting distinct customers by most recent order...");
                    $simpleSql = "SELECT u.ID as customer_id, u.display_name as customer_name, u.user_email as customer_email, " .
                                "u.user_login, u.user_registered, " .
                                "COUNT(DISTINCT p.ID) as total_orders, " .
                                "MAX(p.post_date) as last_order_date " .
                                "FROM `{$usersTable}` u " .
                                "INNER JOIN `{$postsTable}` p ON p.post_author = u.ID AND p.post_type = 'shop_order' " .
                                "GROUP BY u.ID, u.display_name, u.user_email, u.user_login, u.user_registered " .
                                "ORDER BY last_order_date DESC" .
                                ($limit !== null ? " LIMIT {$limit}" : "");
                }
                
                Log::info("💾 Executing simpler legacy customer query: " . $simpleSql);
                $simpleResult = $this->mysqlService->executeSQLQuery($simpleSql);
                
                if (isset($simpleResult['error'])) {
                    Log::warning("⚠️ Simple legacy customer query also failed: " . $simpleResult['error']);
                    return ['error' => $simpleResult['error']];
                }
                
                // Check if simple query has results
                if (!empty($simpleResult) && !(isset($simpleResult['message']) && count($simpleResult) === 1)) {
                    $hasDataRows = false;
                    foreach ($simpleResult as $key => $value) {
                        if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                            $hasDataRows = true;
                            break;
                        }
                    }
                    
                    if ($hasDataRows) {
                        Log::info("✅ Simple legacy customer query found " . count($simpleResult) . " results!");
                        return [
                            'data' => $simpleResult,
                            'sql_query' => $simpleSql
                        ];
                    }
                }
                
                return ['error' => $legacyResult['error']];
            }
            
            // Check if we got results
            if (empty($legacyResult) || (isset($legacyResult['message']) && count($legacyResult) === 1)) {
                Log::info("ℹ️ Legacy customer query also returned 0 results");
                return null;
            }
            
            // Check if we have actual data rows
            $hasDataRows = false;
            $dataRows = [];
            foreach ($legacyResult as $key => $value) {
                if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                    $hasDataRows = true;
                    $dataRows[] = $value;
                }
            }
            
            if ($hasDataRows) {
                $actualCount = count($dataRows);
                Log::info("✅ Legacy customer query found {$actualCount} result(s) (requested: {$limit})");
                
                // If we got fewer results than requested, log additional diagnostic info
                if ($actualCount < $limit) {
                    Log::warning("⚠️ Query returned only {$actualCount} customer(s) but {$limit} were requested.");
                    Log::info("📊 This indicates there are only {$actualCount} customer(s) with orders in the database.");
                    
                    // Try to get total count of customers with orders for diagnostic purposes
                    try {
                        $countSql = "SELECT COUNT(DISTINCT u.ID) as total_customers_with_orders " .
                                    "FROM `{$usersTable}` u " .
                                    "INNER JOIN `{$postsTable}` p ON p.post_author = u.ID AND p.post_type = 'shop_order'";
                        $countResult = $this->mysqlService->executeSQLQuery($countSql);
                        if (!isset($countResult['error']) && !empty($countResult)) {
                            $totalCustomers = is_object($countResult[0]) ? (array)$countResult[0] : $countResult[0];
                            $totalCount = $totalCustomers['total_customers_with_orders'] ?? 'unknown';
                            Log::info("📊 Total customers with orders in database: {$totalCount}");
                        }
                    } catch (\Exception $e) {
                        Log::warning("⚠️ Could not get total customer count: " . $e->getMessage());
                    }
                }
                
                return [
                    'data' => $legacyResult,
                    'sql_query' => $legacySql
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error in legacy customer query fallback: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * ✅ Try order items quantity query using wp_woocommerce_order_items and wp_woocommerce_order_itemmeta
     * This calculates quantity from individual order items when quantity column is not available
     * 
     * @param string $userQuery Original user query
     * @param string $originalSqlQuery Original SQL query that failed
     * @return array|null Returns result array with 'data' and 'sql_query' if successful, null if should not try, or array with 'error' if failed
     */
    private function tryOrderItemsQuantityQuery($userQuery, $originalSqlQuery)
    {
        try {
            Log::info("🔄 Attempting order items quantity query fallback for: " . $userQuery);
            
            // Get WordPress config to find table names
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect order_items and order_itemmeta table names
            $orderItemsTable = $this->detectOrderItemsTableName($currentSiteId, $isMultisite);
            if (!$orderItemsTable) {
                Log::warning("⚠️ Could not detect order_items table name");
                return null;
            }
            
            $orderItemmetaTable = str_replace('_order_items', '_order_itemmeta', $orderItemsTable);
            
            // Detect posts table for product names
            $postsTable = $this->detectPostsTableName($currentSiteId, $isMultisite);
            if (!$postsTable) {
                Log::warning("⚠️ Could not detect posts table name");
                return null;
            }
            
            Log::info("📋 Using order items tables: {$orderItemsTable}, {$orderItemmetaTable}, {$postsTable}");
            
            // Extract LIMIT from original query or user query
            $limit = 10; // Default limit
            if (preg_match('/\bLIMIT\s+(\d+)\b/i', $originalSqlQuery, $limitMatches)) {
                $limit = (int)$limitMatches[1];
            }
            // Extract number from user query
            if (preg_match('/\b(top|best|most)\s+(\d+)\s+(selling|ordered|sold|products?)\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[2];
            } elseif (preg_match('/\b(\d+)\s+(selling|ordered|sold|products?)\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[1];
            }
            
            // Build SQL query using order_items and order_itemmeta
            // Get quantity from _qty meta and product_id from _product_id meta
            $fallbackSql = "SELECT " .
                          "CAST(oim_product.meta_value AS UNSIGNED) as product_id, " .
                          "p.post_title AS product_name, " .
                          "SUM(CAST(oim_qty.meta_value AS UNSIGNED)) AS total_sold " .
                          "FROM `{$orderItemsTable}` oi " .
                          "INNER JOIN `{$orderItemmetaTable}` oim_product ON oim_product.order_item_id = oi.order_item_id AND oim_product.meta_key = '_product_id' " .
                          "INNER JOIN `{$orderItemmetaTable}` oim_qty ON oim_qty.order_item_id = oi.order_item_id AND oim_qty.meta_key = '_qty' " .
                          "INNER JOIN `{$postsTable}` p ON p.ID = CAST(oim_product.meta_value AS UNSIGNED) AND p.post_type = 'product' " .
                          "WHERE oi.order_item_type = 'line_item' " .
                          "GROUP BY CAST(oim_product.meta_value AS UNSIGNED), p.post_title " .
                          "ORDER BY total_sold DESC" .
                          ($limit !== null ? " LIMIT {$limit}" : "");
            
            Log::info("💾 Executing order items quantity query: " . $fallbackSql);
            
            // Execute the fallback query
            $fallbackResult = $this->mysqlService->executeSQLQuery($fallbackSql);
            
            if (isset($fallbackResult['error'])) {
                Log::warning("⚠️ Order items quantity query execution failed: " . $fallbackResult['error']);
                
                // Try simpler query without product name join
                Log::info("🔄 Trying simpler order items quantity query without product name...");
                $simpleSql = "SELECT " .
                            "CAST(oim_product.meta_value AS UNSIGNED) as product_id, " .
                            "SUM(CAST(oim_qty.meta_value AS UNSIGNED)) AS total_sold " .
                            "FROM `{$orderItemsTable}` oi " .
                            "INNER JOIN `{$orderItemmetaTable}` oim_product ON oim_product.order_item_id = oi.order_item_id AND oim_product.meta_key = '_product_id' " .
                            "INNER JOIN `{$orderItemmetaTable}` oim_qty ON oim_qty.order_item_id = oi.order_item_id AND oim_qty.meta_key = '_qty' " .
                            "WHERE oi.order_item_type = 'line_item' " .
                            "GROUP BY CAST(oim_product.meta_value AS UNSIGNED) " .
                            "ORDER BY total_sold DESC " .
                            "LIMIT {$limit}";
                
                Log::info("💾 Executing simpler order items quantity query: " . $simpleSql);
                $simpleResult = $this->mysqlService->executeSQLQuery($simpleSql);
                
                if (isset($simpleResult['error'])) {
                    Log::warning("⚠️ Simple order items quantity query also failed: " . $simpleResult['error']);
                    return ['error' => $simpleResult['error']];
                }
                
                // Check if simple query has results
                if (!empty($simpleResult) && !(isset($simpleResult['message']) && count($simpleResult) === 1)) {
                    $hasDataRows = false;
                    foreach ($simpleResult as $key => $value) {
                        if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                            $hasDataRows = true;
                            break;
                        }
                    }
                    
                    if ($hasDataRows) {
                        // Add product names to results
                        $simpleResult = $this->addProductNamesToResults($simpleResult);
                        Log::info("✅ Simple order items quantity query found " . count($simpleResult) . " results!");
                        return [
                            'data' => $simpleResult,
                            'sql_query' => $simpleSql
                        ];
                    }
                }
                
                return ['error' => $fallbackResult['error']];
            }
            
            // Check if we got results
            if (empty($fallbackResult) || (isset($fallbackResult['message']) && count($fallbackResult) === 1)) {
                Log::info("ℹ️ Order items quantity query also returned 0 results");
                return null;
            }
            
            // Check if we have actual data rows
            $hasDataRows = false;
            foreach ($fallbackResult as $key => $value) {
                if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                    $hasDataRows = true;
                    break;
                }
            }
            
            if ($hasDataRows) {
                Log::info("✅ Order items quantity query found " . count($fallbackResult) . " results!");
                
                return [
                    'data' => $fallbackResult,
                    'sql_query' => $fallbackSql
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error in order items quantity query fallback: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * ✅ Detect order_items table name based on site ID and multisite status
     */
    private function detectOrderItemsTableName($currentSiteId, $isMultisite)
    {
        try {
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Look for wp_woocommerce_order_items or wp{number}_woocommerce_order_items
            foreach ($allTableNames as $tableName) {
                // Match wp_woocommerce_order_items or wp{number}_woocommerce_order_items
                if (preg_match('/^wp\d*_woocommerce_order_items$/', $tableName)) {
                    return $tableName;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("❌ Error detecting order_items table: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ Detect users table name based on site ID and multisite status
     */
    private function detectUsersTableName($currentSiteId, $isMultisite)
    {
        try {
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Users table is network-level in multisite, so it doesn't have site ID prefix
            // Look for wp_users or wp{number}_users (without site ID)
            foreach ($allTableNames as $tableName) {
                // Match wp_users or wp{number}_users (but not wp{number}_{siteid}_users)
                if (preg_match('/^wp\d*_users$/', $tableName) && !preg_match('/^wp\d+_\d+_users$/', $tableName)) {
                    return $tableName;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("❌ Error detecting users table: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ Try legacy order query using wp_posts and wp_postmeta tables
     * This is for old WordPress sites where orders are stored as posts with post_type='shop_order'
     * 
     * @param string $userQuery Original user query
     * @param string $originalSqlQuery Original SQL query that returned 0 results
     * @return array|null Returns result array with 'data' and 'sql_query' if successful, null if should not try, or array with 'error' if failed
     */
    private function tryLegacyOrderQuery($userQuery, $originalSqlQuery)
    {
        try {
            Log::info("🔄 Attempting legacy order query fallback for: " . $userQuery);
            
            // Get WordPress config to find posts table name
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts table name
            $postsTable = $this->detectPostsTableName($currentSiteId, $isMultisite);
            if (!$postsTable) {
                Log::warning("⚠️ Could not detect posts table name for legacy query");
                return null;
            }
            
            $postmetaTable = str_replace('_posts', '_postmeta', $postsTable);
            
            Log::info("📋 Using legacy tables: {$postsTable} and {$postmetaTable}");
            
            // Extract LIMIT from original query if present - NO HARDCODED DEFAULTS
            $limit = null; // No default - will be determined from query or use all available data
            if (preg_match('/\bLIMIT\s+(\d+)\b/i', $originalSqlQuery, $limitMatches)) {
                $limit = (int)$limitMatches[1];
            }
            // Try to extract number from user query
            if ($limit === null && preg_match('/\b(\d+)\s+(order|item|product)/i', strtolower($userQuery), $matches)) {
                $limit = (int)$matches[1];
            } elseif ($limit === null && preg_match('/\b(top|first|last|latest)\s+(\d+)/i', strtolower($userQuery), $matches)) {
                $limit = (int)$matches[2];
            }
            
            // Extract number from user query if "last N orders"
            if (preg_match('/\blast\s+(\d+)\s+orders?\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[1];
            }
            
            // Build legacy SQL query
            // For "last N orders", use: SELECT * FROM wp_posts WHERE post_type='shop_order' ORDER BY post_date DESC LIMIT N
            // Include common order fields that users might expect
            $legacySql = "SELECT p.ID as order_id, p.post_date as order_date, p.post_date_gmt as order_date_gmt, " .
                        "p.post_modified as order_modified, p.post_modified_gmt as order_modified_gmt, " .
                        "p.post_status as status, p.post_title, p.post_excerpt, p.post_content, " .
                        "p.post_parent as parent_id, p.post_author as customer_id " .
                        "FROM `{$postsTable}` p " .
                        "WHERE p.post_type = 'shop_order' " .
                        "ORDER BY p.post_date DESC" .
                        ($limit !== null ? " LIMIT {$limit}" : "");
            
            Log::info("💾 Executing legacy order query: " . $legacySql);
            
            // Execute the legacy query
            $legacyResult = $this->mysqlService->executeSQLQuery($legacySql);
            
            if (isset($legacyResult['error'])) {
                Log::warning("⚠️ Legacy order query execution failed: " . $legacyResult['error']);
                return ['error' => $legacyResult['error']];
            }
            
            // Check if we got results
            if (empty($legacyResult) || (isset($legacyResult['message']) && count($legacyResult) === 1)) {
                Log::info("ℹ️ Legacy order query also returned 0 results");
                return null;
            }
            
            // Check if we have actual data rows
            $hasDataRows = false;
            foreach ($legacyResult as $key => $value) {
                if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                    $hasDataRows = true;
                    break;
                }
            }
            
            if ($hasDataRows) {
                Log::info("✅ Legacy order query found " . count($legacyResult) . " results!");
                
                // Optionally enrich with postmeta data (order totals, customer info, etc.)
                $enrichedResult = $this->enrichLegacyOrderResults($legacyResult, $postmetaTable);
                
                return [
                    'data' => $enrichedResult,
                    'sql_query' => $legacySql
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error in legacy order query fallback: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * ✅ Detect posts table name based on site ID and multisite status
     */
    private function detectPostsTableName($currentSiteId, $isMultisite)
    {
        try {
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Detect multisite pattern
            $hasMultisitePattern = false;
            foreach ($allTableNames as $tableName) {
                if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                    $hasMultisitePattern = true;
                    break;
                }
            }
            
            if ($hasMultisitePattern || $isMultisite) {
                // Find posts table for current site ID
                $pattern = '/^wp\d+_' . preg_quote($currentSiteId, '/') . '_posts$/';
                foreach ($allTableNames as $tableName) {
                    if (preg_match($pattern, $tableName)) {
                        return $tableName;
                    }
                }
            } else {
                // Standard WordPress - find wp_posts or wp{number}_posts
                foreach ($allTableNames as $tableName) {
                    if (preg_match('/^wp\d*_posts$/', $tableName)) {
                        return $tableName;
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("❌ Error detecting posts table: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ Enrich legacy order results with postmeta data (totals, customer info, etc.)
     */
    private function enrichLegacyOrderResults($orders, $postmetaTable)
    {
        if (empty($orders)) {
            return $orders;
        }
        
        try {
            // Get order IDs
            $orderIds = [];
            foreach ($orders as $order) {
                $orderObj = is_object($order) ? (array)$order : $order;
                if (isset($orderObj['order_id'])) {
                    $orderIds[] = (int)$orderObj['order_id'];
                } elseif (isset($orderObj['ID'])) {
                    $orderIds[] = (int)$orderObj['ID'];
                }
            }
            
            if (empty($orderIds)) {
                return $orders;
            }
            
            $orderIdsStr = implode(',', $orderIds);
            
            // Fetch relevant postmeta data
            $metaQuery = "SELECT post_id, meta_key, meta_value 
                         FROM `{$postmetaTable}` 
                         WHERE post_id IN ({$orderIdsStr}) 
                         AND meta_key IN ('_order_total', '_order_currency', '_billing_email', '_billing_first_name', '_billing_last_name', '_billing_phone', '_order_key', '_customer_user')";
            
            $metaResults = \Illuminate\Support\Facades\DB::select($metaQuery);
            
            // Organize meta data by post_id
            $metaByOrder = [];
            foreach ($metaResults as $meta) {
                $metaObj = is_object($meta) ? (array)$meta : $meta;
                $postId = (int)$metaObj['post_id'];
                $metaKey = $metaObj['meta_key'];
                $metaValue = $metaObj['meta_value'];
                
                if (!isset($metaByOrder[$postId])) {
                    $metaByOrder[$postId] = [];
                }
                
                // Convert meta keys to readable names
                $readableKey = str_replace('_', ' ', $metaKey);
                $readableKey = ucwords($readableKey);
                $readableKey = str_replace(' ', '_', $readableKey);
                
                $metaByOrder[$postId][$readableKey] = $metaValue;
            }
            
            // Merge meta data into orders
            $enrichedOrders = [];
            foreach ($orders as $order) {
                $orderObj = is_object($order) ? (array)$order : $order;
                $orderId = isset($orderObj['order_id']) ? (int)$orderObj['order_id'] : (isset($orderObj['ID']) ? (int)$orderObj['ID'] : null);
                
                if ($orderId && isset($metaByOrder[$orderId])) {
                    $orderObj = array_merge($orderObj, $metaByOrder[$orderId]);
                }
                
                $enrichedOrders[] = $orderObj;
            }
            
            return $enrichedOrders;
            
        } catch (\Exception $e) {
            Log::warning("⚠️ Could not enrich legacy order results: " . $e->getMessage());
            // Return original orders if enrichment fails
            return $orders;
        }
    }
    
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
