# syntax=docker/dockerfile:1

# Stage 1: PHP dependencies (composer is not shipped in the final image)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# ext-amqp etc. live in the runtime stage, not in the composer image
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize

# Stage 2: runtime
FROM php:8.3-fpm-alpine AS app
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql amqp-2.2.0 gd zip opcache
WORKDIR /app
COPY --from=vendor /app /app
# Downloaded product images; owned by www-data so the named volume inherits it
RUN mkdir -p storage/images storage/imports && chown -R www-data:www-data storage
