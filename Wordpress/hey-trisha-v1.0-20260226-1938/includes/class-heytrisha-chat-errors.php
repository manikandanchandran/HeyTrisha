<?php
/**
 * User-facing chat error messages for the WordPress plugin.
 *
 * @package HeyTrisha
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_Chat_Errors {

    /**
     * Detect disallowed write/delete intent from natural-language query.
     *
     * @param string $query User message.
     * @return string|null User-facing message or null if allowed.
     */
    public static function blocked_intent_from_query($query) {
        if (!is_string($query) || trim($query) === '') {
            return null;
        }

        $q = strtolower(trim($query));

        if (preg_match('/\b(delete|remove|erase|purge|wipe|drop|truncate|destroy)\b/i', $q)) {
            if (self::mentions_data_target($q) || preg_match('/\b(delete|remove|drop|truncate)\s+(all|the|my|this|these|those|every)\b/i', $q)) {
                return "Sorry, I can't delete data in the database. I can only help you view and analyze existing data.";
            }
        }

        if (preg_match('/\b(insert|update|modify|change|alter|create|replace)\b/i', $q) && self::mentions_data_target($q)) {
            if (!preg_match('/\b(update|edit|change|modify)\s+(post|product|page|order|item)\b/i', $q)) {
                return "Sorry, I can't modify or write data in the database. I can only run read-only queries to view and analyze your store data.";
            }
        }

        if (preg_match('/\b(DELETE|DROP|TRUNCATE|INSERT|UPDATE|ALTER|CREATE)\s+(FROM|INTO|TABLE|DATABASE)?\b/i', $query)) {
            if (preg_match('/\b(DELETE|DROP|TRUNCATE)\b/i', $query)) {
                return "Sorry, I can't delete data in the database. I can only help you view and analyze existing data.";
            }
            return "Sorry, I can't modify or write data in the database. I can only run read-only queries to view and analyze your store data.";
        }

        return null;
    }

    /**
     * Map SQL validator result to a user-facing message.
     *
     * @param array $validation Result from HeyTrisha_SQL_Validator::validate().
     * @return string
     */
    public static function from_sql_validation(array $validation) {
        $code = isset($validation['code']) ? (string) $validation['code'] : '';

        switch ($code) {
            case 'empty_sql':
                return "Sorry, I couldn't generate a valid query for that question. Please try rephrasing it.";
            case 'multiple_statements':
                return "Sorry, I can only run one read-only query at a time.";
            case 'not_read_only':
                return "Sorry, I can only run read-only queries (SELECT) to view and analyze your data.";
            case 'delete_not_allowed':
                return "Sorry, I can't delete data in the database.";
            case 'update_not_allowed':
                return "Sorry, I can't update or change data in the database.";
            case 'insert_not_allowed':
                return "Sorry, I can't insert or add data in the database.";
            case 'drop_not_allowed':
                return "Sorry, I can't drop or remove database tables or structures.";
            case 'write_not_allowed':
                return "Sorry, I can't change data in the database. I can only help you view and analyze existing data.";
            case 'forbidden_pattern':
                return "Sorry, that type of database operation isn't allowed for security reasons.";
            default:
                return "Sorry, I can only run read-only queries to view and analyze your data, not change it.";
        }
    }

    public static function sensitive_data_access() {
        return "Sorry, you don't have access to that specific data. Passwords, API keys, payment details, and other sensitive information cannot be shown in chat.";
    }

    public static function sensitive_table() {
        return "Sorry, you don't have access to that specific data. That information is restricted for privacy and security.";
    }

    public static function from_database_error($raw_error) {
        $msg = strtolower((string) $raw_error);

        if (strpos($msg, "doesn't exist") !== false || strpos($msg, 'base table or view not found') !== false
            || (strpos($msg, 'table') !== false && strpos($msg, 'not found') !== false)) {
            return "Sorry, I couldn't find the data needed to answer that question. Try asking about orders, products, or customers.";
        }

        if (strpos($msg, 'unknown column') !== false || strpos($msg, 'column not found') !== false) {
            return "Sorry, I couldn't retrieve that specific data — the requested field isn't available in your database setup.";
        }

        if (strpos($msg, 'syntax error') !== false || strpos($msg, 'sql syntax') !== false) {
            return "Sorry, I had trouble matching that question to your database structure. Please try rephrasing it.";
        }

        if (strpos($msg, 'connection') !== false || strpos($msg, 'timeout') !== false || strpos($msg, 'gone away') !== false) {
            return 'Sorry, the database connection timed out. Please try again in a moment.';
        }

        if (strpos($msg, 'access denied') !== false || strpos($msg, 'permission denied') !== false) {
            return "Sorry, you don't have access to that specific data.";
        }

        return "Sorry, I couldn't complete that database query. Please try rephrasing your question.";
    }

    /**
     * Build a helpful message when a read-only query succeeds but returns no rows.
     *
     * @param string               $question User's natural-language question.
     * @param string|null          $sql      Executed SQL (optional).
     * @param array<string, mixed> $hints    Diagnostics from heytrisha_gather_empty_result_hints().
     * @return string
     */
    public static function empty_query_results($question, $sql = null, array $hints = array()) {
        $question = trim((string) $question);
        $sql      = is_string($sql) ? trim($sql) : '';
        $q_lower  = strtolower($question);
        $q_lower  = preg_replace('/\borderes?\b/i', 'orders', $q_lower);

        $entity       = self::detect_entity_from_context($q_lower, $sql);
        $entity_label = $entity['label'];
        $has_where    = !empty($hints['has_where']) || ($sql !== '' && preg_match('/\bWHERE\b/i', $sql));
        $has_date     = !empty($hints['has_date_filter']) || ($sql !== '' && preg_match('/\b(date|created|modified|_date|between|curdate|now\s*\()\b/i', $sql));
        $has_status   = !empty($hints['has_status_filter']) || ($sql !== '' && preg_match('/\bstatus\b/i', $sql));
        $has_like     = !empty($hints['has_like_filter']) || ($sql !== '' && preg_match('/\bLIKE\b/i', $sql));
        $row_count    = isset($hints['table_row_count']) ? (int) $hints['table_row_count'] : null;
        $product_count = isset($hints['product_count']) ? (int) $hints['product_count'] : null;

        if ($entity['type'] === 'products' && $product_count !== null && $product_count > 0) {
            return 'I could not find products matching those exact filters, but your store has ' . $product_count . ' product(s) in WooCommerce. Try a broader keyword (for example part of the product name) or ask to list all products.';
        }

        if ($row_count === 0 && $entity['type'] !== 'products') {
            return sprintf(
                'There are no %s in your store database yet, so I cannot show results for that question.',
                $entity_label
            );
        }

        if ($row_count === 0 && $entity['type'] === 'products') {
            return 'I could not find any WooCommerce products in your database. Check that products are saved under Products in WooCommerce and are not in the trash.';
        }

        if ($row_count !== null && $row_count > 0 && $has_where) {
            $reasons = array();
            if ($has_date) {
                $reasons[] = 'the date or time range you asked about';
            }
            if ($has_status) {
                $reasons[] = 'the order or item status you specified';
            }
            if ($has_like) {
                $reasons[] = 'the name or search term you used';
            }
            if (empty($reasons)) {
                $reasons[] = 'the filters in your question';
            }

            return sprintf(
                'Your store has %d %s in total, but none match %s. Try broadening the date range, using a different status, or rephrasing your question.',
                $row_count,
                $entity_label,
                self::join_reasons($reasons)
            );
        }

        if ($entity['type'] === 'orders') {
            return 'I could not find any orders matching your question. This may mean there are no orders yet, or the date range or status filters are too narrow. Try asking about a specific period (for example, "orders this month") or a particular status.';
        }

        if ($entity['type'] === 'products') {
            return 'I could not find any products matching your question. Your store may have no published products yet, or the category, name, or stock filters may not match anything. Try a broader search or ask to list all products.';
        }

        if ($entity['type'] === 'customers') {
            return 'I could not find any customers matching your question. There may be no customer records yet, or the filters may be too specific. Try asking for all customers or a broader date range.';
        }

        if ($has_date) {
            return 'I ran a search but found no records for the date or time period you asked about. Try a wider date range or check whether data exists for that period.';
        }

        if ($has_where || $has_like) {
            return 'I ran a search but found no records matching the filters in your question. The data may not exist yet, or the criteria may be too narrow — try rephrasing with fewer specific conditions.';
        }

        if ($question !== '') {
            return sprintf(
                'I searched your store data for "%s" but found no matching records. The information may not exist yet, or the question may need to be rephrased with a clearer date range, status, or product name.',
                self::truncate_for_message($question, 120)
            );
        }

        return 'I ran a database search but found no matching records. The data may not exist yet, or your question may need a broader date range or fewer filters.';
    }

    /**
     * @param string $q_lower Lowercased user question.
     * @param string $sql     SQL query.
     * @return array{type: string, label: string}
     */
    private static function detect_entity_from_context($q_lower, $sql) {
        $sql_lower = strtolower((string) $sql);

        if (preg_match('/\b(order|orders|wc_orders|shop_order|wc_order_stats)\b/', $q_lower . ' ' . $sql_lower)) {
            return array('type' => 'orders', 'label' => 'orders');
        }
        if (preg_match('/\b(product|products|woocommerce_product|product_cat)\b/', $q_lower . ' ' . $sql_lower)) {
            return array('type' => 'products', 'label' => 'products');
        }
        if (preg_match('/\b(customer|customers|buyer|buyers|user_email|billing_email)\b/', $q_lower . ' ' . $sql_lower)) {
            return array('type' => 'customers', 'label' => 'customers');
        }

        return array('type' => 'records', 'label' => 'records');
    }

    /**
     * @param list<string> $reasons
     */
    private static function join_reasons(array $reasons) {
        $reasons = array_values(array_filter($reasons));
        $count   = count($reasons);

        if ($count === 0) {
            return 'the filters in your question';
        }
        if ($count === 1) {
            return $reasons[0];
        }
        if ($count === 2) {
            return $reasons[0] . ' and ' . $reasons[1];
        }

        $last = array_pop($reasons);
        return implode(', ', $reasons) . ', and ' . $last;
    }

    /**
     * @param string $text
     * @param int    $max
     * @return string
     */
    private static function truncate_for_message($text, $max) {
        if (strlen($text) <= $max) {
            return $text;
        }
        return substr($text, 0, $max - 1) . '…';
    }

    /**
     * Extract a user message from WordPress AJAX / API JSON payloads.
     *
     * @param mixed $data Decoded JSON response.
     * @return string|null
     */
    public static function extract_response_message($data) {
        if (!is_array($data)) {
            return null;
        }
        if (!empty($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }
        if (!empty($data['data']['message']) && is_string($data['data']['message'])) {
            return $data['data']['message'];
        }
        if (!empty($data['details']) && is_string($data['details'])) {
            return $data['details'];
        }
        return null;
    }

    /**
     * @param string $q Lowercased query.
     * @return bool
     */
    private static function mentions_data_target($q) {
        return (bool) preg_match(
            '/\b(data|database|db|table|record|row|entry|entries|customer|order|product|user|sql)\b/i',
            $q
        );
    }
}
