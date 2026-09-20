<?php

namespace App\Http\Controllers;

use App\Services\SpecificationIngestService;
use App\Support\OpenAiKeyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles specification file ingestion for NL-constrained query mode.
 *
 * The WordPress plugin posts raw specification text here after the admin
 * uploads a file in the plugin settings.  This controller validates the
 * request, delegates to SpecificationIngestService, and returns the
 * extracted allowlist + version so the plugin can store them locally.
 */
class SpecificationController extends Controller
{
    protected SpecificationIngestService $ingestService;

    public function __construct(SpecificationIngestService $ingestService)
    {
        $this->ingestService = $ingestService;
    }

    /**
     * POST /api/specification/ingest
     *
     * Expected body:
     *   text  (string, required) — raw specification file content
     *   site  (string, optional) — site URL for logging; site is resolved from API key
     *
     * Returns JSON:
     *   success         bool
     *   allowlist       object  — table_suffix => [col, ...]
     *   version         string  — sha256 of raw text
     *   rules_summary   string
     *   chunks_stored   int
     */
    public function ingest(Request $request): \Illuminate\Http\JsonResponse
    {
        // Site is injected by ApiKeyMiddleware
        $site = $request->get('site');

        if (!$site) {
            return response()->json(['success' => false, 'message' => 'Site not found in request'], 500);
        }

        $validator = \Validator::make($request->all(), [
            'text' => 'required|string|min:10|max:500000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 400);
        }

        $rawText = $request->input('text');

        $openaiKey = OpenAiKeyResolver::forPluginRequest($request, $site);
        if (!$openaiKey) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI API key not configured. Add your OpenAI key in the HeyTrisha plugin settings; it is sent when indexing specifications.',
            ], 500);
        }

        try {
            $result = $this->ingestService->ingest($site, $rawText, $openaiKey);

            return response()->json([
                'success'       => true,
                'allowlist'     => $result['allowlist'],
                'version'       => $result['version'],
                'rules_summary' => $result['rules_summary'],
                'chunks_stored' => $result['chunks_stored'],
                'message'       => 'Specification indexed successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('SpecificationController: ingest failed', [
                'site'  => $site->site_url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Specification indexing failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
