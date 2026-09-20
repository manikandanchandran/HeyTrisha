<?php

namespace App\Support;

/**
 * User-facing chat error messages — specific, safe, and consistent.
 */
class ChatErrorMessages
{
    /**
     * Detect disallowed write/delete intent from natural-language query before SQL generation.
     */
    public static function blockedIntentFromQuery(string $query): ?string
    {
        $q = strtolower(trim($query));

        if (preg_match('/\b(delete|remove|erase|purge|wipe|drop|truncate|destroy)\b/i', $q)) {
            if (self::mentionsDataTarget($q) || preg_match('/\b(delete|remove|drop|truncate)\s+(all|the|my|this|these|those|every)\b/i', $q)) {
                return "Sorry, I can't delete data in the database. I can only help you view and analyze existing data.";
            }
        }

        if (preg_match('/\b(insert|update|modify|change|alter|create|replace)\b/i', $q) && self::mentionsDataTarget($q)) {
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
     * Map SqlReadOnlyValidator result to a user-facing message.
     *
     * @param array{valid: bool, error?: string|null, code?: string|null} $validation
     */
    public static function fromSqlValidation(array $validation): string
    {
        $code = (string) ($validation['code'] ?? '');

        return match ($code) {
            'empty_sql'            => "Sorry, I couldn't generate a valid query for that question. Please try rephrasing it.",
            'multiple_statements'  => "Sorry, I can only run one read-only query at a time.",
            'not_read_only'        => "Sorry, I can only run read-only queries (SELECT) to view and analyze your data.",
            'delete_not_allowed'   => "Sorry, I can't delete data in the database.",
            'update_not_allowed'   => "Sorry, I can't update or change data in the database.",
            'insert_not_allowed'   => "Sorry, I can't insert or add data in the database.",
            'drop_not_allowed'     => "Sorry, I can't drop or remove database tables or structures.",
            'write_not_allowed'    => "Sorry, I can't change data in the database. I can only help you view and analyze existing data.",
            'forbidden_pattern'    => "Sorry, that type of database operation isn't allowed for security reasons.",
            default                => "Sorry, I can only run read-only queries to view and analyze your data, not change it.",
        };
    }

    public static function sensitiveDataAccess(): string
    {
        return "Sorry, you don't have access to that specific data. Passwords, API keys, payment details, and other sensitive information cannot be shown in chat.";
    }

    public static function sensitiveTable(): string
    {
        return "Sorry, you don't have access to that specific data. That information is restricted for privacy and security.";
    }

    public static function schemaRestriction(): string
    {
        return "Sorry, you don't have access to that specific data. I can only answer using the tables defined in your uploaded schema.";
    }

    public static function openAiNotConfigured(): string
    {
        return 'Sorry, the OpenAI API key is not configured. Please add it in HeyTrisha plugin settings and save.';
    }

    public static function databaseConnection(): string
    {
        return 'Sorry, I could not connect to the database. Please check your database settings and try again.';
    }

    public static function connectionTimeout(): string
    {
        return 'Sorry, the database connection timed out. Please try again in a moment.';
    }

    public static function openAiConnection(): string
    {
        return 'Sorry, I could not reach the AI service. Please check your internet connection and OpenAI API key.';
    }

    public static function openAiRateLimit(): string
    {
        return 'Sorry, the AI service is temporarily busy (rate limit). Please wait a moment and try again.';
    }

    public static function sqlGenerationFailed(): string
    {
        return "Sorry, I couldn't turn that question into a database query. Please try rephrasing it.";
    }

    public static function fromDatabaseError(string $rawError): string
    {
        $msg = strtolower($rawError);

        if (str_contains($msg, "doesn't exist") || str_contains($msg, 'base table or view not found')
            || (str_contains($msg, 'table') && str_contains($msg, 'not found'))) {
            return "Sorry, I couldn't find the data needed to answer that question. Try asking about orders, products, or customers.";
        }

        if (str_contains($msg, 'unknown column') || str_contains($msg, 'column not found')) {
            return "Sorry, I couldn't retrieve that specific data — the requested field isn't available in your database setup.";
        }

        if (str_contains($msg, 'syntax error') || str_contains($msg, 'sql syntax')) {
            return "Sorry, I had trouble matching that question to your database structure. Please try rephrasing it.";
        }

        if (str_contains($msg, 'connection') || str_contains($msg, 'timeout') || str_contains($msg, 'gone away')) {
            return self::connectionTimeout();
        }

        if (str_contains($msg, 'access denied') || str_contains($msg, 'permission denied')) {
            return "Sorry, you don't have access to that specific data.";
        }

        return "Sorry, I couldn't complete that database query. Please try rephrasing your question.";
    }

    public static function fromOpenAiError(string $rawError): string
    {
        $msg = strtolower($rawError);

        if (str_contains($msg, 'rate_limit') || str_contains($msg, 'tpm') || str_contains($msg, 'too many requests')) {
            return self::openAiRateLimit();
        }

        if (str_contains($msg, 'invalid api key') || str_contains($msg, 'incorrect api key') || str_contains($msg, 'authentication')) {
            return 'Sorry, the OpenAI API key appears to be invalid. Please check it in HeyTrisha plugin settings.';
        }

        if (str_contains($msg, 'connection') || str_contains($msg, 'timeout') || str_contains($msg, 'curl')) {
            return self::openAiConnection();
        }

        return self::sqlGenerationFailed();
    }

    /**
     * Build a helpful message when a read-only query succeeds but returns no rows.
     *
     * @param  array<string, mixed> $hints  Optional diagnostics (table_row_count, has_where, etc.)
     */
    public static function emptyQueryResults(string $question, ?string $sql = null, array $hints = []): string
    {
        $question = trim($question);
        $sql      = $sql !== null ? trim($sql) : '';
        $qLower   = strtolower($question);
        $qLower   = preg_replace('/\borderes?\b/i', 'orders', $qLower);

        $entity      = self::detectEntityFromContext($qLower, $sql);
        $entityLabel = $entity['label'];
        $hasWhere    = !empty($hints['has_where']) || ($sql !== '' && preg_match('/\bWHERE\b/i', $sql));
        $hasDate     = !empty($hints['has_date_filter']) || ($sql !== '' && preg_match('/\b(date|created|modified|_date|between|curdate|now\s*\()\b/i', $sql));
        $hasStatus   = !empty($hints['has_status_filter']) || ($sql !== '' && preg_match('/\bstatus\b/i', $sql));
        $hasLike     = !empty($hints['has_like_filter']) || ($sql !== '' && preg_match('/\bLIKE\b/i', $sql));
        $rowCount      = isset($hints['table_row_count']) ? (int) $hints['table_row_count'] : null;
        $productCount  = isset($hints['product_count']) ? (int) $hints['product_count'] : null;

        if ($entity['type'] === 'products' && $productCount !== null && $productCount > 0) {
            return 'I could not find products matching those exact filters, but your store has '
                . $productCount
                . ' product(s) in WooCommerce. Try a broader keyword or ask to list all products.';
        }

        if ($rowCount === 0 && $entity['type'] !== 'products') {
            return sprintf(
                'There are no %s in your store database yet, so I cannot show results for that question.',
                $entityLabel
            );
        }

        if ($rowCount === 0 && $entity['type'] === 'products') {
            return 'I could not find any WooCommerce products in your database. Check that products exist under Products in WooCommerce and are not in the trash.';
        }

        if ($rowCount !== null && $rowCount > 0 && $hasWhere) {
            $reasons = [];
            if ($hasDate) {
                $reasons[] = 'the date or time range you asked about';
            }
            if ($hasStatus) {
                $reasons[] = 'the order or item status you specified';
            }
            if ($hasLike) {
                $reasons[] = 'the name or search term you used';
            }
            if ($reasons === []) {
                $reasons[] = 'the filters in your question';
            }

            return sprintf(
                'Your store has %d %s in total, but none match %s. Try broadening the date range, using a different status, or rephrasing your question.',
                $rowCount,
                $entityLabel,
                self::joinReasons($reasons)
            );
        }

        if ($entity['type'] === 'orders') {
            return 'I could not find any orders matching your question. This may mean there are no orders yet, or the date range or status filters are too narrow. Try asking about a specific period (for example, "orders this month") or a particular status.';
        }

        if ($entity['type'] === 'products') {
            return 'I could not find any products matching your question. Try a broader keyword, ask to list all products, or check that products are not only saved as drafts.';
        }

        if ($entity['type'] === 'customers') {
            return 'I could not find any customers matching your question. There may be no customer records yet, or the filters may be too specific. Try asking for all customers or a broader date range.';
        }

        if ($hasDate) {
            return 'I ran a search but found no records for the date or time period you asked about. Try a wider date range or check whether data exists for that period.';
        }

        if ($hasWhere || $hasLike) {
            return 'I ran a search but found no records matching the filters in your question. The data may not exist yet, or the criteria may be too narrow — try rephrasing with fewer specific conditions.';
        }

        if ($question !== '') {
            $snippet = strlen($question) > 120 ? substr($question, 0, 119) . '…' : $question;

            return sprintf(
                'I searched your store data for "%s" but found no matching records. The information may not exist yet, or the question may need to be rephrased with a clearer date range, status, or product name.',
                $snippet
            );
        }

        return 'I ran a database search but found no matching records. The data may not exist yet, or your question may need a broader date range or fewer filters.';
    }

    public static function fromException(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Connection') || str_contains($msg, 'timeout')) {
            return self::connectionTimeout();
        }

        if (stripos($msg, 'OpenAI') !== false) {
            return self::fromOpenAiError($msg);
        }

        if (stripos($msg, 'WordPress') !== false) {
            return 'Sorry, there was a problem connecting to your WordPress site. Please check your API settings.';
        }

        return 'Sorry, something went wrong while processing your request. Please try again or rephrase your question.';
    }

    private static function mentionsDataTarget(string $q): bool
    {
        return (bool) preg_match(
            '/\b(data|database|db|table|record|row|entry|entries|customer|order|product|user|sql)\b/i',
            $q
        );
    }

    /**
     * @return array{type: string, label: string}
     */
    private static function detectEntityFromContext(string $qLower, string $sql): array
    {
        $sqlLower = strtolower($sql);

        if (preg_match('/\b(order|orders|wc_orders|shop_order|wc_order_stats)\b/', $qLower . ' ' . $sqlLower)) {
            return ['type' => 'orders', 'label' => 'orders'];
        }
        if (preg_match('/\b(product|products|woocommerce_product|product_cat)\b/', $qLower . ' ' . $sqlLower)) {
            return ['type' => 'products', 'label' => 'products'];
        }
        if (preg_match('/\b(customer|customers|buyer|buyers|user_email|billing_email)\b/', $qLower . ' ' . $sqlLower)) {
            return ['type' => 'customers', 'label' => 'customers'];
        }

        return ['type' => 'records', 'label' => 'records'];
    }

    /**
     * @param  list<string> $reasons
     */
    private static function joinReasons(array $reasons): string
    {
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
}
