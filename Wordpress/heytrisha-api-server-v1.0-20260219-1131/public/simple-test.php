<?php
/**
 * Ultra-simple test - works even if Laravel is broken
 * This file should work if PHP is working at all
 */
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'PHP is working!',
    'php_version' => PHP_VERSION,
    'server_time' => date('Y-m-d H:i:s'),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_path' => __FILE__,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
]);




