<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostProductSearchService
{
    protected $configService;

    public function __construct(WordPressConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * ✅ Search for posts or products by name
     * @param string $name The name/title to search for
     * @param string $type 'post', 'product', or 'both'
     * @return array|null Returns found item with id, name/title, and type, or null if not found
     */
    public function searchByName($name, $type = 'both')
    {
        Log::info("🔍 Searching for: '$name' (type: $type)");

        $results = [];

        // Search posts if needed
        if ($type === 'post' || $type === 'both') {
            $post = $this->searchPostByName($name);
            if ($post) {
                $results[] = $post;
            }
        }

        // Search products if needed
        if ($type === 'product' || $type === 'both') {
            $product = $this->searchProductByName($name);
            if ($product) {
                $results[] = $product;
            }
        }

        // Return first match (or null if none found)
        return !empty($results) ? $results[0] : null;
    }

    /**
     * ✅ Search WordPress posts by title
     */
    private function searchPostByName($name)
    {
        try {
            $wpConfig = $this->configService->getWordPressApiConfig();
            $baseUrl = $wpConfig['url'] ?? '';
            $authUser = $wpConfig['user'] ?? '';
            $authPass = $wpConfig['password'] ?? '';

            if (empty($baseUrl) || empty($authUser) || empty($authPass)) {
                Log::warning("⚠️ WordPress API credentials missing");
                return null;
            }

            // Search posts by title
            $url = rtrim($baseUrl, '/') . '/wp-json/wp/v2/posts?search=' . urlencode($name) . '&per_page=1';
            
            $response = Http::timeout(10)
                ->withBasicAuth($authUser, $authPass)
                ->get($url);

            if ($response->failed()) {
                Log::warning("⚠️ Failed to search posts: " . $response->status());
                return null;
            }

            $posts = $response->json();
            
            if (empty($posts) || !is_array($posts)) {
                return null;
            }

            $post = $posts[0];
            return [
                'id' => $post['id'] ?? null,
                'title' => $post['title']['rendered'] ?? $post['title'] ?? '',
                'type' => 'post'
            ];

        } catch (\Exception $e) {
            Log::error("❌ Error searching posts: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Search WooCommerce products by name
     */
    private function searchProductByName($name)
    {
        try {
            $wpConfig = $this->configService->getWordPressApiConfig();
            $baseUrl = $wpConfig['url'] ?? '';
            $authUser = $wpConfig['user'] ?? '';
            $authPass = $wpConfig['password'] ?? '';

            if (empty($baseUrl) || empty($authUser) || empty($authPass)) {
                Log::warning("⚠️ WordPress API credentials missing");
                return null;
            }

            // Search products by name
            $url = rtrim($baseUrl, '/') . '/wp-json/wc/v3/products?search=' . urlencode($name) . '&per_page=1';
            
            $response = Http::timeout(10)
                ->withBasicAuth($authUser, $authPass)
                ->get($url);

            if ($response->failed()) {
                Log::warning("⚠️ Failed to search products: " . $response->status());
                return null;
            }

            $products = $response->json();
            
            if (empty($products) || !is_array($products)) {
                return null;
            }

            $product = $products[0];
            return [
                'id' => $product['id'] ?? null,
                'name' => $product['name'] ?? '',
                'type' => 'product'
            ];

        } catch (\Exception $e) {
            Log::error("❌ Error searching products: " . $e->getMessage());
            return null;
        }
    }
}
