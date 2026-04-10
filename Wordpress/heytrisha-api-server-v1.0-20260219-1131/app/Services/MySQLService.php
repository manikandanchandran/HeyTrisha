<?php

// Fetching Working Code 02/01/2025 12:00 PM

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Services\WordPressConfigService;

class MySQLService
{
    protected $configService;

    public function __construct(WordPressConfigService $configService)
    {
        $this->configService = $configService;
        // Update database config when MySQLService is instantiated (lazy loading)
        $this->updateDatabaseConfig();
    }

    /**
     * ✅ Update database config from WordPress (lazy-loaded, not on boot)
     */
    private function updateDatabaseConfig()
    {
        try {
            $dbConfig = $this->configService->getDatabaseConfig();
            
            if (!empty($dbConfig) && !empty($dbConfig['name'])) {
                Config::set('database.connections.mysql.host', $dbConfig['host'] ?? '127.0.0.1');
                Config::set('database.connections.mysql.port', $dbConfig['port'] ?? '3306');
                Config::set('database.connections.mysql.database', $dbConfig['name'] ?? '');
                Config::set('database.connections.mysql.username', $dbConfig['user'] ?? '');
                Config::set('database.connections.mysql.password', $dbConfig['password'] ?? '');
                
                // Clear connection to force reconnection with new config
                DB::purge('mysql');
                
                Log::info("✅ Database config updated from WordPress");
            }
        } catch (\Exception $e) {
            Log::warning("⚠️ Could not update database config from WordPress: " . $e->getMessage());
        }
    }
    /**
     * ✅ Get intelligent database schema - NLP-based filtering
     * Analyzes query to find relevant tables, but lets OpenAI decide which to use
     * NO hardcoded table names - uses dynamic pattern matching
     * 
     * @param string|null $query User's natural language query
     * @return array Database schema with relevant tables
     */
    public function getCompactSchema($query = null)
    {
        Log::info("🔍 Fetching intelligent database schema (NLP-based filtering)...");

        try {
            // STEP 1: Get current site ID from WordPress config
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            Log::info("🌐 STEP 1 - Current Site ID from config: " . $currentSiteId . " (Multisite: " . ($isMultisite ? 'Yes' : 'No') . ")");
            
            // Fetch ALL tables from the database
            $allTables = DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            Log::info("📊 Found " . count($allTableNames) . " total tables in database");
            
            // STEP 1.5: Auto-detect site ID from table names if config returned site ID 1 or Multisite not detected
            // This handles cases where WordPress config fetch fails or returns wrong values
            $detectedSiteId = $this->detectSiteIdFromTables($allTableNames);
            if ($detectedSiteId !== null && ($currentSiteId == 1 || !$isMultisite)) {
                // If config says site ID 1 or not Multisite, but we detect other site IDs, use detected ID
                if ($detectedSiteId != $currentSiteId) {
                    Log::info("🔍 Auto-detected site ID from tables: " . $detectedSiteId . " (config said: " . $currentSiteId . ")");
                    $currentSiteId = $detectedSiteId;
                    $isMultisite = true; // If we detected a site ID > 1, it's definitely Multisite
                }
            }
            
            Log::info("🌐 STEP 1.5 - Final Site ID: " . $currentSiteId . " (Multisite: " . ($isMultisite ? 'Yes' : 'No') . ")");
            
            // STEP 2: Filter by current site ID FIRST (before any other filtering)
            $siteFilteredTables = $this->filterTablesBySiteId($allTableNames, $currentSiteId, $isMultisite);
            
            Log::info("🔍 STEP 2 - After site ID filtering: " . count($siteFilteredTables) . " tables for site ID " . $currentSiteId);
            
            // Verify we have tables for the current site
            if (empty($siteFilteredTables)) {
                Log::error("❌ No tables found for site ID " . $currentSiteId . " - cannot proceed");
                return ["error" => "No tables found for current site. Please check your site ID configuration."];
            }
            
            // Log some example filtered table names to verify
            $examples = array_slice($siteFilteredTables, 0, 5);
            Log::info("📋 Example filtered tables for site " . $currentSiteId . ": " . implode(', ', $examples));
            
            // STEP 3: Then, intelligently filter tables based on query (still NLP - no hardcoded names)
            // BUT ONLY from the site-filtered tables
            $relevantTables = $this->findRelevantTables($query, $siteFilteredTables);
            
            Log::info("✅ STEP 3 - Selected " . count($relevantTables) . " relevant tables for schema (from site " . $currentSiteId . " only)");

            // STEP 4: Get schema for relevant tables (these are already filtered by site ID)
            $schema = [];
            
            // Detect Multisite pattern for verification (need to check again here)
            $hasMultisitePattern = false;
            foreach ($allTableNames as $tableName) {
                if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                    $hasMultisitePattern = true;
                    break;
                }
            }
            
            foreach ($relevantTables as $tableName) {
                // CRITICAL: Verify table matches current site ID (double-check to prevent wrong site tables)
                if ($isMultisite || $hasMultisitePattern) {
                    $pattern = '/^wp\d+_' . preg_quote($currentSiteId, '/') . '_/';
                    if (!preg_match($pattern, $tableName)) {
                        Log::error("❌ ERROR: Table '" . $tableName . "' does not match site ID " . $currentSiteId . " - SKIPPING to prevent wrong site queries");
                        continue;
                    }
                }
                
                // Get all columns for this table
                $columns = DB::select("SHOW COLUMNS FROM `$tableName`");
                // Store table name with all column names
                $schema[$tableName] = array_map(fn($column) => $column->Field, $columns);
            }

            $totalColumns = array_sum(array_map('count', $schema));
            Log::info("✅ STEP 4 - Schema Retrieved: " . count($schema) . " tables with " . $totalColumns . " total columns for site ID " . $currentSiteId);
            
            // Final verification: Log all table names in schema to ensure they're correct
            $schemaTableNames = array_keys($schema);
            if (count($schemaTableNames) > 0) {
                $schemaExamples = array_slice($schemaTableNames, 0, 10);
                Log::info("📋 Final schema tables for site " . $currentSiteId . ": " . implode(', ', $schemaExamples));
                
                // Verify all schema tables match site ID
                foreach ($schemaTableNames as $tableName) {
                    if ($isMultisite || $hasMultisitePattern) {
                        $pattern = '/^wp\d+_' . preg_quote($currentSiteId, '/') . '_/';
                        if (!preg_match($pattern, $tableName)) {
                            Log::error("❌ CRITICAL ERROR: Schema contains table '" . $tableName . "' that does NOT match site ID " . $currentSiteId);
                        }
                    }
                }
            }
            
            // Final verification: Log all table names in schema to ensure they're correct
            $schemaTableNames = array_keys($schema);
            if (count($schemaTableNames) > 0) {
                $schemaExamples = array_slice($schemaTableNames, 0, 10);
                Log::info("📋 Final schema tables for site " . $currentSiteId . ": " . implode(', ', $schemaExamples));
            }
            
            return $schema;
        } catch (\PDOException $e) {
            Log::error("❌ Database connection error: " . $e->getMessage());
            return ["error" => "Database connection failed. Please check your database credentials in the WordPress admin settings."];
        } catch (\Exception $e) {
            Log::error("❌ Error fetching schema: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return ["error" => "Failed to retrieve database schema. Please check your database configuration."];
        }
    }

    /**
     * ✅ Find relevant tables using NLP-based keyword matching
     * NO hardcoded table names - dynamically finds tables based on query keywords
     */
    private function findRelevantTables($query, $allTableNames)
    {
        if (empty($allTableNames)) {
            return [];
        }

        $queryLower = strtolower($query ?? '');
        $relevantTables = [];
        
        // Extract keywords from query
        $keywords = $this->extractKeywords($queryLower);
        Log::info("🔑 Extracted keywords from query: " . implode(', ', $keywords));
        
        // Find tables that match keywords in their names
        foreach ($allTableNames as $table) {
            $tableLower = strtolower($table);
            
            // Check if table name contains any query keywords
            $matches = false;
            foreach ($keywords as $keyword) {
                if (strpos($tableLower, $keyword) !== false) {
                    $matches = true;
                    break;
                }
            }
            
            if ($matches) {
                $relevantTables[] = $table;
            }
        }
        
        // Dynamically identify WordPress core tables by common suffixes (no hardcoded names)
        // This ensures compatibility with any WordPress installation
        $commonSuffixes = ['posts', 'postmeta', 'users', 'usermeta', 'terms', 'term_taxonomy', 'term_relationships', 'options', 'comments', 'commentmeta'];
        foreach ($commonSuffixes as $suffix) {
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                // Match suffix at end of table name (works with any prefix)
                if (preg_match('/' . preg_quote($suffix, '/') . '$/i', $tableLower)) {
                    if (!in_array($table, $relevantTables)) {
                        $relevantTables[] = $table;
                    }
                }
            }
        }
        
        // Dynamically identify WooCommerce tables by common patterns
        // WooCommerce uses tables like: wc_product_meta_lookup, wc_order_product_lookup, wc_orders, wc_order_stats, etc.
        $wooCommercePatterns = ['wc_', '_wc_', 'woocommerce'];
        foreach ($wooCommercePatterns as $pattern) {
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                if (strpos($tableLower, $pattern) !== false) {
                    if (!in_array($table, $relevantTables)) {
                        $relevantTables[] = $table;
                    }
                }
            }
        }
        
        // ✅ CRITICAL: Ensure order-related tables are included when query mentions orders
        $orderKeywords = ['order', 'orders', 'ordered', 'ordering', 'purchase', 'transaction', 'last 3', 'last 5', 'recent', 'latest'];
        $hasOrderKeyword = false;
        foreach ($orderKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                $hasOrderKeyword = true;
                break;
            }
        }
        
        if ($hasOrderKeyword) {
            Log::info("🔍 Order keyword detected in query - searching for order tables...");
            // Find all order-related tables
            $orderTablesFound = [];
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                // Match: wc_order_stats, wc_orders, wc_order_product_lookup, wp_posts (post_type='shop_order')
                if (strpos($tableLower, 'order') !== false || 
                    strpos($tableLower, 'wc_order') !== false) {
                    if (!in_array($table, $relevantTables)) {
                        $relevantTables[] = $table;
                        $orderTablesFound[] = $table;
                        Log::info("✅ Added order-related table to schema: " . $table);
                    }
                }
            }
            
            // ✅ Also ensure posts table is included (for legacy orders with post_type='shop_order')
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                if (preg_match('/_posts$/', $tableLower) && !in_array($table, $relevantTables)) {
                    $relevantTables[] = $table;
                    Log::info("✅ Added posts table to schema for legacy orders: " . $table);
                    break; // Only need one posts table
                }
            }
            
            if (empty($orderTablesFound)) {
                Log::warning("⚠️ No order-related tables found in database! Available tables: " . implode(', ', array_slice($allTableNames, 0, 20)));
            } else {
                Log::info("✅ Found " . count($orderTablesFound) . " order-related tables: " . implode(', ', $orderTablesFound));
            }
        }
        
        // ✅ CRITICAL: Ensure customer/user tables are included when query mentions customers
        $customerKeywords = ['customer', 'customers', 'user', 'users', 'client', 'clients'];
        $hasCustomerKeyword = false;
        foreach ($customerKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                $hasCustomerKeyword = true;
                break;
            }
        }
        
        if ($hasCustomerKeyword) {
            // Find all customer/user-related tables
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                // Match: wp_users, wp_usermeta, wc_customers (if exists)
                if ((strpos($tableLower, 'user') !== false || strpos($tableLower, 'customer') !== false) &&
                    !in_array($table, $relevantTables)) {
                    $relevantTables[] = $table;
                    Log::info("✅ Added customer/user-related table to schema: " . $table);
                }
            }
        }
        
        // If query mentions products, orders, or variations, ensure wp_posts is included
        // (WooCommerce stores products as posts with post_type='product')
        $productRelatedKeywords = ['product', 'order', 'variation', 'cart', 'sale', 'customer'];
        $hasProductKeyword = false;
        foreach ($productRelatedKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                $hasProductKeyword = true;
                break;
            }
        }
        
        if ($hasProductKeyword) {
            // Find wp_posts table (or any prefix_posts)
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                if (preg_match('/_posts$/', $tableLower)) {
                    if (!in_array($table, $relevantTables)) {
                        $relevantTables[] = $table;
                    }
                    break; // Only need one posts table
                }
            }
        }
        
        // Remove duplicates
        $relevantTables = array_unique($relevantTables);
        
        // Limit to 50 tables max to stay within token limits
        if (count($relevantTables) > 50) {
            // Prioritize: keyword matches first, then core tables
            $prioritized = [];
            $others = [];
            
            foreach ($relevantTables as $table) {
                $tableLower = strtolower($table);
                $isKeywordMatch = false;
                foreach ($keywords as $keyword) {
                    if (strpos($tableLower, $keyword) !== false) {
                        $isKeywordMatch = true;
                        break;
                    }
                }
                
                if ($isKeywordMatch) {
                    $prioritized[] = $table;
                } else {
                    $others[] = $table;
                }
            }
            
            $relevantTables = array_merge($prioritized, array_slice($others, 0, 50 - count($prioritized)));
        }
        
        return array_values($relevantTables);
    }

    /**
     * ✅ Extract relevant keywords from user query
     * Helps identify which tables might be relevant
     */
    private function extractKeywords($query)
    {
        // Common database-related keywords
        $keywords = [];
        
        // Extract meaningful words (ignore common stop words)
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'can', 'you', 'give', 'show', 'list', 'get', 'how', 'many', 'what', 'which', 'are', 'is', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
        
        // Split query into words
        $words = preg_split('/\s+/', $query);
        
        foreach ($words as $word) {
            $word = strtolower(trim($word, '.,!?;:()[]{}'));
            // Only include meaningful words (length > 2, not stop words)
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }


    /**
     * ✅ Execute the AI-generated SQL query - Pure NLP approach
     * Only validates SELECT for security, lets database handle everything else
     * Returns actual database errors so OpenAI can learn
     */
    public function executeSQLQuery($sqlQuery)
    {
        try {
            // ✅ Basic validation - SQL must be a string
            if (!is_string($sqlQuery) || empty($sqlQuery)) {
                Log::error("❌ Invalid SQL Query: " . json_encode($sqlQuery));
                return ["error" => "Generated SQL query is invalid"];
            }

            // ✅ Security: Only allow SELECT queries (no INSERT, UPDATE, DELETE, DROP, etc.)
            // Remove SQL comments and whitespace to check for SELECT
            $sqlClean = preg_replace('/--.*$/m', '', $sqlQuery); // Remove single-line comments
            $sqlClean = preg_replace('/\/\*.*?\*\//s', '', $sqlClean); // Remove multi-line comments
            $sqlClean = trim($sqlClean);
            
            // Log original and cleaned SQL for debugging
            Log::info("🔍 SQL Validation - Original: " . substr($sqlQuery, 0, 200));
            Log::info("🔍 SQL Validation - Cleaned: " . substr($sqlClean, 0, 200));
            
            // Check if query contains SELECT (case-insensitive, allowing for WITH clauses, etc.)
            // Must contain SELECT and NOT contain dangerous keywords
            $hasSelect = preg_match('/\bSELECT\b/i', $sqlClean);
            $hasDangerous = preg_match('/\b(INSERT|UPDATE|DELETE|DROP|TRUNCATE|ALTER|CREATE|GRANT|REVOKE|EXEC|EXECUTE)\b/i', $sqlClean);
            
            Log::info("🔍 SQL Validation - Has SELECT: " . ($hasSelect ? 'YES' : 'NO') . " | Has Dangerous: " . ($hasDangerous ? 'YES' : 'NO'));
            
            if (!$hasSelect) {
                Log::error("❌ Non-SELECT query detected. Original SQL: " . $sqlQuery);
                Log::error("❌ Cleaned SQL: " . $sqlClean);
                return ["error" => "I'm having trouble understanding your request. Could you please try rephrasing your question? For example, try asking 'How many orders were placed last month?' or 'What are the top selling products?'"];
            }
            
            if ($hasDangerous) {
                Log::error("❌ Dangerous query detected: " . $sqlQuery);
                return ["error" => "I can only help with data analytics and insights. I cannot modify, delete, or alter any data in your database. Please ask me questions about viewing or analyzing your data instead."];
            }

            Log::info("💾 Executing SQL Query: " . $sqlQuery);

            // ✅ Execute query - let database handle validation
            // If table/column doesn't exist, database will return error
            $result = DB::select($sqlQuery);

            // ✅ Return results or no-data message
            if (empty($result)) {
                Log::info("ℹ️ Query executed successfully but returned no results");
                Log::info("ℹ️ SQL Query that returned no results: " . $sqlQuery);
                
                // ✅ For debugging: Check if the table exists and has data
                try {
                    // Extract table name from SQL query (handle backticks and aliases)
                    $tableName = null;
                    if (preg_match('/FROM\s+`?(\w+)`?(?:\s+AS\s+\w+)?/i', $sqlQuery, $matches)) {
                        $tableName = $matches[1];
                    } elseif (preg_match('/FROM\s+(\w+)/i', $sqlQuery, $matches)) {
                        $tableName = $matches[1];
                    }
                    
                    if ($tableName) {
                        $tableExists = DB::select("SHOW TABLES LIKE '{$tableName}'");
                        if (empty($tableExists)) {
                            Log::warning("⚠️ Table '{$tableName}' does not exist in database");
                            // Try to find similar table names
                            $allTables = DB::select('SHOW TABLES');
                            $allTableNames = array_map(fn($table) => reset($table), $allTables);
                            $similarTables = array_filter($allTableNames, function($t) use ($tableName) {
                                return strpos(strtolower($t), strtolower($tableName)) !== false || 
                                       strpos(strtolower($tableName), strtolower($t)) !== false;
                            });
                            if (!empty($similarTables)) {
                                Log::info("💡 Similar table names found: " . implode(', ', array_slice($similarTables, 0, 5)));
                            }
                        } else {
                            Log::info("✅ Table '{$tableName}' exists in database");
                            // Check row count
                            try {
                                $rowCount = DB::select("SELECT COUNT(*) as count FROM `{$tableName}`");
                                if (!empty($rowCount)) {
                                    $totalRows = $rowCount[0]->count;
                                    Log::info("ℹ️ Table '{$tableName}' has " . $totalRows . " total rows");
                                    
                                    // If table has rows but query returned empty, check WHERE clause
                                    if ($totalRows > 0 && preg_match('/\bWHERE\b/i', $sqlQuery)) {
                                        Log::warning("⚠️ Table has {$totalRows} rows but query returned empty - WHERE clause might be too restrictive");
                                        // Try to execute query without WHERE to see if that returns results
                                        try {
                                            $queryWithoutWhere = preg_replace('/\s+WHERE\s+.*?(?=\s+ORDER\s+BY|\s+LIMIT|$)/i', '', $sqlQuery);
                                            $resultWithoutWhere = DB::select($queryWithoutWhere);
                                            if (!empty($resultWithoutWhere)) {
                                                Log::info("💡 Query without WHERE clause returns " . count($resultWithoutWhere) . " rows - WHERE clause is filtering out all results");
                                            }
                                        } catch (\Exception $e) {
                                            // Ignore errors from test query
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::warning("⚠️ Could not check row count for table '{$tableName}': " . $e->getMessage());
                            }
                        }
                    } else {
                        Log::warning("⚠️ Could not extract table name from SQL query");
                    }
                } catch (\Exception $e) {
                    Log::warning("⚠️ Could not verify table existence: " . $e->getMessage());
                }
                
                return ["message" => "No matching records found"];
            }
            
            Log::info("✅ Query executed successfully, returned " . count($result) . " rows");
            return $result;
            
        } catch (\PDOException $e) {
            // Database connection or query syntax errors
            $errorMsg = $e->getMessage();
            $errorCode = $e->getCode();
            Log::error("❌ SQL Execution Error (PDO): " . $errorMsg);
            Log::error("❌ Error Code: " . $errorCode);
            Log::error("❌ Failed SQL Query: " . $sqlQuery);
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            
            // Provide more specific error messages based on error type
            $userMessage = "I encountered an issue processing your request.";
            
            // Check for common SQL errors and provide helpful guidance
            if (strpos($errorMsg, 'Table') !== false && strpos($errorMsg, "doesn't exist") !== false) {
                $userMessage .= " The requested data table was not found. Please try rephrasing your question.";
            } elseif (strpos($errorMsg, 'Unknown column') !== false) {
                $userMessage .= " There was an issue with the data structure. Please try a different question.";
            } elseif (strpos($errorMsg, 'syntax error') !== false || strpos($errorMsg, 'SQL syntax') !== false) {
                $userMessage .= " There was a syntax error in the generated query. Please try rephrasing your question.";
            } elseif (strpos($errorMsg, 'Connection') !== false || strpos($errorMsg, 'timeout') !== false) {
                $userMessage .= " The database connection timed out. Please try again.";
            } else {
                $userMessage .= " Please try rephrasing your question or check your database connection settings.";
            }
            
            return [
                "error" => $userMessage,
                "raw_error" => $errorMsg,  // Store raw error for fallback logic
                "error_code" => $errorCode
            ];
        } catch (\Exception $e) {
            // ✅ Return user-friendly error message
            $errorMsg = $e->getMessage();
            Log::error("❌ SQL Execution Error: " . $errorMsg);
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            Log::error("❌ Failed SQL Query: " . $sqlQuery);
            
            // Provide more specific error messages
            $userMessage = "I encountered an issue processing your request.";
            
            if (strpos($errorMsg, 'Connection') !== false || strpos($errorMsg, 'timeout') !== false) {
                $userMessage .= " The database connection timed out. Please try again.";
            } elseif (strpos($errorMsg, 'Table') !== false && strpos($errorMsg, "doesn't exist") !== false) {
                $userMessage .= " The requested data table was not found. Please try rephrasing your question.";
            } elseif (strpos($errorMsg, 'Unknown column') !== false) {
                $userMessage .= " There was an issue with the data structure. Please try a different question.";
            } else {
                $userMessage .= " Please try rephrasing your question.";
            }
            
            return [
                "error" => $userMessage,
                "raw_error" => $errorMsg  // Store raw error for fallback logic
            ];
        }
    }
    
    /**
     * ✅ Filter tables by current site ID (for Multisite)
     * Only includes tables that match the current site ID prefix
     */
    private function filterTablesBySiteId($allTableNames, $currentSiteId, $isMultisite)
    {
        $filteredTables = [];
        
        // Detect if this is Multisite by checking table name patterns
        // If we see tables like wp53_5_* and wp53_10_*, it's Multisite
        $hasMultisitePattern = false;
        foreach ($allTableNames as $tableName) {
            if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                $hasMultisitePattern = true;
                break;
            }
        }
        
            // If Multisite pattern detected OR explicitly marked as Multisite, filter by site ID
            if ($hasMultisitePattern || $isMultisite) {
                Log::info("🔍 Multisite detected - filtering tables for site ID: " . $currentSiteId);
                
                // Build the exact pattern to match: wp{networkId}_{currentSiteId}_*
                // Example: For site ID 5, match wp53_5_* but NOT wp53_10_*
                $pattern = '/^wp\d+_' . preg_quote($currentSiteId, '/') . '_/';
                
                // Also match network-level tables (shared across all sites)
                // These typically don't have site ID: wp{networkId}_users, wp{networkId}_usermeta, wp{networkId}_options, etc.
                // Pattern matches: wp53_users, wp53_usermeta, wp_users, wp_usermeta, etc.
                // Match: wp_users OR wp{networkId}_users (but NOT wp{networkId}_{siteId}_users)
                $networkPattern = '/^wp(\d+_)?(users|usermeta|blogs|blog_versions|registration_log|signups|site|sitemeta)$/';
                
                Log::info("🔍 Using pattern to match site ID " . $currentSiteId . ": " . $pattern);
                Log::info("🔍 Also including network-level tables (shared across sites)");
                
                // Log some sample table names to see what we're working with
                $sampleTables = array_slice($allTableNames, 0, 20);
                Log::info("📋 Sample tables from database: " . implode(', ', $sampleTables));
                
                $networkTablesFound = [];
                foreach ($allTableNames as $tableName) {
                    // Match pattern: wp{networkId}_{currentSiteId}_* (site-specific tables)
                    // OR match network-level tables (shared across all sites)
                    if (preg_match($pattern, $tableName)) {
                        $filteredTables[] = $tableName;
                    } elseif (preg_match($networkPattern, $tableName)) {
                        $filteredTables[] = $tableName;
                        $networkTablesFound[] = $tableName;
                    }
                }
            
            Log::info("🔍 Filtered to " . count($filteredTables) . " tables for site ID " . $currentSiteId);
            if (!empty($networkTablesFound)) {
                Log::info("🌐 Network-level tables included: " . implode(', ', $networkTablesFound));
            } else {
                Log::warning("⚠️ No network-level tables found - users/usermeta might be missing from schema");
            }
            
            // Log some example table names for debugging
            if (count($filteredTables) > 0) {
                $examples = array_slice($filteredTables, 0, 10);
                Log::info("📋 Example tables for site " . $currentSiteId . ": " . implode(', ', $examples));
                
                // Verify all examples match the site ID
                foreach ($examples as $example) {
                    if (!preg_match($pattern, $example)) {
                        Log::error("❌ ERROR: Table '" . $example . "' does not match site ID " . $currentSiteId . " pattern!");
                    }
                }
            } else {
                Log::warning("⚠️ No tables found for site ID " . $currentSiteId . " - check if site ID is correct");
                Log::warning("⚠️ Sample table names from database: " . implode(', ', array_slice($allTableNames, 0, 10)));
            }
        } else {
            // Not Multisite - return all tables (they all belong to the single site)
            Log::info("🔍 Not Multisite - returning all " . count($allTableNames) . " tables");
            return $allTableNames;
        }
        
        // CRITICAL: Do NOT fall back to all tables if filtering returns empty
        // This would cause queries to use wrong site tables
        if (empty($filteredTables)) {
            Log::error("❌ Site ID filtering returned empty for site ID " . $currentSiteId);
            Log::error("❌ This means no tables match the pattern for site ID " . $currentSiteId);
            Log::error("❌ Cannot proceed - returning empty array to prevent wrong site queries");
            return [];
        }
        
        return $filteredTables;
    }
    
    /**
     * ✅ Auto-detect site ID from table names
     * Finds the most common site ID in table names (e.g., wp53_5_*, wp53_10_*)
     * Returns the site ID that appears in the most tables
     */
    private function detectSiteIdFromTables($allTableNames)
    {
        $siteIdCounts = [];
        
        foreach ($allTableNames as $tableName) {
            // Match pattern: wp{networkId}_{siteId}_*
            if (preg_match('/^wp\d+_(\d+)_/', $tableName, $matches)) {
                $siteId = (int)$matches[1];
                if ($siteId > 0) {
                    $siteIdCounts[$siteId] = ($siteIdCounts[$siteId] ?? 0) + 1;
                }
            }
        }
        
        if (empty($siteIdCounts)) {
            return null;
        }
        
        // Return the most common site ID (the one with most tables)
        arsort($siteIdCounts);
        $mostCommonSiteId = array_key_first($siteIdCounts);
        
        Log::info("🔍 Detected site IDs from tables: " . json_encode($siteIdCounts));
        Log::info("🔍 Most common site ID: " . $mostCommonSiteId . " (appears in " . $siteIdCounts[$mostCommonSiteId] . " tables)");
        
        return $mostCommonSiteId;
    }

}
