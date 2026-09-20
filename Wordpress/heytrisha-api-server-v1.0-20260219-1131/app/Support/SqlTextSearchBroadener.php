<?php

namespace App\Support;

/**
 * Broadens exact text equality filters in generated SQL to partial LIKE matching,
 * and builds fallback product/category search queries when needed.
 */
class SqlTextSearchBroadener
{
    /**
     * Prompt block for SQL generation — partial / keyword matching for products and categories.
     */
    public static function promptRulesBlock(): string
    {
        return "PRODUCT, CATEGORY & TEXT SEARCH RULES (CRITICAL — NEVER USE EXACT MATCH FOR NAMES):\n" .
            "- When the user mentions a product name, category, topic, or keyword (e.g. \"Homam for marriages\", \"marriage\", \"wedding homam\"):\n" .
            "  * NEVER use exact equality on titles or category names: post_title = 'marriage' is WRONG.\n" .
            "  * ALWAYS use case-insensitive partial matching: LOWER(post_title) LIKE '%marriage%' OR LOWER(post_title) LIKE '%homam%'.\n" .
            "  * Extract meaningful keywords from the user's question (ignore stop words: the, a, show, list, product, category).\n" .
            "  * Match ANY relevant keyword with OR: (LOWER(post_title) LIKE '%marriage%' OR LOWER(post_title) LIKE '%homam%').\n" .
            "  * Also search post_content and post_excerpt when filtering products by topic.\n" .
            "  * For category questions: JOIN terms + term_taxonomy + term_relationships and use LOWER(t.name) LIKE '%keyword%'.\n" .
            "  * \"marriage\" must match products titled \"Homam for marriages\" — use LIKE '%marriage%' not = 'marriage'.\n" .
            "  * Prefer returning the closest matches (ORDER BY title) with LIMIT 25 instead of zero rows.\n" .
            "- Status and order slug filters still use: LOWER(status) = LOWER('processing') OR status LIKE '%processing%'.\n" .
            "- Numeric IDs and dates may still use = ; only human-readable names/descriptions use LIKE.\n\n" .
            "PRODUCT CATALOG RULES (CRITICAL):\n" .
            "- WooCommerce products are stored in the posts table with post_type='product'. Use that table to list or search products.\n" .
            "- NEVER use wc_product_meta_lookup alone to answer \"what products exist\" — it can be empty while products still exist in posts.\n" .
            "- For product listings: SELECT from posts WHERE post_type='product' AND post_status NOT IN ('trash','auto-draft').\n" .
            "- Include draft and pending products unless the user explicitly asks for published only.\n\n";
    }

    /** Status filter for WooCommerce products in fallback queries. */
    private const PRODUCT_STATUS_WHERE = "p.post_type = 'product' AND p.post_status NOT IN ('trash','auto-draft')";

    /** @var list<string> */
    private const TEXT_COLUMNS = [
        'post_title',
        'post_name',
        'post_content',
        'post_excerpt',
        'name',
        'term_name',
        'slug',
        'description',
        'product_name',
        'sku',
        'attribute_name',
        'meta_value',
    ];

    /** @var list<string> */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
        'can', 'you', 'give', 'show', 'list', 'get', 'how', 'many', 'what', 'which', 'are', 'is',
        'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'me', 'my', 'our', 'your', 'any', 'all', 'about', 'tell', 'find',
        'product', 'products', 'item', 'items', 'category', 'categories', 'woocommerce', 'wordpress',
        'store', 'shop', 'available', 'there', 'this', 'that', 'from', 'have', 'has',
    ];

    /**
     * Post-process SQL: replace exact text matches with case-insensitive partial LIKE filters.
     */
    public static function broaden(string $sql, string $userQuery = ''): string
    {
        $sql = trim($sql);
        if ($sql === '' || !preg_match('/\bSELECT\b/i', $sql)) {
            return $sql;
        }

        $keywords = self::extractSearchKeywords($userQuery);

        foreach (self::TEXT_COLUMNS as $column) {
            $colPattern = preg_quote($column, '/');

            // LOWER(alias.column) = LOWER('value') or LOWER(column) = LOWER('value')
            $sql = preg_replace_callback(
                '/\bLOWER\s*\(\s*(?:`?[\w]+`?\.)?`?' . $colPattern . '`?\s*\)\s*=\s*LOWER\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
                static function (array $m) use ($keywords) {
                    return self::likeClauseForColumn($m[0], $m[1], $keywords, true);
                },
                $sql
            );

            // column = 'value' (avoid numeric-looking values)
            $sql = preg_replace_callback(
                '/\b(?:`?[\w]+`?\.)?`?' . $colPattern . '`?\s*=\s*[\'"]([^\'"]+)[\'"]/i',
                static function (array $m) use ($keywords) {
                    $value = $m[1];
                    if (preg_match('/^\d+$/', $value)) {
                        return $m[0];
                    }

                    return self::likeClauseForColumn($m[0], $value, $keywords, false);
                },
                $sql
            );
        }

        return $sql;
    }

    /**
     * Rewrite status filters so similar-meaning words still match the stored
     * slugs (e.g. "payment pending" → LOWER(status) LIKE '%pending%' which
     * matches 'wc-pending', 'pending-payment', etc.).
     */
    public static function broadenStatusFilters(string $sql, string $userQuery = ''): string
    {
        $sql = trim($sql);
        if ($sql === '' || !preg_match('/\bSELECT\b/i', $sql)) {
            return $sql;
        }

        // Any column whose name ends in "status" (status, order_status, post_status, ...),
        // optionally qualified by a table alias and/or backtick-quoted.
        $col = '(?:`?\w+`?\.)?`?\w*status`?';

        // Matches:  [LOWER(]col[)] (= | LIKE) <value>
        // where <value> is either LOWER('x') or a bare 'x'. The LOWER() closing
        // paren is only consumed inside the LOWER alternative, so we never swallow
        // a parenthesis that belongs to an enclosing expression.
        $pattern = '/(?<![A-Za-z0-9_])(LOWER\s*\(\s*' . $col . '\s*\)|' . $col . ')\s*(=|LIKE)\s*'
            . '(?:LOWER\s*\(\s*([\'"])([^\'"]*)\3\s*\)|([\'"])([^\'"]*)\5)/i';

        $result = preg_replace_callback(
            $pattern,
            static function (array $m) use ($userQuery) {
                $left     = $m[1];
                $rawValue = (isset($m[4]) && $m[4] !== '') ? $m[4] : ($m[6] ?? '');
                $value    = trim($rawValue, " \t\n\r\0\x0B%");
                if ($value === '') {
                    return $m[0];
                }

                $stems = \App\Support\SearchSynonyms::expandStatus($value, $userQuery);
                if ($stems === []) {
                    return $m[0];
                }

                // Resolve the bare column expression (strip any outer LOWER(...) wrapper).
                if (preg_match('/LOWER\s*\(\s*(.+?)\s*\)/i', $left, $cm)) {
                    $colExpr = $cm[1];
                } else {
                    $colExpr = trim($left);
                }

                $parts = [];
                foreach (array_slice($stems, 0, 8) as $stem) {
                    $escaped = self::escapeLikeValue($stem);
                    $parts[] = "LOWER({$colExpr}) LIKE '%{$escaped}%'";
                }

                return '(' . implode(' OR ', $parts) . ')';
            },
            $sql
        );

        return is_string($result) ? $result : $sql;
    }

    /**
     * Build a broad product + category search when the primary query returns no rows.
     *
     * @param  array<string, array<int, string>> $schema
     */
    public static function buildFuzzyProductSearchSql(string $userQuery, array $schema, string $tablePrefix = 'wp_'): ?string
    {
        $postsTable = self::findTable($schema, 'posts');
        if ($postsTable === null) {
            return null;
        }

        $keywords = self::extractSearchKeywords($userQuery);
        if ($keywords === []) {
            return self::buildListProductsSql($schema);
        }
        $termsTable         = self::findTable($schema, 'terms');
        $termTaxonomyTable  = self::findTable($schema, 'term_taxonomy');
        $termRelTable       = self::findTable($schema, 'term_relationships');

        $postsEsc = self::quoteIdentifier($postsTable);
        $conditions = [];

        foreach ($keywords as $kw) {
            $escaped = self::escapeLikeValue($kw);
            $titleCond = "LOWER(p.post_title) LIKE '%{$escaped}%'";
            $contentCond = self::columnExistsInSchema($schema, $postsTable, 'post_content')
                ? " OR LOWER(p.post_content) LIKE '%{$escaped}%'"
                : '';
            $excerptCond = self::columnExistsInSchema($schema, $postsTable, 'post_excerpt')
                ? " OR LOWER(p.post_excerpt) LIKE '%{$escaped}%'"
                : '';

            $termCond = '';
            if ($termsTable !== null && $termTaxonomyTable !== null && $termRelTable !== null) {
                $termCond = " OR LOWER(t.name) LIKE '%{$escaped}%' OR LOWER(t.slug) LIKE '%{$escaped}%'";
            }

            $conditions[] = '(' . $titleCond . $contentCond . $excerptCond . $termCond . ')';
        }

        $whereKeywords = implode(' OR ', $conditions);

        $joins = '';
        if ($termsTable !== null && $termTaxonomyTable !== null && $termRelTable !== null) {
            $ttEsc = self::quoteIdentifier($termTaxonomyTable);
            $trEsc = self::quoteIdentifier($termRelTable);
            $tEsc  = self::quoteIdentifier($termsTable);
            $joins = " LEFT JOIN {$trEsc} tr ON p.ID = tr.object_id"
                . " LEFT JOIN {$ttEsc} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id"
                . " LEFT JOIN {$tEsc} t ON tt.term_id = t.term_id";
        }

        $relevanceParts = [];
        foreach (array_slice($keywords, 0, 3) as $kw) {
            $escaped = self::escapeLikeValue($kw);
            $relevanceParts[] = "CASE WHEN LOWER(p.post_title) LIKE '%{$escaped}%' THEN 0 ELSE 1 END";
        }
        $orderBy = $relevanceParts !== []
            ? ' ORDER BY ' . implode(', ', $relevanceParts) . ', p.post_title ASC'
            : ' ORDER BY p.post_title ASC';

        return 'SELECT DISTINCT p.ID, p.post_title, p.post_excerpt, p.post_status'
            . " FROM {$postsEsc} p{$joins}"
            . ' WHERE ' . self::PRODUCT_STATUS_WHERE
            . " AND ({$whereKeywords})"
            . $orderBy
            . ' LIMIT 25';
    }

    /**
     * Build a correct "products by stock status" query (WooCommerce stores stock
     * status in wc_product_meta_lookup.stock_status or postmeta._stock_status —
     * NEVER on the posts table). Returns null when there is no stock intent or no
     * usable stock table in the schema.
     *
     * @param  array<string, array<int, string>> $schema
     */
    public static function buildStockProductSearchSql(array $schema, string $userQuery): ?string
    {
        $stems = \App\Support\SearchSynonyms::stockStemsFromQuery($userQuery);
        if ($stems === []) {
            return null;
        }

        $postsTable = self::findTable($schema, 'posts');
        if ($postsTable === null) {
            return null;
        }
        $postsEsc = self::quoteIdentifier($postsTable);

        $stockConds = [];

        // Preferred: wc_product_meta_lookup.stock_status
        $lookupTable = self::findTable($schema, 'wc_product_meta_lookup');
        if ($lookupTable !== null && self::columnExistsInSchema($schema, $lookupTable, 'stock_status')) {
            $lookupEsc = self::quoteIdentifier($lookupTable);
            foreach ($stems as $stem) {
                $escaped = self::escapeLikeValue($stem);
                $stockConds[] = "LOWER(l.stock_status) LIKE '%{$escaped}%'";
            }

            $hasQty = self::columnExistsInSchema($schema, $lookupTable, 'stock_quantity');
            $qtyCol = $hasQty ? ', l.stock_quantity' : '';

            return 'SELECT p.ID, p.post_title, p.post_status, l.stock_status' . $qtyCol
                . " FROM {$postsEsc} p"
                . " JOIN {$lookupEsc} l ON l.product_id = p.ID"
                . ' WHERE ' . self::PRODUCT_STATUS_WHERE
                . ' AND (' . implode(' OR ', $stockConds) . ')'
                . ' ORDER BY p.post_title ASC LIMIT 100';
        }

        // Fallback: postmeta with meta_key = '_stock_status'
        $postmetaTable = self::findTable($schema, 'postmeta');
        if ($postmetaTable !== null) {
            $pmEsc = self::quoteIdentifier($postmetaTable);
            foreach ($stems as $stem) {
                $escaped = self::escapeLikeValue($stem);
                $stockConds[] = "LOWER(pm.meta_value) LIKE '%{$escaped}%'";
            }

            return 'SELECT p.ID, p.post_title, p.post_status, pm.meta_value AS stock_status'
                . " FROM {$postsEsc} p"
                . " JOIN {$pmEsc} pm ON pm.post_id = p.ID AND pm.meta_key = '_stock_status'"
                . ' WHERE ' . self::PRODUCT_STATUS_WHERE
                . ' AND (' . implode(' OR ', $stockConds) . ')'
                . ' ORDER BY p.post_title ASC LIMIT 100';
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>> $schema
     */
    public static function buildListProductsSql(array $schema): ?string
    {
        $postsTable = self::findTable($schema, 'posts');
        if ($postsTable === null) {
            return null;
        }

        $postsEsc = self::quoteIdentifier($postsTable);

        return 'SELECT p.ID, p.post_title, p.post_excerpt, p.post_status'
            . " FROM {$postsEsc} p"
            . ' WHERE ' . self::PRODUCT_STATUS_WHERE
            . ' ORDER BY p.post_title ASC LIMIT 50';
    }

    /**
     * @return list<string>
     */
    public static function extractSearchKeywords(string $query): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return [];
        }

        $words = preg_split('/\s+/', $query);
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()[]{}\"\'');
            if (strlen($word) < 3) {
                continue;
            }
            if (in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            if (preg_match('/^\d+$/', $word)) {
                continue;
            }
            $keywords[] = $word;
            // Simple stemming: "marriages" → also try "marriage"
            if (strlen($word) > 4 && preg_match('/(ies|es|s)$/', $word)) {
                $stem = preg_replace('/(ies)$/', 'y', $word);
                $stem = preg_replace('/(es|s)$/', '', $stem);
                if (strlen($stem) >= 3 && $stem !== $word) {
                    $keywords[] = $stem;
                }
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * @param  array<string, array<int, string>> $schema
     */
    private static function findTable(array $schema, string $suffix): ?string
    {
        foreach (array_keys($schema) as $table) {
            if (preg_match('/_' . preg_quote($suffix, '/') . '$/i', $table)
                || strcasecmp($table, $suffix) === 0) {
                return $table;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>> $schema
     */
    private static function columnExistsInSchema(array $schema, string $table, string $column): bool
    {
        if (!isset($schema[$table]) || !is_array($schema[$table])) {
            return false;
        }
        foreach ($schema[$table] as $col) {
            if (strcasecmp((string) $col, $column) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function quoteIdentifier(string $table): string
    {
        return '`' . str_replace('`', '``', $table) . '`';
    }

    private static function escapeLikeValue(string $value): string
    {
        return addcslashes(strtolower($value), "\\%_'");
    }

    /**
     * @param  list<string> $keywords
     */
    private static function likeClauseForColumn(string $original, string $value, array $keywords, bool $wasLower): string
    {
        $terms = $keywords !== [] ? $keywords : self::extractSearchKeywords($value);
        if ($terms === []) {
            $terms = [strtolower($value)];
        }

        // Extract column expression from original match
        if (preg_match('/\b((?:`?[\w]+`?\.)?`?[\w]+`?)\s*=/i', $original, $colMatch)) {
            $colExpr = $colMatch[1];
        } else {
            return $original;
        }

        $colLower = $wasLower ? "LOWER({$colExpr})" : $colExpr;
        $parts = [];
        foreach (array_slice(array_unique($terms), 0, 5) as $term) {
            $escaped = self::escapeLikeValue($term);
            $parts[] = "{$colLower} LIKE '%{$escaped}%'";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}
