<?php
// Simple health check endpoint for container orchestration
http_response_code(200);
header('Content-Type: application/json');
$checks = [
    'status' => 'ok',
    'time' => gmdate('c'),
    'php_fpm' => function_exists('opcache_get_status') ? 'up' : 'unknown',
];
echo json_encode($checks);
