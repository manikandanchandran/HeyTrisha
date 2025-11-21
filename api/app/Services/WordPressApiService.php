<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressApiService
{
    protected $baseUrl;
    protected $authHeader;

    public function __construct()
    {
        $this->baseUrl = env('WORDPRESS_API_URL');
        $this->authHeader = [
            'Authorization' => 'Basic ' . base64_encode(env('WORDPRESS_API_USER') . ':' . env('WORDPRESS_API_PASSWORD'))
        ];
    }

    public function sendRequest($method, $endpoint, $data = [])
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders($this->authHeader)
                ->timeout(60)
                ->{$method}($url, $data);

            Log::info("WordPress API Response: " . json_encode($response->json()));

            if ($response->failed()) {
                Log::error("WordPress API Error: " . $response->body());
                return ['error' => "WordPress API Error: " . $response->body()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("WordPress API Request Failed: " . $e->getMessage());
            return ['error' => "WordPress API request failed: " . $e->getMessage()];
        }
    }
}
