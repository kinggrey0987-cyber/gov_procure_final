#!/usr/bin/env sh
set -eu

export PORT="${PORT:-10000}"

envsubst '$PORT' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan optimize:clear || true
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear

php-fpm -D

if [ "${FORCE_DEMO_RESEED_ON_START:-false}" = "true" ]; then
  (
    sleep 10
    echo "[seed] starting workflow-only demo reset (with item updates)..."
    php artisan data:reset-transactions --no-interaction
    echo "[seed] workflow-only demo reset finished."
  ) >> /proc/1/fd/1 2>> /proc/1/fd/2 &
elif [ "${RUN_DEMO_SEED:-false}" = "true" ]; then
  (
    sleep 10
    echo "[seed] starting normal demo seed..."
    php artisan db:seed --force
    echo "[seed] normal demo seed finished."
  ) >> /proc/1/fd/1 2>> /proc/1/fd/2 &
fi

exec nginx -g 'daemon off;'
