#!/bin/sh
set -eu

cd /app

cat > .env <<EOF
APP_NAME="$APP_NAME"
APP_ENV="$APP_ENV"
APP_DEBUG="$APP_DEBUG"
APP_URL="$APP_URL"
APP_ADMIN_URL="$APP_ADMIN_URL"
APP_TIMEZONE="$APP_TIMEZONE"
APP_LOCALE="$APP_LOCALE"
APP_FALLBACK_LOCALE="$APP_FALLBACK_LOCALE"
APP_CURRENCY="$APP_CURRENCY"
DB_CONNECTION="$DB_CONNECTION"
DB_HOST="$DB_HOST"
DB_PORT="$DB_PORT"
DB_DATABASE="$DB_DATABASE"
DB_PREFIX="$DB_PREFIX"
DB_USERNAME="$DB_USERNAME"
DB_PASSWORD="$DB_PASSWORD"
SESSION_DRIVER="$SESSION_DRIVER"
QUEUE_CONNECTION="$QUEUE_CONNECTION"
CACHE_STORE="$CACHE_STORE"
FILESYSTEM_DISK="$FILESYSTEM_DISK"
LOG_CHANNEL="$LOG_CHANNEL"
LOG_LEVEL="$LOG_LEVEL"
EOF

echo "EmpireMart installer: checking database connection..."
php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE").";charset=utf8mb4", getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); echo "DB_CONNECTION_OK
";'

php artisan key:generate --force
php artisan bagisto:install --no-interaction

php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$db = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=".$pdo->quote($db))->fetchColumn();
$admins = (int) Illuminate\Support\Facades\DB::table("admins")->count();
echo "INSTALL_VERIFY tables={$tables} admins={$admins}
";
if ($tables < 1 || $admins < 1) exit(3);
'

echo "EMPIREMART_INSTALL_FINISHED"
exec sleep 1800
