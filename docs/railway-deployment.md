# Deploy EmpireMart on Railway

This service runs the **EmpireMart checkout in this repository** (Bagisto 2.4.10 / Laravel 12), not a newly cloned upstream Bagisto copy.

## Services and storage

Use one Railway project with two independent services:

1. **MySQL 8.0** for the store database. Give this service a persistent volume mounted at `/var/lib/mysql`.
2. **EmpireMart Web** from this repository. Keep the repository root as the build context, set the Dockerfile path to `docker/railway/Dockerfile`, expose port `8080`, and set the health-check path to `/health`.

Attach a separate persistent volume to EmpireMart Web at:

```text
/var/www/bagisto/storage
```

Do not mount a volume over `/var/www/bagisto`, `/var/www/bagisto/public`, or `/var/www/bagisto/bootstrap`: that would hide the application code or built assets.

The web image and the database must be separate services. Do not run MySQL inside the web container. Do not connect the new web service to any old installer database unless its contents have been inspected and backed up.

## Environment variables for the web service

Generate a Laravel key once, on a trusted machine with PHP:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Put the resulting value into Railway as `APP_KEY`. Do not commit it to Git or paste it into source files. Keep the same key after installation and across deployments.

Set these application values. Replace the `APP_URL` example with the HTTPS domain Railway gives the web service:

```dotenv
APP_NAME=EmpireMart
APP_ENV=local
APP_DEBUG=false
APP_URL=https://YOUR-RAILWAY-DOMAIN
APP_TIMEZONE=Asia/Kabul
APP_LOCALE=fa_AF
APP_FALLBACK_LOCALE=en
APP_CURRENCY=AFN
APP_DEFAULT_COUNTRY=AF
```

Set the database values with Railway reference variables pointing at the **new MySQL service**. If that service is named `MySQL`, the references are:

```dotenv
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

If the database service has a different Railway name, change the reference prefix to that exact service name. Do not use a public TCP proxy for communication between services in the same Railway project; use the private service references.

Keep the initial runtime values simple:

```dotenv
SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public
```

## First installation — important

1. Deploy the MySQL service and verify that it is healthy.
2. Create a **new, empty database** for EmpireMart. Do not point the installer at a database containing data.
3. Deploy the web service with the variables above. For the first installation only, use `APP_ENV=local` and `APP_DEBUG=false`. The Docker entrypoint requires `APP_KEY` to be set before the installer can load.
4. Open the Railway HTTPS domain. Bagisto should redirect to `/install`.
5. Complete the web installer. Select Dari (`fa_AF`), Afghani (`AFN`), Afghanistan (`AF`) and Asia/Kabul. Enter the database connection details if the installer asks for them. Create a unique administrator email and a strong password; do not use sample credentials.
6. Confirm the storefront, admin login, images and product pages work.
7. Change the Railway variable `APP_ENV` from `local` to `production` and redeploy. Keep `APP_DEBUG=false`.

**Do not run `php artisan bagisto:install --no-interaction` on the production service.** Bagisto's installer is a fresh-install command: it runs `db:wipe` and `migrate:fresh`. In a production environment, Laravel's production confirmation can also stop that non-interactive command before tables are created. The first-install web wizard is used here, against a new empty database, while `APP_ENV=local`; after it succeeds, the service is switched to production. See the [Bagisto installation guide](https://devdocs.bagisto.com/getting-started/installation) and [deployment guide](https://devdocs.bagisto.com/getting-started/deployment.html).

The entrypoint deliberately does **not** run migrations, wipe tables, seed data, or generate a new APP_KEY. This prevents ordinary restarts and deployments from modifying store data.

## Operational notes

- The PHP-FPM pool is deliberately small (four workers) and PHP memory is capped at 256 MB to avoid a large idle footprint on a small instance. Increase only after checking Railway memory metrics.
- The application upload limit in this image is 64 MB (HTTP body 70 MB). Increase both PHP and Nginx limits together if a real requirement warrants it.
- The persistent storage volume holds uploaded media and Laravel runtime files. Database backups and volume backups are separate; configure both before accepting real orders.
- The initial setup uses synchronous queues. Before enabling asynchronous jobs, create a separate worker service running the appropriate Laravel queue command. Add one scheduler service only when scheduled jobs are needed.
- Before deploying schema changes to a live shop, take a database backup and review the migration. Do not put `migrate:fresh` or `db:wipe` into a start command or an automatic deploy hook.
- The health endpoint calls a small PHP probe through PHP-FPM. It confirms Nginx and PHP-FPM respond, but it does not test the database. Check database connectivity, logs, admin login, checkout, and storage separately after deployment.
