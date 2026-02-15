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
    && docker-php-ext-install pdo pdo_pgsql mbstring bcmath gd

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --optimize-autoloader

# Create required Laravel directories
RUN mkdir -p storage/framework/{cache,data,sessions,views} bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

# Expose Render port
EXPOSE 8080

# Run migrations and start PHP built-in server
CMD sh -c "php artisan migrate --force; php -S 0.0.0.0:${PORT:-8080} -t public"
