<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SpecificationIngestService
 *
 * Processes a raw specification text uploaded by a WordPress admin:
 *
 *  1. Calls OpenAI to extract a structured allowlist (tables + columns)
 *     and a short rules_summary from the natural-language content.
 *  2. Splits the text into overlapping chunks.
 *  3. Embeds each chunk with the OpenAI embeddings API.
 *  4. Persists everything to site_specification_chunks and site_specifications.
 *
 * Chunk size / overlap are conservative so the total token budget stays
 * well below the 8 k context limit of text-embedding-3-small.
 */
class SpecificationIngestService
{
    // Maximum characters per chunk (~450 tokens at ~3.5 chars/token)
    private const CHUNK_SIZE    = 1600;
    // Overlap between consecutive chunks in characters
    private const CHUNK_OVERLAP = 200;
    // Maximum chunks stored per site (cost guard)
    private const MAX_CHUNKS    = 120;
    // Embedding model
    private const EMBED_MODEL   = 'text-embedding-3-small';
    // Extraction model
    private const EXTRACT_MODEL = 'gpt-3.5-turbo';

    /**
     * Ingest a raw specification text for the given site.
     *
     * @param  Site        $site
     * @param  string      $rawText            Full text of the uploaded file.
     * @param  string|null $openAiKeyOverride  From plugin header or resolver; if null, uses site record.
     * @return array  Array with keys: allowlist, version, rules_summary, chunks_stored.
     * @throws \RuntimeException on unrecoverable failure.
     */
    public function ingest(Site $site, string $rawText, ?string $openAiKeyOverride = null): array
    {
        $openaiKey = $openAiKeyOverride ?? $site->getOpenAIKey();

        if (empty($openaiKey)) {
            throw new \RuntimeException('OpenAI API key not configured for this site.');
        }

        $version = hash('sha256', $rawText);

        Log::info('SpecificationIngest: starting', [
            'site'    => $site->site_url,
            'version' => substr($version, 0, 12),
            'length'  => strlen($rawText),
        ]);

        // ------------------------------------------------------------------
        // 1. Extract structured allowlist + rules summary via LLM
        // ------------------------------------------------------------------
        $extracted = $this->extractAllowlist($rawText, $openaiKey);

        Log::info('SpecificationIngest: allowlist extracted', [
            'tables'        => count($extracted['allowlist'] ?? []),
            'rules_summary' => substr($extracted['rules_summary'] ?? '', 0, 80),
        ]);

        // ------------------------------------------------------------------
        // 2. Chunk text
        // ------------------------------------------------------------------
        $chunks = $this->chunkText($rawText);
        $chunks = array_slice($chunks, 0, self::MAX_CHUNKS);

        Log::info('SpecificationIngest: chunks created', ['count' => count($chunks)]);

        // ------------------------------------------------------------------
        // 3. Embed chunks
        // ------------------------------------------------------------------
        $embeddings = $this->embedChunks($chunks, $openaiKey);

        // ------------------------------------------------------------------
        // 4. Persist to database (atomic replace)
        // ------------------------------------------------------------------
        $this->persistSpec($site, $version, $extracted, $chunks, $embeddings);

        Log::info('SpecificationIngest: complete', ['chunks_stored' => count($chunks)]);

        return [
            'allowlist'      => $extracted['allowlist'] ?? [],
            'version'        => $version,
            'rules_summary'  => $extracted['rules_summary'] ?? '',
            'chunks_stored'  => count($chunks),
        ];
    }

    // -----------------------------------------------------------------------
    // LLM extraction
    // -----------------------------------------------------------------------

    /**
     * Ask the LLM to parse the specification text into a structured allowlist.
     *
     * The response is a JSON object with:
     *   allowed_tables: { table_suffix: ["col1","col2",...] | ["*"] }
     *   forbidden_tables: ["suffix", ...]
     *   rules_summary: "short plain-English description of constraints"
     *
     * Table suffixes are without the WordPress prefix (e.g. "wc_orders").
     */
    private function extractAllowlist(string $rawText, string $openaiKey): array
    {
        $systemPrompt = <<<'SYSTEM'
You are a database schema analyst.
The user provides text that describes their database and business rules.
The text may be structured (SQL, JSON, table-column lists) or plain English.

Your task: extract a machine-readable summary in VALID JSON with exactly these keys:
{
  "allowed_tables": {
    "<table_suffix_without_wp_prefix>": ["<col1>", "<col2>", ...]
  },
  "forbidden_tables": ["<table_suffix>", ...],
  "rules_summary": "<one or two sentences summarising key business rules>"
}

Rules:
- table_suffix should be the bare name without any wp_ or numeric prefix (e.g. "wc_orders", "posts", "wc_order_stats").
- If all columns of a table are allowed, use ["*"] as the column list.
- If the text does not mention specific columns for a table, assume ["*"].
- Only include tables that the text explicitly mentions as relevant or allowed.
- forbidden_tables are tables explicitly marked as off-limits or not to be queried.
- If nothing is forbidden, use an empty array.
- rules_summary must be a single short string (max 200 chars).
- Output ONLY valid JSON — no markdown, no backticks, no explanation.
SYSTEM;

        $userPrompt = "Specification text:\n\n" . mb_substr($rawText, 0, 6000);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => self::EXTRACT_MODEL,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens'  => 800,
            'temperature' => 0,
        ]);

        if ($response->failed()) {
            Log::error('SpecificationIngest: extraction API error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 300),
            ]);
            throw new \RuntimeException('OpenAI extraction failed: HTTP ' . $response->status());
        }

        $content = $response->json('choices.0.message.content', '{}');
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['allowed_tables'])) {
            Log::warning('SpecificationIngest: could not parse extraction JSON', ['raw' => substr($content, 0, 400)]);
            // Return permissive fallback so upload doesn't fail entirely
            return [
                'allowlist'       => [],
                'forbidden_tables'=> [],
                'rules_summary'   => 'Could not extract structured rules from file.',
            ];
        }

        return [
            'allowlist'        => $data['allowed_tables']   ?? [],
            'forbidden_tables' => $data['forbidden_tables'] ?? [],
            'rules_summary'    => $data['rules_summary']    ?? '',
        ];
    }

    // -----------------------------------------------------------------------
    // Chunking
    // -----------------------------------------------------------------------

    /**
     * Split text into overlapping character-level chunks.
     *
     * @return string[]
     */
    private function chunkText(string $text): array
    {
        $text   = trim($text);
        $len    = mb_strlen($text);
        $chunks = [];
        $start  = 0;

        while ($start < $len) {
            $chunk    = mb_substr($text, $start, self::CHUNK_SIZE);
            $chunks[] = $chunk;
            $start   += (self::CHUNK_SIZE - self::CHUNK_OVERLAP);
        }

        return $chunks;
    }

    // -----------------------------------------------------------------------
    // Embeddings
    // -----------------------------------------------------------------------

    /**
     * Embed an array of text chunks, returning a parallel array of float vectors.
     * Sends all chunks in a single API call (batch).
     *
     * @param  string[] $chunks
     * @return float[][]   One embedding array per chunk.
     */
    private function embedChunks(array $chunks, string $openaiKey): array
    {
        if (empty($chunks)) {
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/embeddings', [
            'model' => self::EMBED_MODEL,
            'input' => $chunks,
        ]);

        if ($response->failed()) {
            Log::error('SpecificationIngest: embeddings API error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 300),
            ]);
            // Return empty embeddings — chunks stored without vectors; retrieval degrades to full-text
            return array_fill(0, count($chunks), []);
        }

        $data = $response->json('data', []);
        // Sort by index to ensure alignment with $chunks array
        usort($data, fn($a, $b) => $a['index'] <=> $b['index']);

        return array_column($data, 'embedding');
    }

    // -----------------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------------

    /**
     * Atomically replace specification data for the site.
     */
    private function persistSpec(
        Site   $site,
        string $version,
        array  $extracted,
        array  $chunks,
        array  $embeddings
    ): void {
        DB::transaction(function () use ($site, $version, $extracted, $chunks, $embeddings) {
            // Remove old chunks
            DB::table('site_specification_chunks')->where('site_id', $site->id)->delete();

            // Insert new chunks
            $rows = [];
            foreach ($chunks as $i => $chunk) {
                $embedding = $embeddings[$i] ?? [];
                $rows[]    = [
                    'site_id'     => $site->id,
                    'spec_version'=> $version,
                    'chunk_index' => $i,
                    'content'     => $chunk,
                    'embedding'   => json_encode($embedding),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            if (!empty($rows)) {
                DB::table('site_specification_chunks')->insert($rows);
            }

            // Upsert site_specifications
            $forbiddenJson = json_encode($extracted['forbidden_tables'] ?? []);
            $allowlistJson = json_encode($extracted['allowlist'] ?? []);

            DB::table('site_specifications')->updateOrInsert(
                ['site_id' => $site->id],
                [
                    'spec_version'          => $version,
                    'allowlist_json'         => $allowlistJson,
                    'rules_summary'          => $extracted['rules_summary'] ?? '',
                    'forbidden_tables_json'  => $forbiddenJson,
                    'updated_at'             => now(),
                    'created_at'             => now(),
                ]
            );
        });
    }

    // -----------------------------------------------------------------------
    // Retrieval (called from QueryController)
    // -----------------------------------------------------------------------

    /**
     * Retrieve the top-k most relevant chunks for a given question.
     *
     * Uses cosine similarity between the question embedding and stored chunk
     * embeddings.  Falls back to returning the first k chunks if no embeddings
     * are available.
     *
     * @param  Site   $site
     * @param  string $question
     * @param  string $openaiKey
     * @param  int    $topK
     * @return string[]  Array of chunk texts, most relevant first.
     */
    public function retrieveChunks(Site $site, string $question, string $openaiKey, int $topK = 5): array
    {
        $rows = DB::table('site_specification_chunks')
            ->where('site_id', $site->id)
            ->select(['chunk_index', 'content', 'embedding'])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Embed the question
        $qEmbedding = $this->embedSingle($question, $openaiKey);

        if (empty($qEmbedding)) {
            // No embedding — return first k chunks as fallback
            return $rows->take($topK)->pluck('content')->toArray();
        }

        // Score each chunk
        $scored = $rows->map(function ($row) use ($qEmbedding) {
            $chunkEmb = json_decode($row->embedding ?? '[]', true);
            $score    = empty($chunkEmb) ? 0.0 : $this->cosineSimilarity($qEmbedding, $chunkEmb);
            return ['content' => $row->content, 'score' => $score];
        });

        // Sort descending by score, take top k
        $top = $scored->sortByDesc('score')->take($topK);

        return $top->pluck('content')->toArray();
    }

    /**
     * Get the stored allowlist for a site.
     *
     * @param  Site  $site
     * @return array|null  Map of suffix => [column,...] or null if none stored.
     */
    public function getAllowlist(Site $site): ?array
    {
        $spec = DB::table('site_specifications')->where('site_id', $site->id)->first();

        if (!$spec) {
            return null;
        }

        $data = json_decode($spec->allowlist_json ?? '{}', true);
        return (is_array($data) && !empty($data)) ? $data : null;
    }

    /**
     * Get the stored rules_summary for a site.
     */
    public function getRulesSummary(Site $site): string
    {
        $spec = DB::table('site_specifications')->where('site_id', $site->id)->first();
        return $spec?->rules_summary ?? '';
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function embedSingle(string $text, string $openaiKey): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(15)->post('https://api.openai.com/v1/embeddings', [
            'model' => self::EMBED_MODEL,
            'input' => $text,
        ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json('data.0.embedding', []);
    }

    /**
     * Cosine similarity between two float vectors.
     * Returns value in [-1, 1]; higher = more similar.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }

        $denom = sqrt($na) * sqrt($nb);
        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}
