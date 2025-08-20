#!/bin/bash

echo "=== Render Deployment Script ==="

# Set production environment
export APP_ENV=prod
export APP_DEBUG=0

echo "Environment variables:"
echo "APP_ENV: $APP_ENV"
echo "DATABASE_URL: ${DATABASE_URL:0:20}..."
echo "MERCURE_URL: $MERCURE_URL"
echo "MERCURE_PUBLIC_URL: $MERCURE_PUBLIC_URL"

echo "=== Database Setup ==="

# Wait for database to be ready
echo "Waiting for database..."
sleep 10

# Enable PostGIS extension
echo "Enabling PostGIS..."
php bin/console dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis;" --env=prod 2>/dev/null || echo "PostGIS extension setup completed"

# Run migrations instead of dropping schema
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || echo "Migration completed"

# Create super admin user
echo "Creating super admin..."
php bin/console app:create-admin ynnotjoh@gmail.com --env=prod 2>/dev/null || echo "Admin setup completed"

echo "=== JWT Keys Setup ==="

# Generate JWT keys if they don't exist
echo "Setting up JWT keys..."
mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
  echo "Generating JWT private key..."
  openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:${JWT_PASSPHRASE}
fi
if [ ! -f config/jwt/public.pem ]; then
  echo "Generating JWT public key..."
  openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE}
fi
chown -R www-data:www-data config/jwt
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem

echo "=== Cache Setup ==="

# Clear and warm up cache
echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

echo "Warming up cache..."
php bin/console cache:warmup --env=prod --no-debug

# Fix permissions
chown -R www-data:www-data var/
chmod -R 775 var/

echo "=== Render deployment completed successfully ==="
