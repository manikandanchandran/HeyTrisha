<?php

// CRITICAL: Suppress PHP notices and warnings from other plugins/themes
// This prevents "headers already sent" errors when other plugins output notices
// We only suppress display, errors are still logged
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Start output buffering early to catch any stray output from other plugins
ob_start();

// Handle utility scripts (clear-cache.php, key-generator.php, database-installer.php)
// These should run BEFORE Laravel loads
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);
$script_name = basename($path);

// Allow utility scripts to run directly
$utility_scripts = ['clear-cache.php', 'key-generator.php', 'database-installer.php'];
if (in_array($script_name, $utility_scripts)) {
    $script_path = __DIR__ . '/' . $script_name;
    if (file_exists($script_path)) {
        // Clean output buffer and include the utility script
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        require $script_path;
        exit;
    }
}

// ============================================================================
// STANDALONE API HANDLERS (bypass Laravel - works on PHP 8.0+)
// These handle critical endpoints WITHOUT loading Laravel framework
// to avoid PHP version compatibility issues with Symfony packages
// ============================================================================

/**
 * Load .env file and return values as array
 */
function heytrisha_load_env() {
    $env_file = __DIR__ . '/../.env';
    $env = [];
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim(trim($value), '"\'');
            }
        }
    }
    return $env;
}

/**
 * Get PDO database connection
 */
function heytrisha_get_db() {
    $env = heytrisha_load_env();
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $dbname = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    
    if (empty($dbname) || empty($user)) {
        return null;
    }
    
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('HeyTrisha DB connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Decrypt Laravel-encrypted value (simplified)
 * For shared hosting where Laravel can't load, we try direct value first
 */
function heytrisha_decrypt_value($encrypted, $app_key) {
    if (empty($encrypted) || empty($app_key)) return null;
    
    // Remove "base64:" prefix from APP_KEY
    $key = $app_key;
    if (strpos($key, 'base64:') === 0) {
        $key = base64_decode(substr($key, 7));
    }
    
    try {
        $payload = json_decode(base64_decode($encrypted), true);
        if (!$payload || !isset($payload['iv']) || !isset($payload['value']) || !isset($payload['mac'])) {
            return null;
        }
        
        $iv = base64_decode($payload['iv']);
        $value = base64_decode($payload['value']);
        
        // Verify MAC
        $calculated = hash_hmac('sha256', $payload['iv'] . $payload['value'], $key, true);
        if (!hash_equals(base64_decode($payload['mac'] ?? ''), $calculated)) {
            // Try alternative MAC calculation
            $mac_raw = hash_hmac('sha256', $payload['iv'] . $payload['value'], $key);
            if ($payload['mac'] !== $mac_raw) {
                error_log('HeyTrisha: MAC verification failed');
                return null;
            }
        }
        
        $decrypted = openssl_decrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return null;
        }
        
        // Laravel wraps the value in serialize(), try to unserialize
        $unserialized = @unserialize($decrypted);
        if ($unserialized !== false) {
            return $unserialized;
        }
        
        return $decrypted;
    } catch (Exception $e) {
        error_log('HeyTrisha decrypt error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Send JSON response and exit
 */
function heytrisha_json_response($data, $status = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Extract SQL query from OpenAI response
 * Handles markdown code blocks, explanatory text, and various formats
 * (Ported from SQLGeneratorService.php)
 */
function heytrisha_extract_sql($response) {
    if (empty($response)) return '';
    
    $response = trim($response);
    
    // Remove markdown code blocks
    $response = preg_replace('/^```sql\s*\n?/i', '', $response);
    $response = preg_replace('/^```\s*\n?/i', '', $response);
    $response = preg_replace('/\n?\s*```$/i', '', $response);
    $response = preg_replace('/```sql\s*(.*?)\s*```/is', '$1', $response);
    $response = preg_replace('/```\s*(.*?)\s*```/is', '$1', $response);
    $response = trim($response);
    
    // If it starts with SELECT, use it directly
    if (preg_match('/^\s*SELECT\s/i', $response)) {
        $response = preg_replace('/;\s*$/s', '', $response);
        // Remove explanatory text after the query (double newline usually marks end of SQL)
        $response = preg_replace('/\n\s*\n[A-Z][^;]*$/s', '', $response);
        return trim($response);
    }
    
    // Try to extract SELECT ... FROM ... pattern (including multiline)
    if (preg_match('/(SELECT\s+[\s\S]*?FROM\s+[\s\S]*?)(?:\n\s*\n|\z)/i', $response, $matches)) {
        $sql = trim($matches[1]);
        $sql = preg_replace('/;\s*$/s', '', $sql);
        return $sql;
    }
    
    // Try simpler extraction
    if (preg_match('/(SELECT\s+[^;]+)/i', $response, $matches)) {
        return trim($matches[1]);
    }
    
    // Last resort: find SELECT position and extract from there
    $selectPos = stripos($response, 'SELECT');
    if ($selectPos !== false) {
        $extracted = substr($response, $selectPos);
        $extracted = preg_replace('/\n\s*\n.*$/s', '', $extracted);
        $extracted = trim($extracted);
        $extracted = rtrim($extracted, '.;!?');
        if (!empty($extracted) && strlen($extracted) > 20 && preg_match('/\bFROM\b/i', $extracted)) {
            return $extracted;
        }
    }
    
    return '';
}

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    heytrisha_json_response(['status' => 'ok'], 200);
}

// ============================================================================
// STANDALONE: /api/query endpoint (bypasses Laravel)
// ============================================================================
if ((strpos($path, '/api/query') !== false) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Get API key from Authorization header
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $api_key = '';
    if (preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
        $api_key = trim($matches[1]);
    }
    
    if (empty($api_key)) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'API key is required. Please provide Authorization: Bearer {API_KEY} header.'
        ], 401);
    }
    
    // 2. Connect to database
    $pdo = heytrisha_get_db();
    if (!$pdo) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Database connection failed. Please check API server configuration.'
        ], 500);
    }
    
    // 3. Find site by API key hash
    $api_key_hash = hash('sha256', $api_key);
    try {
        $stmt = $pdo->prepare('SELECT * FROM sites WHERE api_key_hash = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$api_key_hash]);
        $site = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('HeyTrisha: Failed to query sites table: ' . $e->getMessage());
        heytrisha_json_response([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    }
    
    if (!$site) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Invalid or inactive API key.'
        ], 403);
    }
    
    // 4. Get request body
    $raw_body = file_get_contents('php://input');
    $body = json_decode($raw_body, true);
    
    if (!$body || empty($body['question'])) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Please provide a "question" in the request body.'
        ], 400);
    }
    
    $question = trim($body['question']);
    $schema = $body['schema'] ?? [];
    $order_storage = $body['order_storage'] ?? 'unknown'; // 'hpos', 'legacy', or 'unknown'
    $table_prefix = $body['table_prefix'] ?? 'wp_';
    
    // 5. Get OpenAI key from site record (decrypt it)
    $env = heytrisha_load_env();
    $app_key = $env['APP_KEY'] ?? '';
    $openai_key = heytrisha_decrypt_value($site['openai_key'] ?? '', $app_key);
    
    // Fallback: try .env OPENAI_API_KEY
    if (empty($openai_key)) {
        $openai_key = $env['OPENAI_API_KEY'] ?? '';
    }
    
    if (empty($openai_key)) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'OpenAI API key not configured. Please set it in plugin settings or API .env file.'
        ], 500);
    }
    
    // 6. Build schema string and table list (same format as SQLGeneratorService.php)
    $schema_str = '';
    $table_list = [];
    if (!empty($schema) && is_array($schema)) {
        foreach ($schema as $table => $columns) {
            if (is_array($columns)) {
                $schema_str .= "$table(" . implode(',', $columns) . ")\n";
                $table_list[] = $table;
            }
        }
    }
    
    if (empty($schema_str)) {
        error_log('HeyTrisha: Empty schema received. Raw schema: ' . json_encode($schema));
        heytrisha_json_response([
            'success' => false,
            'message' => 'No database schema provided. Please check plugin configuration.'
        ], 400);
    }
    
    error_log('HeyTrisha: Schema received with ' . count($table_list) . ' tables');
    
    // Table names list for reference in prompt (limit to first 20 to avoid token overflow)
    $tableNamesList = implode(', ', array_slice($table_list, 0, 20));
    if (count($table_list) > 20) {
        $tableNamesList .= ' ... and ' . (count($table_list) - 20) . ' more tables';
    }
    
    // 7. PHP-side conversation detection (saves API calls for simple greetings)
    $question_lower = strtolower(trim($question));
    // ✅ Fixed: Catch "Hii", "Hiii", etc. and allow variations at start of string
    $is_greeting = preg_match('/^(h+i+|hello|hey|good\s*(morning|afternoon|evening)|howdy|greetings|yo)\b/i', $question) || 
                    preg_match('/^(hi|hello|hey)\s*$/i', $question); // Also catch standalone greetings
    $is_thanks = preg_match('/^(thank|thanks|thx|cheers)\b/i', $question);
    $is_farewell = preg_match('/^(bye|goodbye|see\s+you|good\s*night)\b/i', $question);
    
    $data_keywords = ['order', 'product', 'sale', 'customer', 'user', 'post', 'revenue', 'sell', 'price', 'stock', 'inventory', 'count', 'how many', 'list', 'show', 'display', 'get', 'find', 'total', 'report', 'seo', 'keyword', 'categor', 'shipping', 'coupon', 'discount', 'refund', 'payment', 'woo'];
    $has_data_keyword = false;
    foreach ($data_keywords as $kw) {
        if (strpos($question_lower, $kw) !== false) {
            $has_data_keyword = true;
            break;
        }
    }
    
    // If clearly conversational and has no data keywords, respond directly
    if (($is_greeting || $is_thanks || $is_farewell) && !$has_data_keyword) {
        try {
            $stmt = $pdo->prepare('UPDATE sites SET query_count = query_count + 1, last_query_at = NOW() WHERE id = ?');
            $stmt->execute([$site['id']]);
        } catch (PDOException $e) {}
        
        if ($is_greeting) {
            $msg = "Hello! 👋 I'm Trisha, your AI assistant. I can help you with your WooCommerce store data. Try asking me things like:\n• \"Show me the last 5 orders\"\n• \"What are the best selling products?\"\n• \"Total sales this month\"\n• \"How many customers do we have?\"";
        } elseif ($is_thanks) {
            $msg = "You're welcome! 😊 Feel free to ask me anything about your store data anytime.";
        } else {
            $msg = "Goodbye! 👋 Come back anytime you need help with your store data.";
        }
        
        heytrisha_json_response([
            'success' => true,
            'type' => 'conversation',
            'message' => $msg,
        ], 200);
    }
    
    // 8. Build the DETAILED SQL prompt (same as SQLGeneratorService.php lines 136-506)
    // This is the exact same prompt used by the Laravel service, ported here for the
    // standalone handler that bypasses Laravel due to PHP version compatibility
    $isMultisite = false;
    $userQuery = $question;
    
    // Build HPOS-aware order table guidance
    // Find the actual table names from schema
    $orders_table = '';
    $posts_table = '';
    $postmeta_table = '';
    $users_table = '';
    $order_stats_table = '';
    $order_product_lookup_table = '';
    $customer_lookup_table = '';
    $order_items_table = '';
    $order_itemmeta_table = '';
    foreach ($table_list as $t) {
        if (preg_match('/wc_orders$/', $t)) $orders_table = $t;
        if (preg_match('/(?<![a-z_])posts$/', $t)) $posts_table = $t;
        if (preg_match('/postmeta$/', $t)) $postmeta_table = $t;
        if (preg_match('/(?<![a-z_])users$/', $t)) $users_table = $t;
        if (preg_match('/wc_order_stats$/', $t)) $order_stats_table = $t;
        if (preg_match('/wc_order_product_lookup$/', $t)) $order_product_lookup_table = $t;
        if (preg_match('/wc_customer_lookup$/', $t)) $customer_lookup_table = $t;
        if (preg_match('/woocommerce_order_items$/', $t)) $order_items_table = $t;
        if (preg_match('/woocommerce_order_itemmeta$/', $t)) $order_itemmeta_table = $t;
    }
    // Fallback defaults
    if (empty($postmeta_table) && !empty($posts_table)) $postmeta_table = str_replace('posts', 'postmeta', $posts_table);
    if (empty($users_table)) $users_table = $table_prefix . 'users';
    
    if ($order_storage === 'hpos' && !empty($orders_table)) {
        $orderTableContext = "⚠️⚠️⚠️ ORDER STORAGE: This site uses WooCommerce HPOS (High-Performance Order Storage).\n" .
            "  * The PRIMARY orders table is: {$orders_table}\n" .
            "  * USE {$orders_table} for ALL order queries (latest orders, order list, order count, etc.)\n" .
            "  * DO NOT use the posts table for orders.\n" .
            "  * Date column in {$orders_table}: Look for date_created_gmt or date_created in the schema.\n";
    } elseif ($order_storage === 'legacy' && !empty($posts_table)) {
        $orderTableContext = "⚠️⚠️⚠️ ORDER STORAGE: This site uses WooCommerce LEGACY order storage.\n" .
            "  * Orders are stored in: {$posts_table} with post_type = 'shop_order'\n" .
            "  * USE {$posts_table} for ALL order queries with WHERE post_type = 'shop_order'\n" .
            "  * DO NOT use wc_orders table even if it appears in the schema (it may be empty).\n" .
            "  * Date column: post_date or post_date_gmt\n" .
            "  * Status column: post_status (values like 'wc-processing', 'wc-completed', 'wc-on-hold')\n" .
            "  * Order total: JOIN with postmeta table WHERE meta_key = '_order_total'\n";
    } else {
        // Unknown - provide both options with preference for posts (more reliable)
        $orderTableContext = "⚠️⚠️⚠️ ORDER STORAGE: Unknown mode. Check both tables:\n" .
            "  * If the posts table has post_type column, try: SELECT * FROM {$posts_table} WHERE post_type = 'shop_order' ORDER BY post_date DESC\n" .
            (!empty($orders_table) ? "  * Alternatively try: SELECT * FROM {$orders_table} ORDER BY date_created_gmt DESC\n" : "") .
            "  * If one returns no results, the other is likely the correct one.\n";
    }
    
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
              "- For posts: Look for tables ending in 'posts' in the schema\n" .
              "- " . $orderTableContext . "\n" .
              "- ⚠️⚠️⚠️ CRITICAL ORDER QUERY RULES:\n" .
              "  * When user asks for 'orders list', 'list orders', 'show orders', 'get orders', 'share orders', 'orders', 'all orders', 'latest orders', 'give me orders':\n" .
              "    → Use SELECT * FROM [correct_order_table] ORDER BY [date_column] DESC LIMIT 20\n" .
              "    → For LEGACY mode: SELECT * FROM {$posts_table} WHERE post_type = 'shop_order' ORDER BY post_date DESC LIMIT 20\n" .
              "    → For HPOS mode: SELECT * FROM {$orders_table} ORDER BY date_created_gmt DESC LIMIT 20\n" .
              "  * When user asks for 'last N orders', 'recent orders', 'latest orders':\n" .
              "    → For LEGACY: SELECT * FROM {$posts_table} WHERE post_type = 'shop_order' ORDER BY post_date DESC LIMIT N\n" .
              "    → For HPOS: SELECT * FROM {$orders_table} ORDER BY date_created_gmt DESC LIMIT N\n" .
              "    → If user says 'latest orders' without a number, use LIMIT 10\n" .
              "    → DO NOT add extra WHERE clauses unless user mentions dates/status\n" .
              "    → DO NOT use COUNT(*) - user wants to SEE the orders, not count them\n" .
              "  * For 'total sales' or 'revenue' queries:\n" .
              ($order_stats_table ?
              "    → ✅ BEST: SELECT COALESCE(SUM(total_sales), 0) AS total_sales FROM {$order_stats_table}\n" .
              "    → With date: SELECT COALESCE(SUM(total_sales), 0) AS total_sales FROM {$order_stats_table} WHERE date_created_gmt >= '2025-01-01' AND date_created_gmt < '2026-01-01'\n"
              :
              "    → For LEGACY: SELECT COALESCE(SUM(pm.meta_value), 0) AS total_sales FROM {$posts_table} p JOIN {$postmeta_table} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND pm.meta_key = '_order_total'\n" .
              "    → For HPOS: SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM {$orders_table}\n"
              ) .
              "    → ⚠️ ALWAYS use COALESCE(SUM(...), 0) to avoid NULL when no orders in date range\n" .
              "    → Add date WHERE clauses if user mentions time period\n" .
              "- ⚠️⚠️⚠️ CRITICAL PRODUCT QUERY RULES:\n" .
              "  * ✅✅✅ When user asks for 'best selling products', 'most selling product', 'top products', 'products sold', 'how many products sold':\n" .
              "    → These are ANALYTICS queries - MUST generate SQL\n" .
              ($order_product_lookup_table ?
              "    → ✅ BEST TABLE: {$order_product_lookup_table} - has `product_id`, `product_qty`, `date_created`, `product_net_revenue`\n" .
              "    → Example (best sellers with names): SELECT opl.product_id, p.post_title AS product_name, COALESCE(SUM(opl.product_qty), 0) AS total_sold FROM {$order_product_lookup_table} opl JOIN {$posts_table} p ON opl.product_id = p.ID GROUP BY opl.product_id, p.post_title ORDER BY total_sold DESC LIMIT 10\n" .
              "    → Example (products sold last year with names): SELECT opl.product_id, p.post_title AS product_name, COALESCE(SUM(opl.product_qty), 0) AS total_sold FROM {$order_product_lookup_table} opl JOIN {$posts_table} p ON opl.product_id = p.ID WHERE opl.date_created >= '2025-01-01' AND opl.date_created < '2026-01-01' GROUP BY opl.product_id, p.post_title ORDER BY total_sold DESC LIMIT 50\n" .
              "    → Example (how many products sold last year): SELECT COALESCE(SUM(product_qty), 0) AS total_sold, COUNT(DISTINCT product_id) AS unique_products FROM {$order_product_lookup_table} WHERE date_created >= '2025-01-01' AND date_created < '2026-01-01'\n"
              :
              "    → Check schema for table containing 'order_product' - use EXACT table name\n" .
              "    → The quantity column is usually `product_qty` - check schema for EXACT name\n" .
              "    → 🚨🚨🚨 CRITICAL FALLBACK: If NO order_product_lookup table exists, use wp_woocommerce_order_items + wp_woocommerce_order_itemmeta:\n" .
              "      → 🚨🚨🚨 NEVER use 'oi.product_qty' - wp_woocommerce_order_items (aliased as 'oi') does NOT have a 'product_qty' column!\n" .
              "      → ✅ CORRECT: Quantity comes from wp_woocommerce_order_itemmeta WHERE meta_key='_qty', NOT from order_items table\n" .
              "      → Example: SELECT CAST(oim_product.meta_value AS UNSIGNED) as product_id, p.post_title AS product_name, SUM(CAST(oim_qty.meta_value AS UNSIGNED)) AS total_sold FROM wp_woocommerce_order_items oi INNER JOIN wp_woocommerce_order_itemmeta oim_product ON oim_product.order_item_id = oi.order_item_id AND oim_product.meta_key = '_product_id' INNER JOIN wp_woocommerce_order_itemmeta oim_qty ON oim_qty.order_item_id = oi.order_item_id AND oim_qty.meta_key = '_qty' INNER JOIN {$posts_table} p ON p.ID = CAST(oim_product.meta_value AS UNSIGNED) WHERE oi.order_item_type = 'line_item' GROUP BY CAST(oim_product.meta_value AS UNSIGNED), p.post_title ORDER BY total_sold DESC LIMIT 10\n"
              ) .
              "    → 🚨🚨🚨 CRITICAL: NEVER use 'oi.product_qty' or 'order_items.product_qty' - this column does NOT exist!\n" .
              "    → ✅ If using order_items table, quantity MUST come from order_itemmeta WHERE meta_key='_qty'\n" .
              "    → ⚠️ ALWAYS use COALESCE(SUM(product_qty), 0) to avoid NULL results\n" .
              "    → ⚠️ ALWAYS JOIN with {$posts_table} to include product names (post_title)\n" .
              "    → ⚠️ If user mentions 'last year', 'this year', etc., add date filter on `date_created` column\n" .
              "  * ✅✅✅ Product sales data, order analytics, revenue statistics are BUSINESS DATA - MUST generate SQL\n" .
              "- ⚠️⚠️⚠️ CRITICAL COLUMN NAMING RULES:\n" .
              "  * WordPress core tables ({$posts_table}, {$users_table}) use UPPERCASE `ID` as primary key\n" .
              "  * WooCommerce analytics tables use LOWERCASE descriptive names - NEVER use `ID` for these:\n" .
              ($order_stats_table ? "    → {$order_stats_table}: columns are `order_id` (NOT `ID`!), `customer_id`, `total_sales`, `status`, `date_created_gmt`, `num_items_sold`, `net_total`\n" : "") .
              ($orders_table ? "    → {$orders_table} (HPOS): columns are `id` (lowercase!), `customer_id`, `total_amount`, `status`, `date_created_gmt`\n" : "") .
              ($order_product_lookup_table ? "    → {$order_product_lookup_table}: columns include `order_id`, `product_id`, `product_qty`, `date_created`, `product_net_revenue`\n" : "") .
              ($customer_lookup_table ? "    → {$customer_lookup_table}: columns are `customer_id`, `user_id`, `username`, `first_name`, `last_name`, `email`, `date_registered`, `date_last_active`\n" : "") .
              "  * ⚠️ ALWAYS check the schema for EXACT column names before using them\n" .
              "  * When counting rows, ALWAYS use COUNT(*) - it is safer than COUNT(column_name)\n" .
              "  * ⚠️ ALWAYS use COALESCE() with SUM/AVG to avoid NULL: COALESCE(SUM(col), 0) AS alias\n" .
              "- ⚠️⚠️⚠️ CRITICAL CUSTOMER ORDER QUERIES:\n" .
              "  * When user asks for 'latest customers', 'customer details', 'most ordered customer', 'customers who ordered', 'ordered customer details':\n" .
              (($customer_lookup_table && $order_stats_table) ?
              "    → ✅ BEST APPROACH: Use {$customer_lookup_table} which has ALL customer info including guests:\n" .
              "      → Example (most ordered customer): SELECT c.customer_id, c.first_name, c.last_name, c.email, COUNT(*) AS total_orders FROM {$order_stats_table} o JOIN {$customer_lookup_table} c ON o.customer_id = c.customer_id WHERE o.customer_id > 0 GROUP BY c.customer_id, c.first_name, c.last_name, c.email ORDER BY total_orders DESC LIMIT 10\n" .
              "      → Example (latest ordered customers): SELECT c.first_name, c.last_name, c.email, o.order_id, o.total_sales, o.date_created_gmt FROM {$order_stats_table} o JOIN {$customer_lookup_table} c ON o.customer_id = c.customer_id WHERE o.customer_id > 0 ORDER BY o.date_created_gmt DESC LIMIT 10\n"
              :
              "    → Use {$users_table} with LEFT JOIN to include all orders:\n"
              ) .
              "    → ⚠️ CRITICAL: guest orders have customer_id = 0. Add WHERE customer_id > 0 when joining with customer/user tables\n" .
              ($order_stats_table ? "    → ⚠️ NEVER use o.ID for {$order_stats_table} - it does NOT have an `ID` column. Use o.order_id or COUNT(*)\n" : "") .
              "    → For HPOS mode: SELECT u.ID, u.display_name, u.user_email, o.id AS order_id, o.total_amount, o.date_created_gmt FROM {$orders_table} o LEFT JOIN {$users_table} u ON o.customer_id = u.ID WHERE o.customer_id > 0 ORDER BY o.date_created_gmt DESC LIMIT 10\n" .
              "    → For LEGACY mode: SELECT u.ID, u.display_name, u.user_email, p.ID AS order_id, p.post_date FROM {$posts_table} p LEFT JOIN {$postmeta_table} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user' LEFT JOIN {$users_table} u ON pm.meta_value = u.ID WHERE p.post_type = 'shop_order' ORDER BY p.post_date DESC LIMIT 10\n" .
              "- For users: Look for tables containing 'user' in the schema (usually 'users' and 'usermeta')\n" .
              "- DO NOT construct table names - use ONLY what appears in the schema\n\n" .
              "WORDPRESS USER STRUCTURE (CRITICAL):\n" .
              "- WordPress users are stored in a table containing 'users' in its name (check schema for EXACT name)\n" .
              "- User roles/capabilities are stored in a table containing 'usermeta' in its name (check schema for EXACT name)\n" .
              "- When user asks for 'users with roles' or 'users list with roles', you MUST:\n" .
              "  1. FIRST: Look in the schema for the EXACT table name containing 'users'\n" .
              "  2. SECOND: Look in the schema for the EXACT table name containing 'usermeta'\n" .
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
              "   - 'show all users' = SELECT all users from users table\n" .
              "   - 'show all orders' = SELECT * or SELECT specific columns (NOT COUNT)\n" .
              "   - 'how many orders' = SELECT COUNT(*) (COUNT query)\n" .
              "   - 🚨🚨🚨 CRITICAL 'LAST N ORDERS' QUERIES:\n" .
              "     * When user says 'last 3 orders', 'last 5 orders', 'share last 3 orders':\n" .
              "       → Extract the number N from the query\n" .
              "       → Generate: SELECT * FROM order_table ORDER BY date_column DESC LIMIT N\n" .
              "       → DO NOT add WHERE clauses - user wants the LAST N orders, period\n" .
              "       → DO NOT use COUNT(*) - user wants to SEE the orders\n" .
              "   - ⚠️⚠️⚠️ CRITICAL TIME CONSTRAINTS: If user mentions ANY time period (EXCEPT 'last N orders'), you MUST include a WHERE clause with date filter:\n" .
              "     * 'last year' → WHERE date_created >= '2025-01-01' AND date_created < '2026-01-01'\n" .
              "     * 'this year' → WHERE date_created >= '2026-01-01' AND date_created < '2027-01-01'\n" .
              "     * 'last month' → WHERE date_created >= 'YYYY-MM-01' AND date_created < 'YYYY-MM-01' (previous month)\n" .
              "     * 'last December' → WHERE date_created >= '2025-12-01' AND date_created < '2026-01-01'\n" .
              "     * 'yesterday', 'today', 'this week', 'last week' → Calculate appropriate date range\n" .
              "     * NEVER ignore time constraints - they are critical for accurate results!\n" .
              "2. SECOND: Look at the schema below and find the EXACT table name you need\n" .
              "   - For posts: Find the table containing 'posts' in its name from the schema\n" .
              "   - For orders: Find the table containing 'order' or 'wc_orders' in its name from the schema\n" .
              "   - For users: Find tables containing 'users' and 'usermeta' in their names from the schema\n" .
              "   - Use the EXACT table name from the schema - do NOT modify it\n" .
              "3. THIRD: Find the EXACT column names in that table from the schema\n" .
              "   - Status column might be 'status', 'order_status', 'post_status' - check the schema\n" .
              "   - ID column might be 'id', 'ID', 'order_id' - check the schema\n" .
              "   - Use ONLY column names that exist in the schema - NEVER assume column names\n" .
              "4. FOURTH: Generate the query using the EXACT table and column names from the schema\n" .
              "\n" .
              "CRITICAL STATUS MATCHING RULES:\n" .
              "- WooCommerce status values might be stored as slugs (e.g., 'wc-processing' instead of 'processing')\n" .
              "- ALWAYS use case-insensitive matching: LOWER(column_name) = LOWER('processing') OR column_name LIKE '%processing%'\n" .
              "- If the status column contains slugs like 'wc-processing', use: WHERE column_name LIKE '%processing%'\n" .
              "\n" .
              "CRITICAL WOOCOMMERCE ORDER TABLES & DATE COLUMNS:\n" .
              "- WooCommerce stores orders in multiple tables: wc_order_stats, wc_orders, wc_order_product_lookup, posts (legacy)\n" .
              "- ⚠️⚠️⚠️ CRITICAL: ALWAYS check the schema to find the EXACT date column name - do NOT assume it's 'date_created'\n" .
              "- Common date column names: date_created, date_created_gmt, order_date, post_date, created_date\n" .
              "- Look at the schema columns for the table you're using and find which date column exists\n" .
              "- When filtering by date, use >= and < for accuracy: [date_column] >= '2024-12-01' AND [date_column] < '2025-01-01'\n" .
              "\n" .
              "QUERY TYPE RULES:\n" .
              "- If user asks to 'show', 'list', 'display', 'get', 'share' orders/posts/products: Use SELECT * or SELECT specific_columns (NOT COUNT)\n" .
              "- 🚨🚨🚨 CRITICAL: 'last N orders' → SELECT * FROM order_table ORDER BY date_column DESC LIMIT N\n" .
              "- If user asks 'how many', 'count', 'number of': Use SELECT COUNT(*) AS count_name\n" .
              "- If user asks for 'total sales', 'revenue', 'total amount': Use SELECT SUM(column_name) AS total_amount\n" .
              "- If user asks for 'average price', 'average order value': Use SELECT AVG(column_name) AS average_value\n" .
              "\n" .
              "AGGREGATE FUNCTION RULES:\n" .
              "- COUNT(*): Count all rows (use for 'how many orders', 'number of products')\n" .
              "- ⚠️ SUM(column): ALWAYS wrap in COALESCE: COALESCE(SUM(column), 0) AS alias - prevents NULL when no rows match\n" .
              "- ⚠️ AVG(column): ALWAYS wrap in COALESCE: COALESCE(AVG(column), 0) AS alias\n" .
              "- MAX(column): Maximum value (use for 'highest price', 'latest date')\n" .
              "- MIN(column): Minimum value (use for 'lowest price', 'earliest date')\n" .
              "- ⚠️⚠️⚠️ CRITICAL: ALWAYS use COALESCE() with SUM and AVG to return 0 instead of NULL when no data matches\n" .
              "- When using aggregate functions, give the result a clear alias: COUNT(*) AS order_count, COALESCE(SUM(total_sales), 0) AS total_revenue\n" .
              "- For 'top 10', 'best selling', use ORDER BY DESC LIMIT 10\n" .
              "\n" .
              "PRODUCT NAME RULES (CRITICAL):\n" .
              "- ⚠️⚠️⚠️ MANDATORY: If your query returns product_id, you MUST also include the product name (post_title from posts table)\n" .
              "- To get product name, JOIN with the posts table (find table containing 'posts' in schema)\n" .
              "- Join condition: posts.ID = product_lookup.product_id\n" .
              "- SELECT both product_id AND post_title AS product_name so users see product names, not just IDs\n" .
              "- ⚠️⚠️⚠️ CRITICAL: When using GROUP BY product_id, you MUST also include post_title in SELECT and GROUP BY\n" .
              "- NEVER return only product_id without product_name - users need to see actual product names\n\n" .
              "CRITICAL RULES:\n" .
              "- Generate ONLY ONE SQL query - never multiple queries separated by semicolons\n" .
              "- If user asks for multiple counts, use subqueries or UNION ALL in a single query\n" .
              "- ⚠️⚠️⚠️ CRITICAL: Use ONLY table and column names from the schema below\n" .
              "- ⚠️⚠️⚠️ CRITICAL: For quantity columns, check the schema for EXACT column name (might be 'product_qty', 'quantity', 'qty', 'order_item_qty')\n" .
              "- ⚠️⚠️⚠️ CRITICAL: DO NOT assume column names - ALWAYS check the schema first\n" .
              "- 🚨🚨🚨 CRITICAL ERROR TO AVOID: wp_woocommerce_order_items (aliased as 'oi') does NOT have a 'product_qty' column!\n" .
              "- 🚨🚨🚨 NEVER use 'oi.product_qty' or 'order_items.product_qty' - this will cause 'Unknown column' database error!\n" .
              "- ✅ If using order_items table, quantity MUST come from wp_woocommerce_order_itemmeta WHERE meta_key='_qty'\n\n" .
              "SECURITY RULES:\n" .
              "- NEVER select user_pass (password) columns\n" .
              "- Everything else (orders, products, customers, billing info, emails, addresses, phone numbers) is business data → ALWAYS generate SQL\n" .
              "- NEVER refuse to generate SQL for any order, product, customer, or sales query\n" .
              "- NEVER say 'I cannot', 'I'm sorry', 'I apologize' - just generate the SQL query\n" .
              "\n" .
              "   - For DATE/TIME filtering, ALWAYS use date ranges (>= and <) for maximum accuracy:\n" .
              "     * Format: date_column >= 'YYYY-MM-DD' AND date_column < 'YYYY-MM-DD'\n" .
              "     * 'last month' = date_created >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND date_created < DATE_FORMAT(CURDATE(), '%Y-%m-01')\n" .
              "     * 'last December' = date_created >= '2025-12-01' AND date_created < '2026-01-01'\n" .
              "     * 'this year' = date_created >= CONCAT(YEAR(CURDATE()), '-01-01') AND date_created < CONCAT(YEAR(CURDATE()) + 1, '-01-01')\n" .
              "     * NEVER use YEAR() and MONTH() functions - they can fail with different date formats\n" .
              "     * ALWAYS use >= for start date and < (not <=) for end date\n" .
              "6. Use the EXACT table and column names from schema - do NOT invent or modify names\n" .
              "7. Return ONLY ONE SQL query - no multiple queries, no semicolons, no explanations, no markdown, no code blocks\n\n" .
              "User request: \"$userQuery\"\n\n" .
              "Database schema (" . count($table_list) . " tables):\n$schema_str\n\n" .
              "⚠️⚠️⚠️ CRITICAL: TABLE AND COLUMN NAMES IN SCHEMA ARE EXACT - USE THEM EXACTLY ⚠️⚠️⚠️\n" .
              "The schema above shows the EXACT table names and column names that exist in the database.\n" .
              "You MUST copy the table names EXACTLY as they appear in the schema.\n" .
              "You MUST copy the column names EXACTLY as they appear in the schema.\n" .
              "DO NOT modify, abbreviate, or construct table names.\n" .
              "DO NOT modify, abbreviate, or construct column names.\n" .
              "⚠️⚠️⚠️ BEFORE using any column name, CHECK THE SCHEMA to find the EXACT column name ⚠️⚠️⚠️\n\n" .
              "SQL:";
    
    // System message with SQL security rules (same as SQLGeneratorService.php)
    $system_message = 'You are an EXPERT WordPress/WooCommerce SQL developer. Your ONLY job is to generate a single MySQL SELECT query based on the user\'s request and the provided database schema. ' .
        'SECURITY: NEVER generate SQL that retrieves user_pass (passwords) columns. All other data (orders, products, customers, billing info, addresses, emails) is business data and MUST be returned when requested. ' .
        'CRITICAL INSTRUCTIONS: ' .
        '1) Analyze the provided schema to find EXACT table and column names. ' .
        '2) "show/list/display/get/share/give me/latest" means SELECT data rows (NOT COUNT). "how many/count/total number" means SELECT COUNT(*). ' .
        '3) Use ONLY tables and columns that exist in the provided schema. ' .
        '4) For WooCommerce orders: The ORDER STORAGE mode tells you which table to use. ' .
        ($order_storage === 'hpos' ? 'This site uses HPOS - use the wc_orders table. ' : 
         ($order_storage === 'legacy' ? 'This site uses LEGACY storage - use the posts table with post_type=\'shop_order\'. ' : 
          'Check both wc_orders and posts tables. ')) .
        '5) 🚨🚨🚨 CRITICAL: wp_woocommerce_order_items (aliased as "oi") does NOT have a "product_qty" column! NEVER use "oi.product_qty" - this will cause a database error! ' .
        '6) If you need quantity from order_items, it MUST come from wp_woocommerce_order_itemmeta WHERE meta_key=\'_qty\', NOT from the order_items table directly. ' .
        '7) Return ONLY the raw SQL query - no semicolons, no explanations, no markdown, no code blocks, no apologies. ' .
        '8) NEVER refuse to generate SQL for order, product, customer, or sales queries. These are legitimate business analytics. ' .
        '9) If unsure which table to use, pick the most likely one from the schema and generate the query anyway. NEVER say "I cannot" or "I\'m sorry".';
    
    // 9. Estimate prompt size and choose appropriate model
    $estimated_tokens = strlen($prompt) / 4;
    $model = 'gpt-4o-mini'; // 128K context, fast, cheap, good at SQL generation
    $max_tokens = 500;
    
    if ($estimated_tokens > 30000) {
        error_log('HeyTrisha: WARNING - Prompt is very large (~' . round($estimated_tokens) . ' tokens)');
    }
    
    // 10. Call OpenAI API
    $openai_payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system_message],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => $max_tokens,
        'temperature' => 0.1, // Low temperature for consistent, accurate SQL
    ]);
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $openai_payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $openai_key,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $openai_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$openai_response || $curl_error) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'OpenAI API request failed: ' . ($curl_error ?: 'Empty response')
        ], 500);
    }
    
    $openai_data = json_decode($openai_response, true);
    
    if ($http_code !== 200) {
        $error_msg = $openai_data['error']['message'] ?? 'Unknown OpenAI error';
        
        // Handle rate limit errors
        if (strpos($error_msg, 'rate_limit') !== false || strpos($error_msg, 'TPM') !== false) {
            heytrisha_json_response([
                'success' => false,
                'message' => 'OpenAI rate limit reached. Please wait a moment and try again.'
            ], 429);
        }
        
        heytrisha_json_response([
            'success' => false,
            'message' => 'OpenAI API error: ' . $error_msg
        ], 500);
    }
    
    $ai_content = trim($openai_data['choices'][0]['message']['content'] ?? '');
    
    // Debug: Log what OpenAI returned
    error_log('HeyTrisha: OpenAI raw response: ' . substr($ai_content, 0, 500));
    error_log('HeyTrisha: Schema tables sent: ' . $tableNamesList);
    
    // 11. Extract SQL from OpenAI response using the extraction function
    // (ported from SQLGeneratorService.php extraction logic)
    $sql = heytrisha_extract_sql($ai_content);
    
    // Debug: Log extracted SQL
    error_log('HeyTrisha: Extracted SQL: ' . ($sql ?: '(empty)'));
    
    if (empty($sql)) {
        // Check if AI refused to generate SQL (privacy/security refusal)
        if (preg_match('/\b(cannot|unable|sorry|apologize|don\'t|do not|privacy|security|sensitive)\b/i', $ai_content) && 
            !preg_match('/\bSELECT\b/i', $ai_content)) {
            heytrisha_json_response([
                'success' => true,
                'type' => 'conversation',
                'message' => "Sorry, I couldn't process that request. Please try again or rephrase your query.",
            ], 200);
        }
        
        error_log('HeyTrisha: Could not extract SQL from OpenAI response: ' . substr($ai_content, 0, 500));
        
        heytrisha_json_response([
            'success' => false,
            'message' => 'I could not generate a database query for your question. Please try rephrasing it.'
        ], 400);
    }
    
    // 12. Validate: must be a SELECT query
    if (!preg_match('/^\s*SELECT\s/i', $sql)) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Generated query is not a SELECT query. Only read queries are allowed.'
        ], 400);
    }
    
    // 13. Post-processing: Fix common issues (ported from SQLGeneratorService.php)
    // Check "last N orders" queries for proper LIMIT and ORDER BY
    if (preg_match('/\blast\s+(\d+)\s+orders?\b/i', $question, $limit_matches)) {
        $requested_limit = (int)$limit_matches[1];
        
        // Ensure LIMIT matches requested count
        if (!preg_match('/\bLIMIT\s+(\d+)\b/i', $sql, $sql_limit)) {
            if (preg_match('/\bORDER\s+BY\b/i', $sql)) {
                $sql = rtrim(rtrim($sql, ';'), ' ') . " LIMIT {$requested_limit}";
            }
        } else {
            $actual_limit = (int)$sql_limit[1];
            if ($actual_limit != $requested_limit) {
                $sql = preg_replace('/\bLIMIT\s+\d+\b/i', "LIMIT {$requested_limit}", $sql);
            }
        }
        
        // Ensure ORDER BY exists for "last N" queries
        if (!preg_match('/\bORDER\s+BY\b/i', $sql)) {
            if (preg_match('/\bLIMIT\b/i', $sql)) {
                $sql = preg_replace('/\bLIMIT\b/i', "ORDER BY 1 DESC LIMIT", $sql);
            } else {
                $sql .= " ORDER BY 1 DESC LIMIT {$requested_limit}";
            }
        }
    }
    
    // Build explanation from the question
    $explanation = "Results for: " . $question;
    
    // 14. Update query count
    try {
        $stmt = $pdo->prepare('UPDATE sites SET query_count = query_count + 1, last_query_at = NOW() WHERE id = ?');
        $stmt->execute([$site['id']]);
    } catch (PDOException $e) {
        error_log('HeyTrisha: Failed to update query count: ' . $e->getMessage());
    }
    
    // 15. Return SQL to plugin for local execution
    heytrisha_json_response([
        'success' => true,
        'type' => 'sql',
        'sql' => $sql,
        'explanation' => $explanation,
        'message' => 'SQL query generated successfully.'
    ], 200);
}

// ============================================================================
// STANDALONE: /api/health endpoint
// ============================================================================
if ((strpos($path, '/api/health') !== false || strpos($path, '/health') !== false) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $env = heytrisha_load_env();
    $info = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'app_key_set' => !empty($env['APP_KEY'] ?? ''),
    ];
    
    // Check database
    $pdo = heytrisha_get_db();
    $info['database_connected'] = ($pdo !== null);
    
    if ($pdo) {
        try {
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM sites');
            $result = $stmt->fetch();
            $info['registered_sites'] = (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            $info['sites_table_error'] = $e->getMessage();
        }
    }
    
    heytrisha_json_response($info, 200);
}

// ============================================================================
// STANDALONE: /api/register endpoint
// ============================================================================
if (strpos($path, '/api/register') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_body = file_get_contents('php://input');
    $body = json_decode($raw_body, true);
    
    if (!$body) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Invalid JSON in request body.'
        ], 400);
    }
    
    $site_url = $body['site_url'] ?? '';
    $email = $body['email'] ?? '';
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';
    $first_name = $body['first_name'] ?? '';
    $last_name = $body['last_name'] ?? '';
    $openai_key_raw = $body['openai_key'] ?? '';
    
    if (empty($site_url) || empty($email)) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'site_url and email are required.'
        ], 400);
    }
    
    $pdo = heytrisha_get_db();
    if (!$pdo) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Database connection failed.'
        ], 500);
    }
    
    // Check if site already exists
    try {
        $stmt = $pdo->prepare('SELECT id, api_key_hash FROM sites WHERE site_url = ? LIMIT 1');
        $stmt->execute([rtrim($site_url, '/')]);
        $existing = $stmt->fetch();
    } catch (PDOException $e) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ], 500);
    }
    
    // Generate API key
    $api_key = 'ht_' . bin2hex(random_bytes(32));
    $api_key_hash = hash('sha256', $api_key);
    
    // Encrypt OpenAI key if provided
    $env = heytrisha_load_env();
    $app_key_raw = $env['APP_KEY'] ?? '';
    $encrypted_openai = '';
    if (!empty($openai_key_raw) && !empty($app_key_raw)) {
        $key = $app_key_raw;
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }
        $iv = random_bytes(16);
        $value = openssl_encrypt(serialize($openai_key_raw), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($value !== false) {
            $iv_b64 = base64_encode($iv);
            $value_b64 = base64_encode($value);
            $mac = hash_hmac('sha256', $iv_b64 . $value_b64, $key);
            $encrypted_openai = base64_encode(json_encode([
                'iv' => $iv_b64,
                'value' => $value_b64,
                'mac' => $mac,
                'tag' => '',
            ]));
        }
    }
    
    $now = date('Y-m-d H:i:s');
    
    try {
        if ($existing) {
            // Update existing site
            $stmt = $pdo->prepare('UPDATE sites SET api_key_hash = ?, email = ?, username = ?, first_name = ?, last_name = ?, openai_key = ?, is_active = 1, updated_at = ? WHERE id = ?');
            $stmt->execute([$api_key_hash, $email, $username, $first_name, $last_name, $encrypted_openai, $now, $existing['id']]);
        } else {
            // Insert new site
            $stmt = $pdo->prepare('INSERT INTO sites (site_url, api_key_hash, email, username, password, first_name, last_name, openai_key, is_active, query_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)');
            $hashed_password = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : '';
            $stmt->execute([rtrim($site_url, '/'), $api_key_hash, $email, $username, $hashed_password, $first_name, $last_name, $encrypted_openai, $now, $now]);
        }
        
        heytrisha_json_response([
            'success' => true,
            'message' => 'Site registered successfully.',
            'api_key' => $api_key,
        ], 200);
        
    } catch (PDOException $e) {
        heytrisha_json_response([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
        ], 500);
    }
}

// ============================================================================
// STANDALONE: /api/diagnostic endpoint  
// ============================================================================
if ((strpos($path, '/api/diagnostic') !== false || strpos($path, '/diagnostic') !== false) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $env = heytrisha_load_env();
    $diagnostics = [
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'vendor_exists' => file_exists(__DIR__ . '/../vendor/autoload.php'),
        'env_file_exists' => file_exists(__DIR__ . '/../.env'),
        'storage_writable' => is_writable(__DIR__ . '/../storage'),
        'bootstrap_cache_writable' => is_writable(__DIR__ . '/../bootstrap/cache'),
        'app_key_exists' => !empty($env['APP_KEY'] ?? ''),
        'db_configured' => !empty($env['DB_DATABASE'] ?? ''),
        'openai_key_in_env' => !empty($env['OPENAI_API_KEY'] ?? ''),
        'curl_available' => function_exists('curl_init'),
        'openssl_available' => function_exists('openssl_encrypt'),
    ];
    
    $pdo = heytrisha_get_db();
    $diagnostics['database_connected'] = ($pdo !== null);
    
    if ($pdo) {
        try {
            $diagnostics['sites_table_exists'] = true;
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM sites');
            $result = $stmt->fetch();
            $diagnostics['registered_sites'] = (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            $diagnostics['sites_table_exists'] = false;
            $diagnostics['sites_table_error'] = $e->getMessage();
        }
    }
    
    heytrisha_json_response($diagnostics, 200);
}

// Allow health, diagnostic and API endpoints for external API server
// These are needed for the standalone API server (api.heytrisha.com)
// Note: This file is now used as a standalone Laravel front controller,
// so we explicitly allow all Laravel API routes we call from the plugin.
$allowed_paths = [
    '/api/health',
    '/api/diagnostic',
    '/health',
    '/diagnostic',
    '/api/register',
    '/api/config', // ✅ Allow config endpoint for updating site configuration
    '/api/config/update',
    '/api/chat',
    '/api/query', // ✅ Allow query endpoint for chatbot queries
    '/api/regenerate-key', // ✅ Allow API key regeneration
    '/api/site/info', // ✅ Allow site info endpoint
];

// Prevent direct access if not called from WordPress (ABSPATH defined)
// EXCEPT for health/diagnostic endpoints (needed for external API server)
// Also allow direct access with allow_direct=1 parameter for testing
$is_allowed_path = false;
foreach ($allowed_paths as $allowed) {
    if (strpos($path, $allowed) !== false) {
        $is_allowed_path = true;
        break;
    }
}

if (!defined('ABSPATH') && !$is_allowed_path && (!isset($_GET['allow_direct']) || $_GET['allow_direct'] !== '1')) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Direct access not allowed. Please use WordPress REST API: /wp-json/heytrisha/v1/api/'
    ]);
    exit;
}

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Simple test endpoint before Laravel loads (for debugging)
if (isset($_GET['test']) && $_GET['test'] === 'simple') {
    // Clean output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    $env_file = __DIR__.'/../.env';
    $env_data = [];
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                if (in_array($key, ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'APP_KEY'])) {
                    $env_data[$key] = $key === 'DB_PASSWORD' ? '***hidden***' : (strlen($value) > 50 ? substr($value, 0, 20) . '...' : $value);
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Simple test endpoint works!',
        'php_version' => PHP_VERSION,
        'vendor_exists' => file_exists(__DIR__.'/../vendor/autoload.php'),
        'env_exists' => file_exists($env_file),
        'env_data' => $env_data,
        'storage_writable' => is_writable(__DIR__.'/../storage'),
        'bootstrap_cache_writable' => is_writable(__DIR__.'/../bootstrap/cache'),
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'script_path' => __FILE__,
    ], JSON_PRETTY_PRINT);
    exit;
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

// Check if vendor exists
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Laravel dependencies not installed. Please run: cd api && composer install --no-dev --optimize-autoloader'
    ]);
    exit;
}

// Disable Composer platform check for shared hosting compatibility
// This allows the plugin to work on PHP 7.4.3+ even if vendor was installed on PHP 8.2+
$platform_check = __DIR__.'/../vendor/composer/platform_check.php';
if (file_exists($platform_check)) {
    // Temporarily disable platform check by renaming it
    $platform_check_backup = __DIR__.'/../vendor/composer/platform_check.php.bak';
    if (!file_exists($platform_check_backup)) {
        @rename($platform_check, $platform_check_backup);
    }
}

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Check .env and APP_KEY
|--------------------------------------------------------------------------
|
| Ensure .env file exists and has APP_KEY set
|
*/

$env_file = __DIR__.'/../.env';
$env_example = __DIR__.'/../.env.example';

// Ensure storage directories exist and are writable
$storage_dirs = [
    __DIR__.'/../storage',
    __DIR__.'/../storage/app',
    __DIR__.'/../storage/framework',
    __DIR__.'/../storage/framework/cache',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/logs',
    __DIR__.'/../bootstrap/cache',
];

foreach ($storage_dirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Try to make writable if not already
    if (file_exists($dir) && !is_writable($dir)) {
        @chmod($dir, 0755);
    }
}

// Create .env from .env.example if it doesn't exist
if (!file_exists($env_file)) {
    if (file_exists($env_example)) {
        copy($env_example, $env_file);
    } else {
        // Create minimal .env file
        $minimal_env = "APP_NAME=HeyTrisha\n";
        $minimal_env .= "APP_ENV=production\n";
        $minimal_env .= "APP_DEBUG=true\n";
        $minimal_env .= "APP_URL=\n\n";
        $minimal_env .= "LOG_CHANNEL=stack\n";
        $minimal_env .= "LOG_LEVEL=error\n\n";
        $minimal_env .= "DB_CONNECTION=mysql\n";
        $minimal_env .= "DB_HOST=127.0.0.1\n";
        $minimal_env .= "DB_PORT=3306\n";
        $minimal_env .= "DB_DATABASE=\n";
        $minimal_env .= "DB_USERNAME=\n";
        $minimal_env .= "DB_PASSWORD=\n\n";
        $minimal_env .= "OPENAI_API_KEY=\n";
        @file_put_contents($env_file, $minimal_env);
    }
}

// Generate APP_KEY if missing
if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    if (!preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]+={0,2}$/m', $env_content)) {
        // Generate key programmatically
        $key = 'base64:' . base64_encode(random_bytes(32));
        if (preg_match('/^APP_KEY=.*$/m', $env_content)) {
            $env_content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env_content);
        } else {
            if (preg_match('/^(APP_NAME=.*)$/m', $env_content)) {
                $env_content = preg_replace('/^(APP_NAME=.*)$/m', '$1' . "\n" . 'APP_KEY=' . $key, $env_content);
            } else {
                $env_content = 'APP_KEY=' . $key . "\n" . $env_content;
            }
        }
        file_put_contents($env_file, $env_content);
    }
    
    // Load .env variables into $_ENV and putenv BEFORE Laravel loads
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            if (!empty($key)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    // Enable debug mode for better error messages (can be disabled in production)
    if (!isset($_ENV['APP_DEBUG']) || $_ENV['APP_DEBUG'] === '') {
        $_ENV['APP_DEBUG'] = 'true';
        putenv('APP_DEBUG=true');
    }
}

// Clear ALL bootstrap cache files to prevent stale cache issues
$bootstrap_cache = __DIR__.'/../bootstrap/cache';
$cache_files = ['config.php', 'routes.php', 'services.php', 'packages.php'];
foreach ($cache_files as $cache_file) {
    $cache_path = $bootstrap_cache . '/' . $cache_file;
    if (file_exists($cache_path)) {
        // Always clear cache to prevent syntax errors from cached files
        @unlink($cache_path);
    }
}

// Also clear compiled views cache that might contain syntax errors
$compiled_views = __DIR__.'/../storage/framework/views';
if (is_dir($compiled_views)) {
    $files = glob($compiled_views . '/*.php');
    if ($files) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

try {
    // Load environment variables (ensure they're loaded)
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                if (!empty($key)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
    
    // Bootstrap Laravel application
    $app = require_once __DIR__.'/../bootstrap/app.php';
    
    // Set APP_DEBUG for better error messages
    if (!defined('APP_DEBUG')) {
        $debug_value = isset($_ENV['APP_DEBUG']) ? $_ENV['APP_DEBUG'] : 'true';
        define('APP_DEBUG', $debug_value === 'true' || $debug_value === true);
    }
    
    // CRITICAL: Set facade root before any facades are used
    // This must be done before making the kernel or handling exceptions
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    
    // Make kernel with error handling
    try {
        $kernel = $app->make(Kernel::class);
    } catch (\Exception $kernelError) {
        throw new \Exception("Failed to create Kernel: " . $kernelError->getMessage(), 0, $kernelError);
    }
    
    // Bootstrap the application early to ensure all service providers are loaded
    // This ensures facades are available even if exceptions occur during request handling
    // CRITICAL: Bootstrap must complete successfully for all services (including View) to be available
    $kernel->bootstrap();
    
    // ✅ Capture request - check for WordPress proxy data first
    // If WordPress proxy set request body in global variable, use it
    if (isset($GLOBALS['heytrisha_request_body']) && !empty($GLOBALS['heytrisha_request_body'])) {
        // Log what we're receiving
        error_log("🔍 Laravel Debug - heytrisha_request_body: " . json_encode($GLOBALS['heytrisha_request_body']));
        
        // ✅ CRITICAL: Set $_POST so Laravel's Request::capture() can read it
        // Laravel checks $_POST for POST request data
        $_POST = $GLOBALS['heytrisha_request_body'];
        
        // Ensure Content-Type is set
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        
        // ✅ Use Request::capture() which reads from $_POST automatically
        // This is more reliable than Request::create() for POST data
        $request = Request::capture();
        
        // ✅ CRITICAL: Manually ensure data is in the request
        // Even though we set $_POST, manually populate request bag to be sure
        foreach ($GLOBALS['heytrisha_request_body'] as $key => $value) {
            $request->request->set($key, $value);
            // Also set in query bag (some Laravel methods check there)
            $request->query->set($key, $value);
        }
        
        error_log("🔍 Laravel Debug - Request captured");
        error_log("🔍 Laravel Debug - _POST: " . json_encode($_POST));
        error_log("🔍 Laravel Debug - query from input(): " . var_export($request->input('query'), true));
        error_log("🔍 Laravel Debug - query from request->request->get(): " . var_export($request->request->get('query'), true));
        error_log("🔍 Laravel Debug - Request all(): " . json_encode($request->all()));
        
        // Clear the global variable
        unset($GLOBALS['heytrisha_request_body']);
    } else {
        // Normal Laravel request capture
        $request = Request::capture();
    }
    
    // Handle request
    $response = $kernel->handle($request);
    
    // Check if we're being called from WordPress (via proxy)
    // If so, output JSON instead of sending headers
    if (defined('ABSPATH')) {
        // Clean any output from other plugins/themes that might have been buffered
        // This prevents "headers already sent" errors
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Called from WordPress - return JSON string for WordPress to handle
        $content = $response->getContent();
        echo $content;
        $kernel->terminate($request, $response);
        exit;
    }
    
    // Normal Laravel response (standalone)
    // Clean output buffer first
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $response->send();
    
    // Terminate
    $kernel->terminate($request, $response);
    
} catch (\Throwable $e) {
    // Clean any output from other plugins/themes that might have been buffered
    // This prevents "headers already sent" errors
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Better error handling for shared hosting
    http_response_code(500);
    header('Content-Type: application/json');
    
    // Check for common issues
    $error_message = 'Internal server error';
    $error_details = [];
    
    // Check APP_KEY
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        if (!preg_match('/^APP_KEY=base64:/m', $env_content)) {
            $error_message = 'APP_KEY is missing. Please regenerate it from WordPress admin.';
            $error_details[] = 'APP_KEY not found in .env file';
        }
    } else {
        $error_message = '.env file not found. Please check plugin installation.';
        $error_details[] = '.env file missing';
    }
    
    // Check storage permissions
    $storage_path = __DIR__.'/../storage';
    if (!is_writable($storage_path)) {
        $error_details[] = 'Storage directory not writable';
    }
    
    // Check bootstrap cache
    $bootstrap_cache = __DIR__.'/../bootstrap/cache';
    if (file_exists($bootstrap_cache) && !is_writable($bootstrap_cache)) {
        $error_details[] = 'Bootstrap cache not writable';
    }
    
    // Log detailed error
    $log_message = 'Hey Trisha Laravel Error: ' . $e->getMessage() . PHP_EOL;
    $log_message .= 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    $log_message .= 'Stack trace: ' . $e->getTraceAsString();
    error_log($log_message);
    
    // Write to Laravel log if possible
    $log_file = __DIR__.'/../storage/logs/laravel.log';
    if (is_writable(dirname($log_file))) {
        @file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $log_message . PHP_EOL . PHP_EOL, FILE_APPEND);
    }
    
    $response_data = [
        'success' => false,
        'message' => $error_message,
    ];
    
    // Include error details in debug mode or if APP_DEBUG is set
    $show_debug = (defined('APP_DEBUG') && APP_DEBUG) || (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true');
    if ($show_debug) {
        $response_data['error'] = $e->getMessage();
        $response_data['file'] = $e->getFile() . ':' . $e->getLine();
        $response_data['type'] = get_class($e);
        if (!empty($error_details)) {
            $response_data['details'] = $error_details;
        }
        // Include first few lines of stack trace
        $trace = $e->getTraceAsString();
        $trace_lines = explode("\n", $trace);
        $response_data['trace'] = array_slice($trace_lines, 0, 10);
    }
    
    echo json_encode($response_data, JSON_PRETTY_PRINT);
    exit;
}
