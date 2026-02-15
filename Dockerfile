# Use official PHP 8.2 CLI image
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql pdo_sqlite mbstring bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Copy rest of the project
COPY . .

# Re-run composer scripts after full copy
RUN composer dump-autoload --optimize

# Create required Laravel directories
RUN mkdir -p storage/framework/{cache,data,sessions,views} bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create startup script
RUN echo '#!/bin/sh\n\
set -e\n\
echo "==> Caching config..."\n\
php artisan config:clear\n\
echo "==> Running migrations..."\n\
php artisan migrate --force\n\
echo "==> Seeding admin user..."\n\
php artisan db:seed --class=AdminSeeder --force\n\
echo "==> Starting server on port ${PORT:-8080}..."\n\
php -S 0.0.0.0:${PORT:-8080} -t public\n\
' > /start.sh && chmod +x /start.sh

# Expose Render port
EXPOSE 8080

CMD ["/start.sh"]
