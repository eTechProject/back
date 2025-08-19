FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update \
    && apt-get install -y git unzip zip libpq-dev curl nginx \
    && docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/app

# Copy application code
COPY . /var/www/app

# Install PHP dependencies (désactive les scripts pour éviter les erreurs en build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Build assets if needed (uncomment if you use asset mapper or encore)
# RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/app/var /var/www/app/public

# Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Expose the port expected by Render
ENV PORT=10000
EXPOSE 10000

# Start both PHP-FPM and Nginx
CMD service nginx start && php-fpm

