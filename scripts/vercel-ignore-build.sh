#!/bin/sh
#
# Vercel cannot build or run this repository.
#
# EmpireMart is a Bagisto store: a Laravel application that needs PHP 8.3 with the
# intl, gd, mbstring and pdo_mysql extensions, a MySQL database, and a writable
# storage directory for compiled views, caches and logs. Vercel's builder finds the
# Vite configuration in the repository root and tries to build a static front end
# from it, which cannot produce a working store - the deployment has failed on every
# commit since the sources were imported.
#
# So the build is skipped here, and the reason is printed into the build log. Deploy
# the store from this repository's production images (docker/production, published by
# .github/workflows/docker_publish.yml) or on a PHP host; docs/afghanistan-store.md
# has the steps and the settings.
#
# Vercel's rule: exit code 1 continues the build, exit code 0 skips it. If this store
# ever moves behind something Vercel can build - a static front end, for instance -
# delete this file and the ignoreCommand in vercel.json.
#
set -eu

echo "Vercel build skipped on purpose."
echo "This repository is a PHP/MySQL Bagisto store, which Vercel cannot build or run."
echo "Deploy it from docker/production, or from a PHP 8.3 host - see docs/afghanistan-store.md."

exit 0
