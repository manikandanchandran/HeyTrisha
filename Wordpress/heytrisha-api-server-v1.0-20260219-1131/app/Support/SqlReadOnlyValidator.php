<?php

namespace App\Support;

/**
 * Enforces read-only SQL — blocks CRUD and other write/administrative statements.
 */
class SqlReadOnlyValidator
{
    /** @var list<string> */
    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
        'GRANT', 'REVOKE', 'MERGE', 'CALL', 'EXEC', 'EXECUTE',
        'LOCK', 'UNLOCK', 'HANDLER', 'PREPARE', 'DEALLOCATE',
        'RENAME', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'KILL',
    ];

    /** @var list<string> */
    private const ALLOWED_START_KEYWORDS = [
        'SELECT', 'WITH', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN',
    ];

    /**
     * @return array{valid: bool, error: string|null, code: string|null}
     */
    public static function validate(string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return self::fail('SQL query is empty.', 'empty_sql');
        }

        $clean = trim(self::stripComments($sql));

        if (self::hasMultipleStatements($clean)) {
            return self::fail('Multiple SQL statements are not allowed.', 'multiple_statements');
        }

        if (!self::startsWithReadOnlyKeyword($clean)) {
            return self::fail('Only read-only SQL (SELECT, WITH, SHOW, DESCRIBE, EXPLAIN) is allowed.', 'not_read_only');
        }

        $writeCode = self::detectWriteKeywordCode($clean);
        if ($writeCode !== null) {
            return self::fail('Write operations are not permitted.', $writeCode);
        }

        if (self::containsForbiddenPatterns($clean)) {
            return self::fail('This SQL contains operations that are not allowed.', 'forbidden_pattern');
        }

        return ['valid' => true, 'error' => null, 'code' => null];
    }

    /**
     * @return array{valid: bool, error: string|null, code: string}
     */
    private static function fail(string $message, string $code): array
    {
        return ['valid' => false, 'error' => $message, 'code' => $code];
    }

    private static function detectWriteKeywordCode(string $sql): ?string
    {
        $check = self::stripQuotedStrings($sql);
        $groups = [
            'delete_not_allowed' => ['DELETE'],
            'drop_not_allowed'   => ['DROP', 'TRUNCATE'],
            'update_not_allowed' => ['UPDATE'],
            'insert_not_allowed' => ['INSERT', 'REPLACE'],
        ];

        foreach ($groups as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . $keyword . '\b/i', $check)) {
                    return $code;
                }
            }
        }

        if (self::containsOtherForbiddenKeywords($check)) {
            return 'write_not_allowed';
        }

        return null;
    }

    private static function containsOtherForbiddenKeywords(string $sql): bool
    {
        $other = array_diff(self::FORBIDDEN_KEYWORDS, [
            'DELETE', 'UPDATE', 'INSERT', 'DROP', 'TRUNCATE', 'REPLACE',
        ]);
        $pattern = '/\b(' . implode('|', $other) . ')\b/i';

        return (bool) preg_match($pattern, $sql);
    }

    private static function stripComments(string $sql): string
    {
        $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;

        return $sql;
    }

    private static function stripQuotedStrings(string $sql): string
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", "''", $sql) ?? $sql;
        $sql = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', $sql) ?? $sql;

        return $sql;
    }

    private static function hasMultipleStatements(string $sql): bool
    {
        $check = rtrim(trim(self::stripQuotedStrings($sql)), ';');

        return str_contains($check, ';');
    }

    private static function startsWithReadOnlyKeyword(string $sql): bool
    {
        if (!preg_match('/^(\w+)/', $sql, $matches)) {
            return false;
        }

        return in_array(strtoupper($matches[1]), self::ALLOWED_START_KEYWORDS, true);
    }

    private static function containsForbiddenPatterns(string $sql): bool
    {
        $check = self::stripQuotedStrings($sql);
        $patterns = [
            '/\bREPLACE\s+INTO\b/i',
            '/\bINSERT\s+INTO\b/i',
            '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i',
            '/\bFOR\s+UPDATE\b/i',
            '/\bLOCK\s+TABLES\b/i',
            '/\bLOAD\s+DATA\b/i',
            '/\bLOAD\s+XML\b/i',
            '/\bSET\s+\w+\s*=/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $check)) {
                return true;
            }
        }

        return false;
    }
}
