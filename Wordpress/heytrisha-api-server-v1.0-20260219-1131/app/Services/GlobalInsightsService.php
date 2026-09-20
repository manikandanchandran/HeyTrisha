<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI;

class GlobalInsightsService
{
    /**
     * @param string $globalMode evidence_grounded|model_only|both
     * @return array{answer: string, sources: array<int, array{url: string, title?: string, snippet?: string}>, mode_used: string}
     */
    public function getGlobalInsights(string $question, string $openaiKey, string $globalMode = 'both'): array
    {
        $globalMode = strtolower(trim($globalMode));
        if (!in_array($globalMode, ['evidence_grounded', 'model_only', 'both'], true)) {
            $globalMode = 'both';
        }

        $sources = [];

        if ($globalMode === 'evidence_grounded' || $globalMode === 'both') {
            try {
                $sources = $this->retrieveEvidenceSources($question);
                $sources = $this->redactSources($sources);
            } catch (\Throwable $e) {
                Log::warning('GlobalInsights: retrieval failed', ['error' => $e->getMessage()]);
                $sources = [];
            }

            if (!empty($sources)) {
                $answer = $this->synthesizeEvidenceGroundedAnswer($question, $sources, $openaiKey);
                return [
                    'answer'    => $answer,
                    'sources'   => $sources,
                    'mode_used' => 'evidence_grounded',
                ];
            }
        }

        // Fallback: model-only
        $answer = $this->synthesizeModelOnlyAnswer($question, $openaiKey);
        return [
            'answer'    => $answer,
            'sources'   => [],
            'mode_used' => 'model_only',
        ];
    }

    /**
     * Minimal, open-data retrieval without API keys:
     * - Wikipedia OpenSearch → page titles
     * - Wikipedia REST summary → snippets + canonical URLs
     *
     * @return array<int, array{url: string, title?: string, snippet?: string}>
     */
    private function retrieveEvidenceSources(string $question): array
    {
        // Keep it conservative: focus on category-level info. Extract 1–2 search phrases using simple heuristics.
        $terms = $this->extractSearchTerms($question);
        if (empty($terms)) {
            return [];
        }

        $results = [];
        $seen    = [];

        foreach ($terms as $term) {
            $titles = $this->wikipediaOpenSearch($term);
            foreach (array_slice($titles, 0, 2) as $title) {
                $summary = $this->wikipediaSummary($title);
                if (!$summary) {
                    continue;
                }

                $url = $summary['content_urls']['desktop']['page'] ?? null;
                if (!$url || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;

                $results[] = [
                    'url'     => $url,
                    'title'   => $summary['title'] ?? $title,
                    'snippet' => $summary['extract'] ?? null,
                ];

                if (count($results) >= 4) {
                    break 2;
                }
            }
        }

        return $results;
    }

    /**
     * Defense-in-depth redaction on retrieved snippets before returning to clients.
     *
     * @param array<int, array{url: string, title?: string, snippet?: string}> $sources
     * @return array<int, array{url: string, title?: string, snippet?: string}>
     */
    private function redactSources(array $sources): array
    {
        $out = [];
        foreach ($sources as $s) {
            $snippet = (string) ($s['snippet'] ?? '');
            if ($snippet !== '') {
                // Emails
                $snippet = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $snippet);
                // Phone-like sequences (very loose)
                $snippet = preg_replace('/\+?\d[\d\-\s().]{7,}\d/', '[redacted-phone]', $snippet);
            }

            $out[] = [
                'url'     => (string) ($s['url'] ?? ''),
                'title'   => isset($s['title']) ? (string) $s['title'] : null,
                'snippet' => $snippet !== '' ? $snippet : null,
            ];
        }

        return array_values(array_filter($out, fn ($s) => !empty($s['url'])));
    }

    /**
     * @return string[]
     */
    private function extractSearchTerms(string $question): array
    {
        $q = trim($question);
        if ($q === '') {
            return [];
        }

        // Heuristic: remove common comparison words; keep remaining as a broad term.
        $lower = strtolower($q);
        $lower = preg_replace('/\\b(compare|vs|versus|benchmark|global|industry|average|market)\\b/i', '', $lower);
        $lower = trim(preg_replace('/\\s+/', ' ', $lower));

        // Pick the first ~6 words as a search term; plus a generic "product pricing" variant.
        $words = preg_split('/\\s+/', $lower);
        $base  = trim(implode(' ', array_slice($words ?: [], 0, 6)));
        $base  = $base !== '' ? $base : $q;

        $terms = [$base];
        if (!str_contains(strtolower($base), 'price')) {
            $terms[] = $base . ' pricing';
        }

        // Deduplicate
        $uniq = [];
        foreach ($terms as $t) {
            $t = trim($t);
            if ($t !== '' && !in_array($t, $uniq, true)) {
                $uniq[] = $t;
            }
        }
        return array_slice($uniq, 0, 2);
    }

    /**
     * @return string[]
     */
    private function wikipediaOpenSearch(string $term): array
    {
        $resp = Http::timeout(10)->get('https://en.wikipedia.org/w/api.php', [
            'action'   => 'opensearch',
            'search'   => $term,
            'limit'    => 5,
            'namespace'=> 0,
            'format'   => 'json',
        ]);

        if ($resp->failed()) {
            return [];
        }

        $json = $resp->json();
        if (!is_array($json) || !isset($json[1]) || !is_array($json[1])) {
            return [];
        }

        return array_values(array_filter($json[1], fn ($t) => is_string($t) && $t !== ''));
    }

    private function wikipediaSummary(string $title): ?array
    {
        $encoded = rawurlencode($title);
        $resp    = Http::timeout(10)->get("https://en.wikipedia.org/api/rest_v1/page/summary/{$encoded}");
        if ($resp->failed()) {
            return null;
        }
        $json = $resp->json();
        return is_array($json) ? $json : null;
    }

    /**
     * @param array<int, array{url: string, title?: string, snippet?: string}> $sources
     */
    private function synthesizeEvidenceGroundedAnswer(string $question, array $sources, string $openaiKey): string
    {
        $evidenceLines = [];
        foreach ($sources as $s) {
            $evidenceLines[] = "- " . ($s['title'] ?? 'Source') . " (" . $s['url'] . ")\n  " . ($s['snippet'] ?? '');
        }
        $evidence = implode("\n", $evidenceLines);

        $prompt = "You are an ecommerce analytics assistant.\n"
            . "The user wants GLOBAL insights about products, grounded in open sources.\n\n"
            . "RULES:\n"
            . "- Use ONLY the provided evidence. If evidence is insufficient, say what is missing.\n"
            . "- Do not invent benchmarks or numbers.\n"
            . "- Provide practical, actionable insights and a short set of next steps.\n\n"
            . "USER QUESTION:\n{$question}\n\n"
            . "EVIDENCE:\n{$evidence}\n\n"
            . "Write a concise answer. End with a 'Sources' list referencing the URLs.";

        $resp = OpenAI::client($openaiKey)->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are careful, evidence-grounded, and do not hallucinate.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 500,
        ]);

        $text = $resp->choices[0]->message->content ?? '';
        return trim((string) $text);
    }

    private function synthesizeModelOnlyAnswer(string $question, string $openaiKey): string
    {
        $prompt = "You are an ecommerce analytics assistant.\n"
            . "The user is asking for GLOBAL/market insights about products.\n\n"
            . "RULES:\n"
            . "- If you are unsure, say so.\n"
            . "- Do not claim exact market benchmarks unless you clearly frame them as general guidance.\n"
            . "- Provide actionable recommendations.\n\n"
            . "USER QUESTION:\n{$question}\n";

        $resp = OpenAI::client($openaiKey)->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'Be helpful, but avoid making up precise facts.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 450,
        ]);

        $text = $resp->choices[0]->message->content ?? '';
        return trim((string) $text);
    }
}

