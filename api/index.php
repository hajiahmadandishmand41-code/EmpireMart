<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

if (getenv('VERCEL') === '1') {
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
            throw new RuntimeException('Unable to create temporary Laravel storage.');
        }
    }
}

$kernel = $app->make(Kernel::class);

$request = Request::capture();

$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
