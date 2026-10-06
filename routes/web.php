<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/__empire-db-check', function () {
    $expected = (string) env('EMPIRE_DB_CHECK_TOKEN');

    abort_unless(
        $expected !== ''
        && hash_equals($expected, (string) request()->header('X-Empire-DB-Check')),
        404
    );

    try {
        $pdo = DB::connection()->getPdo();
        $config = DB::connection()->getConfig();

        $hasAdmins = Schema::hasTable('admins');
        $adminCount = $hasAdmins ? DB::table('admins')->count() : 0;

        return response()->json([
            'ok' => true,
            'driver' => $config['driver'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $config['database'] ?? null,
            'pdo' => $pdo ? true : false,
            'admins_table' => $hasAdmins,
            'admins_count' => $adminCount,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'host' => DB::connection()->getConfig('host'),
            'port' => DB::connection()->getConfig('port'),
            'database' => DB::connection()->getConfig('database'),
            'error' => $e->getMessage(),
        ], 500);
    }
});
