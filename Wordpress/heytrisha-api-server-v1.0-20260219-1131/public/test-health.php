<?php
/**
 * Simple health check endpoint that works before Laravel loads
 * Use this to test if PHP and basic file structure is working
 */

header('Content-Type: application/json');

$diagnostics = [
    'status' => 'ok',
    'php_version' => PHP_VERSION,
    'timestamp' => date('c'),
    'server_time' => date('Y-m-d H:i:s'),
];

// Check if vendor exists
$diagnostics['vendor_exists'] = file_exists(__DIR__ . '/../vendor/autoload.php');

// Check if .env exists
$diagnostics['env_exists'] = file_exists(__DIR__ . '/../.env');

// Check storage
$diagnostics['storage_exists'] = file_exists(__DIR__ . '/../storage');
$diagnostics['storage_writable'] = is_writable(__DIR__ . '/../storage');

// Check bootstrap cache
$diagnostics['bootstrap_cache_exists'] = file_exists(__DIR__ . '/../bootstrap/cache');
$diagnostics['bootstrap_cache_writable'] = is_writable(__DIR__ . '/../bootstrap/cache');

// Try to read .env APP_KEY
if ($diagnostics['env_exists']) {
    $env_content = file_get_contents(__DIR__ . '/../.env');
    $diagnostics['app_key_in_env'] = preg_match('/^APP_KEY=base64:/m', $env_content);
    if ($diagnostics['app_key_in_env']) {
        preg_match('/^APP_KEY=(.+)$/m', $env_content, $matches);
        $diagnostics['app_key_length'] = isset($matches[1]) ? strlen($matches[1]) : 0;
    }
}

// Try to load Laravel (if vendor exists)
if ($diagnostics['vendor_exists']) {
    try {
        require __DIR__ . '/../vendor/autoload.php';
        $app = require_once __DIR__ . '/../bootstrap/app.php';
        $diagnostics['laravel_loaded'] = true;
        $diagnostics['laravel_version'] = $app->version();
    } catch (Exception $e) {
        $diagnostics['laravel_loaded'] = false;
        $diagnostics['laravel_error'] = $e->getMessage();
        $diagnostics['laravel_error_file'] = $e->getFile() . ':' . $e->getLine();
    }
} else {
    $diagnostics['laravel_loaded'] = false;
    $diagnostics['laravel_error'] = 'Vendor directory not found. Run: composer install';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);




