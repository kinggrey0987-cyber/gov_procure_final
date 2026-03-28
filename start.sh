#!/usr/bin/env sh
set -eu

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan migrate --force

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"