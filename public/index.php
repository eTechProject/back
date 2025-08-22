<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    try {
        return new Kernel($context['APP_ENV'] ?? 'prod', (bool) ($context['APP_DEBUG'] ?? false));
    } catch (\Throwable $e) {
        // Early boot failure (container compilation, config, etc.)
        error_log('BOOT FAILURE: '.$e->getMessage().' ['.$e->getFile().':'.$e->getLine().']');
        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'error' => 'boot_failure',
            'message' => $e->getMessage(),
            'class' => $e::class,
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            // Avoid full trace length; keep short for security while debugging
            'trace_head' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5),
            'hint' => 'Temporarily set APP_DEBUG=1 for full stack trace. Remove this catch once resolved.'
        ]);
        exit(1);
    }
};
