<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\WordPressConfigService;

class PostProductSearchService
{
    protected $baseUrl;
    protected $authHeader;
    protected $configService;

    public function __construct(WordPressConfigService $configService)
    {
        $this->configService = $configService;
        $wpApiConfig = $this->configService->getWordPressApiConfig();
        $this->baseUrl = $wpApiConfig['url'] ?? '';
        $authUser = $wpApiConfig['user'] ?? '';
        $authPass = $wpApiConfig['password'] ?? '';
        $this->authHeader = [
            'Authorization' => 'Basic ' . base64_encode($authUser . ':' . $authPass)
        ];
    }

    /**
     * ✅ Search for posts or products by name
     * 
     * @param string $name The name/title to search for
     * @param string $type 'post', 'product', or 'both'
     * @return array|null Returns array with 'id', 'title' or 'name', and 'type', or null if not found
     */
    public function searchByName($name, $type = 'both')
    {
        try {
            // Search posts if type is 'post' or 'both'
            if ($type === 'post' || $type === 'both') {
                $post = $this->searchPostByName($name);
                if ($post) {
                    return $post;
                }
            }

            // Search products if type is 'product' or 'both'
            if ($type === 'product' || $type === 'both') {
                $product = $this->searchProductByName($name);
                if ($product) {
                    return $product;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("❌ Error searching by name: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Search WordPress posts by title
     */
    private function searchPostByName($name)
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/wp-json/wp/v2/posts?search=' . urlencode($name) . '&per_page=10';
            
            $response = Http::withHeaders($this->authHeader)
                ->timeout(10)
                ->get($url);

            if ($response->failed()) {
                Log::warning("⚠️ WordPress posts search failed: " . $response->status());
                return null;
            }

            $posts = $response->json();
            
            if (!is_array($posts) || empty($posts)) {
                return null;
            }

            // Find exact or closest match
            $nameLower = strtolower($name);
            $exactMatch = null;
            $partialMatch = null;

            foreach ($posts as $post) {
                $postTitle = isset($post['title']['rendered']) ? $post['title']['rendered'] : ($post['title'] ?? '');
                $postTitleLower = strtolower($postTitle);

                // Exact match (case-insensitive)
                if ($postTitleLower === $nameLower) {
                    $exactMatch = [
                        'id' => $post['id'],
                        'title' => $postTitle,
                        'type' => 'post'
                    ];
                    break;
                }

                // Partial match (contains the name)
                if (!$partialMatch && strpos($postTitleLower, $nameLower) !== false) {
                    $partialMatch = [
                        'id' => $post['id'],
                        'title' => $postTitle,
                        'type' => 'post'
                    ];
                }
            }

            return $exactMatch ?? $partialMatch;
        } catch (\Exception $e) {
            Log::error("❌ Error searching post by name: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Search WooCommerce products by name
     */
    private function searchProductByName($name)
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/wp-json/wc/v3/products?search=' . urlencode($name) . '&per_page=10';
            
            $response = Http::withHeaders($this->authHeader)
                ->timeout(10)
                ->get($url);

            if ($response->failed()) {
                Log::warning("⚠️ WooCommerce products search failed: " . $response->status());
                return null;
            }

            $products = $response->json();
            
            if (!is_array($products) || empty($products)) {
                return null;
            }

            // Find exact or closest match
            $nameLower = strtolower($name);
            $exactMatch = null;
            $partialMatch = null;

            foreach ($products as $product) {
                $productName = $product['name'] ?? '';
                $productNameLower = strtolower($productName);

                // Exact match (case-insensitive)
                if ($productNameLower === $nameLower) {
                    $exactMatch = [
                        'id' => $product['id'],
                        'name' => $productName,
                        'type' => 'product'
                    ];
                    break;
                }

                // Partial match (contains the name)
                if (!$partialMatch && strpos($productNameLower, $nameLower) !== false) {
                    $partialMatch = [
                        'id' => $product['id'],
                        'name' => $productName,
                        'type' => 'product'
                    ];
                }
            }

            return $exactMatch ?? $partialMatch;
        } catch (\Exception $e) {
            Log::error("❌ Error searching product by name: " . $e->getMessage());
            return null;
        }
    }
}
