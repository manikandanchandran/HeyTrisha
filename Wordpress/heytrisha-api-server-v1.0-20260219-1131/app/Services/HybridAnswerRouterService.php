<?php

namespace App\Services;

class HybridAnswerRouterService
{
    /**
     * @param string      $question
     * @param string|null $mode                  auto|local|global|compare
     * @param bool        $hybridArchitectureEnabled When true, most questions use DB→web unless clearly local-only or global-only.
     * @return array{type: "local"|"global"|"compare"|"hybrid", reason: string}
     */
    public function route(string $question, ?string $mode = 'auto', bool $hybridArchitectureEnabled = false): array
    {
        $mode = strtolower(trim((string) $mode));
        if ($mode === 'local' || $mode === 'global' || $mode === 'compare') {
            return ['type' => $mode, 'reason' => 'Explicit mode provided'];
        }

        $q = strtolower($question);

        // Compare / benchmark vs market (always; structured comparison output)
        $compareNeedles = [
            'compare', 'vs', 'versus', 'benchmark', 'industry average', 'global average', 'market average', 'how do we stack up',
            'other products', 'similar products', 'against competitors', 'against competition', 'stack up',
        ];
        foreach ($compareNeedles as $needle) {
            if (str_contains($q, $needle)) {
                return ['type' => 'compare', 'reason' => 'Detected comparison/benchmark intent'];
            }
        }

        if (str_contains($q, ' in the market') || str_contains($q, ' on the market') || str_contains($q, ' to the market')) {
            if (
                str_contains($q, 'product') || str_contains($q, 'products') || str_contains($q, 'item')
                || str_contains($q, 'sku') || str_contains($q, 'price') || str_contains($q, 'pricing')
            ) {
                return ['type' => 'compare', 'reason' => 'Detected in-the-market product comparison intent'];
            }
        }

        if ($hybridArchitectureEnabled && $this->hasLocalCatalogAnchor($q)) {
            $marketNeedles = [
                'market', 'competitor', 'competition', 'competitive', 'industry', 'benchmark', 'global',
                'average price', 'price range', 'typical', 'others charge', 'what others',
            ];
            foreach ($marketNeedles as $needle) {
                if (str_contains($q, $needle)) {
                    return ['type' => 'compare', 'reason' => 'Hybrid: catalog anchor + market/competitor context'];
                }
            }
        }

        // Local business analytics intent
        $localNeedles = [
            'my store', 'our store', 'our sales', 'my sales', 'orders', 'revenue', 'refund', 'returns',
            'conversion', 'abandoned cart', 'inventory', 'stock', 'out of stock', 'customers', 'repeat customers',
        ];
        foreach ($localNeedles as $needle) {
            if (!str_contains($q, $needle)) {
                continue;
            }
            // Hybrid: advisory/strategic questions that mention the store still need DB + web.
            if ($hybridArchitectureEnabled && !$this->isLikelyDataPullOnly($q) && !$this->hasTimeOrAggregateContext($q)) {
                continue;
            }
            return ['type' => 'local', 'reason' => 'Detected store analytics intent'];
        }

        // Hybrid on: short-circuit "reporting" questions to fast local SQL only
        if ($hybridArchitectureEnabled && $this->isLikelyDataPullOnly($q)) {
            return ['type' => 'local', 'reason' => 'Hybrid: read-only metrics/listing query (local SQL only)'];
        }

        // Global-only when no merchant anchor (general market / category knowledge)
        $globalNeedles = [
            'global', 'market', 'competitor', 'benchmarks', 'typical', 'average price', 'price range', 'category norms',
            'what do customers expect', 'industry trend', 'trend',
        ];
        foreach ($globalNeedles as $needle) {
            if (str_contains($q, $needle)) {
                if ($hybridArchitectureEnabled && $this->hasMerchantAnchor($q)) {
                    return ['type' => 'hybrid', 'reason' => 'Hybrid: merchant-scoped question with market/global vocabulary'];
                }
                return ['type' => 'global', 'reason' => 'Detected global/market context intent'];
            }
        }

        if ($hybridArchitectureEnabled) {
            return ['type' => 'hybrid', 'reason' => 'Hybrid architecture default: database context then open-web enrichment'];
        }

        return ['type' => 'local', 'reason' => 'Defaulted to local (auto mode)'];
    }

    private function hasLocalCatalogAnchor(string $q): bool
    {
        $anchors = [
            'my product', 'our product', 'this product', 'these products', 'this item', 'these items',
            'my sku', 'our sku', 'this sku', 'my catalog', 'our catalog', 'my store', 'our store',
            'my woocommerce', 'our woocommerce', 'this listing', 'these listings',
        ];
        foreach ($anchors as $a) {
            if (str_contains($q, $a)) {
                return true;
            }
        }

        return false;
    }

    private function hasMerchantAnchor(string $q): bool
    {
        if ($this->hasLocalCatalogAnchor($q)) {
            return true;
        }

        return (bool) preg_match('/\b(we|our|my)\s+/i', $q);
    }

    /**
     * True when the question is primarily a metrics/list/export read from the DB (skip web round-trip).
     */
    private function isLikelyDataPullOnly(string $q): bool
    {
        $advisory = [
            'recommend', 'suggestion', 'should i', 'best way to', 'strategy', 'vs ', 'versus', 'compare ',
            'competitor', 'improve ', 'optimize ', 'write ', 'draft ', 'campaign', 'seo', 'keyword',
            'marketing copy', 'positioning', 'branding', 'email ', 'social media',
        ];
        foreach ($advisory as $a) {
            if (str_contains($q, $a)) {
                return false;
            }
        }

        $pull = [
            'how many', 'how much did', 'count of', 'number of ', 'total ', 'sum ', 'average ', 'avg ',
            'list ', 'show all', 'show me all', 'display all', 'export', 'breakdown', 'group by',
            'per month', 'per week', 'per day', 'last 7', 'last 30', 'last 90', 'ytd', 'year to date',
            'last month', 'this month', 'last week', 'this week', 'yesterday', 'today', 'last year',
            'top 10', 'top 5', 'bottom ', 'orders in', 'orders from', 'revenue for', 'sales for',
            'sales last', 'revenue last', 'orders last',
            'refund rate', 'return rate', 'stock on hand', 'units sold', 'quantity sold',
        ];
        foreach ($pull as $p) {
            if (str_contains($q, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Time-bounded or roll-up store metrics are usually answerable from SQL alone.
     */
    private function hasTimeOrAggregateContext(string $q): bool
    {
        return (bool) preg_match(
            '/\b(last|this|past|previous|next)\s+(day|week|month|quarter|year)|\b\d{4}-\d{2}-\d{2}\b|ytd|year to date|q[1-4]\b/i',
            $q
        );
    }
}
