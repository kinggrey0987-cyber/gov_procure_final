#!/usr/bin/env sh
set -eu

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan optimize:clear || true
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"