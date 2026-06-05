<?php

namespace App\Services;

use OpenAI;

class CompareInsightsService
{
    /**
     * @param array<int, mixed> $localData
     * @param array<int, array{url: string, title?: string, snippet?: string}> $globalSources
     * @return array{answer: string, comparison: array<string, mixed>}
     */
    public function compare(
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
                ['role' => 'system', 'content' => 'You output only valid JSON. Be conservative; do not invent numbers.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 700,
        ]);

        $content = trim((string) ($resp->choices[0]->message->content ?? ''));
        $json    = json_decode($content, true);

        if (!is_array($json) || !isset($json['answer'], $json['comparison'])) {
            // Safe fallback: keep globalAnswer and mention local row count
            return [
                'answer' => trim($globalAnswer) . "\n\n(Local data rows: " . count($localData) . ")",
                'comparison' => [
                    'local_rows' => count($localData),
                    'global_sources_count' => count($globalSources),
                    'note' => 'Comparison JSON parse fallback',
                ],
            ];
        }

        return [
            'answer' => (string) $json['answer'],
            'comparison' => is_array($json['comparison']) ? $json['comparison'] : ['note' => 'Invalid comparison object'],
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

        return "You are an ecommerce analytics assistant.\n\n"
            . "Task: Compare LOCAL store/catalog data against GLOBAL market insights (including similar products or category norms when evidence allows).\n\n"
            . "Rules:\n"
            . "- Use local data to compute local metrics where possible.\n"
            . "- Do NOT invent global benchmark numbers; if missing, describe qualitatively.\n"
            . "- Return JSON ONLY with keys: answer (string), comparison (object).\n\n"
            . "User question:\n{$question}\n\n"
            . "Local data (JSON):\n{$localPreview}\n\n"
            . "Global insights (text):\n{$globalAnswer}\n\n"
            . "Global sources (JSON):\n{$sourcesJson}\n\n"
            . "Required JSON format:\n"
            . "{\n"
            . "  \"answer\": \"...numbers + narrative...\",\n"
            . "  \"comparison\": {\n"
            . "    \"local\": {\"highlights\": []},\n"
            . "    \"global\": {\"highlights\": []},\n"
            . "    \"deltas\": [{\"metric\": \"\", \"local\": \"\", \"global\": \"\", \"note\": \"\"}],\n"
            . "    \"next_steps\": [\"...\"]\n"
            . "  }\n"
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

