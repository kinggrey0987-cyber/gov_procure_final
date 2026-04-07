FROM composer:2 AS composer
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize

FROM node:20-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM php:8.3-fpm-alpine

WORKDIR /app

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    nginx \
    gettext \
    libpq-dev \
    oniguruma-dev \
    icu-dev \
    libzip-dev \
    zip

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    intl \
    zip

COPY --from=composer /app /app
COPY --from=frontend /app/public/build /app/public/build

COPY deploy/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh \
    && rm -f /etc/nginx/http.d/default.conf \
    && mkdir -p /run/nginx /var/lib/nginx/tmp \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD ["start.sh"]