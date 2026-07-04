# ─────────────────────────────────────────────
# Stage 1 — Composer dependencies
# ─────────────────────────────────────────────
FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views \ && chmod -R 775 bootstrap/cache storage
RUN composer dump-autoload --optimize --no-dev

# ─────────────────────────────────────────────
# Stage 2 — Production image
# ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    unzip

# PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# Copy app from vendor stage
COPY --from=vendor /app /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

# ─────────────────────────────────────────────
# Stage 3 — CI / testing (with dev deps)
# ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS testing

RUN apk add --no-cache bash curl libpng-dev libzip-dev oniguruma-dev zip unzip git

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# SQLite for in-memory tests
RUN apk add --no-cache sqlite-dev && docker-php-ext-install pdo_sqlite

# PCOV for test coverage reports (dev/testing only — never shipped in the production stage)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-scripts --prefer-dist

RUN chown -R www-data:www-data storage bootstrap/cache
