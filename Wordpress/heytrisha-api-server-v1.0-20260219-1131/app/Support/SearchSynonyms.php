<?php

namespace App\Support;

/**
 * Maps similar-meaning words/phrases to the partial LIKE "stems" that match the
 * real values stored in the database, so the chatbot can answer even when the
 * user's wording differs from the stored slug.
 *
 * Example: "payment pending", "pending payment", "awaiting payment" all map to
 * the stem "pending", which LIKE-matches stored slugs such as 'wc-pending',
 * 'pending-payment', or 'payment-pending'. 
 */
class SearchSynonyms
{
    /**
     * WooCommerce / WordPress status synonym groups.
     *
     * "phrases" are compared (separator-insensitive) against the value the model
     * used and the user's question. "stems" are short LIKE substrings that match
     * the actual stored slugs regardless of prefixes/separators.
     *
     * @var list<array{phrases: list<string>, stems: list<string>}>
     */
    private const STATUS_GROUPS = [
        [
            'phrases' => ['pending', 'pending payment', 'payment pending', 'awaiting payment', 'unpaid', 'payment due', 'not paid'],
            'stems'   => ['pending'],
        ],
        [
            'phrases' => ['processing', 'process', 'in progress', 'being processed', 'under processing'],
            'stems'   => ['processing', 'process'],
        ],
        [
            'phrases' => ['on hold', 'on-hold', 'onhold', 'hold', 'held', 'awaiting', 'suspended'],
            'stems'   => ['on-hold', 'hold'],
        ],
        [
            'phrases' => ['completed', 'complete', 'done', 'fulfilled', 'finished', 'delivered', 'shipped'],
            'stems'   => ['complet'],
        ],
        [
            'phrases' => ['cancelled', 'canceled', 'cancel', 'void', 'voided'],
            'stems'   => ['cancel'],
        ],
        [
            'phrases' => ['refunded', 'refund', 'returned', 'money back'],
            'stems'   => ['refund'],
        ],
        [
            'phrases' => ['failed', 'fail', 'failure', 'declined', 'decline', 'unsuccessful'],
            'stems'   => ['fail'],
        ],
        [
            'phrases' => ['draft', 'checkout draft', 'auto draft'],
            'stems'   => ['draft'],
        ],
        [
            'phrases' => ['trash', 'trashed', 'deleted'],
            'stems'   => ['trash'],
        ],
        // Stock / inventory status (stored as 'instock' / 'outofstock' / 'onbackorder').
        [
            'phrases' => ['in stock', 'instock', 'in-stock', 'available', 'in inventory'],
            'stems'   => ['instock'],
        ],
        [
            'phrases' => ['out of stock', 'outofstock', 'out-of-stock', 'sold out', 'soldout', 'unavailable', 'no stock'],
            'stems'   => ['outofstock'],
        ],
        [
            'phrases' => ['on backorder', 'onbackorder', 'backorder', 'back order'],
            'stems'   => ['backorder'],
        ],
    ];

    /**
     * Resolve the LIKE stems to match for a status value, considering both the
     * value itself and the original user question.
     *
     * @return list<string>
     */
    public static function expandStatus(string $value, string $userQuery = ''): array
    {
        $value = trim($value);

        // Numeric statuses are exact codes — never broaden them.
        if ($value !== '' && preg_match('/^\d+$/', $value)) {
            return [];
        }

        $normValue = self::normalize($value);
        $normQuery = self::normalize($userQuery);
        $stems = [];

        foreach (self::STATUS_GROUPS as $group) {
            $matched = false;

            foreach ($group['phrases'] as $phrase) {
                $normPhrase = self::normalize($phrase);
                if ($normPhrase === '') {
                    continue;
                }
                if ($normValue !== '' && str_contains($normValue, $normPhrase)) {
                    $matched = true;
                    break;
                }
                if ($normQuery !== '' && str_contains($normQuery, $normPhrase)) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                foreach ($group['stems'] as $stem) {
                    $stems[] = $stem;
                }
            }
        }

        // Fallback: keep meaningful words from the value so we still match
        // something instead of failing on an unknown status.
        if ($stems === []) {
            foreach (preg_split('/[^a-z0-9]+/', strtolower($value)) as $word) {
                if (is_string($word) && strlen($word) >= 3) {
                    $stems[] = $word;
                }
            }
            if ($stems === [] && $value !== '') {
                $stems[] = strtolower($value);
            }
        }

        return array_values(array_unique($stems));
    }

    /**
     * Detect stock/inventory intent in a user question and return the matching
     * stock_status LIKE stems ('instock' / 'outofstock' / 'backorder').
     * Returns an empty array when the question is not about stock.
     *
     * @return list<string>
     */
    public static function stockStemsFromQuery(string $query): array
    {
        $n = self::normalize($query); // spaces removed: "in stock" -> "instock"
        if ($n === '') {
            return [];
        }

        // Check the more specific phrases first.
        if (str_contains($n, 'outofstock') || str_contains($n, 'soldout')
            || str_contains($n, 'unavailable') || str_contains($n, 'nostock')) {
            return ['outofstock'];
        }
        if (str_contains($n, 'backorder')) {
            return ['backorder'];
        }
        if (str_contains($n, 'instock') || str_contains($n, 'inventory')) {
            return ['instock'];
        }

        return [];
    }

    /**
     * Prompt guidance so the model treats similar-meaning words as the same
     * filter and never refuses just because the wording differs.
     */
    public static function promptBlock(): string
    {
        return "SIMILAR-MEANING & SYNONYM MATCHING (CRITICAL — NEVER FAIL BECAUSE OF WORDING):\n" .
            "- Users describe the same thing with different words. Treat synonyms / related phrases as the SAME filter and match them with case-insensitive partial LIKE (with OR) — never return an error or zero rows just because the wording differs from the stored value.\n" .
            "- ORDER / POST STATUS slugs are usually stored as 'wc-<status>' (e.g. 'wc-pending'). Match the CORE stem, not the user's full phrase:\n" .
            "  * 'payment pending', 'pending payment', 'pending', 'awaiting payment', 'unpaid' → LOWER(status) LIKE '%pending%'\n" .
            "  * 'processing', 'in progress' → LOWER(status) LIKE '%processing%'\n" .
            "  * 'on hold', 'on-hold', 'hold', 'awaiting' → (LOWER(status) LIKE '%on-hold%' OR LOWER(status) LIKE '%hold%')\n" .
            "  * 'completed', 'complete', 'done', 'fulfilled', 'delivered' → LOWER(status) LIKE '%complet%'\n" .
            "  * 'cancelled', 'canceled', 'cancel', 'void' → LOWER(status) LIKE '%cancel%'\n" .
            "  * 'refunded', 'refund', 'returned' → LOWER(status) LIKE '%refund%'\n" .
            "  * 'failed', 'declined', 'unsuccessful' → LOWER(status) LIKE '%fail%'\n" .
            "- WRONG: status LIKE '%payment pending%' (the full phrase will not match 'wc-pending'). RIGHT: status LIKE '%pending%'.\n" .
            "- Use the EXACT status column name from the schema (might be 'status', 'order_status', 'post_status').\n" .
            "- Apply the same idea to product / category / topic keywords: extract the meaningful word and use partial LIKE so close wording still returns rows.\n" .
            "- If unsure which synonym the user means, match ALL plausible ones with OR rather than returning zero rows.\n\n";
    }

    private static function normalize(string $text): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower($text));
    }
}
