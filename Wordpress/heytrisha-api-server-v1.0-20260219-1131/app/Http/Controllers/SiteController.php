<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class SiteController extends Controller
{
    /**
     * Register a new site
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'site_url' => 'required|url|max:255',
            'openai_key' => 'required|string|min:20',
            'email' => 'required|email|max:255',
            'username' => 'required|string|min:3|max:255|unique:sites,username',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'db_name' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'required|string',
            'wordpress_version' => 'nullable|string|max:50',
            'woocommerce_version' => 'nullable|string|max:50',
            'plugin_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $validated = $validator->validated();

        // Check if site already exists
        $existingSite = Site::where('site_url', $validated['site_url'])->first();
        
        if ($existingSite) {
            return response()->json([
                'success' => false,
                'message' => 'Site already registered. Please use the update endpoint or contact support.'
            ], 409);
        }

        // Generate API key
        $apiKey = Site::generateAPIKey();

        // Create new site
        try {
            $site = new Site();
            $site->site_url = $validated['site_url'];
            $site->api_key_hash = Site::hashAPIKey($apiKey);
            $site->setOpenAIKey($validated['openai_key']);
            $site->email = $validated['email'];
            $site->username = $validated['username'];
            $site->password = Hash::make($validated['password']);
            $site->first_name = $validated['first_name'];
            $site->last_name = $validated['last_name'];
            $site->db_name = $validated['db_name'];
            $site->db_username = $validated['db_username'];
            $site->setDbPassword($validated['db_password']);
            $site->wordpress_version = $validated['wordpress_version'] ?? null;
            $site->woocommerce_version = $validated['woocommerce_version'] ?? null;
            $site->plugin_version = $validated['plugin_version'] ?? null;
            $site->is_active = true;
            $site->save();

            Log::info('New site registered', [
                'site_url' => $validated['site_url'],
                'email' => $validated['email'],
                'username' => $validated['username']
            ]);

            return response()->json([
                'success' => true,
                'api_key' => $apiKey,
                'message' => 'Site registered successfully. Please save this API key securely - it will not be shown again.'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Site registration failed', [
                'site_url' => $validated['site_url'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update site configuration
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateConfig(Request $request)
    {
        // Get site from API key
        $apiKey = $request->bearerToken();
        $site = Site::findByAPIKey($apiKey);

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key'
            ], 403);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'openai_key' => 'nullable|string|min:20',
            'email' => 'nullable|email|max:255',
            // Optional profile + database fields (same rules as register, but nullable)
            'username' => 'nullable|string|min:3|max:255',
            'password' => 'nullable|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'db_name' => 'nullable|string|max:255',
            'db_username' => 'nullable|string|max:255',
            'db_password' => 'nullable|string',
            'wordpress_version' => 'nullable|string|max:50',
            'woocommerce_version' => 'nullable|string|max:50',
            'plugin_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $validated = $validator->validated();

        // Update fields
        if (isset($validated['openai_key'])) {
            $site->setOpenAIKey($validated['openai_key']);
        }

        if (isset($validated['email'])) {
            $site->email = $validated['email'];
        }

        // Optional profile fields
        if (isset($validated['username']) && $validated['username'] !== '') {
            $site->username = $validated['username'];
        }

        if (isset($validated['password']) && $validated['password'] !== '') {
            $site->password = Hash::make($validated['password']);
        }

        if (isset($validated['first_name']) && $validated['first_name'] !== '') {
            $site->first_name = $validated['first_name'];
        }

        if (isset($validated['last_name']) && $validated['last_name'] !== '') {
            $site->last_name = $validated['last_name'];
        }

        // Optional DB + version fields
        if (isset($validated['db_name']) && $validated['db_name'] !== '') {
            $site->db_name = $validated['db_name'];
        }

        if (isset($validated['db_username']) && $validated['db_username'] !== '') {
            $site->db_username = $validated['db_username'];
        }

        if (isset($validated['db_password']) && $validated['db_password'] !== '') {
            $site->setDbPassword($validated['db_password']);
        }

        if (isset($validated['wordpress_version']) && $validated['wordpress_version'] !== '') {
            $site->wordpress_version = $validated['wordpress_version'];
        }

        if (isset($validated['woocommerce_version']) && $validated['woocommerce_version'] !== '') {
            $site->woocommerce_version = $validated['woocommerce_version'];
        }

        if (isset($validated['plugin_version']) && $validated['plugin_version'] !== '') {
            $site->plugin_version = $validated['plugin_version'];
        }

        $site->save();

        Log::info('Site config updated', [
            'site_url' => $site->site_url
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configuration updated successfully'
        ], 200);
    }

    /**
     * Regenerate API key
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateAPIKey(Request $request)
    {
        // Get site from current API key
        $apiKey = $request->bearerToken();
        $site = Site::findByAPIKey($apiKey);

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key'
            ], 403);
        }

        // Generate new API key
        $newAPIKey = Site::generateAPIKey();
        $site->api_key_hash = Site::hashAPIKey($newAPIKey);
        $site->save();

        Log::info('API key regenerated', [
            'site_url' => $site->site_url
        ]);

        return response()->json([
            'success' => true,
            'new_api_key' => $newAPIKey,
            'message' => 'API key regenerated successfully. Please update your WordPress plugin settings with the new key. The old key is now invalid.'
        ], 200);
    }

    /**
     * Get site info
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInfo(Request $request)
    {
        // Get site from API key
        $apiKey = $request->bearerToken();
        $site = Site::findByAPIKey($apiKey);

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'site' => [
                'site_url' => $site->site_url,
                'email' => $site->email,
                'wordpress_version' => $site->wordpress_version,
                'woocommerce_version' => $site->woocommerce_version,
                'plugin_version' => $site->plugin_version,
                'query_count' => $site->query_count,
                'last_query_at' => $site->last_query_at,
                'registered_at' => $site->created_at,
            ]
        ], 200);
    }
}



