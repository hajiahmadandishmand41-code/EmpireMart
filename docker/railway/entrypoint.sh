#!/bin/sh
set -eu

cd /var/www/html

# Railway normally provides PORT=8080. Keep Apache in sync if it changes.
PORT="${PORT:-8080}"
if [ "$PORT" != "8080" ]; then
    sed -i "s/^Listen 8080$/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost *:8080>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage/framework storage/logs bootstrap/cache
chown www-data:www-data storage/app storage/app/public
chmod -R ug+rwX storage/framework storage/logs bootstrap/cache

# The public disk is persisted on a Railway volume. Recreate only the symlink;
# do not run migrations, seeders, or reset any database during container boot.
php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true

exec "$@"
