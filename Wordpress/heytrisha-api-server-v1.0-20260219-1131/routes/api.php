<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NLPController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\SpecificationController;
use App\Http\Middleware\ApiKeyMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public endpoints (no authentication required)

// Simple health check endpoint
Route::get('/health', function () {
    try {
        $info = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'server_time' => date('Y-m-d H:i:s'),
        ];

        return response()->json($info, 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Health check failed.'
        ], 500);
    }
});

// Diagnostic endpoint
Route::get('/diagnostic', function () {
    if (!app()->environment('local')) {
        abort(404);
    }

    try {
        $diagnostics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_time' => date('Y-m-d H:i:s'),
            'vendor_exists' => file_exists(base_path('vendor/autoload.php')),
            'env_file_exists' => file_exists(base_path('.env')),
            'storage_writable' => is_writable(storage_path()),
            'bootstrap_cache_writable' => is_writable(base_path('bootstrap/cache')),
        ];
        
        // Check APP_KEY
        try {
            $appKey = env('APP_KEY', '');
            $diagnostics['app_key_exists'] = !empty($appKey);
        } catch (\Exception $e) {
            $diagnostics['app_key_error'] = true;
        }
        
        // Check database
        try {
            \DB::connection()->getPdo();
            $diagnostics['database_connected'] = true;
            $diagnostics['sites_table_exists'] = \Schema::hasTable('sites');
        } catch (\Exception $e) {
            $diagnostics['database_connected'] = false;
            $diagnostics['database_error'] = true;
        }
        
        return response()->json($diagnostics, 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Diagnostic failed.'
        ], 500);
    }
});

// Site registration (public - allows new sites to register)
Route::post('/register', [SiteController::class, 'register']);

// Protected endpoints (require API key authentication)
Route::middleware('auth:sanctum')->group(function () {
    // For future use with Sanctum if needed
});

// Custom API key authentication middleware
// Using full class name instead of alias to avoid "Target class [api.key] does not exist" error
Route::middleware(ApiKeyMiddleware::class)->group(function () {
    // Query processing (NEW SECURE VERSION - calls WordPress REST API)
    Route::post('/query', [QueryController::class, 'process']);

    // Specification ingestion — accepts NL / structured text, extracts allowlist + embeds chunks
    Route::post('/specification/ingest', [SpecificationController::class, 'ingest']);
    
    // Legacy query endpoint (OLD VERSION - direct database access)
    // Keep this for backward compatibility but mark as deprecated
    Route::post('/query-legacy', [NLPController::class, 'handleQuery']);
    
    // Site management
    Route::put('/config', [SiteController::class, 'updateConfig']);
    Route::post('/regenerate-key', [SiteController::class, 'regenerateAPIKey']);
    Route::get('/site/info', [SiteController::class, 'getInfo']);
});
