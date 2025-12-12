<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WordPressConfigService
{
    protected $config = null;
    protected $wordpressUrl;
    protected $sharedToken;

    public function __construct()
    {
        try {
            // Get WordPress URL from request or use default
            // Shared token from .env (minimal config needed for initial connection)
            $this->wordpressUrl = $this->getWordPressUrl();
            $this->sharedToken = env('WORDPRESS_SHARED_TOKEN', '');
            
            // Try to load cached config immediately (fast path)
            $cached = Cache::get('heytrisha_wordpress_config');
            if ($cached !== null) {
                $this->config = $cached;
            }
        } catch (\Exception $e) {
            // Don't fail on constructor - will use fallback when needed
            Log::warning("⚠️ WordPressConfigService constructor error: " . $e->getMessage());
            $this->wordpressUrl = '';
            $this->sharedToken = '';
        }
    }

    /**
     * ✅ Get WordPress URL dynamically (no hardcoded values)
     */
    private function getWordPressUrl()
    {
        // Priority 1: Get from .env (for initial setup)
        $url = env('WORDPRESS_CONFIG_URL', '');
        
        if (!empty($url)) {
            return rtrim($url, '/');
        }
        
        // Priority 2: Get from HTTP request (if available)
        if (!app()->runningInConsole()) {
            $request = request();
            if ($request && $request->hasHeader('Referer')) {
                $referer = $request->header('Referer');
                if (!empty($referer)) {
                    $parsed = parse_url($referer);
                    if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
                        $url = $parsed['scheme'] . '://' . $parsed['host'];
                        if (isset($parsed['port'])) {
                            $url .= ':' . $parsed['port'];
                        }
                        if (isset($parsed['path'])) {
                            // Extract WordPress path (remove /wp-admin, /wp-json, etc.)
                            $path = $parsed['path'];
                            $path = preg_replace('#/wp-(admin|json|content).*$#', '', $path);
                            $url .= rtrim($path, '/');
                        }
                        return rtrim($url, '/');
                    }
                }
            }
            
            // Try to get from current request
            if ($request) {
                $url = $request->getSchemeAndHttpHost();
                $path = $request->getBasePath();
                // Remove API paths if present
                $path = preg_replace('#/api.*$#', '', $path);
                $path = preg_replace('#/wp-json.*$#', '', $path);
                if (!empty($path)) {
                    $url .= rtrim($path, '/');
                }
                return rtrim($url, '/');
            }
        }
        
        // Priority 3: Try to detect from server variables (for console/CLI)
        if (app()->runningInConsole()) {
            // Check if we can get from environment
            $url = env('APP_URL', '');
            if (!empty($url)) {
                return rtrim($url, '/');
            }
        }
        
        // Last resort: Return empty - will use fallback config
        // This ensures no hardcoded URLs for open-source distribution
        return '';
    }

    /**
     * ✅ Get configuration from WordPress database
     * Caches config for 5 minutes to reduce API calls
     * Uses fast fallback if WordPress is slow
     */
    public function getConfig()
    {
        // Return cached config if available
        if ($this->config !== null) {
            return $this->config;
        }

        // Try to get from cache first
        $cached = Cache::get('heytrisha_wordpress_config');
        if ($cached !== null) {
            $this->config = $cached;
            return $this->config;
        }

        // If WordPress URL is empty, use fallback immediately (no hardcoded URLs)
        if (empty($this->wordpressUrl) || empty($this->sharedToken)) {
            Log::info("ℹ️ WordPress URL or token not configured, using fallback config");
            $this->config = $this->getFallbackConfig();
            return $this->config;
        }

        // Fetch from WordPress REST API with very short timeout
        try {
            $url = rtrim($this->wordpressUrl, '/') . '/wp-json/heytrisha/v1/config?token=' . urlencode($this->sharedToken);
            
            // Use very short timeout (2 seconds) - if WordPress is slow, use fallback immediately
            $response = Http::timeout(2)->get($url);

            if ($response->failed()) {
                Log::warning("⚠️ Failed to fetch config from WordPress (Status: " . $response->status() . "), using fallback");
                // Fallback to .env immediately if WordPress fetch fails
                $this->config = $this->getFallbackConfig();
                return $this->config;
            }

            $this->config = $response->json();
            
            // Validate config structure
            if (!is_array($this->config) || empty($this->config)) {
                Log::warning("⚠️ Invalid config structure from WordPress, using fallback");
                $this->config = $this->getFallbackConfig();
                return $this->config;
            }
            
            // Cache for 5 minutes
            Cache::put('heytrisha_wordpress_config', $this->config, 300);
            
            Log::info("✅ Config fetched from WordPress successfully");
            return $this->config;
            
        } catch (\Exception $e) {
            Log::warning("⚠️ Error fetching config from WordPress: " . $e->getMessage() . " - Using fallback");
            // Fallback to .env immediately if WordPress fetch fails or times out
            $this->config = $this->getFallbackConfig();
            return $this->config;
        }
    }

    /**
     * ✅ Fallback to .env if WordPress fetch fails
     */
    private function getFallbackConfig()
    {
        Log::warning("⚠️ Using fallback .env config");
        return [
            'openai_api_key' => env('OPENAI_API_KEY', ''),
            'database' => [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'name' => env('DB_DATABASE', ''),
                'user' => env('DB_USERNAME', ''),
                'password' => env('DB_PASSWORD', ''),
            ],
            'wordpress_api' => [
                'url' => env('WORDPRESS_API_URL', ''),
                'user' => env('WORDPRESS_API_USER', ''),
                'password' => env('WORDPRESS_API_PASSWORD', ''),
            ],
        ];
    }

    /**
     * ✅ Get OpenAI API Key
     */
    public function getOpenAIApiKey()
    {
        $config = $this->getConfig();
        return $config['openai_api_key'] ?? '';
    }

    /**
     * ✅ Get Database Config
     */
    public function getDatabaseConfig()
    {
        $config = $this->getConfig();
        return $config['database'] ?? [];
    }

    /**
     * ✅ Get WordPress API Config
     */
    public function getWordPressApiConfig()
    {
        $config = $this->getConfig();
        return $config['wordpress_api'] ?? [];
    }

    /**
     * ✅ Clear cached config (call this after settings update)
     */
    public function clearCache()
    {
        Cache::forget('heytrisha_wordpress_config');
        $this->config = null;
    }
}

