# syntax=docker/dockerfile:1

FROM php:8.2-fpm-bookworm

# Install system packages, PHP extensions and Node.js
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    zip \
    default-mysql-client \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    ca-certificates \
    gnupg \
    passwd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    bcmath \
    gd \
    zip \
    exif \
    pcntl \
    opcache \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG PUID=1000
ARG PGID=1000
RUN groupmod -o -g "${PGID}" www-data \
    && usermod -o -u "${PUID}" -g "${PGID}" www-data

WORKDIR /var/www/html

# Copy source code
COPY . .

# PHP / Nginx / Supervisor configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
