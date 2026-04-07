#!/usr/bin/env sh
set -eu

export PORT="${PORT:-10000}"

envsubst '$PORT' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan optimize:clear || true
php artisan migrate --force

php-fpm -D

exec nginx -g 'daemon off;'