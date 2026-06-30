#!/usr/bin/env bash
set -e

cd /var/www/html

echo "[entrypoint] Starting initialization..."

# -----------------------------------------------------------------------------
# Ensure .env exists
# -----------------------------------------------------------------------------
if [ ! -f .env ]; then
    echo "[entrypoint] Creating .env from .env.example"
    cp .env.example .env
fi

# -----------------------------------------------------------------------------
# Fix Git safe directory
# -----------------------------------------------------------------------------
git config --global --add safe.directory /var/www/html >/dev/null 2>&1 || true

# -----------------------------------------------------------------------------
# Prepare runtime directories (must exist before composer/artisan write to them)
# -----------------------------------------------------------------------------
mkdir -p storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

# -----------------------------------------------------------------------------
# Composer dependencies (install only when composer.lock changes)
# -----------------------------------------------------------------------------
COMPOSER_LOCK_HASH_FILE="storage/framework/cache/.composer.lock.sha256"

CURRENT_COMPOSER_HASH=""
if [ -f composer.lock ]; then
    CURRENT_COMPOSER_HASH="$(sha256sum composer.lock | awk '{print $1}')"
fi

SAVED_COMPOSER_HASH=""
if [ -f "$COMPOSER_LOCK_HASH_FILE" ]; then
    SAVED_COMPOSER_HASH="$(cat "$COMPOSER_LOCK_HASH_FILE")"
fi

if [ ! -f vendor/autoload.php ] || [ "$CURRENT_COMPOSER_HASH" != "$SAVED_COMPOSER_HASH" ]; then
    echo "[entrypoint] Installing Composer dependencies..."

    composer install --no-interaction --prefer-dist

    echo "$CURRENT_COMPOSER_HASH" > "$COMPOSER_LOCK_HASH_FILE"
fi

# -----------------------------------------------------------------------------
# Node dependencies (install only when package-lock.json changes)
# -----------------------------------------------------------------------------
NPM_LOCK_HASH_FILE="storage/framework/cache/.package-lock.sha256"

CURRENT_NPM_HASH=""
if [ -f package-lock.json ]; then
    CURRENT_NPM_HASH="$(sha256sum package-lock.json | awk '{print $1}')"
fi

SAVED_NPM_HASH=""
if [ -f "$NPM_LOCK_HASH_FILE" ]; then
    SAVED_NPM_HASH="$(cat "$NPM_LOCK_HASH_FILE")"
fi

if [ ! -x node_modules/.bin/vite ] || [ "$CURRENT_NPM_HASH" != "$SAVED_NPM_HASH" ]; then
    echo "[entrypoint] Installing NPM dependencies..."

    npm install

    echo "$CURRENT_NPM_HASH" > "$NPM_LOCK_HASH_FILE"
fi

# -----------------------------------------------------------------------------
# Generate APP_KEY
# -----------------------------------------------------------------------------
if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[entrypoint] Generating APP_KEY..."
    php artisan key:generate --force
fi

# -----------------------------------------------------------------------------
# Wait for MySQL
# -----------------------------------------------------------------------------
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    echo "[entrypoint] Waiting for MySQL (${DB_HOST}:${DB_PORT})..."

    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);" >/dev/null 2>&1
    do
        sleep 3
    done

    echo "[entrypoint] MySQL is ready."
fi

# -----------------------------------------------------------------------------
# Run migrations
# -----------------------------------------------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force
fi

# -----------------------------------------------------------------------------
# Run seeders
# -----------------------------------------------------------------------------
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "[entrypoint] Running seeders..."
    php artisan db:seed --force
fi

# -----------------------------------------------------------------------------
# Storage link
# -----------------------------------------------------------------------------
if [ ! -L public/storage ]; then
    echo "[entrypoint] Creating storage symlink..."
    php artisan storage:link
fi

# -----------------------------------------------------------------------------
# Cache only outside local environment
# -----------------------------------------------------------------------------
if [ "${APP_ENV:-local}" != "local" ]; then
    echo "[entrypoint] Caching configuration..."

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# -----------------------------------------------------------------------------
# Permissions (LAST: the artisan/composer steps above run as root and may have
# created root-owned files in storage/. Re-own everything to www-data — which
# was remapped to the host UID/GID at build time — so both php-fpm AND the host
# user can read/write. Done last so nothing root-creates files afterwards.)
# -----------------------------------------------------------------------------
echo "[entrypoint] Fixing permissions on storage and bootstrap/cache..."
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

echo "[entrypoint] Initialization completed."

exec "$@"