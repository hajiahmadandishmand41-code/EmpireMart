<?php

namespace Webkul\Installer\Helpers;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as BagistoDatabaseSeeder;
use Webkul\Installer\Database\Seeders\ProductTableSeeder;
use Webkul\Product\Console\Commands\Indexer;

class DatabaseManager
{
    /**
     * Admin User ID for which the admin user will be created during installation.
     */
    const int USER_ID = 1;

    /**
     * Default admin name for which the admin user will be created during installation.
     */
    const string DEFAULT_ADMIN_NAME = 'Admin';

    /**
     * Default admin email for which the admin user will be created during installation.
     */
    const string DEFAULT_ADMIN_EMAIL = 'admin@example.com';

    /**
     * Default admin password for which the admin user will be created during installation.
     */
    const string DEFAULT_ADMIN_PASSWORD = 'admin123';

    /**
     * Check if the application is installed.
     *
     * The database is the single source of truth for the installation state.
     * Environments with an ephemeral or read-only filesystem (for example
     * Vercel serverless functions) cannot persist `storage/installed` or a
     * local `.env` file, because configuration there arrives through real
     * environment variables. Requiring those files before consulting the
     * database would make such runtimes believe the application is never
     * installed and would endlessly redirect every request to the installer.
     *
     * Therefore the check only requires:
     *
     *  - a working connection to the configured database;
     *  - an `admins` table, which only exists after the migrations ran;
     *  - at least one administrator record, which only exists after the
     *    seeders created the first back-office user.
     */
    public function isInstalled(): bool
    {
        try {
            DB::connection()->getPdo();

            if (! DB::connection()->getDatabaseName()) {
                return false;
            }

            if (! Schema::hasTable('admins')) {
                return false;
            }

            return DB::table('admins')->exists();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check database connection.
     */
    public function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Drop all the tables and migrate in the database.
     */
    public function migrateFresh(): bool
    {
        try {
            Artisan::call('migrate:fresh');

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Seed the database.
     */
    public function seed($parameter): bool
    {
        try {
            app(BagistoDatabaseSeeder::class)->run($parameter);

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Generate fake product data.
     *
     * @return void|string
     */
    public function seedSampleProducts($parameters): bool
    {
        try {
            app(ProductTableSeeder::class)->run($parameters);

            Artisan::registerCommand(app(Indexer::class));

            Artisan::call('indexer:index', [
                '--mode' => ['full'],
                '--quiet' => true,
            ]);

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Create admin user.
     */
    public function createAdminUser(array $data = []): bool
    {
        try {
            DB::table('admins')->insert([
                'id' => self::USER_ID,
                'name' => $data['name'] ?? self::DEFAULT_ADMIN_NAME,
                'email' => $data['email'] ?? self::DEFAULT_ADMIN_EMAIL,
                'password' => bcrypt($data['password'] ?? self::DEFAULT_ADMIN_PASSWORD),
                'role_id' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }
}
