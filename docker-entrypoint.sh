#!/bin/bash
set -e

echo "=== Docker Entrypoint Started ===" >&2

# Fix Apache MPM conflict at runtime
echo "Fixing Apache MPM modules..." >&2
a2dismod mpm_event mpm_worker 2>/dev/null || true
find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -o -name 'mpm_*.conf' | grep -v mpm_prefork | xargs rm -f 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Use Railway's PORT env var (defaults to 80)
echo "Checking PORT: ${PORT:-80}" >&2
if [ -n "$PORT" ]; then
    echo "Configuring Apache for PORT=$PORT" >&2
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
fi

# Set default environment variables for Railway
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export DB_DATABASE="${DB_DATABASE:-/data/database.sqlite}"

echo "Environment: LOG_CHANNEL=$LOG_CHANNEL, QUEUE_CONNECTION=$QUEUE_CONNECTION, DB_DATABASE=$DB_DATABASE" >&2

# Create persistent storage directory structure
echo "Creating storage directories..." >&2
mkdir -p /data/storage/photos 2>&1 || echo "Failed to create /data/storage/photos" >&2
chown -R www-data:www-data /data 2>&1 || echo "Failed to chown /data" >&2
chmod -R 775 /data 2>&1 || echo "Failed to chmod /data" >&2

# Create SQLite database on persistent volume if it doesn't exist
if [ ! -f "$DB_DATABASE" ]; then
    echo "Creating SQLite database at $DB_DATABASE..." >&2
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
fi

# Ensure storage directories exist
echo "Setting up Laravel storage directories..." >&2
mkdir -p storage/framework/{sessions,views,cache} 2>&1 || true
mkdir -p storage/logs 2>&1 || true
mkdir -p storage/app/public 2>&1 || true

# Link persistent storage for photos
if [ ! -L "storage/app/public/photos" ]; then
    if [ -d "/data/storage/photos" ]; then
        echo "Linking photos directory to persistent volume..." >&2
        rm -rf storage/app/public/photos 2>&1 || true
        ln -sf /data/storage/photos storage/app/public/photos || echo "Failed to create symlink" >&2
    fi
fi

# Generate app key if not set and .env exists
if [ -z "$APP_KEY" ]; then
    if [ -f "/var/www/html/.env" ]; then
        echo "Generating application key..." >&2
        php artisan key:generate --force 2>&1 || echo "Warning: key:generate failed" >&2
    else
        echo "APP_KEY is not set and .env is missing; set APP_KEY in Railway variables" >&2
    fi
else
    echo "APP_KEY already set, skipping generation" >&2
fi

# Run migrations with timeout protection
echo "Running database migrations..." >&2
timeout 60 php artisan migrate --force 2>&1 || echo "Warning: Migrations had issues, but continuing startup" >&2

# Cache for production (non-blocking, with timeout)
echo "Caching configuration..." >&2
timeout 30 php artisan config:cache 2>&1 || echo "Warning: Config cache failed" >&2
timeout 30 php artisan route:cache 2>&1 || echo "Warning: Route cache failed" >&2
timeout 30 php artisan view:cache 2>&1 || echo "Warning: View cache failed" >&2

# Create storage link (non-blocking)
echo "Creating storage link..." >&2
php artisan storage:link --force 2>&1 || true

# Auto-setup Telegram webhook if enabled (non-blocking)
if [ "$AUTO_SET_WEBHOOK" = "true" ] && [ -n "$APP_URL" ] && [ -n "$TELEGRAM_BOT_TOKEN" ]; then
    echo "Setting up Telegram webhook..." >&2
    timeout 30 php artisan telegram:set-webhook 2>&1 || echo "Warning: Failed to set webhook. You can set it manually later." >&2
fi

echo "=== Docker Entrypoint Complete, Starting Apache ===" >&2
exec "$@"
