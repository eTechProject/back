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

# Install PHP dependencies (optimisé pour Render, pas de scripts, pas de progress, pas d'interaction, dist only, mémoire illimitée)
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-progress --prefer-dist --no-interaction || { cat /var/www/app/composer.lock || true; cat /var/www/app/composer.json || true; exit 1; }

# Build assets if needed (uncomment if you use asset mapper or encore)
# RUN npm install && npm run build

# Crée les dossiers var et public si absents, puis change les permissions
RUN mkdir -p /var/www/app/var /var/www/app/public \
    && chown -R www-data:www-data /var/www/app/var /var/www/app/public

# Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Expose the port expected by Render
ENV PORT=10000
EXPOSE 10000

# Start PHP-FPM in background, Nginx in foreground (nécessaire pour Render)
CMD php-fpm & nginx -g 'daemon off;'

