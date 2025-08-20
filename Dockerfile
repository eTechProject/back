FROM php:8.2-fpm
 
# Install system dependencies
RUN apt-get update \
&& apt-get install -y git unzip zip libpq-dev curl nginx libicu-dev \
&& docker-php-ext-install pdo pdo_pgsql intl \
&& apt-get clean && rm -rf /var/lib/apt/lists/*
 
# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
 
WORKDIR /var/www/app
 
# Copy all application files first
# Copy the rest of the application
COPY . .

# Make deploy script executable
RUN chmod +x deploy.sh
 
# Install PHP dependencies with scripts (needed for autoload_runtime.php)
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-progress --prefer-dist --no-interaction --no-scripts --quiet || { echo "Composer install failed"; exit 1; }

# Generate the autoload_runtime.php file manually if it doesn't exist
RUN if [ ! -f "vendor/autoload_runtime.php" ]; then \
        composer dump-autoload --optimize --no-dev; \
        echo '<?php return require_once __DIR__."/autoload.php";' > vendor/autoload_runtime.php; \
    fi

# Create minimal .env file for Symfony (even when env vars are set externally)
RUN echo "# Minimal .env file for Docker deployment" > .env && \
    echo "APP_ENV=prod" >> .env && \
    echo "APP_DEBUG=0" >> .env

# Set default environment variables for production
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Create cache and log directories with proper permissions
RUN mkdir -p var/cache/prod var/log var/sessions public/uploads migrations \
    && chown -R www-data:www-data var public migrations \
    && chmod -R 775 var public migrations

# Warm up Symfony cache for production (if console is available)
# RUN php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || echo "Cache warmup skipped (console not available)"

# Create startup script
RUN echo '#!/bin/bash\n\
export APP_ENV=prod\n\
export APP_DEBUG=0\n\
\n\
echo "=== Database Setup ===" \n\
\n\
# Fix permissions\n\
mkdir -p /var/www/app/var/cache/prod /var/www/app/var/log\n\
chown -R www-data:www-data /var/www/app/var /var/www/app/public\n\
chmod -R 775 /var/www/app/var /var/www/app/public\n\
\n\
# Clear cache\n\
rm -rf /var/www/app/var/cache/* 2>/dev/null || true\n\
\n\
# Wait for database to be ready\n\
sleep 5\n\
\n\
# Enable PostGIS extension\n\
echo "Enabling PostGIS..."\n\
php bin/console dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis;" --env=prod 2>/dev/null || true\n\
\n\
# Drop and recreate schema (force clean state)\n\
echo "Creating database schema..."\n\
php bin/console doctrine:schema:drop --force --env=prod 2>/dev/null || true\n\
php bin/console doctrine:schema:create --env=prod\n\
\n\
echo "=== Starting Services ===" \n\
\n\
# Start services\n\
php-fpm &\n\
nginx -g "daemon off;"\n' > /start.sh && chmod +x /start.sh
 
# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
 
ENV PORT=10000
EXPOSE 10000
 
# Start PHP-FPM in background, Nginx in foreground
CMD ["/start.sh"]