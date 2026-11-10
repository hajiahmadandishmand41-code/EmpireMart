#!/bin/sh
set -eu

APP_DIR="${APP_DIR:-/var/www/bagisto}"
cd "$APP_DIR"

if [ -z "${APP_KEY:-}" ]; then
    echo "[EmpireMart] ERROR: APP_KEY is not configured."
    exit 1
fi

# Railway mounts the persistent volume over storage/, so recreate Laravel's
# writable runtime directories on every container start.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /run/php

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Ensure public/storage points into the persistent storage volume.
php artisan storage:link --force --no-interaction

# Do not run migrations, db:wipe, migrate:fresh, or seeders at startup.
# The first installation is completed once through Bagisto's web installer.
exec "$@"
