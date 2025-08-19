FROM php:8.2-fpm
 
# Install system dependencies
RUN apt-get update \
&& apt-get install -y git unzip zip libpq-dev curl nginx \
&& docker-php-ext-install pdo pdo_pgsql
 
# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
 
WORKDIR /var/www/app
 
# Copy composer files first (better caching)
COPY composer.json composer.lock ./
 
# Install PHP dependencies (optimisé pour Render, pas de progress, pas d'interaction, dist only, mémoire illimitée)
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --optimize-autoloader --no-progress --prefer-dist --no-interaction || { cat /var/www/app/composer.lock || true; cat /var/www/app/composer.json || true; exit 1; }
 
# Copy rest of the app
COPY . .
 
# Ensure var/ and public/ exist
RUN mkdir -p var public \
&& chown -R www-data:www-data var public
 
# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
 
ENV PORT=10000
EXPOSE 10000
 
# Start PHP-FPM in background, Nginx in foreground
CMD php-fpm & nginx -g 'daemon off;'