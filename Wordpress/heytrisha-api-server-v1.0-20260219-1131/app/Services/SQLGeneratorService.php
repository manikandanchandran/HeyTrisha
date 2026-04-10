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
use App\Services\WordPressConfigService;

class SQLGeneratorService
{
    protected $configService;

    public function __construct(WordPressConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * ✅ Generate SQL query using OpenAI NLP
     * Sends FULL user input + FULL database schema to OpenAI
     * OpenAI uses NLP to understand the query and generate appropriate SQL
     * 
     * @param string $userQuery The natural language query from user
     * @param array $schema Complete database schema (all tables, all columns)
     * @param string|null $openaiKey Optional OpenAI API key (if not provided, uses config service)
     * @return array Contains 'query' (SQL) or 'error'
     */
    public function queryChatGPTForSQL($userQuery, $schema, $openaiKey = null)
    {
        Log::info("🔍 Starting NLP SQL Generation");
        Log::info("📝 User Query: " . $userQuery);
        Log::info("📊 Schema: " . count($schema) . " tables");
        
        // Get WordPress Multisite information (for logging only, not for constructing table names)
        $wpInfo = $this->configService->getWordPressInfo();
        $isMultisite = $wpInfo['is_multisite'] ?? false;
        
        Log::info("🌐 WordPress Info - Is Multisite: " . ($isMultisite ? 'Yes' : 'No'));
        
        // Log all table names in schema for debugging
        $tableNames = array_keys($schema);
        Log::info("📋 Tables in schema: " . implode(', ', array_slice($tableNames, 0, 10)) . (count($tableNames) > 10 ? '...' : ''));

        // Build compact schema string - most efficient format
        // Format: table(column1,column2,...) - minimal tokens
        $schemaStr = "";
        $tableList = [];
        
        foreach ($schema as $table => $columns) {
            // Compact format: table(column1,column2,...)
            $schemaStr .= "$table(" . implode(',', $columns) . ")\n";
            $tableList[] = $table;
        }
        
        // Create a list of table names for explicit reference in prompt (limit to first 20 to avoid token limit)
        $tableNamesList = implode(', ', array_slice($tableList, 0, 20));
        if (count($tableList) > 20) {
            $tableNamesList .= ' ... and ' . (count($tableList) - 20) . ' more tables';
        }

        // Enhanced prompt with WordPress/WooCommerce context
        $prompt = "You are an EXPERT WordPress developer and SQL developer. Your task is to analyze the database schema and generate a CORRECT MySQL SELECT query.\n\n" .
                  "YOUR ROLE:\n" .
                  "- You are a WordPress expert who understands WordPress database structure\n" .
                  "- You are a SQL expert who writes precise, correct queries\n" .
                  "- You MUST analyze the provided schema CAREFULLY before generating any query\n" .
                  "- You MUST use ONLY the tables and columns that EXIST in the schema\n" .
                  "- You MUST understand what the user is asking for (show data vs count data)\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: You MUST include WHERE clause with date filter if user mentions ANY time period (last year, this month, yesterday, etc.)\n\n" .
                  "IMPORTANT WORDPRESS + WOOCOMMERCE CONTEXT:\n" .
                  ($isMultisite ? 
                  "- ⚠️ THIS IS A WORDPRESS MULTISITE/NETWORK INSTALLATION\n" .
                  "- In Multisite, each site has its own tables with different prefixes\n" .
                  "- The schema below contains ONLY the tables for the current site\n" .
                  "- " : 
                  "- This is a standard WordPress site (NOT Multisite)\n" .
                  "- ") .
                  "- CRITICAL: The schema below shows the EXACT table names that exist in the database\n" .
                  "- You MUST use ONLY the EXACT table names from the schema - do NOT modify, construct, or invent table names\n" .
                  "- Example table names in schema: " . $tableNamesList . "\n" .
                  "- Use the EXACT table name as it appears in the schema - even if it looks unusual\n" .
                  "- For posts: Look for tables containing 'posts' in the schema\n" .
                  "- For orders: Look for tables containing 'order' or 'wc_orders' in the schema\n" .
                  "- ⚠️⚠️⚠️ CRITICAL ORDER QUERY RULES:\n" .
                  "  * When user asks for 'orders list', 'list orders', 'show orders', 'get orders', 'share orders', 'orders', 'all orders':\n" .
                  "    → Use SELECT * FROM order_table (or SELECT specific columns like order_id, order_date, status, total)\n" .
                  "    → Use ORDER BY date_column DESC to show most recent first\n" .
                  "    → Use LIMIT 50 or LIMIT 100 to avoid returning too many rows\n" .
                  "    → Example: 'orders list' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 50\n" .
                  "  * 🚨🚨🚨 CRITICAL: When user asks for 'last 3 orders', 'last 5 orders', 'recent orders', 'latest orders', 'last N orders', 'share last 3 orders':\n" .
                  "    → YOU MUST generate: SELECT * FROM order_table ORDER BY date_column DESC LIMIT N\n" .
                  "    → DO NOT add WHERE clauses unless user explicitly mentions dates/status\n" .
                  "    → DO NOT use COUNT(*) - user wants to SEE the orders, not count them\n" .
                  "    → DO NOT filter by status unless user explicitly asks for specific status\n" .
                  "    → Example: 'last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "    → Example: 'share last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "    → Example: 'can you share last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "    → Example: 'last 5 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 5\n" .
                  "  * ⚠️ CRITICAL: DO NOT use COUNT(*) for these queries - user wants to SEE the orders, not count them\n" .
                  "  * ⚠️ CRITICAL: 'orders list' means SELECT * FROM orders, NOT SELECT COUNT(*) FROM orders\n" .
                  "  * ⚠️ CRITICAL: 'last 3 orders' means SELECT * FROM orders ORDER BY date DESC LIMIT 3, NOT SELECT COUNT(*) FROM orders\n" .
                  "  * ⚠️ CRITICAL: 'share last 3 orders' means SELECT * FROM orders ORDER BY date DESC LIMIT 3, NOT SELECT COUNT(*) FROM orders\n" .
                  "  * ⚠️ CRITICAL: Check the schema CAREFULLY for the EXACT order table name:\n" .
                  "    - Look for tables containing 'order' in the schema (might be wc_orders, wc_order_stats, wp_posts with post_type='shop_order')\n" .
                  "    - For WooCommerce HPOS: Use wc_orders table (check schema for exact name like wp53_5_wc_orders)\n" .
                  "    - For WooCommerce legacy: Use wp_posts table WHERE post_type='shop_order' (check schema for exact name like wp53_5_posts)\n" .
                  "    - Use the EXACT table name from schema - COPY it exactly as it appears\n" .
                  "  * ⚠️ CRITICAL: Check the schema CAREFULLY for the EXACT date column name:\n" .
                  "    - For wc_orders: Check schema for date column (might be date_created, date_created_gmt, order_date)\n" .
                  "    - For wp_posts: Use post_date column (check schema to confirm)\n" .
                  "    - Use the EXACT column name from schema - COPY it exactly as it appears\n" .
                  "  * ⚠️ CRITICAL: If schema shows multiple order tables, prefer wc_orders over wp_posts for 'last N orders' queries\n" .
                  "  * ⚠️ CRITICAL: If wc_orders table exists in schema, USE IT for 'last N orders' queries\n" .
                  "- ⚠️⚠️⚠️ CRITICAL PRODUCT QUERY RULES:\n" .
                  "  * ✅✅✅ When user asks for 'best selling products', 'most selling product', 'top products', 'best sellers', 'top selling products', 'can you share most selling product':\n" .
                  "    → These are ANALYTICS queries - NOT sensitive personal information - MUST generate SQL\n" .
                  "    → ⚠️⚠️⚠️ CRITICAL: Check schema for EXACT column name - might be 'quantity', 'product_qty', 'qty', or 'order_item_qty'\n" .
                  "    → Use: SELECT product_id, SUM([EXACT_COLUMN_NAME_FROM_SCHEMA]) AS total_sold FROM [EXACT_TABLE_NAME] GROUP BY product_id ORDER BY total_sold DESC LIMIT N\n" .
                  "    → Or: SELECT product_id, product_name, SUM([EXACT_COLUMN_NAME_FROM_SCHEMA]) AS total_sold FROM [EXACT_TABLE_NAME] JOIN wp_posts ON product_id = ID GROUP BY product_id ORDER BY total_sold DESC LIMIT N\n" .
                  "    → Example: 'best selling products' → SELECT product_id, SUM(product_qty) AS total_sold FROM wp_wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 10\n" .
                  "    → Example: 'most selling product' → SELECT product_id, SUM(product_qty) AS total_sold FROM wp_wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 1\n" .
                  "    → Example: 'can you share most selling product' → SELECT product_id, SUM(product_qty) AS total_sold FROM wp_wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 1\n" .
                  "    → ⚠️⚠️⚠️ CRITICAL: Check schema for EXACT table name (might be wp_wc_order_product_lookup, wp53_5_wc_order_product_lookup, etc.)\n" .
                  "    → ⚠️⚠️⚠️ CRITICAL: Check schema for EXACT column name for quantity (might be product_qty, quantity, qty, order_item_qty)\n" .
                  "    → ⚠️⚠️⚠️ CRITICAL: DO NOT use generic 'quantity' - use the EXACT column name from the schema\n" .
                  "    → ⚠️⚠️⚠️ FALLBACK: If quantity column doesn't exist in order_product_lookup table, use order_items/order_itemmeta:\n" .
                  "      → JOIN wp_woocommerce_order_items with wp_woocommerce_order_itemmeta (meta_key='_product_id' and meta_key='_qty')\n" .
                  "      → 🚨🚨🚨 CRITICAL ERROR TO AVOID: wp_woocommerce_order_items (aliased as 'oi') does NOT have a 'product_qty' column!\n" .
                  "      → 🚨🚨🚨 NEVER use 'oi.product_qty' - this column does NOT exist!\n" .
                  "      → ✅ CORRECT: Quantity comes from wp_woocommerce_order_itemmeta WHERE meta_key='_qty', NOT from order_items table\n" .
                  "      → Example: SELECT CAST(oim_product.meta_value AS UNSIGNED) as product_id, p.post_title AS product_name, SUM(CAST(oim_qty.meta_value AS UNSIGNED)) AS total_sold FROM wp_woocommerce_order_items oi INNER JOIN wp_woocommerce_order_itemmeta oim_product ON oim_product.order_item_id = oi.order_item_id AND oim_product.meta_key = '_product_id' INNER JOIN wp_woocommerce_order_itemmeta oim_qty ON oim_qty.order_item_id = oi.order_item_id AND oim_qty.meta_key = '_qty' INNER JOIN wp_posts p ON p.ID = CAST(oim_product.meta_value AS UNSIGNED) WHERE oi.order_item_type = 'line_item' GROUP BY CAST(oim_product.meta_value AS UNSIGNED), p.post_title ORDER BY total_sold DESC LIMIT N\n" .
                  "    → ⚠️⚠️⚠️ CRITICAL: Always calculate quantity from actual order data - never use hardcoded values\n" .
                  "    → 🚨🚨🚨 NEVER reference 'oi.product_qty' - this column does NOT exist in wp_woocommerce_order_items table!\n" .
                  "  * ✅✅✅ Product sales data, order analytics, revenue statistics are BUSINESS DATA - NOT sensitive - MUST generate SQL\n" .
                  "  * ⚠️ CRITICAL: Check the schema CAREFULLY for product-related tables:\n" .
                  "    - Look for tables containing 'product' or 'order_product' in the schema\n" .
                  "    - Common tables: wc_order_product_lookup, wc_product_meta_lookup, wp_posts (with post_type='product')\n" .
                  "    - Use the EXACT table name from schema - COPY it exactly as it appears\n" .
                  "- ⚠️⚠️⚠️ CRITICAL CUSTOMER ORDER QUERIES:\n" .
                  "  * When user asks for 'latest customers', 'customer details', 'lastest N customers', 'customers who ordered', 'ordered customers list':\n" .
                  "    → FIRST: Check schema for EXACT table names - look for 'wc_orders' OR 'posts' table\n" .
                  "    → If 'wc_orders' exists in schema: Use HPOS tables (wc_orders + wp_users)\n" .
                  "      → JOIN wc_orders with wp_users table\n" .
                  "      → Example: SELECT users.display_name, users.user_email, orders.order_id, orders.date_created FROM wc_orders AS orders JOIN wp_users AS users ON orders.customer_id = users.ID ORDER BY orders.date_created DESC LIMIT N\n" .
                  "    → If 'wc_orders' does NOT exist in schema: Use LEGACY tables (wp_posts + wp_users)\n" .
                  "      → JOIN wp_posts (post_type='shop_order') with wp_users table\n" .
                  "      → Use post_author to join with users.ID\n" .
                  "      → Example: SELECT DISTINCT u.display_name, u.user_email, COUNT(p.ID) as total_orders, MAX(p.post_date) as last_order_date FROM wp_users u INNER JOIN wp_posts p ON p.post_author = u.ID AND p.post_type = 'shop_order' GROUP BY u.ID ORDER BY last_order_date DESC LIMIT N\n" .
                  "    → Use SELECT to show customer info (name, email) and order info (order_id, order_date, total_orders)\n" .
                  "    → Use ORDER BY order_date DESC or last_order_date DESC to show most recent first\n" .
                  "    → Use LIMIT N for 'last N customers' or LIMIT 50 for 'customers list'\n" .
                  "    → Check schema for EXACT table names: might be wc_orders + wp_users (HPOS), or wp_posts + wp_users (legacy)\n" .
                  "    → Check schema for EXACT column names: customer_id might be customer_id (HPOS), post_author (legacy), user_id, or billing_email\n" .
                  "    → Check schema for EXACT date column: might be date_created, date_created_gmt, order_date (HPOS), or post_date (legacy)\n" .
                  "  * DO NOT use COUNT(*) for customer order queries - user wants to SEE the customers and their order details\n" .
                  "  * ⚠️⚠️⚠️ CRITICAL: If schema shows 'wp_posts' but NOT 'wc_orders', you MUST use legacy approach with wp_posts WHERE post_type='shop_order'\n" .
                  "- For users: Look for tables containing 'user' in the schema (usually 'users' and 'usermeta')\n" .
                  "- DO NOT construct table names - use ONLY what appears in the schema\n\n" .
                  "WORDPRESS USER STRUCTURE (CRITICAL):\n" .
                  "- WordPress users are stored in a table containing 'users' in its name (check schema for EXACT name)\n" .
                  "- User roles/capabilities are stored in a table containing 'usermeta' in its name (check schema for EXACT name)\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: In Multisite, users and usermeta tables are ALWAYS network-level (shared across all sites)\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: Users table does NOT have site ID prefix - it's 'wp53_users' or 'wp_users', NOT 'wp53_5_users'\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: Usermeta table does NOT have site ID prefix - it's 'wp53_usermeta' or 'wp_usermeta', NOT 'wp53_5_usermeta'\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: Check the schema CAREFULLY - look for tables ending in '_users' or '_usermeta' WITHOUT a number before the underscore\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: If schema shows 'wp53_users', use 'wp53_users' - do NOT change it to 'wp53_5_users'\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: If schema shows 'wp53_usermeta', use 'wp53_usermeta' - do NOT change it to 'wp53_5_usermeta'\n" .
                  "- When user asks for 'users with roles' or 'users list with roles', you MUST:\n" .
                  "  1. FIRST: Look in the schema for the EXACT table name containing 'users' (might be 'wp_users', 'wp53_users', or 'wp53_5_users' - use what's in schema)\n" .
                  "  2. SECOND: Look in the schema for the EXACT table name containing 'usermeta' (might be 'wp_usermeta', 'wp53_usermeta', or 'wp53_5_usermeta' - use what's in schema)\n" .
                  "  3. SELECT from the users table using the EXACT name from schema\n" .
                  "  4. JOIN with usermeta table using the EXACT name from schema\n" .
                  "  5. Join condition: usermeta.user_id = users.ID\n" .
                  "  6. Filter usermeta.meta_key = 'wp_capabilities' to get roles\n" .
                  "  7. SELECT user fields (ID, user_login, user_email, display_name) AND the role from usermeta\n" .
                  "- When user asks 'show all users' or 'list users', SELECT from users table - do NOT search for specific usernames\n" .
                  "- 'user roles' in a query means 'show me the roles', NOT 'search for username called user roles'\n" .
                  "- ⚠️⚠️⚠️ MOST IMPORTANT: Use ONLY the EXACT table names that appear in the schema below - do NOT assume or construct table names ⚠️⚠️⚠️\n\n" .
                  "CRITICAL ANALYSIS STEPS (YOU MUST FOLLOW THESE):\n" .
                  "1. FIRST: Read the user's request carefully and understand the INTENT:\n" .
                  "   - ✅✅✅ 'best selling products', 'most selling product', 'top products' = Analytics query - MUST generate SQL\n" .
                  "     → Use: SELECT product_id, SUM(quantity) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT N\n" .
                  "     → These are BUSINESS ANALYTICS, NOT sensitive personal information - MUST generate SQL\n" .
                  "   - ✅✅✅ 'product sales', 'order statistics', 'revenue data' = Analytics query - MUST generate SQL\n" .
                  "     → These are BUSINESS DATA, NOT sensitive - MUST generate SQL\n" .
                  "   - 'show all users' = SELECT all users from users table (NOT searching for specific username)\n" .
                  "   - 'show all users with roles' = SELECT users JOIN usermeta to get roles (NOT searching for username 'roles')\n" .
                  "   - 'list users' = SELECT from users table (NOT searching for a user)\n" .
                  "   - 'show all orders' = SELECT * or SELECT specific columns (NOT COUNT)\n" .
                  "   - 'how many orders' = SELECT COUNT(*) (COUNT query)\n" .
                  "   - 🚨🚨🚨 CRITICAL 'LAST N ORDERS' QUERIES:\n" .
                  "     * When user says 'last 3 orders', 'last 5 orders', 'share last 3 orders', 'can you share last 3 orders':\n" .
                  "       → Extract the number N from the query (3, 5, etc.)\n" .
                  "       → Generate: SELECT * FROM order_table ORDER BY date_column DESC LIMIT N\n" .
                  "       → DO NOT add WHERE clauses - user wants the LAST N orders, period\n" .
                  "       → DO NOT filter by status - user wants ALL orders, just the last N\n" .
                  "       → DO NOT use COUNT(*) - user wants to SEE the orders\n" .
                  "       → Example: 'last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "       → Example: 'share last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "       → Example: 'can you share last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "       → Use the EXACT table name from schema (might be wp53_5_wc_orders, wp_posts, etc.)\n" .
                  "       → Use the EXACT date column from schema (might be date_created, date_created_gmt, post_date, etc.)\n" .
                  "   - CRITICAL: When user says 'with roles', 'with that user roles', 'with user roles' - they mean 'INCLUDE roles in the result', NOT 'search for username roles'\n" .
                  "   - CRITICAL: When user says 'all users', 'list users', 'show users' - they want ALL users, NOT searching for a specific user\n" .
                  "   - ⚠️⚠️⚠️ CRITICAL TIME CONSTRAINTS: If user mentions ANY time period (EXCEPT 'last N orders'), you MUST include a WHERE clause with date filter:\n" .
                  "     * 'last year' → WHERE date_created >= '2025-01-01' AND date_created < '2026-01-01'\n" .
                  "     * 'this year' → WHERE date_created >= '2026-01-01' AND date_created < '2027-01-01'\n" .
                  "     * 'last month' → WHERE date_created >= 'YYYY-MM-01' AND date_created < 'YYYY-MM-01' (previous month)\n" .
                  "     * 'last December' → WHERE date_created >= '2025-12-01' AND date_created < '2026-01-01'\n" .
                  "     * 'yesterday', 'today', 'this week', 'last week' → Calculate appropriate date range\n" .
                  "     * NEVER ignore time constraints - they are critical for accurate results!\n" .
                  "2. SECOND: Look at the schema below and find the EXACT table name you need\n" .
                  "   - Check the available tables list above to see what tables exist\n" .
                  "   - For posts: Find the table containing 'posts' in its name from the schema\n" .
                  "   - For orders: Find the table containing 'order' or 'wc_orders' in its name from the schema\n" .
                  "   - For users: Find tables containing 'users' and 'usermeta' in their names from the schema\n" .
                  "     * Users table: Contains user data (ID, user_login, user_email, display_name)\n" .
                  "     * Usermeta table: Contains user roles/capabilities (user_id, meta_key, meta_value)\n" .
                  "     * For user roles queries, JOIN users with usermeta WHERE meta_key = 'wp_capabilities'\n" .
                  "   - Use the EXACT table name from the schema - do NOT modify it, construct it, or invent table names\n" .
                  "   - Example: If the schema shows 'wp53_5_posts', use 'wp53_5_posts' exactly - do NOT change it to 'wp53_posts'\n" .
                  "   - Example: If the schema shows 'wp53_5_wc_orders', use 'wp53_5_wc_orders' exactly - do NOT change it to 'wp53_wc_orders'\n" .
                  "   - DO NOT try to construct table names - use ONLY what appears in the schema\n" .
                  "3. THIRD: Find the EXACT column names in that table from the schema\n" .
                  "   - Look at the schema columns for the order table you found\n" .
                  "   - Status column might be named 'status', 'order_status', 'post_status', etc. - check the schema\n" .
                  "   - ID column might be named 'id', 'ID', 'order_id', etc. - check the schema\n" .
                  "   - Use ONLY the column names that appear in the schema - do NOT assume column names\n" .
                  "4. FOURTH: Generate the query using the EXACT table and column names from the schema\n" .
                  "   - Use the EXACT table name from the schema (copy it exactly as it appears)\n" .
                  "   - Use the EXACT column names from the schema\n" .
                  "   - DO NOT modify, construct, or abbreviate table names - use them exactly as shown in the schema\n" .
                  "\n" .
                  "CRITICAL STATUS MATCHING RULES:\n" .
                  "- WooCommerce order status values may be stored with different casing (e.g., 'processing', 'Processing', 'PROCESSING')\n" .
                  "- WooCommerce status values might be stored as slugs (e.g., 'wc-processing' instead of 'processing')\n" .
                  "- ALWAYS check the schema to find the EXACT column name for status (might be 'status', 'order_status', 'post_status', etc.)\n" .
                  "- Use the EXACT column name from the schema - do NOT assume it's named 'status'\n" .
                  "- ALWAYS use case-insensitive matching: LOWER(column_name) = LOWER('processing') OR column_name LIKE '%processing%'\n" .
                  "- If the status column contains slugs like 'wc-processing', use: WHERE column_name LIKE '%processing%' or WHERE column_name = 'wc-processing'\n" .
                  "- When user asks for 'processing status', use: WHERE LOWER(column_name) = LOWER('processing') OR column_name LIKE '%processing%'\n" .
                  "- Replace 'column_name' with the EXACT status column name from the schema\n" .
                  "\n" .
                  "CRITICAL WOOCOMMERCE ORDER TABLES & DATE COLUMNS:\n" .
                  "- WooCommerce stores orders in multiple tables: wc_order_stats, wc_orders, wc_order_product_lookup, posts (legacy)\n" .
                  "- 🚨🚨🚨 CRITICAL COLUMN DIFFERENCES BETWEEN ORDER TABLES:\n" .
                  "  * wc_order_stats has `total_sales` column (for analytics/revenue sums)\n" .
                  "  * wc_orders (HPOS) has `total_amount` column (NOT `total_sales`!) - for individual order totals\n" .
                  "  * ⚠️ NEVER use `total_sales` on wc_orders table - it does NOT exist! Use `total_amount` instead\n" .
                  "  * ⚠️ NEVER use `total_amount` on wc_order_stats table - it does NOT exist! Use `total_sales` instead\n" .
                  "- wc_order_stats: Analytical data - CHECK SCHEMA for date column (might be: date_created, order_date, date_created_gmt)\n" .
                  "- wc_order_product_lookup: Product sales data - CHECK SCHEMA for date column (might be: date_created, order_date, date_created_gmt)\n" .
                  "- wc_orders: HPOS orders - CHECK SCHEMA for date column (might be: date_created_gmt, date_created, order_date)\n" .
                  "- posts table (post_type='shop_order'): Legacy orders - date column is 'post_date'\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: ALWAYS check the schema to find the EXACT date column name - do NOT assume it's 'date_created'\n" .
                  "- Common date column names: date_created, date_created_gmt, order_date, post_date, created_date\n" .
                  "- Look at the schema columns for the table you're using and find which date column exists\n" .
                  "- Use the EXACT date column name from the schema\n" .
                  "- When filtering by date, use >= and < for accuracy: [date_column] >= '2024-12-01' AND [date_column] < '2025-01-01'\n" .
                  "\n" .
                  "QUERY TYPE RULES:\n" .
                  "- If user asks to 'show', 'list', 'display', 'get', 'share' orders/posts/products: Use SELECT * or SELECT specific_columns (NOT COUNT)\n" .
                  "- 🚨🚨🚨 CRITICAL: 'last N orders' queries (where N is a number like 3, 5, 10):\n" .
                  "  * Pattern: 'last 3 orders', 'last 5 orders', 'share last 3 orders', 'can you share last 3 orders'\n" .
                  "  * SQL Pattern: SELECT * FROM order_table ORDER BY date_column DESC LIMIT N\n" .
                  "  * Extract N from query: 'last 3 orders' → LIMIT 3, 'last 5 orders' → LIMIT 5\n" .
                  "  * DO NOT add WHERE clauses - user wants the LAST N orders regardless of status or date range\n" .
                  "  * DO NOT use COUNT(*) - user wants to SEE the orders, not count them\n" .
                  "  * Examples:\n" .
                  "    - 'last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "    - 'share last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "    - 'can you share last 3 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 3\n" .
                  "    - 'last 5 orders' → SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 5\n" .
                  "- Examples: 'show orders', 'list orders', 'get orders', 'share orders', 'orders list' → SELECT * FROM order_table ORDER BY date_column DESC LIMIT 50\n" .
                  "- If user asks 'how many', 'count', 'number of': Use SELECT COUNT(*) AS count_name\n" .
                  "- If user asks for 'all orders with processing status': SELECT * FROM table WHERE status condition (NOT COUNT)\n" .
                  "- If user asks for 'count of orders with processing status': SELECT COUNT(*) AS order_count FROM table WHERE status condition\n" .
                  "- If user asks for 'total sales', 'revenue', 'total amount': Use SELECT SUM(total_sales) from wc_order_stats OR SELECT SUM(total_amount) from wc_orders (check schema for correct column!)\n" .
                  "- If user asks for 'average price', 'average order value': Use SELECT AVG(column_name) AS average_value\n" .
                  "- ⚠️ CRITICAL: 'orders list', 'list orders', 'show orders', 'get orders', 'share orders' means SELECT * FROM orders table (NOT COUNT)\n" .
                  "- ⚠️ CRITICAL: 'last 3 orders', 'recent orders', 'latest orders' means SELECT * FROM orders ORDER BY date DESC LIMIT 3\n" .
                  "\n" .
                  "AGGREGATE FUNCTION RULES (CRITICAL FOR ACCURACY):\n" .
                  "- COUNT(*): Count all rows (use for 'how many orders', 'number of products')\n" .
                  "- COUNT(DISTINCT column): Count unique values (use for 'how many different products', 'unique customers')\n" .
                  "- SUM(column): Sum numeric values (use for 'total sales', 'total revenue', 'total amount')\n" .
                  "- AVG(column): Average of numeric values (use for 'average price', 'average order value')\n" .
                  "- MAX(column): Maximum value (use for 'highest price', 'latest date')\n" .
                  "- MIN(column): Minimum value (use for 'lowest price', 'earliest date')\n" .
                  "- When using aggregate functions, you MUST:\n" .
                  "  * Give the result a clear alias: COUNT(*) AS order_count, SUM(total_sales) AS total_revenue\n" .
                  "  * If grouping by category/product/user, include GROUP BY clause\n" .
                  "  * For 'top 10', 'best selling', use ORDER BY DESC LIMIT 10\n" .
                  "  * For 'bottom 5', 'lowest', use ORDER BY ASC LIMIT 5\n" .
                  "- Examples:\n" .
                  "  * 'How many orders in December?' → SELECT COUNT(*) AS order_count FROM orders WHERE date >= '2024-12-01' AND date < '2025-01-01'\n" .
                  "  * 'Total sales last month' → SELECT SUM(total_sales) AS total_revenue FROM wc_order_stats WHERE date >= ... AND date < ...\n" .
                  "  * 'Top 5 best selling products' → SELECT product_id, SUM(product_qty) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 5\n" .
                  "  * 'Best selling products' → SELECT product_id, SUM(product_qty) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 10\n" .
                  "  * 'Most selling product' → SELECT product_id, SUM(product_qty) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 1\n" .
                  "  * 'What are the best selling products?' → SELECT product_id, SUM(product_qty) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 10\n" .
                  "  * 'can you share most selling product?' → SELECT product_id, SUM(product_qty) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 1\n" .
                  "  * ⚠️⚠️⚠️ CRITICAL: Check schema for EXACT column name - might be 'product_qty', 'quantity', 'qty', or 'order_item_qty'\n" .
                  "  * Average order value' → SELECT AVG(total_sales) AS average_order_value FROM wc_order_stats\n" .
                  "  * ⚠️ 'Most ordered product from last year' → SELECT product_id, COUNT(*) AS order_count FROM wc_order_product_lookup WHERE date_created >= '2025-01-01' AND date_created < '2026-01-01' GROUP BY product_id ORDER BY order_count DESC LIMIT 1\n" .
                  "  * ⚠️ 'Best selling product this month' → SELECT product_id, SUM(quantity) AS total_sold FROM wc_order_product_lookup WHERE date_created >= 'YYYY-MM-01' AND date_created < 'YYYY-MM+1-01' GROUP BY product_id ORDER BY total_sold DESC LIMIT 1\n" .
                  "  * ⚠️ CRITICAL: For 'best selling', 'most selling', 'top selling' queries:\n" .
                  "    → Use SUM(quantity) or COUNT(*) grouped by product_id\n" .
                  "    → Use ORDER BY total_sold DESC or ORDER BY order_count DESC\n" .
                  "    → Use LIMIT 1 for single product, LIMIT 10 for multiple products\n" .
                  "    → JOIN with posts table to get product names (post_title)\n" .
                  "    → Example: SELECT p.post_title AS product_name, ol.product_id, SUM(ol.quantity) AS total_sold FROM wc_order_product_lookup ol JOIN wp_posts p ON p.ID = ol.product_id GROUP BY ol.product_id ORDER BY total_sold DESC LIMIT 10\n" .
                  "\n" .
                  "PRODUCT NAME RULES (CRITICAL):\n" .
                  "- ⚠️⚠️⚠️ MANDATORY: If your query returns product_id, you MUST also include the product name (post_title from posts table)\n" .
                  "- To get product name, JOIN with the posts table (find table containing 'posts' in schema)\n" .
                  "- Join condition: posts.ID = product_lookup.product_id (or similar based on schema)\n" .
                  "- SELECT both product_id AND post_title AS product_name so users see product names, not just IDs\n" .
                  "- Example: SELECT ol.product_id, p.post_title AS product_name, SUM(ol.quantity) AS total_sold FROM wc_order_product_lookup ol JOIN wp_posts p ON p.ID = ol.product_id WHERE p.post_type = 'product' GROUP BY ol.product_id ORDER BY total_sold DESC LIMIT 10\n" .
                  "- Example: SELECT product_id, post_title AS product_name, COUNT(*) AS order_count FROM wc_order_product_lookup JOIN wp_posts ON wp_posts.ID = wc_order_product_lookup.product_id WHERE wp_posts.post_type = 'product' GROUP BY product_id\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: ALWAYS include product name (post_title AS product_name) when product_id is in the SELECT statement\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: When using GROUP BY product_id, you MUST also include post_title in SELECT and GROUP BY\n" .
                  "- Example with GROUP BY: SELECT ol.product_id, p.post_title AS product_name, SUM(ol.quantity) AS total_sold FROM wc_order_product_lookup ol JOIN wp_posts p ON p.ID = ol.product_id WHERE p.post_type = 'product' GROUP BY ol.product_id, p.post_title ORDER BY total_sold DESC\n" .
                  "- NEVER return only product_id without product_name - users need to see actual product names\n\n" .
                  "CRITICAL RULES:\n" .
                  "- Generate ONLY ONE SQL query - never multiple queries separated by semicolons\n" .
                  "- If user asks for multiple counts (e.g., products AND variations), use subqueries or UNION ALL in a single query\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: Use ONLY table and column names from the schema below - do NOT use example table names like 'wp_posts'\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: Look at the schema to find the ACTUAL table names and use those EXACT names\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: For quantity columns, check the schema for the EXACT column name (might be 'product_qty', 'quantity', 'qty', 'order_item_qty')\n" .
                  "- ⚠️⚠️⚠️ CRITICAL: DO NOT assume column names - ALWAYS check the schema first\n" .
                  "- 🚨🚨🚨 CRITICAL ERROR TO AVOID: wp_woocommerce_order_items (aliased as 'oi') does NOT have a 'product_qty' column!\n" .
                  "- 🚨🚨🚨 NEVER use 'oi.product_qty' or 'order_items.product_qty' - this will cause 'Unknown column' database error!\n" .
                  "- ✅ If using order_items table, quantity MUST come from wp_woocommerce_order_itemmeta WHERE meta_key='_qty'\n\n" .
                  "User request: \"$userQuery\"\n\n" .
                  "Database schema (" . count($tableList) . " tables):\n$schemaStr\n\n" .
                  "⚠️⚠️⚠️ CRITICAL: TABLE AND COLUMN NAMES IN SCHEMA ARE EXACT - USE THEM EXACTLY ⚠️⚠️⚠️\n" .
                  "The schema above shows the EXACT table names and column names that exist in the database.\n" .
                  "You MUST copy the table names EXACTLY as they appear in the schema.\n" .
                  "You MUST copy the column names EXACTLY as they appear in the schema.\n" .
                  "DO NOT modify, abbreviate, or construct table names.\n" .
                  "DO NOT modify, abbreviate, or construct column names.\n" .
                  "DO NOT use generic names like 'wp_wc_order_product_lookup' - use the EXACT name from schema.\n" .
                  "DO NOT assume column names like 'quantity' - check the schema for the EXACT column name (might be 'product_qty', 'quantity', 'qty', 'order_item_qty').\n" .
                  "⚠️⚠️⚠️ BEFORE using any column name, CHECK THE SCHEMA to find the EXACT column name ⚠️⚠️⚠️\n\n" .
                  "YOUR TASK:\n" .
                  "1. Analyze the schema above CAREFULLY\n" .
                  "2. Find the EXACT table name you need from the schema:\n" .
                  "   - Look at the schema above - each line shows a table name followed by its columns\n" .
                  "   - For posts: Find the table containing 'posts' in its name from the schema\n" .
                  "   - For orders: Find the table containing 'order' or 'wc_orders' in its name from the schema\n" .
                  "   - For products: Find the table containing 'product' in its name from the schema\n" .
                  "3. ⚠️⚠️⚠️ CRITICAL: Check if quantity column exists in the table:\n" .
                  "   - Look at the schema columns for the order/product table\n" .
                  "   - If you see 'product_qty', 'quantity', 'qty', or 'order_item_qty' in the columns, use that EXACT name\n" .
                  "   - If NO quantity column exists in order_product_lookup table, you MUST use order_items/order_itemmeta fallback:\n" .
                  "     → JOIN wp_woocommerce_order_items with wp_woocommerce_order_itemmeta\n" .
                  "     → Get product_id from meta_key='_product_id' and quantity from meta_key='_qty'\n" .
                  "     → 🚨🚨🚨 CRITICAL ERROR TO AVOID: wp_woocommerce_order_items (aliased as 'oi') does NOT have a 'product_qty' column!\n" .
                  "     → 🚨🚨🚨 NEVER use 'oi.product_qty' - this column does NOT exist!\n" .
                  "     → ✅ CORRECT: Quantity comes from wp_woocommerce_order_itemmeta WHERE meta_key='_qty', NOT from order_items table\n" .
                  "     → Example: SELECT CAST(oim_product.meta_value AS UNSIGNED) as product_id, SUM(CAST(oim_qty.meta_value AS UNSIGNED)) AS total_sold FROM wp_woocommerce_order_items oi INNER JOIN wp_woocommerce_order_itemmeta oim_product ON oim_product.order_item_id = oi.order_item_id AND oim_product.meta_key = '_product_id' INNER JOIN wp_woocommerce_order_itemmeta oim_qty ON oim_qty.order_item_id = oi.order_item_id AND oim_qty.meta_key = '_qty' WHERE oi.order_item_type = 'line_item' GROUP BY CAST(oim_product.meta_value AS UNSIGNED) ORDER BY total_sold DESC\n" .
                  "   - ⚠️⚠️⚠️ ALWAYS calculate quantity from actual order data - NEVER use hardcoded or assumed values\n" .
                  "   - 🚨🚨🚨 NEVER reference 'oi.product_qty' - this column does NOT exist in wp_woocommerce_order_items table!\n" .
                  "   - For users: Find tables containing 'users' and 'usermeta' in their names from the schema\n" .
                  "     * When user asks for 'users with roles' or 'all users list with roles':\n" .
                  "       - SELECT from users table (e.g., wp_users, wp53_5_users)\n" .
                  "       - JOIN with usermeta table (e.g., wp_usermeta, wp53_5_usermeta)\n" .
                  "       - Join: usermeta.user_id = users.ID AND usermeta.meta_key = 'wp_capabilities'\n" .
                  "       - SELECT user fields (ID, user_login, user_email, display_name) AND meta_value AS role/capabilities\n" .
                  "     * When user asks 'show all users' or 'list users': SELECT from users table - do NOT search for usernames\n" .
                  "     * 'user roles' means 'show me roles', NOT 'search for username user roles'\n" .
                  "   - COPY the EXACT table name from the schema - do NOT modify, construct, or abbreviate it\n" .
                  "   - Example: If schema shows 'wp53_5_posts', use 'wp53_5_posts' exactly - do NOT change it to 'wp_posts' or 'wp53_posts'\n" .
                  "   - Example: If schema shows 'wp53_5_wc_order_product_lookup', use 'wp53_5_wc_order_product_lookup' exactly - do NOT change it to 'wp_wc_order_product_lookup'\n" .
                  "   - CRITICAL: The table name MUST match EXACTLY what appears in the schema above\n" .
                  "3. Identify the EXACT column names in that table from the schema:\n" .
                  "   - Look at the schema columns for the table you found\n" .
                  "   - Status column might be 'status', 'order_status', 'post_status' - check the schema\n" .
                  "   - ID column might be 'id', 'ID', 'order_id', 'post_id' - check the schema\n" .
                  "   - ⚠️⚠️⚠️ DATE column might be 'date_created', 'date_created_gmt', 'order_date', 'post_date' - check the schema\n" .
                  "   - Use ONLY column names that exist in the schema - NEVER assume column names\n" .
                  "4. Determine if user wants to SHOW data or COUNT data\n" .
                  "5. Generate a SINGLE, CORRECT MySQL SELECT query:\n" .
                  "   - Use the EXACT table name from the schema (copy it exactly as it appears)\n" .
                  "   - Use the EXACT column names from the schema\n" .
                  "   - For status filtering, use case-insensitive matching: LOWER(column_name) = LOWER('processing') OR column_name LIKE '%processing%'\n" .
                  "   - CRITICAL: Use the table name EXACTLY as it appears in the schema - do NOT modify it\n" .
                  "\n" .
                  "🚨 CRITICAL SECURITY RULES - DATA ANALYTICS ONLY:\n" .
                  "This is a DATA ANALYTICS tool, NOT a data extraction tool. You MUST protect user privacy:\n" .
                  "✅✅✅ CRITICAL: Product sales data, order analytics, revenue statistics, best selling products, order counts, and business metrics are NOT sensitive personal information. These are legitimate analytics queries and you MUST generate SQL for them.\n" .
                  "✅ ALLOWED QUERIES (MUST generate SQL):\n" .
                  "   - 'best selling products', 'most selling product', 'top products' → Generate SQL with SUM/COUNT and GROUP BY\n" .
                  "   - 'order statistics', 'sales data', 'revenue reports' → Generate SQL with SUM/COUNT/AVG\n" .
                  "   - 'product performance', 'order counts', 'sales trends' → Generate SQL for analytics\n" .
                  "   - 'last N orders', 'recent orders', 'order list' → Generate SQL to show orders\n" .
                  "   - All product, order, and sales analytics queries → MUST generate SQL\n" .
                  "❌ BLOCKED QUERIES (DO NOT generate SQL):\n" .
                  "   - 'user passwords', 'customer passwords', 'get password' → Refuse (sensitive)\n" .
                  "   - 'user emails', 'customer emails', 'show emails' → Refuse (sensitive personal data)\n" .
                  "   - 'user addresses', 'customer addresses' → Refuse (sensitive personal data)\n" .
                  "   - 'credit card numbers', 'payment details' → Refuse (sensitive financial data)\n" .
                  "1. ❌ NEVER select or return sensitive columns:\n" .
                  "   - Passwords (user_pass, password, pwd, passwd, etc.)\n" .
                  "   - Email addresses (user_email, email, mail) - unless for analytics counts\n" .
                  "   - Personal information (phone, address, ssn, credit_card, ip_address)\n" .
                  "   - Authentication data (token, api_key, session_token, activation_key, reset_key)\n" .
                  "   - Usernames (user_login, login, username) - unless for analytics counts\n" .
                  "2. ✅ ALLOWED: Product sales, order analytics, revenue data (NOT sensitive):\n" .
                  "   - Product sales: SELECT product_id, SUM(quantity) AS total_sold FROM wc_order_product_lookup GROUP BY product_id (OK)\n" .
                  "   - Order statistics: SELECT * FROM wc_orders ORDER BY date_created DESC LIMIT 10 (OK)\n" .
                  "   - Revenue data: SELECT SUM(total_sales) AS revenue FROM wc_order_stats (OK)\n" .
                  "   - Best selling products: SELECT product_id, SUM(quantity) AS total_sold FROM wc_order_product_lookup GROUP BY product_id ORDER BY total_sold DESC LIMIT 10 (OK)\n" .
                  "   - Counts: SELECT COUNT(*) AS total_users (OK)\n" .
                  "   - Sums: SELECT SUM(total_sales) AS revenue (OK)\n" .
                  "   - Averages: SELECT AVG(order_value) AS avg_value (OK)\n" .
                  "   - Statistics: SELECT status, COUNT(*) AS count GROUP BY status (OK)\n" .
                  "3. ❌ NEVER use SELECT * on user-related tables (users, usermeta, customers) - but orders/products are OK\n" .
                  "   - Bad: SELECT * FROM wp_users (sensitive personal data)\n" .
                  "   - Good: SELECT COUNT(*) AS user_count FROM wp_users (analytics only)\n" .
                  "   - Good: SELECT * FROM wc_orders (orders are NOT sensitive - business data)\n" .
                  "   - Good: SELECT * FROM wp_posts WHERE post_type='product' (products are NOT sensitive)\n" .
                  "4. ❌ NEVER return individual user records with personal data\n" .
                  "   - Bad: SELECT user_login, user_email FROM wp_users (sensitive)\n" .
                  "   - Good: SELECT COUNT(*) AS total_users FROM wp_users WHERE user_role = 'customer' (analytics)\n" .
                  "   - Good: SELECT order_id, order_date, total FROM wc_orders (orders are business data, NOT sensitive)\n" .
                  "5. If query asks for sensitive data (passwords, emails, personal info): Refuse - DO NOT GENERATE SQL\n" .
                  "6. If query asks for product sales, orders, or business analytics: MUST GENERATE SQL - these are NOT sensitive\n" .
                  "7. Focus on ANALYTICS and INSIGHTS, not raw personal data extraction\n" .
                  "\n" .
                  "   - For DATE/TIME filtering, ALWAYS use date ranges (>= and <) for maximum accuracy:\n" .
                  "     * CRITICAL: Use date ranges, NOT YEAR()/MONTH() functions - date ranges work with all date formats and timezones\n" .
                  "     * Format: date_column >= 'YYYY-MM-DD' AND date_column < 'YYYY-MM-DD'\n" .
                  "     * 'last month' = date_created >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND date_created < DATE_FORMAT(CURDATE(), '%Y-%m-01')\n" .
                  "     * 'last December' or 'last Dec month' = Most recent December:\n" .
                  "       - If current month is January-December: Use previous year December\n" .
                  "       - Example in Jan 2026: date_created >= '2025-12-01' AND date_created < '2026-01-01'\n" .
                  "       - Calculate: December of (current year - 1) if current month >= 1\n" .
                  "     * 'this month' = date_created >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND date_created < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')\n" .
                  "     * 'December 2025' (specific) = date_created >= '2025-12-01' AND date_created < '2026-01-01'\n" .
                  "     * 'this year' = date_created >= CONCAT(YEAR(CURDATE()), '-01-01') AND date_created < CONCAT(YEAR(CURDATE()) + 1, '-01-01')\n" .
                  "     * NEVER use YEAR() and MONTH() functions - they can fail with different date formats and timezones\n" .
                  "     * ALWAYS use >= for start date and < (not <=) for end date to avoid boundary issues\n" .
                  "     * Example: date_created >= '2025-12-01' AND date_created < '2026-01-01' (entire December 2025)\n" .
                  "6. Use the EXACT table and column names from schema - do NOT invent or modify names\n" .
                  "7. Return ONLY ONE SQL query - no multiple queries, no semicolons, no explanations, no markdown, no code blocks\n\n" .
                  "SQL:";

        try {
            // Use provided OpenAI key (from Site model) or fallback to config service
            $apiKey = $openaiKey ?? $this->configService->getOpenAIApiKey();
            
            if (empty($apiKey)) {
                Log::error("OpenAI API Key is missing!");
                return ['error' => 'OpenAI API key is not configured. Please set it in the plugin settings.'];
            }
            
            Log::info("✅ Using OpenAI API key (length: " . strlen($apiKey) . ")");

            // Use gpt-3.5-turbo for better rate limits (1M tokens/min vs 10K for gpt-4)
            // Still excellent for SQL generation and handles NLP well
            $model = 'gpt-3.5-turbo';
            
            // Calculate approximate token count (rough estimate: 1 token ≈ 4 characters)
            $estimatedTokens = strlen($prompt) / 4;
            Log::info("📊 Estimated prompt tokens: ~" . round($estimatedTokens));
            
            // Check if prompt is too large for model context window
            // gpt-3.5-turbo has 16,385 token context, we need to leave room for response
            if ($estimatedTokens > 14000) {
                Log::error("❌ Prompt too large (" . round($estimatedTokens) . " tokens). Maximum is ~14,000 tokens.");
                return [
                    'error' => 'The database schema is too large for this query. Please try a more specific query. ' .
                              'For example: "Show posts from last week" instead of "Show all posts".'
                ];
            }
            
            if ($estimatedTokens > 10000) {
                Log::warning("⚠️ Prompt is large (" . round($estimatedTokens) . " tokens). Consider more specific queries.");
            }
            
            // Adjust max_tokens based on model
            $maxTokens = 300;
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => '🚨 CRITICAL SECURITY: You are a DATA ANALYTICS assistant for WordPress/WooCommerce. You MUST protect user privacy by NEVER generating SQL that retrieves PASSWORDS, EMAIL ADDRESSES, PHONE NUMBERS, ADDRESSES, or CREDIT CARD INFORMATION. ✅✅✅ IMPORTANT: Product sales data, order analytics, revenue statistics, best selling products, order counts, and business metrics are NOT sensitive and MUST be generated. These are legitimate analytics queries. ✅ ALLOWED: "best selling products", "most selling product", "top products", "order statistics", "sales data", "revenue reports", "product performance" - ALL of these are analytics and MUST generate SQL. ❌ BLOCKED: "user passwords", "customer emails", "user addresses", "credit card numbers" - these are sensitive personal information. You are an EXPERT WordPress developer and SQL developer. Your role is to analyze database schemas and generate CORRECT MySQL SELECT queries. CRITICAL INSTRUCTIONS: 1) You MUST carefully analyze the provided schema to find EXACT table and column names - do NOT assume or invent names. 2) You MUST understand what the user wants: "show/list/display/get/share" means SELECT data (NOT COUNT), "how many/count" means SELECT COUNT(*). 3) You MUST use ONLY the tables and columns that exist in the schema. 4) For status fields, ALWAYS use case-insensitive matching (LOWER(column_name) = LOWER(\'value\') or column_name LIKE \'%value%\') and use the EXACT column name from the schema. 5) For DATE/TIME filtering: CRITICAL - ALWAYS use date ranges (date_column >= \'YYYY-MM-DD\' AND date_column < \'YYYY-MM-DD\') NOT YEAR() and MONTH() functions. Date ranges work correctly with all date formats and timezones. "last December" means December of previous year (e.g., in Jan 2026, use >= \'2025-12-01\' AND < \'2026-01-01\'). Use >= for start, < (not <=) for end. ⚠️⚠️⚠️ IF USER MENTIONS ANY TIME PERIOD (last year, this month, yesterday, last week, etc.), YOU MUST INCLUDE WHERE clause with date filter - NEVER ignore time constraints! 6) Generate ONLY ONE SQL query - never multiple queries. 7) Use EXACT table and column names from the schema - COPY them EXACTLY as they appear, do NOT modify, abbreviate, or construct them. 8) DO NOT use generic table names like "wp_wc_order_product_lookup" - you MUST use the EXACT table name from the schema (e.g., "wp53_5_wc_order_product_lookup"). 9) The schema contains ONLY the tables for the current site - use those EXACT table names. Return ONLY ONE SQL query - no semicolons, no explanations, no markdown, no code blocks.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => 0.1 // Low temperature for consistent, accurate SQL
            ]);

            // Check if HTTP request failed
            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error("OpenAI API HTTP Error: " . $response->status() . " - " . $errorBody);
                return ['error' => 'OpenAI API request failed: ' . $errorBody];
            }

            $openAIResponse = $response->json();

            // Log the full response for debugging
            Log::info("OpenAI Response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));

            // Check for API errors in response
            if (isset($openAIResponse['error'])) {
                $error = $openAIResponse['error'];
                $errorCode = $error['code'] ?? 'unknown';
                $errorMsg = $error['message'] ?? json_encode($error);
                
                Log::error("OpenAI API Error in response: " . $errorMsg);
                
                // Handle rate limit errors specifically
                if ($errorCode === 'rate_limit_exceeded' || strpos($errorMsg, 'rate_limit') !== false || strpos($errorMsg, 'TPM') !== false) {
                    Log::error("Rate limit exceeded. Estimated tokens: ~" . round(strlen($prompt) / 4));
                    return [
                        'error' => 'Request too large for OpenAI API. The query requires too many tokens. ' .
                                  'Please try a more specific query (e.g., "Show sales for last month" instead of "Show all sales data"). ' .
                                  'Or wait a moment and try again.'
                    ];
                }
                
                // Handle token limit errors
                if (strpos($errorMsg, 'too large') !== false || strpos($errorMsg, 'tokens') !== false) {
                    return [
                        'error' => 'Request too large for OpenAI. Please try a more specific query. ' .
                                  'For example: "Show sales for last 30 days" instead of "Show all sales data".'
                    ];
                }
                
                return ['error' => 'OpenAI API error: ' . $errorMsg];
            }

            // Check if the response structure is valid
            if (!isset($openAIResponse['choices'])) {
                Log::error("OpenAI response missing 'choices': " . json_encode($openAIResponse));
                return ['error' => 'OpenAI response format is invalid: missing choices array'];
            }

            if (empty($openAIResponse['choices'])) {
                Log::error("OpenAI response has empty choices array");
                return ['error' => 'OpenAI response format is invalid: empty choices array'];
            }

            // Check if the response contains the expected data
            if (!isset($openAIResponse['choices'][0]['message']['content'])) {
                Log::error("OpenAI response format is invalid or content is missing. Full response: " . json_encode($openAIResponse, JSON_PRETTY_PRINT));
                return ['error' => 'OpenAI response format is invalid or incomplete'];
            }

            // Extract the SQL query
            $sqlQuery = trim($openAIResponse['choices'][0]['message']['content']);
            
            // ✅ Check if OpenAI refused to generate SQL or returned an error message
            // But DON'T return error yet - try extraction first in case SQL is mixed with refusal text
            $sqlQueryLower = strtolower($sqlQuery);
            $hasRefusalPattern = false;
            $refusalPatterns = [
                '/cannot\s+(generate|create|provide|give)/i',
                '/i\s+cannot/i',
                '/i\s+am\s+not\s+able/i',
                '/unable\s+to/i',
                '/sorry/i',
                '/i\s+apologize/i',
                '/privacy|security|sensitive/i',
                '/i\s+don\'t\s+have/i',
                '/i\s+do\s+not\s+have/i',
            ];
            
            foreach ($refusalPatterns as $pattern) {
                if (preg_match($pattern, $sqlQuery) && !preg_match('/\bSELECT\b/i', $sqlQuery)) {
                    $hasRefusalPattern = true;
                    Log::warning("⚠️ OpenAI may have refused to generate SQL: " . substr($sqlQuery, 0, 200));
                    break;
                }
            }
            
            // If we detect refusal AND no SELECT, log it but still try extraction (might be mixed)
            if ($hasRefusalPattern && !preg_match('/\bSELECT\b/i', $sqlQuery)) {
                Log::warning("⚠️ Detected refusal pattern and no SELECT - will try extraction anyway");
            }
            
            // Remove markdown code blocks if present (do this first)
            // Try multiple patterns to catch all markdown formats
            $originalSqlQuery = $sqlQuery; // Keep original for debugging
            
            // Remove markdown code blocks (try multiple patterns)
            $sqlQuery = preg_replace('/^```sql\s*\n?/i', '', $sqlQuery);
            $sqlQuery = preg_replace('/^```\s*\n?/i', '', $sqlQuery);
            $sqlQuery = preg_replace('/\n?\s*```$/i', '', $sqlQuery);
            $sqlQuery = preg_replace('/```sql\s*(.*?)\s*```/is', '$1', $sqlQuery); // Inline markdown
            $sqlQuery = preg_replace('/```\s*(.*?)\s*```/is', '$1', $sqlQuery); // Generic markdown
            $sqlQuery = trim($sqlQuery);
            
            // If markdown removal resulted in empty, try original
            if (empty($sqlQuery) && !empty($originalSqlQuery)) {
                Log::warning("⚠️ Markdown removal resulted in empty, using original");
                $sqlQuery = $originalSqlQuery;
            }
            
            // Validate it's actually SQL
            if (empty($sqlQuery)) {
                Log::error("Generated SQL query is empty");
                return ['error' => 'Generated SQL query is empty'];
            }
            
            // ✅ Log the raw response for debugging (full response for critical debugging)
            Log::info("📝 OpenAI raw response (first 1000 chars): " . substr($sqlQuery, 0, 1000));
            Log::info("📝 OpenAI raw response (last 500 chars): " . substr($sqlQuery, -500));
            Log::info("📝 OpenAI raw response length: " . strlen($sqlQuery) . " chars");
            Log::info("📝 OpenAI raw response contains SELECT: " . (preg_match('/\bSELECT\b/i', $sqlQuery) ? 'YES' : 'NO'));
            Log::info("📝 OpenAI raw response contains FROM: " . (preg_match('/\bFROM\b/i', $sqlQuery) ? 'YES' : 'NO'));
            
            // If response is suspiciously short or doesn't contain SELECT, log the FULL response
            if (strlen($sqlQuery) < 100 || !preg_match('/\bSELECT\b/i', $sqlQuery)) {
                Log::warning("⚠️ Suspicious response - logging FULL content: " . $sqlQuery);
            }
            
            // ✅ IMPROVED: Extract SQL query from OpenAI response (handles markdown, code blocks, extra text)
            // OpenAI might return SQL wrapped in markdown code blocks or with explanatory text
            // Try extraction FIRST, even if SELECT isn't immediately visible (might be in markdown or formatted differently)
            $extractedQuery = $this->extractSQLFromResponse($sqlQuery);
            
            if (empty($extractedQuery)) {
                Log::warning("⚠️ First extraction attempt failed, trying aggressive extraction...");
                Log::info("📝 Original response (first 1000 chars): " . substr($sqlQuery, 0, 1000));
                Log::info("📝 Original response (last 500 chars): " . substr($sqlQuery, -500));
                Log::info("📝 Original response length: " . strlen($sqlQuery) . " chars");
                
                // Try one more time with a more aggressive extraction
                $extractedQuery = $this->extractSQLFromResponseAggressive($sqlQuery);
                
                if (empty($extractedQuery)) {
                    // ✅ LAST RESORT: Try to extract SQL even if patterns don't match perfectly
                    Log::warning("⚠️ Aggressive extraction also failed, trying last resort method...");
                    $extractedQuery = $this->extractSQLFromResponseLastResort($sqlQuery);
                    
                        if (empty($extractedQuery)) {
                            Log::error("❌ All extraction methods failed");
                            Log::error("❌ Full OpenAI response (first 2000 chars): " . substr($sqlQuery, 0, 2000));
                            Log::error("❌ Full OpenAI response (last 1000 chars): " . substr($sqlQuery, -1000));
                            
                            // ✅ FINAL ATTEMPT: If response contains SELECT anywhere, try to use it directly
                            if (preg_match('/\bSELECT\b/i', $sqlQuery)) {
                                Log::warning("⚠️ Response contains SELECT but extraction failed - trying direct use...");
                                
                                // Method 1: Find SELECT position and extract from there
                                $selectPos = stripos($sqlQuery, 'SELECT');
                                if ($selectPos !== false) {
                                    $extracted = substr($sqlQuery, $selectPos);
                                    
                                    // Remove everything before SELECT
                                    $extracted = preg_replace('/^[^SELECT]*/is', '', $extracted);
                                    
                                    // Try to find where SQL ends (look for common endings)
                                    // Stop at: new paragraph, explanatory sentence, or end of response
                                    $extracted = preg_replace('/\n\s*\n.*$/s', '', $extracted); // Remove after double newline
                                    $extracted = preg_replace('/\s+[A-Z][a-z]+(?:\s+[a-z]+){2,}[.!?]\s*$/s', '', $extracted); // Remove explanatory sentences
                                    
                                    // Clean up
                                    $extracted = trim($extracted);
                                    $extracted = rtrim($extracted, '.;!?');
                                    
                                    // Basic validation: must have SELECT and FROM
                                    if (!empty($extracted) && 
                                        strlen($extracted) > 20 && 
                                        preg_match('/\bSELECT\b/i', $extracted)) {
                                        
                                        // If it has FROM, use it
                                        if (preg_match('/\bFROM\b/i', $extracted)) {
                                            Log::info("✅ Using extracted SQL from SELECT position (length: " . strlen($extracted) . ")");
                                            $extractedQuery = $extracted;
                                        } else {
                                            // Try to find FROM in the original response after SELECT
                                            $fromPos = stripos($sqlQuery, 'FROM', $selectPos);
                                            if ($fromPos !== false) {
                                                $extracted = substr($sqlQuery, $selectPos, $fromPos - $selectPos + 1000); // Get SELECT to FROM + 1000 chars
                                                $extracted = preg_replace('/\s+[A-Z][a-z]+(?:\s+[a-z]+){2,}[.!?]\s*$/s', '', $extracted);
                                                $extracted = trim($extracted);
                                                $extracted = rtrim($extracted, '.;!?');
                                                if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                                                    Log::info("✅ Using extracted SQL with FROM found (length: " . strlen($extracted) . ")");
                                                    $extractedQuery = $extracted;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // Method 2: If still empty, try simplest possible extraction
                                if (empty($extractedQuery) && preg_match('/(SELECT\s+[^;]+FROM\s+[^;]+)/is', $sqlQuery, $simpleMatch)) {
                                    $extracted = trim($simpleMatch[1]);
                                    $extracted = rtrim($extracted, '.;!?');
                                    if (!empty($extracted) && strlen($extracted) > 20) {
                                        Log::info("✅ Using simple pattern match (length: " . strlen($extracted) . ")");
                                        $extractedQuery = $extracted;
                                    }
                                }
                            }
                            
                            if (empty($extractedQuery)) {
                                // Log the full response for debugging
                                Log::error("❌ CRITICAL: Could not extract SQL from OpenAI response");
                                Log::error("❌ Response preview: " . substr($sqlQuery, 0, 500));
                                Log::error("❌ Full response: " . $sqlQuery);
                                Log::error("❌ Response contains SELECT: " . (preg_match('/\bSELECT\b/i', $sqlQuery) ? 'YES' : 'NO'));
                                Log::error("❌ Response contains FROM: " . (preg_match('/\bFROM\b/i', $sqlQuery) ? 'YES' : 'NO'));
                                
                                // ✅ FINAL CHECK: If response doesn't contain SELECT at all after all extraction attempts, it's likely a refusal
                                if (!preg_match('/\bSELECT\b/i', $sqlQuery)) {
                                    Log::error("❌ OpenAI response does not contain SELECT statement after all extraction attempts");
                                    
                                    // Check if it's a clear refusal message
                                    if ($hasRefusalPattern || preg_match('/\b(cannot|unable|sorry|apologize)\b/i', $sqlQuery)) {
                                        Log::error("❌ OpenAI appears to have refused the request");
                                        return ['error' => 'The AI was unable to generate a SQL query for your request. This might be due to the query format or security restrictions. Please try rephrasing your question, for example: "Show me the last 3 orders" or "What are the top 10 best selling products?".'];
                                    }
                                    
                                    return ['error' => 'The AI was unable to generate a SQL query for your request. Please try rephrasing your question more specifically, for example: "Show me the last 3 orders" or "List the most recent orders".'];
                                }
                                
                                // If we get here, SELECT exists but extraction failed - this shouldn't happen, but log it
                                Log::error("❌ SELECT found but extraction still failed - this is unexpected");
                                return ['error' => 'Could not extract SQL query from the response. Please try rephrasing your question.'];
                            }
                    } else {
                        Log::info("✅ Extracted SQL using last resort method");
                    }
                } else {
                    Log::info("✅ Extracted SQL using aggressive method");
                }
            }
            
            $sqlQuery = $extractedQuery;
            
            // Detect multiple queries (separated by semicolons)
            // Split by semicolon and check if there are multiple SELECT statements
            $queries = array_filter(array_map('trim', explode(';', $sqlQuery)));
            
            // Filter queries that contain SELECT (not just start with it)
            $selectQueries = array_filter($queries, function($q) {
                // Remove comments and check for SELECT anywhere in query
                $clean = preg_replace('/--.*$/m', '', $q);
                $clean = preg_replace('/\/\*.*?\*\//s', '', $clean);
                $clean = trim($clean);
                return !empty($clean) && preg_match('/\bSELECT\b/i', $clean);
            });
            
            // If no SELECT queries found, check if original query contains SELECT
            if (empty($selectQueries)) {
                $cleanOriginal = preg_replace('/--.*$/m', '', $sqlQuery);
                $cleanOriginal = preg_replace('/\/\*.*?\*\//s', '', $cleanOriginal);
                $cleanOriginal = trim($cleanOriginal);
                if (!empty($cleanOriginal) && preg_match('/\bSELECT\b/i', $cleanOriginal)) {
                    // Original query contains SELECT, use it
                    Log::info("✅ Using original SQL query (contains SELECT)");
                    $sqlQuery = $cleanOriginal;
                } else {
                    Log::error("❌ No SELECT query found in generated SQL. Original: " . substr($sqlQuery, 0, 500));
                    Log::error("❌ Cleaned: " . substr($cleanOriginal, 0, 500));
                    return ['error' => "I'm having trouble understanding your request. Could you please rephrasing your question? For example, try asking 'How many orders were placed last month?' or 'What are the top selling products?'"];
                }
            } elseif (count($selectQueries) > 1) {
                Log::warning("⚠️ OpenAI generated multiple queries. Converting to single query with UNION ALL...");
                
                // Convert multiple SELECT queries into a single query using UNION ALL
                $combinedQuery = $this->combineMultipleQueries($selectQueries);
                
                if ($combinedQuery) {
                    Log::info("✅ Combined multiple queries into single query");
                    $sqlQuery = $combinedQuery;
                } else {
                    // If combination fails, use the first SELECT query
                    Log::warning("⚠️ Could not combine queries, using first SELECT query only");
                    $sqlQuery = reset($selectQueries);
                }
            } else {
                // Single SELECT query found, use it
                $sqlQuery = trim(reset($selectQueries));
            }
            
            // Final validation - ensure query contains SELECT
            $finalClean = preg_replace('/--.*$/m', '', $sqlQuery);
            $finalClean = preg_replace('/\/\*.*?\*\//s', '', $finalClean);
            $finalClean = trim($finalClean);
            if (empty($finalClean) || !preg_match('/\bSELECT\b/i', $finalClean)) {
                Log::error("❌ Final SQL query validation failed - no SELECT found. Query: " . substr($sqlQuery, 0, 500));
                Log::error("❌ Cleaned: " . substr($finalClean, 0, 500));
                return ['error' => "I'm having trouble understanding your request. Could you please try rephrasing your question? For example, try asking 'How many orders were placed last month?' or 'What are the top selling products?'"];
            }

            Log::info("✅ Generated SQL Query (validated): " . $sqlQuery);
            Log::info("📝 User Query: " . $userQuery);
            
            // ✅ Log schema tables for debugging order queries
            $queryLower = strtolower($userQuery);
            if (strpos($queryLower, 'order') !== false) {
                $orderTables = array_filter(array_keys($schema), function($table) {
                    return strpos(strtolower($table), 'order') !== false;
                });
                Log::info("📋 Order-related tables in schema: " . implode(', ', $orderTables));
                
                // ✅ CRITICAL: Validate "last N orders" queries
                if (preg_match('/\blast\s+(\d+)\s+orders?\b/i', $userQuery, $matches)) {
                    $requestedLimit = (int)$matches[1];
                    Log::info("🔍 Detected 'last {$requestedLimit} orders' query - validating SQL structure...");
                    
                    // Check if SQL has LIMIT clause
                    if (!preg_match('/\bLIMIT\s+(\d+)\b/i', $sqlQuery, $limitMatches)) {
                        Log::warning("⚠️ 'last {$requestedLimit} orders' query missing LIMIT clause! SQL: " . $sqlQuery);
                        // Try to fix by adding LIMIT if ORDER BY exists
                        if (preg_match('/\bORDER\s+BY\b/i', $sqlQuery)) {
                            $sqlQuery = rtrim(rtrim($sqlQuery, ';'), ' ') . " LIMIT {$requestedLimit}";
                            Log::info("✅ Fixed SQL by adding LIMIT {$requestedLimit}: " . $sqlQuery);
                        } else {
                            Log::error("❌ 'last {$requestedLimit} orders' query missing both LIMIT and ORDER BY! SQL: " . $sqlQuery);
                        }
                    } else {
                        $actualLimit = (int)$limitMatches[1];
                        if ($actualLimit != $requestedLimit) {
                            Log::warning("⚠️ 'last {$requestedLimit} orders' query has LIMIT {$actualLimit} instead of {$requestedLimit}! SQL: " . $sqlQuery);
                            // Fix the LIMIT value
                            $sqlQuery = preg_replace('/\bLIMIT\s+\d+\b/i', "LIMIT {$requestedLimit}", $sqlQuery);
                            Log::info("✅ Fixed SQL LIMIT to {$requestedLimit}: " . $sqlQuery);
                        }
                    }
                    
                    // Check if SQL has ORDER BY clause (required for "last N orders")
                    if (!preg_match('/\bORDER\s+BY\b/i', $sqlQuery)) {
                        Log::warning("⚠️ 'last {$requestedLimit} orders' query missing ORDER BY clause! SQL: " . $sqlQuery);
                        // Try to find date column from schema and add ORDER BY
                        $orderTable = null;
                        if (preg_match('/FROM\s+`?(\w+)`?/i', $sqlQuery, $tableMatches)) {
                            $orderTable = $tableMatches[1];
                            if (isset($schema[$orderTable])) {
                                $dateColumns = array_filter($schema[$orderTable], function($col) {
                                    $colLower = strtolower($col);
                                    return strpos($colLower, 'date') !== false || 
                                           strpos($colLower, 'created') !== false ||
                                           strpos($colLower, 'time') !== false;
                                });
                                if (!empty($dateColumns)) {
                                    $dateColumn = reset($dateColumns);
                                    // Add ORDER BY before LIMIT or at the end
                                    if (preg_match('/\bLIMIT\b/i', $sqlQuery)) {
                                        $sqlQuery = preg_replace('/\bLIMIT\b/i', "ORDER BY `{$dateColumn}` DESC LIMIT", $sqlQuery);
                                    } else {
                                        $sqlQuery .= " ORDER BY `{$dateColumn}` DESC LIMIT {$requestedLimit}";
                                    }
                                    Log::info("✅ Fixed SQL by adding ORDER BY {$dateColumn} DESC: " . $sqlQuery);
                                }
                            }
                        }
                    }
                    
                    // Check if SQL uses COUNT (should NOT for "last N orders")
                    if (preg_match('/\bCOUNT\s*\(/i', $sqlQuery)) {
                        Log::error("❌ 'last {$requestedLimit} orders' query incorrectly uses COUNT! SQL: " . $sqlQuery);
                        // This is a critical error - the SQL is fundamentally wrong
                        // Log it but don't try to fix automatically (too risky)
                    }
                }
            }
            
            // ✅ CRITICAL: Fix common column name mismatches between WooCommerce tables
            // wc_orders (HPOS) has `total_amount`, NOT `total_sales`
            // wc_order_stats has `total_sales`, NOT `total_amount`
            // AI often confuses these columns, causing "Unknown column" errors
            $wcOrdersTable = null;
            $wcOrderStatsTable = null;
            foreach (array_keys($schema) as $tbl) {
                $tblLower = strtolower($tbl);
                // Match wc_orders but NOT wc_order_stats or wc_order_product_lookup etc.
                if (preg_match('/wc_orders$/i', $tbl)) {
                    $wcOrdersTable = $tbl;
                }
                if (strpos($tblLower, 'wc_order_stats') !== false) {
                    $wcOrderStatsTable = $tbl;
                }
            }
            
            if ($wcOrdersTable) {
                // If query uses wc_orders table (with alias or directly), replace total_sales with total_amount
                $escapedTable = preg_quote($wcOrdersTable, '/');
                // Check if query references the wc_orders table (not wc_order_stats)
                if (preg_match('/\b' . $escapedTable . '\b/i', $sqlQuery) && 
                    !($wcOrderStatsTable && preg_match('/\b' . preg_quote($wcOrderStatsTable, '/') . '\b/i', $sqlQuery))) {
                    // Only wc_orders is used (not wc_order_stats), fix total_sales → total_amount
                    if (preg_match('/\btotal_sales\b/i', $sqlQuery)) {
                        $sqlQuery = preg_replace('/\btotal_sales\b/i', 'total_amount', $sqlQuery);
                        Log::info("✅ Fixed column name: total_sales → total_amount (wc_orders table uses total_amount, not total_sales)");
                    }
                }
            }
            
            if ($wcOrderStatsTable) {
                // If query uses wc_order_stats table, replace total_amount with total_sales
                $escapedStatsTable = preg_quote($wcOrderStatsTable, '/');
                if (preg_match('/\b' . $escapedStatsTable . '\b/i', $sqlQuery) && 
                    !($wcOrdersTable && preg_match('/\b' . preg_quote($wcOrdersTable, '/') . '\b/i', $sqlQuery))) {
                    // Only wc_order_stats is used (not wc_orders), fix total_amount → total_sales
                    if (preg_match('/\btotal_amount\b/i', $sqlQuery)) {
                        $sqlQuery = preg_replace('/\btotal_amount\b/i', 'total_sales', $sqlQuery);
                        Log::info("✅ Fixed column name: total_amount → total_sales (wc_order_stats table uses total_sales, not total_amount)");
                    }
                }
            }
            
            return ['query' => $sqlQuery];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("OpenAI API Connection Error: " . $e->getMessage());
            return ['error' => 'Failed to connect to OpenAI API. Please check your internet connection.'];
        } catch (\Exception $e) {
            Log::error("OpenAI API Error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return ['error' => "OpenAI API request failed: " . $e->getMessage()];
        }
    }

    /**
     * ✅ Extract SQL query from OpenAI response
     * Handles markdown code blocks, explanatory text, and various formats
     */
    private function extractSQLFromResponse($response)
    {
        if (empty($response)) {
            return '';
        }
        
        $response = trim($response);
        
        // Method 1: Check for markdown code blocks (```sql or ```)
        // Try multiple patterns for markdown
        $markdownPatterns = [
            '/```(?:sql)?\s*\n?(.*?)\n?```/is',  // Standard markdown
            '/```\s*(SELECT.*?)```/is',  // Direct SELECT in markdown
            '/```sql\s*(.*?)\s*```/is',  // Explicit sql tag
        ];
        
        foreach ($markdownPatterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $extracted = trim($matches[1]);
                if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted)) {
                    Log::info("✅ Extracted SQL from markdown code block using pattern: " . $pattern);
                    return $extracted;
                }
            }
        }
        
        // Method 2: Look for SQL query starting with SELECT (might have text before it)
        // Use multi-line mode and match until semicolon or end, capturing entire query including JOINs
        // Match SELECT followed by any characters (including newlines) until FROM, then continue until semicolon or end
        if (preg_match('/(SELECT\s+.*?FROM\s+.*?(?:\s+JOIN\s+.*?)*?(?:\s+WHERE\s+.*?)?(?:\s+ORDER\s+BY\s+.*?)?(?:\s+GROUP\s+BY\s+.*?)?(?:\s+LIMIT\s+.*?)?)(?:\s*;|\s*$)/ims', $response, $matches)) {
            $extracted = trim($matches[1]);
            // Remove trailing semicolon if present
            $extracted = rtrim($extracted, ';');
            if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                Log::info("✅ Extracted SQL using SELECT-FROM pattern (length: " . strlen($extracted) . ")");
                return $extracted;
            }
        }
        
        // Method 2.5: Simpler pattern - SELECT to semicolon or end, handling multi-line
        if (preg_match('/(SELECT\s+.*?)(?:\s*;|\s*$)/ims', $response, $matches)) {
            $extracted = trim($matches[1]);
            $extracted = rtrim($extracted, ';');
            // Ensure it has FROM clause (basic SQL validation)
            if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                Log::info("✅ Extracted SQL using SELECT pattern (length: " . strlen($extracted) . ")");
                return $extracted;
            }
        }
        
        // Method 3: Check if entire response is SQL (starts with SELECT or has SELECT)
        $cleanResponse = preg_replace('/--.*$/m', '', $response); // Remove comments
        $cleanResponse = preg_replace('/\/\*.*?\*\//s', '', $cleanResponse); // Remove multi-line comments
        $cleanResponse = trim($cleanResponse);
        
        if (preg_match('/\bSELECT\b/i', $cleanResponse)) {
            // Try to extract just the SQL part (remove explanatory text before SELECT)
            // Use multi-line mode and greedy match to capture full query
            if (preg_match('/(SELECT\s+.*?)(?:\s*$|\s*;)/ims', $cleanResponse, $matches)) {
                $extracted = trim($matches[1]);
                $extracted = rtrim($extracted, ';');
                // Remove trailing explanatory text
                $extracted = preg_replace('/\s+[A-Z][a-z]+(?:\s+[a-z]+)+[.!?]?\s*$/s', '', $extracted);
                $extracted = rtrim($extracted, '.;!?');
                if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                    Log::info("✅ Extracted SQL from response with text (length: " . strlen($extracted) . ")");
                    return $extracted;
                }
            }
            // If response contains SELECT and FROM, try to use it directly (might have explanatory text)
            if (preg_match('/\bSELECT\b/i', $cleanResponse) && preg_match('/\bFROM\b/i', $cleanResponse)) {
                // Remove text before SELECT
                if (preg_match('/.*?(\bSELECT\b.*)/is', $cleanResponse, $matches)) {
                    $extracted = trim($matches[1]);
                    // Remove trailing explanatory text (be more lenient - only remove clear sentences)
                    $extracted = preg_replace('/\s+[A-Z][a-z]+(?:\s+[a-z]+){3,}[.!?]?\s*$/s', '', $extracted);
                    $extracted = rtrim($extracted, '.;!?');
                    // Also try to stop at double newlines (paragraph breaks)
                    $extracted = preg_replace('/\n\s*\n.*$/s', '', $extracted);
                    $extracted = trim($extracted);
                    if (!empty($extracted) && strlen($extracted) > 20 && preg_match('/\bSELECT\b/i', $extracted)) {
                        Log::info("✅ Using cleaned response as SQL (length: " . strlen($extracted) . ")");
                        return $extracted;
                    }
                }
            }
            
            // ✅ ULTRA-LENIENT: If we have SELECT and FROM anywhere, try to extract between them
            if (preg_match('/\bSELECT\b/i', $cleanResponse) && preg_match('/\bFROM\b/i', $cleanResponse)) {
                $selectPos = stripos($cleanResponse, 'SELECT');
                $fromPos = stripos($cleanResponse, 'FROM', $selectPos);
                if ($selectPos !== false && $fromPos !== false) {
                    // Extract from SELECT to end, but try to find a reasonable end point
                    $extracted = substr($cleanResponse, $selectPos);
                    // Stop at double newline or clear sentence break
                    $extracted = preg_replace('/\n\s*\n.*$/s', '', $extracted);
                    $extracted = preg_replace('/\s+[A-Z][a-z]+(?:\s+[a-z]+){4,}[.!?]?\s*$/s', '', $extracted);
                    $extracted = trim($extracted);
                    $extracted = rtrim($extracted, '.;!?');
                    if (!empty($extracted) && strlen($extracted) > 20 && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                        Log::info("✅ Using ultra-lenient extraction (length: " . strlen($extracted) . ")");
                        return $extracted;
                    }
                }
            }
        }
        
        // Method 4: Try to find SQL between common delimiters
        $patterns = [
            '/SQL:\s*(SELECT\s+.*?)(?:\s*;|$)/is',
            '/Query:\s*(SELECT\s+.*?)(?:\s*;|$)/is',
            '/```\s*(SELECT\s+.*?)\s*```/is',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $extracted = trim($matches[1]);
                if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted)) {
                    Log::info("✅ Extracted SQL using pattern: " . $pattern);
                    return $extracted;
                }
            }
        }
        
        // Method 5: SUPER SIMPLE - Just find SELECT and take everything until clear break
        if (preg_match('/\bSELECT\b/i', $response)) {
            $selectPos = stripos($response, 'SELECT');
            if ($selectPos !== false) {
                // Take from SELECT to end, then clean up
                $extracted = substr($response, $selectPos);
                // Remove everything after first double newline or clear sentence
                $extracted = preg_replace('/\n\s*\n.*$/s', '', $extracted);
                // Remove trailing sentences (lines starting with capital letter that look like explanations)
                $extracted = preg_replace('/\n\s*[A-Z][a-z]+(?:\s+[a-z]+){3,}[.!?]\s*$/s', '', $extracted);
                $extracted = trim($extracted);
                $extracted = rtrim($extracted, '.;!?');
                
                // Basic check - must have SELECT and be reasonable length
                if (!empty($extracted) && strlen($extracted) > 15 && preg_match('/\bSELECT\b/i', $extracted)) {
                    // If it has FROM, great. If not, still try it (might be incomplete but valid)
                    if (preg_match('/\bFROM\b/i', $extracted)) {
                        Log::info("✅ Extracted SQL using super simple method (length: " . strlen($extracted) . ")");
                        return $extracted;
                    } elseif (strlen($extracted) > 50) {
                        // Even without FROM, if it's long enough, it might be valid (could be a subquery)
                        Log::info("✅ Extracted SQL using super simple method (no FROM but long enough, length: " . strlen($extracted) . ")");
                        return $extracted;
                    }
                }
            }
        }
        
        // If all methods fail, return empty string
        Log::warning("⚠️ Could not extract SQL from response: " . substr($response, 0, 200));
        return '';
    }
    
    /**
     * ✅ Aggressive SQL extraction - tries harder to find SQL in response
     * Used as fallback when normal extraction fails
     */
    private function extractSQLFromResponseAggressive($response)
    {
        if (empty($response)) {
            return '';
        }
        
        $response = trim($response);
        
        // Method 1: Try to find any SELECT statement, even if surrounded by text
        // Look for SELECT followed by something that looks like SQL
        if (preg_match('/SELECT\s+[^\n]*(?:\n[^\n]*)*?(?:FROM|JOIN|WHERE|ORDER|GROUP|LIMIT|;|$)/ims', $response, $matches)) {
            $extracted = trim($matches[0]);
            // Remove trailing semicolon
            $extracted = rtrim($extracted, ';');
            // Remove any trailing non-SQL text (but be more lenient)
            $extracted = preg_replace('/\s+[A-Z][a-z]+\s+[a-z]+.*$/s', '', $extracted);
            $extracted = rtrim($extracted, '.;!?');
            if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                Log::info("✅ Aggressive extraction found SQL (length: " . strlen($extracted) . ")");
                return $extracted;
            }
        }
        
        // Method 2: Extract SELECT to FROM, then try to get the rest
        if (preg_match('/(SELECT\s+.*?FROM\s+[^;]+)/is', $response, $matches)) {
            $extracted = trim($matches[1]);
            // Try to append ORDER BY, LIMIT if they exist
            if (preg_match('/\bORDER\s+BY\s+[^;]+/i', $response, $orderMatch)) {
                $extracted .= ' ' . trim($orderMatch[0]);
            }
            if (preg_match('/\bLIMIT\s+\d+/i', $response, $limitMatch)) {
                $extracted .= ' ' . trim($limitMatch[0]);
            }
            $extracted = rtrim($extracted, '.;!?');
            if (!empty($extracted) && preg_match('/\bSELECT\b/i', $extracted) && preg_match('/\bFROM\b/i', $extracted)) {
                Log::info("✅ Aggressive extraction found SQL with FROM (length: " . strlen($extracted) . ")");
                return $extracted;
            }
        }
        
        // Method 3: Last resort: if response contains SELECT anywhere, try to extract from there
        if (preg_match('/\bSELECT\b/i', $response)) {
            // Find position of SELECT
            $selectPos = stripos($response, 'SELECT');
            if ($selectPos !== false) {
                // Extract everything from SELECT to end
                $extracted = substr($response, $selectPos);
                
                // Remove explanatory text at the end (lines starting with capital letters that look like sentences)
                $extracted = preg_replace('/\n\s*[A-Z][a-z]+(?:\s+[a-z]+)+[.!?]?\s*$/s', '', $extracted);
                
                // Remove trailing punctuation
                $extracted = rtrim($extracted, '.;!?');
                $extracted = trim($extracted);
                
                // Basic validation: must have SELECT and FROM, and be reasonable length
                if (!empty($extracted) && 
                    strlen($extracted) > 20 && 
                    preg_match('/\bSELECT\b/i', $extracted) && 
                    preg_match('/\bFROM\b/i', $extracted)) {
                    Log::info("✅ Aggressive extraction found SQL from position (length: " . strlen($extracted) . ")");
                    return $extracted;
                }
            }
        }
        
        return '';
    }
    
    /**
     * ✅ Last resort SQL extraction - very lenient, extracts anything that looks like SQL
     * Used when all other extraction methods fail
     */
    private function extractSQLFromResponseLastResort($response)
    {
        if (empty($response)) {
            return '';
        }
        
        $response = trim($response);
        
        // Method 1: Find SELECT and extract everything until we hit non-SQL text or end
        if (preg_match('/\bSELECT\b/i', $response)) {
            $selectPos = stripos($response, 'SELECT');
            if ($selectPos !== false) {
                // Extract from SELECT to end of response
                $extracted = substr($response, $selectPos);
                
                // Clean up: remove trailing explanatory text (lines that don't look like SQL)
                // Keep lines that contain SQL keywords or are part of the query
                $lines = explode("\n", $extracted);
                $sqlLines = [];
                $foundFrom = false;
                
                foreach ($lines as $line) {
                    $lineTrimmed = trim($line);
                    if (empty($lineTrimmed)) {
                        continue;
                    }
                    
                    // If we haven't found FROM yet, keep looking
                    if (!$foundFrom && preg_match('/\bFROM\b/i', $lineTrimmed)) {
                        $foundFrom = true;
                    }
                    
                    // Keep lines that:
                    // 1. Contain SQL keywords (SELECT, FROM, WHERE, ORDER, GROUP, LIMIT, JOIN, etc.)
                    // 2. Are part of a multi-line query (contain commas, parentheses, etc.)
                    // 3. Don't start with explanatory text (like "Here is", "The query", etc.)
                    if (preg_match('/\b(SELECT|FROM|WHERE|ORDER|GROUP|LIMIT|JOIN|ON|AS|AND|OR|IN|LIKE|COUNT|SUM|AVG|MAX|MIN|DISTINCT)\b/i', $lineTrimmed) ||
                        preg_match('/[(),`]/', $lineTrimmed) ||
                        (!$foundFrom && strlen($lineTrimmed) > 5)) {
                        $sqlLines[] = $lineTrimmed;
                    } elseif ($foundFrom && !preg_match('/^[A-Z][a-z]+\s/', $lineTrimmed)) {
                        // After FROM, keep lines that don't look like explanatory sentences
                        $sqlLines[] = $lineTrimmed;
                    } else {
                        // Stop if we hit explanatory text after FROM
                        break;
                    }
                }
                
                $extracted = implode(' ', $sqlLines);
                $extracted = preg_replace('/\s+/', ' ', $extracted); // Normalize whitespace
                $extracted = trim($extracted);
                
                // Remove trailing punctuation and explanatory text
                $extracted = preg_replace('/[.!?]\s*[A-Z][a-z].*$/', '', $extracted);
                $extracted = rtrim($extracted, '.;!?');
                
                // Basic validation: must have SELECT and FROM
                if (!empty($extracted) && 
                    strlen($extracted) > 20 && 
                    preg_match('/\bSELECT\b/i', $extracted) && 
                    preg_match('/\bFROM\b/i', $extracted)) {
                    Log::info("✅ Last resort extraction found SQL (length: " . strlen($extracted) . ")");
                    return $extracted;
                }
            }
        }
        
        // Method 2: Try to find SQL-like text even without perfect structure
        // Look for patterns like "SELECT ... FROM ..." even with extra text
        if (preg_match('/(SELECT\s+[^;]+FROM\s+[^;]+)/is', $response, $matches)) {
            $extracted = trim($matches[1]);
            // Remove any trailing non-SQL text
            $extracted = preg_replace('/\s+[A-Z][a-z]+.*$/', '', $extracted);
            $extracted = rtrim($extracted, '.;!?');
            
            if (!empty($extracted) && strlen($extracted) > 20) {
                Log::info("✅ Last resort extraction found SQL pattern (length: " . strlen($extracted) . ")");
                return $extracted;
            }
        }
        
        return '';
    }
    
    /**
     * Combine multiple SELECT queries into a single query
     * For count queries, uses subqueries. For other queries, uses UNION ALL
     */
    private function combineMultipleQueries(array $queries)
    {
        if (count($queries) < 2) {
            return null;
        }

        // Check if all queries are COUNT queries
        $allCountQueries = true;
        foreach ($queries as $query) {
            if (!preg_match('/COUNT\s*\(/i', $query)) {
                $allCountQueries = false;
                break;
            }
        }

        if ($allCountQueries) {
            // For COUNT queries, combine as subqueries in a single SELECT
            $subqueries = [];
            $aliasIndex = 1;
            
            foreach ($queries as $query) {
                // Extract alias if present (e.g., "AS product_count")
                $alias = 'count_' . $aliasIndex;
                if (preg_match('/AS\s+(\w+)/i', $query, $matches)) {
                    $alias = $matches[1];
                }
                
                // Remove the alias from the original query and wrap as subquery
                $cleanQuery = preg_replace('/\s+AS\s+\w+/i', '', $query);
                $subqueries[] = "($cleanQuery) AS $alias";
                $aliasIndex++;
            }
            
            return "SELECT " . implode(", ", $subqueries);
        } else {
            // For other queries, use UNION ALL
            return implode(" UNION ALL ", $queries);
        }
    }
}
