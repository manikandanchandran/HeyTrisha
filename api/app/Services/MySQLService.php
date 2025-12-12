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
            // Fetch ALL tables from the database
            $allTables = DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            Log::info("📊 Found " . count($allTableNames) . " total tables in database");
            
            // Intelligently filter tables based on query (still NLP - no hardcoded names)
            $relevantTables = $this->findRelevantTables($query, $allTableNames);
            
            Log::info("✅ Selected " . count($relevantTables) . " relevant tables for schema");

            // Get schema for relevant tables
            $schema = [];
            foreach ($relevantTables as $tableName) {
                // Get all columns for this table
                $columns = DB::select("SHOW COLUMNS FROM `$tableName`");
                // Store table name with all column names
                $schema[$tableName] = array_map(fn($column) => $column->Field, $columns);
            }

            $totalColumns = array_sum(array_map('count', $schema));
            Log::info("✅ Schema Retrieved: " . count($schema) . " tables with " . $totalColumns . " total columns");
            
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
            $sqlUpper = strtoupper(trim($sqlQuery));
            if (!preg_match('/^\s*SELECT/i', $sqlUpper)) {
                Log::error("❌ Non-SELECT query detected: " . $sqlQuery);
                return ["error" => "Only SELECT queries are allowed for data retrieval"];
            }

            Log::info("💾 Executing SQL Query: " . $sqlQuery);

            // ✅ Execute query - let database handle validation
            // If table/column doesn't exist, database will return error
            $result = DB::select($sqlQuery);

            // ✅ Return results or no-data message
            if (empty($result)) {
                Log::info("ℹ️ Query executed successfully but returned no results");
                return ["message" => "No matching records found"];
            }
            
            Log::info("✅ Query executed successfully, returned " . count($result) . " rows");
            return $result;
            
        } catch (\PDOException $e) {
            // Database connection or query syntax errors
            $errorMsg = $e->getMessage();
            Log::error("❌ SQL Execution Error (PDO): " . $errorMsg);
            Log::error("❌ Failed SQL Query: " . $sqlQuery);
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            
            // Return user-friendly error message
            return [
                "error" => "Database query error. Please check your database connection and try rephrasing your request.",
                "sql_query" => $sqlQuery // Include SQL for debugging
            ];
        } catch (\Exception $e) {
            // ✅ Return actual database error - this helps OpenAI learn
            $errorMsg = $e->getMessage();
            Log::error("❌ SQL Execution Error: " . $errorMsg);
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            Log::error("❌ Failed SQL Query: " . $sqlQuery);
            
            // Return detailed error so user/OpenAI can understand what went wrong
            return [
                "error" => "Database query error: " . $errorMsg,
                "sql_query" => $sqlQuery // Include SQL for debugging
            ];
        }
    }

}
