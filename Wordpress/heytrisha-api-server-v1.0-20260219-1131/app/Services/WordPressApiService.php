<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressApiService
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
