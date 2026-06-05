<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Resolves which OpenAI API key to use for a single authenticated site.
 *
 * Callers must pass the {@see \App\Models\Site} loaded for the current request's API key (see
 * {@see \App\Http\Middleware\ApiKeyMiddleware}). That row's encrypted key belongs only to that merchant; another
 * site's key is never used.
 *
 * Priority:
 *  1. X-HeyTrisha-OpenAI-Key header (sent by the WordPress plugin — source of truth)
 *  2. Encrypted `sites.openai_key` for this site only (Laravel Crypt — set at registration / PUT /api/config)
 *
 * There is no server-wide OpenAI key in the environment.
 */
class OpenAiKeyResolver
{
    private const MIN_KEY_LENGTH = 20;

    public static function forPluginRequest(Request $request, Site $site): ?string
    {
        $header = $request->header('X-HeyTrisha-OpenAI-Key', '');
        if (is_string($header)) {
            $header = trim($header);
            if (strlen($header) >= self::MIN_KEY_LENGTH) {
                return $header;
            }
        }

        $db = $site->getOpenAIKey();
        if (is_string($db) && strlen(trim($db)) >= self::MIN_KEY_LENGTH) {
            return trim($db);
        }

        return null;
    }
}
