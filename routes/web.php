<?php

/**
 * ----------------------------------------------------------------------------
 * TEMPORARY — EmpireMart production bootstrap routes. REMOVE ME.
 * ----------------------------------------------------------------------------
 * These two routes exist only to bootstrap Bagisto on the production database
 * through a short-lived, token-protected HTTP channel (the environments that
 * could normally host the installer CLI cannot reach the database). They must
 * be deleted together with the `EMPIRE_INSTALL_TOKEN` environment variable
 * as soon as the installation is verified (see the removal commit).
 *
 * Security properties:
 *  - Both routes answer 404 unless a non-empty `EMPIRE_INSTALL_TOKEN` env var
 *    is configured AND the request carries it in the `X-Empire-Install-Token`
 *    header (compared with `hash_equals`).
 *  - No endpoint ever returns secrets: the database password is never read
 *    out, and a generated admin password is returned exactly once, only to
 *    the token holder, at the moment the admin record is created.
 *  - The install endpoint is non-destructive: it refuses to run any wipe /
 *    migrate:fresh / drop operation against a database that already contains
 *    tables.
 * ----------------------------------------------------------------------------
 */

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Webkul\Installer\Database\Seeders\DatabaseSeeder;
use Webkul\Installer\Helpers\DatabaseManager;

if (! function_exists('empireGuardToken')) {
    /**
     * Abort with 404 unless the temporary install token is configured and
     * presented in the request header.
     */
    function empireGuardToken(): void
    {
        $expected = (string) env('EMPIRE_INSTALL_TOKEN');

        abort_unless(
            $expected !== ''
            && hash_equals($expected, (string) request()->header('X-Empire-Install-Token')),
            404
        );
    }
}

if (! function_exists('empireDbStatus')) {
    /**
     * Collect read-only database diagnostics. Secret values are never read
     * into the payload; the password stays inside PDO only.
     */
    function empireDbStatus(): array
    {
        $config = DB::connection()->getConfig();

        $payload = [
            'driver' => $config['driver'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
        ];

        try {
            DB::connection()->getPdo();

            $payload['pdo'] = true;
        } catch (\Throwable $e) {
            /**
             * Connection errors may contain the error message from the server;
             * they never contain the password, so the message itself is safe
             * to report to the token holder for root-cause analysis.
             */
            $payload['pdo'] = false;

            $payload['error'] = $e->getMessage();

            return $payload;
        }

        $payload['tables_total'] = (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?',
            [$config['database']]
        )->c;

        $payload['migrations_table'] = Schema::hasTable('migrations');
        $payload['migrations_count'] = $payload['migrations_table']
            ? DB::table('migrations')->count()
            : 0;

        $payload['admins_table'] = Schema::hasTable('admins');
        $payload['admins_count'] = $payload['admins_table']
            ? DB::table('admins')->count()
            : 0;

        $coreTables = ['channels', 'categories', 'products', 'customers', 'orders', 'locales', 'currencies'];

        $payload['core_tables_missing'] = array_values(array_filter(
            $coreTables,
            fn (string $table) => ! Schema::hasTable($table)
        ));

        $payload['installed'] = app(DatabaseManager::class)->isInstalled();

        return $payload;
    }
}

Route::get('/__empire/status', function () {
    empireGuardToken();

    return response()->json(array_merge(
        [
            'ok' => true,
            'app' => [
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'marker_file' => file_exists(storage_path('installed')),
        ],
        ['db' => empireDbStatus()]
    ));
})->withoutMiddleware([VerifyCsrfToken::class]);

Route::post('/__empire/install', function () {
    empireGuardToken();

    /**
     * Which stage(s) to execute. Defaults to the full unattended sequence.
     * Staging keeps every single HTTP call short enough to run inside a
     * serverless function time budget and makes retries idempotent.
     */
    $stage = (string) request()->input('stage', 'all');

    $allowedStages = ['migrate', 'seed', 'admin', 'all'];

    if (! in_array($stage, $allowedStages, true)) {
        return response()->json([
            'ok' => false,
            'message' => "Invalid stage '{$stage}'. Allowed: ".implode(', ', $allowedStages).'.',
        ], 422);
    }

    /**
     * Guard 1: never touch an application that is already installed.
     */
    if (app(DatabaseManager::class)->isInstalled()) {
        return response()->json([
            'ok' => false,
            'message' => 'EmpireMart is already installed. No action taken.',
            'db' => empireDbStatus(),
        ], 409);
    }

    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'stage' => 'connect',
            'error' => $e->getMessage(),
        ], 500);
    }

    $tablesTotal = (int) DB::selectOne(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ?',
        [DB::connection()->getDatabaseName()]
    )->c;

    $steps = [];
    $adminPassword = null;

    try {
        /**
         * Guard 2: non-empty database — only step in to create a missing
         * admin on an otherwise migrated schema; never run anything that
         * destroys existing data.
         */
        if ($tablesTotal > 0 && ! Schema::hasTable('admins')) {
            return response()->json([
                'ok' => false,
                'mode' => 'refused',
                'tables_total' => $tablesTotal,
                'message' => 'Database is not empty but the schema is not a known Bagisto layout (admins table missing). Refusing any automatic change; manual review required.',
            ], 422);
        }

        if ($tablesTotal > 0 && Schema::hasTable('admins') && DB::table('admins')->count() > 0) {
            return response()->json([
                'ok' => false,
                'mode' => 'refused',
                'tables_total' => $tablesTotal,
                'message' => 'Database already contains a migrated schema and at least one admin. Refusing any automatic change.',
                'db' => empireDbStatus(),
            ], 422);
        }

        /**
         * Stage: migrate. Safe on an empty database; Laravel `migrate` only
         * executes pending migrations, so re-running it is idempotent.
         */
        if (in_array($stage, ['migrate', 'all'], true) && ! Schema::hasTable('migrations')) {
            $code = Artisan::call('migrate', ['--force' => true]);

            if ($code !== 0) {
                throw new RuntimeException('migrate failed: '.trim(Artisan::output()));
            }

            $steps[] = 'migrate';
        }

        /**
         * Stage: seed the required base data (channel, locales, currencies,
         * attributes, ...) via the official Bagisto installer seeder.
         * Skipped entirely when the seeder has already populated `channels`.
         */
        if (in_array($stage, ['seed', 'all'], true)) {
            if (! Schema::hasTable('channels') || DB::table('channels')->count() === 0) {
                app(DatabaseSeeder::class)->run([
                    'default_locale' => env('APP_LOCALE', 'en'),
                    'allowed_locales' => [env('APP_LOCALE', 'en')],
                    'default_currency' => env('APP_CURRENCY', 'USD'),
                    'allowed_currencies' => [env('APP_CURRENCY', 'USD')],
                    'skip_admin_creation' => true,
                ]);

                $steps[] = 'seed';
            } else {
                $steps[] = 'seed (skipped, already seeded)';
            }
        }

        /**
         * Stage: create the first admin. A strong random password is
         * generated, stored as bcrypt and returned to the token holder in
         * this single response. Once the admin exists, subsequent calls
         * never expose it.
         */
        if (in_array($stage, ['admin', 'all'], true)) {
            if (Schema::hasTable('admins') && DB::table('admins')->count() === 0) {
                $adminEmail = (string) request()->input('email', DatabaseManager::DEFAULT_ADMIN_EMAIL);
                $adminName = (string) request()->input('name', 'Administrator');

                if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
                    return response()->json([
                        'ok' => false,
                        'stage' => 'admin',
                        'message' => "Invalid admin email '{$adminEmail}'.",
                    ], 422);
                }

                $adminPassword = bin2hex(random_bytes(12));

                if (! app(DatabaseManager::class)->createAdminUser([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => $adminPassword,
                ])) {
                    throw new RuntimeException('Admin user creation failed.');
                }

                $steps[] = 'admin';
            } else {
                $steps[] = 'admin (skipped, already exists)';
            }
        }

        /**
         * Best-effort marker + cache cleanup; never fatal on a read-only
         * filesystem.
         */
        @file_put_contents(storage_path('installed'), 'Bagisto is successfully installed.');

        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
            // Non-critical on serverless runtimes.
        }

        $response = [
            'ok' => true,
            'stage' => $stage,
            'steps' => $steps,
            'db' => empireDbStatus(),
        ];

        if ($adminPassword !== null) {
            $response['admin_email'] = $adminEmail;

            $response['admin_password_once'] = $adminPassword;

            $response['notice'] = 'This is the only time the generated admin password is shown. Store it now, sign in, and change it immediately.';
        }

        return response()->json($response);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'ok' => false,
            'stage' => $stage,
            'steps' => $steps,
            'error' => $e->getMessage(),
        ], 500);
    }
})->withoutMiddleware([VerifyCsrfToken::class]);
