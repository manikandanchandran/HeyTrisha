<?php
// Ultra-simple test - just echo JSON
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'php_version' => PHP_VERSION]);

