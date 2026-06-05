<?php

namespace App\Services;

use OpenAI;

/**
 * General hybrid synthesis: answers any merchant question using local DB rows + open-web context.
 */
class HybridKnowledgeService
{
    /**
     * @param array<int, mixed> $localData
     * @param array<int, array{url: string, title?: string, snippet?: string}> $globalSources
     * @return array{answer: string, insights: array<string, mixed>}
     */
    public function synthesize(
        string $question,
        array $localData,
        string $globalAnswer,
        array $globalSources,
        string $openaiKey
    ): array {
        $prompt = $this->buildPrompt($question, $localData, $globalAnswer, $globalSources);

        $resp = OpenAI::client($openaiKey)->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You output only valid JSON. Ground claims in local rows and/or cited evidence; do not invent competitor SKUs or secret metrics.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 900,
        ]);

        $content = trim((string) ($resp->choices[0]->message->content ?? ''));
        $json    = json_decode($content, true);

        if (!is_array($json) || !isset($json['answer'])) {
            $fallback = trim($globalAnswer);
            if ($fallback === '') {
                $fallback = 'Here is what your store data shows, combined with general market guidance where helpful.';
            }

            return [
                'answer'    => $fallback . "\n\n(Local data rows: " . count($localData) . ')',
                'insights'  => [
                    'local_rows'           => count($localData),
                    'global_sources_count' => count($globalSources),
                    'note'                 => 'Hybrid JSON parse fallback',
                ],
            ];
        }

        return [
            'answer'   => (string) $json['answer'],
            'insights' => is_array($json['insights'] ?? null) ? $json['insights'] : ['note' => 'insights optional'],
        ];
    }

    /**
     * @param array<int, mixed> $localData
     * @param array<int, array{url: string, title?: string, snippet?: string}> $globalSources
     */
    private function buildPrompt(string $question, array $localData, string $globalAnswer, array $globalSources): string
    {
        $localPreview = $this->compactJson($localData, 6000);
        $sourcesJson  = $this->compactJson($globalSources, 2000);

        return "You are an ecommerce assistant for a WooCommerce merchant.\n\n"
            . "Task: Answer the user's question using BOTH:\n"
            . "(1) LOCAL rows from their database (authoritative for their catalog, prices, stock, orders, customers), and\n"
            . "(2) GLOBAL/market context from the supplied text and sources (category norms, buyer expectations, positioning, SEO, shipping, compliance — whatever fits the question).\n\n"
            . "Rules:\n"
            . "- Lead with what LOCAL data proves; add GLOBAL perspective where it adds value.\n"
            . "- If local rows are empty, say so and lean on global guidance while noting uncertainty.\n"
            . "- Do not invent exact competitor prices or confidential statistics.\n"
            . "- Return JSON ONLY with keys: answer (markdown string), insights (object: optional bullets, risks, next_steps arrays).\n\n"
            . "User question:\n{$question}\n\n"
            . "Local database rows (JSON):\n{$localPreview}\n\n"
            . "Global/market context (text):\n{$globalAnswer}\n\n"
            . "Sources (JSON):\n{$sourcesJson}\n\n"
            . "Required JSON format:\n"
            . "{\n"
            . "  \"answer\": \"...\",\n"
            . "  \"insights\": {\"highlights\":[],\"risks\":[],\"next_steps\":[]}\n"
            . "}";
    }

    /**
     * @param mixed $value
     */
    private function compactJson($value, int $maxChars): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return '[]';
        }
        if (strlen($json) <= $maxChars) {
            return $json;
        }

        return substr($json, 0, $maxChars) . '...';
    }
}
