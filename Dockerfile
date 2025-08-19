FROM php:8.2-fpm
 
# Install system dependencies
RUN apt-get update \
&& apt-get install -y git unzip zip libpq-dev curl nginx \
&& docker-php-ext-install pdo pdo_pgsql
 
# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
 
WORKDIR /var/www/app
 
# Copy all application files first
COPY . .
 
# Install PHP dependencies with scripts (needed for autoload_runtime.php)
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --optimize-autoloader --no-progress --prefer-dist --no-interaction --quiet || { echo "Composer install failed"; exit 1; }

# Generate runtime autoloader explicitly if needed
RUN composer run-script --no-interaction auto-scripts || echo "Auto-scripts completed with warnings"
 
# Ensure var/ and public/ exist
RUN mkdir -p var public \
&& chown -R www-data:www-data var public
 
# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
 
ENV PORT=10000
EXPOSE 10000
 
# Start PHP-FPM in background, Nginx in foreground
CMD ["sh", "-c", "php-fpm & nginx -g 'daemon off;'"]