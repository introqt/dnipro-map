# ── Stage 1: Build frontend assets ──
FROM node:20-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

# ── Stage 2: PHP + Apache ──
FROM php:8.4-apache

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y \
        libsqlite3-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_sqlite zip bcmath opcache intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Fix Apache MPM conflict - force clean MPM setup
RUN set -eux; \
    a2dismod mpm_event mpm_worker || true; \
    find /etc/apache2/mods-enabled/ -name 'mpm_*' -delete; \
    a2enmod mpm_prefork; \
    a2enmod rewrite

# Set Apache DocumentRoot to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set ServerName to suppress Apache warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application code
COPY . .

# Copy built frontend assets from stage 1
COPY --from=frontend /app/public/build public/build

# Re-run composer scripts (post-autoload-dump etc.)
RUN composer dump-autoload --optimize

# Create persistent data directory and storage structure
RUN mkdir -p /data/storage/photos \
    && mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p storage/app/public \
    && mkdir -p bootstrap/cache

# Make writable
RUN chown -R www-data:www-data storage bootstrap/cache /data \
    && chmod -R 775 /data

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
