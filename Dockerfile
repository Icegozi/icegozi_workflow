# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: Build frontend assets with Vite
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

# Install node dependencies (cached unless lockfiles change)
COPY package.json package-lock.json ./
RUN npm ci

# Build the Vite assets into public/build
COPY . .
RUN npm run build


# ---------------------------------------------------------------------------
# Stage 2: PHP dependencies via Composer (PHP 8.2 to match locked deps)
# ---------------------------------------------------------------------------
FROM php:8.2-cli-bookworm AS vendor

# Minimal tooling + extensions Composer needs to resolve/install
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies (no scripts; artisan not fully available yet)
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --optimize-autoloader


# ---------------------------------------------------------------------------
# Stage 3: Runtime image (PHP-FPM + Nginx via Supervisor)
# ---------------------------------------------------------------------------
FROM php:8.2-fpm-bookworm AS app

# System packages and PHP extensions required by Laravel
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        zip \
        unzip \
        git \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        exif \
        pcntl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy application source
COPY . .

# Bring in built assets and vendor dependencies from earlier stages
COPY --from=assets /app/public/build ./public/build
COPY --from=vendor /app/vendor ./vendor

# PHP / Nginx / Supervisor configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Laravel needs writable storage and cache directories
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
