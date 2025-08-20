FROM php:8.2-fpm
 
# Install system dependencies
RUN apt-get update \
&& apt-get install -y git unzip zip libpq-dev curl nginx libicu-dev openssl \
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
# Create super admin user\n\
echo "Creating super admin..."\n\
php bin/console app:create-admin ynnotjoh@gmail.com --env=prod 2>/dev/null || echo "Admin creation failed or already exists"\n\
\n\
echo "=== Cache Setup ===" \n\
\n\
# Generate JWT keys if they don'\''t exist\n\
echo "Setting up JWT keys..."\n\
mkdir -p config/jwt\n\
if [ ! -f config/jwt/private.pem ]; then\n\
  openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:${JWT_PASSPHRASE:-c1bdcabb020acfd03d86808959952dc577f864c49217c871e440fb406ebe609}\n\
fi\n\
if [ ! -f config/jwt/public.pem ]; then\n\
  openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE:-c1bdcabb020acfd03d86808959952dc577f864c49217c871e440fb406ebe609}\n\
fi\n\
chown -R www-data:www-data config/jwt\n\
chmod 600 config/jwt/private.pem\n\
chmod 644 config/jwt/public.pem\n\
\n\
# Fix permissions and generate cache as www-data user\n\
mkdir -p /var/www/app/var/cache/prod /var/www/app/var/log\n\
rm -rf /var/www/app/var/cache/* 2>/dev/null || true\n\
chown -R www-data:www-data /var/www/app\n\
chmod -R 755 /var/www/app\n\
chmod -R 775 /var/www/app/var\n\
\n\
# Generate cache as www-data to avoid permission issues\n\
echo "Generating cache..."\n\
su www-data -s /bin/bash -c "php bin/console cache:warmup --env=prod" || echo "Cache warmup failed"\n\
\n\
echo "=== Starting Services ===" \n\
\n\
# Start services\n\
php-fpm &\n\
nginx -g "daemon off;" &\n\
\n\
sleep 3\n\
echo "=== Service Ready ===" \n\
echo "API available at http://localhost:10000"\n\
echo "Health check: http://localhost:10000/health"\n\
\n\
wait\n' > /start.sh && chmod +x /start.sh
 
# Copy Nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
 
ENV PORT=10000
EXPOSE 10000
 
# Start PHP-FPM in background, Nginx in foreground
CMD ["/start.sh"]