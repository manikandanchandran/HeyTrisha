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
            // Get WordPress URL from request (no .env dependency)
            $this->wordpressUrl = $this->getWordPressUrl();
            // Get shared token from environment variable
            $this->sharedToken = env('WORDPRESS_SHARED_TOKEN', '');
            
            // Try to load cached config immediately (fast path)
            // Use try-catch to prevent errors if Cache is not available yet
            try {
                $cached = Cache::get('heytrisha_wordpress_config');
                if ($cached !== null) {
                    $this->config = $cached;
                }
            } catch (\Exception $cacheError) {
                // Cache not available yet, that's okay - will fetch when needed
                $this->config = null;
            }
        } catch (\Exception $e) {
            // Don't fail on constructor - will use fallback when needed
            try {
                Log::warning("⚠️ WordPressConfigService constructor error: " . $e->getMessage());
            } catch (\Exception $logError) {
                // Log not available, that's okay
            }
            $this->wordpressUrl = '';
            $this->sharedToken = '';
        }
    }

    /**
     * ✅ Get WordPress URL dynamically (no hardcoded values)
     */
    private function getWordPressUrl()
    {
        // Get from HTTP request (no .env dependency)
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

        // ✅ PRIORITY 1: Try to get from HTTP headers (when called via WordPress proxy)
        // This is the fastest and most reliable method
        $headerConfig = $this->getConfigFromHeaders();
        if ($headerConfig !== null) {
            Log::info("✅ Config loaded from HTTP headers (WordPress proxy)");
            $this->config = $headerConfig;
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
     * ✅ Get configuration from HTTP headers (injected by WordPress proxy)
     * This is the fastest and most reliable method when called via proxy
     */
    private function getConfigFromHeaders()
    {
        // Check if WordPress injected the config via HTTP headers
        if (isset($_SERVER['HTTP_X_WORDPRESS_OPENAI_KEY'])) {
            return [
                'openai_api_key' => $_SERVER['HTTP_X_WORDPRESS_OPENAI_KEY'] ?? '',
                'database' => [
                    'host' => $_SERVER['HTTP_X_WORDPRESS_DB_HOST'] ?? '127.0.0.1',
                    'port' => $_SERVER['HTTP_X_WORDPRESS_DB_PORT'] ?? '3306',
                    'name' => $_SERVER['HTTP_X_WORDPRESS_DB_NAME'] ?? '',
                    'user' => $_SERVER['HTTP_X_WORDPRESS_DB_USER'] ?? '',
                    'password' => $_SERVER['HTTP_X_WORDPRESS_DB_PASSWORD'] ?? '',
                ],
                'wordpress_api' => [
                    'url' => $_SERVER['HTTP_X_WORDPRESS_API_URL'] ?? '',
                    'user' => $_SERVER['HTTP_X_WORDPRESS_API_USER'] ?? '',
                    'password' => $_SERVER['HTTP_X_WORDPRESS_API_PASSWORD'] ?? '',
                ],
                'woocommerce_api' => [
                    'consumer_key' => $_SERVER['HTTP_X_WOOCOMMERCE_KEY'] ?? '',
                    'consumer_secret' => $_SERVER['HTTP_X_WOOCOMMERCE_SECRET'] ?? '',
                ],
                'wordpress_info' => [
                    'is_multisite' => false,
                    'current_site_id' => 1,
                ],
            ];
        }
        
        return null; // No headers found, try other methods
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
            'woocommerce_api' => [
                'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY', ''),
                'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET', ''),
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
     * ✅ Get WooCommerce API Config
     */
    public function getWooCommerceApiConfig()
    {
        $config = $this->getConfig();
        return $config['woocommerce_api'] ?? [
            'consumer_key' => '',
            'consumer_secret' => '',
        ];
    }

    /**
     * ✅ Get WordPress site information (Multisite status and current site ID)
     */
    public function getWordPressInfo()
    {
        $config = $this->getConfig();
        return $config['wordpress_info'] ?? [
            'is_multisite' => false,
            'current_site_id' => 1
        ];
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

