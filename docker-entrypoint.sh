#!/bin/bash
set -e

# Use Railway's PORT env var (defaults to 80)
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
fi

# Set default environment variables for Railway
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export DB_DATABASE="${DB_DATABASE:-/data/database.sqlite}"

# Create persistent storage directory structure
mkdir -p /data/storage/photos
chown -R www-data:www-data /data
chmod -R 775 /data

# Create SQLite database on persistent volume if it doesn't exist
if [ ! -f "$DB_DATABASE" ]; then
    echo "Creating SQLite database at $DB_DATABASE..."
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
fi

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p storage/app/public

# Link persistent storage for photos
if [ ! -L "storage/app/public/photos" ] && [ -d "/data/storage/photos" ]; then
    echo "Linking photos directory to persistent volume..."
    rm -rf storage/app/public/photos
    ln -sf /data/storage/photos storage/app/public/photos
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force || echo "Warning: Failed to generate app key"
fi

# Run migrations with error handling
echo "Running database migrations..."
if php artisan migrate --force; then
    echo "Migrations completed successfully"
else
    echo "Warning: Migrations had issues, but continuing startup"
fi

# Cache for production (non-blocking)
echo "Caching configuration..."
php artisan config:cache || echo "Warning: Config cache failed"
php artisan route:cache || echo "Warning: Route cache failed"
php artisan view:cache || echo "Warning: View cache failed"

# Create storage link (non-blocking)
php artisan storage:link --force 2>/dev/null || true

# Auto-setup Telegram webhook if enabled (non-blocking)
if [ "$AUTO_SET_WEBHOOK" = "true" ] && [ -n "$APP_URL" ] && [ -n "$TELEGRAM_BOT_TOKEN" ]; then
    echo "Setting up Telegram webhook..."
    php artisan telegram:set-webhook || echo "Warning: Failed to set webhook. You can set it manually later."
fi

echo "Starting Apache..."
exec "$@"
