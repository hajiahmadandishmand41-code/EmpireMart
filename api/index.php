<?php

use Illuminate\\Contracts\\Http\\Kernel;
use Illuminate\\Http\\Request;
use Throwable;

defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));

ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require __DIR__.'/../vendor/autoload.php';

    $app = require_once __DIR__.'/../bootstrap/app.php';

    // Vercel's filesystem is ephemeral. Keep Laravel's writable runtime files in /tmp.
    if (getenv('VERCEL') === '1' || ($_SERVER['VERCEL'] ?? null) === '1') {
        $storagePath = sys_get_temp_dir().'/empiremart/storage';

        $app->useStoragePath($storagePath);

        foreach ([
            'app',
            'app/public',
            'framework/cache/data',
            'framework/sessions',
            'framework/views',
            'logs',
        ] as $directory) {
            $path = $storagePath.'/'.$directory;

            if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
                throw new RuntimeException('Unable to create temporary Laravel storage directory: '.$directory);
            }
        }
    }

    $kernel = $app->make(Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    // Log enough context to diagnose startup failures without exposing secrets to visitors.
    $message = preg_replace('/(password|secret|token|key)\\s*[=:]\\s*[^\\s,;]+/i', '$1=[redacted]', $exception->getMessage());
    $frames = [];

    foreach (array_slice($exception->getTrace(), 0, 10) as $frame) {
        $frames[] = ($frame['file'] ?? '[internal]').':'.($frame['line'] ?? 0).' '.($frame['function'] ?? '[unknown]');
    }

    error_log(sprintf(
        '[EmpireMart PHP startup] %s: %s at %s:%d; trace: %s',
        get_class($exception),
        $message,
        $exception->getFile(),
        $exception->getLine(),
        implode(' <- ', $frames)
    ));

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
    }

    echo "Internal Server Error\n";
}
