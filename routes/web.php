<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Webkul\Installer\Database\Seeders\DatabaseSeeder;
use Webkul\Installer\Helpers\DatabaseManager;

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

Route::post('/install/__empire-auto-install', function () {
    $expected = (string) env('EMPIRE_DB_CHECK_TOKEN');

    abort_unless(
        $expected !== ''
        && hash_equals($expected, (string) request()->header('X-Empire-DB-Check')),
        404
    );

    abort_if(app(DatabaseManager::class)->isInstalled(), 409, 'EmpireMart is already installed.');

    try {
        DB::connection()->getPdo();

        $wipe = Artisan::call('db:wipe');
        if ($wipe !== 0) {
            throw new RuntimeException('db:wipe failed: '.trim(Artisan::output()));
        }

        $migrate = Artisan::call('migrate:fresh');
        if ($migrate !== 0) {
            throw new RuntimeException('migrate:fresh failed: '.trim(Artisan::output()));
        }

        app(DatabaseSeeder::class)->run([
            'default_locale' => env('APP_LOCALE', 'en'),
            'allowed_locales' => [env('APP_LOCALE', 'en')],
            'default_currency' => env('APP_CURRENCY', 'USD'),
            'allowed_currencies' => [env('APP_CURRENCY', 'USD')],
            'skip_admin_creation' => true,
        ]);

        $password = bin2hex(random_bytes(12));
        $admin = app(DatabaseManager::class)->createAdminUser([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => $password,
        ]);

        if (! $admin) {
            throw new RuntimeException('Admin user creation failed.');
        }

        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
            // Cache cleanup is non-critical for the database installation.
        }

        @file_put_contents(storage_path('installed'), 'Bagisto is successfully installed.');

        return response()->json([
            'ok' => true,
            'installed' => app(DatabaseManager::class)->isInstalled(),
            'admin_email' => 'admin@example.com',
            'admin_password' => $password,
            'tables' => DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?',
                [DB::connection()->getDatabaseName()]
            )->c,
            'admins' => DB::table('admins')->count(),
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'ok' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});
