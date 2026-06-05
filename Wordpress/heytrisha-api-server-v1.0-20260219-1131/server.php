<?php
/**
 * Router for PHP built-in server.
 *
 * Start with:
 *   php -S localhost:8000 server.php
 *
 * This forwards non-static requests to `public/index.php`.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$public_dir = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$path = $public_dir . $uri;

// Serve existing static files directly.
if ($uri !== '/' && is_file($path)) {
    return false;
}

require $public_dir . DIRECTORY_SEPARATOR . 'index.php';

