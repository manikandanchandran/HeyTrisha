<?php
/**
 * Simple API Test Script
 * Access this file directly to test if Laravel is working
 * URL: /wp-content/plugins/heytrisha-woo/api/public/test-api.php
 */

header('Content-Type: application/json');

$results = [
    'test' => 'API Diagnostic',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: PHP Version
$results['checks']['php_version'] = [
    'status' => version_compare(PHP_VERSION, '7.4.3', '>=') ? 'ok' : 'fail',
    'value' => PHP_VERSION,
    'required' => '7.4.3+'
];

// Check 2: Vendor folder
$vendor_path = __DIR__ . '/../vendor/autoload.php';
$results['checks']['vendor'] = [
    'status' => file_exists($vendor_path) ? 'ok' : 'fail',
    'path' => $vendor_path,
    'exists' => file_exists($vendor_path)
];

// Check 3: .env file
$env_path = __DIR__ . '/../.env';
$results['checks']['env_file'] = [
    'status' => file_exists($env_path) ? 'ok' : 'fail',
    'path' => $env_path,
    'exists' => file_exists($env_path)
];

// Check 4: APP_KEY
if (file_exists($env_path)) {
    $env_content = file_get_contents($env_path);
    $has_app_key = preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]+={0,2}$/m', $env_content);
    $results['checks']['app_key'] = [
        'status' => $has_app_key ? 'ok' : 'fail',
        'exists' => $has_app_key,
        'value' => $has_app_key ? 'Set' : 'Missing'
    ];
} else {
    $results['checks']['app_key'] = [
        'status' => 'fail',
        'exists' => false,
        'value' => 'Cannot check - .env file missing'
    ];
}

// Check 5: Storage permissions
$storage_path = __DIR__ . '/../storage';
$results['checks']['storage'] = [
    'status' => is_writable($storage_path) ? 'ok' : 'fail',
    'path' => $storage_path,
    'writable' => is_writable($storage_path),
    'exists' => file_exists($storage_path)
];

// Check 6: Try to load Laravel
if (file_exists($vendor_path)) {
    try {
        require $vendor_path;
        
        // Try to load .env
        $env_file = __DIR__.'/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $value = trim($value, '"\'');
                    if (!empty($key)) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
        
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $results['checks']['laravel_bootstrap'] = [
            'status' => 'ok',
            'message' => 'Laravel loaded successfully',
            'version' => $app->version()
        ];
    } catch (\Throwable $e) {
        $results['checks']['laravel_bootstrap'] = [
            'status' => 'fail',
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
} else {
    $results['checks']['laravel_bootstrap'] = [
        'status' => 'fail',
        'message' => 'Vendor folder not found'
    ];
}

// Summary
$all_ok = true;
foreach ($results['checks'] as $check) {
    if (isset($check['status']) && $check['status'] !== 'ok') {
        $all_ok = false;
        break;
    }
}

$results['summary'] = [
    'all_checks_passed' => $all_ok,
    'total_checks' => count($results['checks']),
    'passed' => count(array_filter($results['checks'], function($c) { return isset($c['status']) && $c['status'] === 'ok'; })),
    'failed' => count(array_filter($results['checks'], function($c) { return isset($c['status']) && $c['status'] === 'fail'; }))
];

echo json_encode($results, JSON_PRETTY_PRINT);






