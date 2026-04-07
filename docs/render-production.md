# Render Production Deployment (Docker)

This app is configured to run on Render Web Service using Docker with Nginx + PHP-FPM.

## Runtime flow

1. Container starts with `start.sh`.
2. Nginx config is rendered with Render-assigned `PORT`.
3. Laravel runs:
   - `php artisan package:discover --ansi || true`
   - `php artisan migrate --force`
   - `php artisan storage:link || true`
4. `php-fpm` starts in background.
5. `nginx` runs in foreground.

## Health check

Set health check path to `/up` in Render.

## Render settings

- Runtime: Docker
- Dockerfile path: `Dockerfile`
- Health Check Path: `/up`
- Auto Deploy: enabled (optional)

## Required environment variables

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-render-service>.onrender.com`
- `APP_KEY=<generated-app-key>`
- `LOG_CHANNEL=stderr`
- `DB_CONNECTION=pgsql`
- `DB_HOST=<supabase-host>`
- `DB_PORT=5432`
- `DB_DATABASE=<supabase-db>`
- `DB_USERNAME=<supabase-user>`
- `DB_PASSWORD=<supabase-password>`

Optional mail hardening variables for 2FA delivery:
- `MAIL_MAILER=smtp`
- `MAIL_HOST=<smtp-host>`
- `MAIL_PORT=<smtp-port>`
- `MAIL_USERNAME=<smtp-username>`
- `MAIL_PASSWORD=<smtp-password>`
- `MAIL_ENCRYPTION=tls`
- `MAIL_FROM_ADDRESS=<from-email>`
- `MAIL_FROM_NAME="GovSupply Connect"`

## Notes

- This setup intentionally avoids `php artisan serve` in production.
- Migrations still run at startup to match current deployment behavior.
- Storage link creation is idempotent and safe with `|| true`.
