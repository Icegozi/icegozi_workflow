#!/usr/bin/env bash
set -e

cd /var/www/html

# Ensure an .env file exists
if [ ! -f .env ]; then
    echo "[entrypoint] .env not found, copying from .env.example"
    cp .env.example .env
fi

# Generate the application key if it is missing
if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[entrypoint] Generating application key"
    php artisan key:generate --force
fi

# Make sure runtime directories are writable
chown -R www-data:www-data storage bootstrap/cache || true

# Wait for the database to become available before migrating
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    echo "[entrypoint] Waiting for database ${DB_HOST}:${DB_PORT} ..."
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);" 2>/dev/null; do
        echo "[entrypoint] Database not ready yet, retrying in 3s..."
        sleep 3
    done
    echo "[entrypoint] Database is up."
fi

# Run migrations (and seed only when explicitly requested)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running migrations"
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "[entrypoint] Running seeders"
    php artisan db:seed --force
fi

# Link storage and cache configuration for production performance
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Starting services"
exec "$@"
