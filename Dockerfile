# syntax=docker/dockerfile:1.7
# =============================================================================
# Campaign Operations Platform — production image
# One image, several roles (ROLE=web | horizon | scheduler):
#   web        nginx + php-fpm under supervisord
#   horizon    Laravel Horizon queue workers
#   scheduler  Laravel scheduler (schedule:work)
# =============================================================================

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# --- Base PHP runtime with required extensions ------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS php-base

RUN apk add --no-cache icu-libs libpq libzip postgresql-client nginx supervisor su-exec tini \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql pgsql zip \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps \
 && rm -rf /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# --- Composer dependencies (no dev) ------------------------------------------
FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --no-autoloader

# --- Frontend build (needs vendor/livewire for the Livewire ESM bundle) -------
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY config/navigation.php ./config/navigation.php
COPY --from=vendor /var/www/html/vendor/livewire ./vendor/livewire
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources ./vendor/laravel/framework/src/Illuminate/Pagination/resources
RUN npm run build

# --- Runtime image -----------------------------------------------------------
FROM php-base AS runtime

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && rm /usr/bin/composer \
 && rm -rf node_modules tests .github \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache /run/nginx \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=json \
    ROLE=web

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD if [ "$ROLE" = "web" ]; then wget -qO- http://127.0.0.1:8080/up >/dev/null || exit 1; fi

ENTRYPOINT ["/sbin/tini", "--", "entrypoint"]
