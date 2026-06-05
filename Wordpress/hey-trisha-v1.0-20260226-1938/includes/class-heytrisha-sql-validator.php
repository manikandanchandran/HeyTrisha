<?php
/**
 * Read-only SQL validation for Hey Trisha analytics queries.
 *
 * @package HeyTrisha
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_SQL_Validator {

    /** @var string[] */
    private static $forbidden_keywords = array(
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
        'GRANT', 'REVOKE', 'MERGE', 'CALL', 'EXEC', 'EXECUTE',
        'LOCK', 'UNLOCK', 'HANDLER', 'PREPARE', 'DEALLOCATE',
        'RENAME', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'KILL',
    );

    /** @var string[] */
    private static $allowed_start_keywords = array(
        'SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN',
    );

    /**
     * Validate that SQL is read-only (no CRUD / write operations).
     *
     * @param string $sql SQL query.
     * @return array{valid: bool, error: string|null}
     */
    public static function validate($sql) {
        if (!is_string($sql) || trim($sql) === '') {
            return array('valid' => false, 'error' => 'SQL query is empty.', 'code' => 'empty_sql');
        }

        $clean = trim(self::strip_comments($sql));

        if (self::has_multiple_statements($clean)) {
            return array('valid' => false, 'error' => 'Multiple SQL statements are not allowed.', 'code' => 'multiple_statements');
        }

        if (!self::starts_with_read_only_keyword($clean)) {
            return array(
                'valid' => false,
                'error' => 'Only read-only SQL (SELECT, WITH, SHOW, DESCRIBE, EXPLAIN) is allowed.',
                'code'  => 'not_read_only',
            );
        }

        $write_code = self::detect_write_keyword_code($clean);
        if ($write_code !== null) {
            return array(
                'valid' => false,
                'error' => 'Write operations are not permitted.',
                'code'  => $write_code,
            );
        }

        if (self::contains_forbidden_patterns($clean)) {
            return array(
                'valid' => false,
                'error' => 'This SQL contains operations that are not allowed.',
                'code'  => 'forbidden_pattern',
            );
        }

        return array('valid' => true, 'error' => null, 'code' => null);
    }

    /**
     * @param string $sql SQL without comments.
     * @return string|null Error code for the first forbidden write keyword found.
     */
    private static function detect_write_keyword_code($sql) {
        $check = self::strip_quoted_strings($sql);
        $groups = array(
            'delete_not_allowed' => array('DELETE'),
            'drop_not_allowed'   => array('DROP', 'TRUNCATE'),
            'update_not_allowed' => array('UPDATE'),
            'insert_not_allowed' => array('INSERT', 'REPLACE'),
        );

        foreach ($groups as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . $keyword . '\b/i', $check)) {
                    return $code;
                }
            }
        }

        if (self::contains_other_forbidden_keywords($check)) {
            return 'write_not_allowed';
        }

        return null;
    }

    /**
     * @param string $sql SQL with quoted strings stripped.
     * @return bool
     */
    private static function contains_other_forbidden_keywords($sql) {
        $other = array_diff(self::$forbidden_keywords, array(
            'DELETE', 'UPDATE', 'INSERT', 'DROP', 'TRUNCATE', 'REPLACE',
        ));
        $pattern = '/\b(' . implode('|', $other) . ')\b/i';
        return (bool) preg_match($pattern, $sql);
    }

    /**
     * Replace generic wp_ prefix with the site's table prefix.
     *
     * @param string $sql SQL query.
     * @return string
     */
    public static function sanitize_table_names($sql) {
        global $wpdb;
        return str_replace('wp_', $wpdb->prefix, $sql);
    }

    /**
     * Ensure a LIMIT clause exists and cap excessive limits.
     *
     * @param string $sql   SQL query.
     * @param int    $max   Maximum row limit.
     * @return string
     */
    public static function ensure_limit($sql, $max = 200) {
        $max = max(1, (int) $max);

        if (!preg_match('/\bLIMIT\s+(\d+)/i', $sql, $matches)) {
            return rtrim(rtrim($sql), ';') . ' LIMIT ' . $max;
        }

        $current = (int) $matches[1];
        if ($current > $max) {
            return preg_replace('/\bLIMIT\s+\d+\b/i', 'LIMIT ' . $max, $sql, 1);
        }

        return $sql;
    }

    /**
     * @param string $sql SQL query.
     * @return string
     */
    private static function strip_comments($sql) {
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        return $sql;
    }

    /**
     * @param string $sql SQL query.
     * @return string
     */
    private static function strip_quoted_strings($sql) {
        $sql = preg_replace("/'(?:''|[^'])*'/", "''", $sql);
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', $sql);
        return $sql;
    }

    /**
     * @param string $sql SQL query.
     * @return bool
     */
    private static function has_multiple_statements($sql) {
        $check = rtrim(trim(self::strip_quoted_strings($sql)), ';');
        return strpos($check, ';') !== false;
    }

    /**
     * @param string $sql SQL query.
     * @return bool
     */
    private static function starts_with_read_only_keyword($sql) {
        if (!preg_match('/^(\w+)/', $sql, $matches)) {
            return false;
        }
        return in_array(strtoupper($matches[1]), self::$allowed_start_keywords, true);
    }

    /**
     * @param string $sql SQL query.
     * @return bool
     */
    private static function contains_forbidden_patterns($sql) {
        $patterns = array(
            '/\bREPLACE\s+INTO\b/i',
            '/\bINSERT\s+INTO\b/i',
            '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i',
            '/\bFOR\s+UPDATE\b/i',
            '/\bLOCK\s+TABLES\b/i',
            '/\bLOAD\s+DATA\b/i',
            '/\bLOAD\s+XML\b/i',
            '/\bSET\s+\w+\s*=/i',
        );
        $check = self::strip_quoted_strings($sql);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $check)) {
                return true;
            }
        }
        return false;
    }
}
