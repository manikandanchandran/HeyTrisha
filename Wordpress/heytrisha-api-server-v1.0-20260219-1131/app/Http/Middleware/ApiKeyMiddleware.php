<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Site;
use Illuminate\Support\Facades\Log;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get API key from Authorization header
        $apiKey = $request->bearerToken();

        if (!$apiKey) {
            Log::warning('API request without API key', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'API key is required. Please provide Authorization: Bearer {API_KEY} header.'
            ], 401);
        }

        // Find site by API key
        $site = Site::findByAPIKey($apiKey);

        if (!$site) {
            Log::warning('Invalid API key attempted', [
                'api_key_prefix' => substr($apiKey, 0, 10) . '...',
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API key. Please check your API key in plugin settings.'
            ], 403);
        }

        // Add site to request for use in controllers
        $request->merge([
            'site' => $site,
            'site_id' => $site->id,
            'site_url' => $site->site_url,
        ]);

        // Log API usage (optional - can be disabled for performance)
        Log::info('API request authenticated', [
            'site_url' => $site->site_url,
            'endpoint' => $request->path(),
        ]);

        return $next($request);
    }
}




