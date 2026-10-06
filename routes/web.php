<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/install/__empire-db-check', function () {
    $expected = (string) env('EMPIRE_DB_CHECK_TOKEN');

    abort_unless(
        $expected !== ''
        && hash_equals($expected, (string) request()->header('X-Empire-DB-Check')),
        404
    );

    try {
        $config = DB::connection()->getConfig();
        $pdo = DB::connection()->getPdo();
        $hasAdmins = Schema::hasTable('admins');
        $adminCount = $hasAdmins ? DB::table('admins')->count() : 0;

        return response()->json([
            'ok' => true,
            'driver' => $config['driver'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $config['database'] ?? null,
            'pdo' => (bool) $pdo,
            'admins_table' => $hasAdmins,
            'admins_count' => $adminCount,
        ]);
    } catch (\Throwable $e) {
        $config = DB::connection()->getConfig();

        return response()->json([
            'ok' => false,
            'driver' => $config['driver'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $config['database'] ?? null,
            'error' => $e->getMessage(),
        ], 500);
    }
});
