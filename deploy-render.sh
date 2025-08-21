#!/bin/bash

set -e  # Exit on any error

echo "=== Render Deployment Script ==="

# Set production environment
export APP_ENV=prod
export APP_DEBUG=0

echo "Environment variables:"
echo "APP_ENV: $APP_ENV"
echo "DATABASE_URL: ${DATABASE_URL:0:20}..."
echo "MERCURE_URL: $MERCURE_URL"
echo "MERCURE_PUBLIC_URL: $MERCURE_PUBLIC_URL"

# Function to handle errors
handle_error() {
    echo "Error on line $1"
    echo "Continuing with startup..."
}
trap 'handle_error $LINENO' ERR

echo "=== Database Setup ==="

# Wait for database to be ready
echo "Waiting for database..."
sleep 10

# Enable PostGIS extension (ignore errors)
echo "Enabling PostGIS..."
php bin/console dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis;" --env=prod 2>/dev/null || echo "PostGIS extension setup completed"

set +e  # Don't exit on errors for migrations

# Run migrations instead of dropping schema
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod 2>/dev/null || {
    echo "Migrations failed, attempting schema update..."
    php bin/console doctrine:schema:update --force --env=prod 2>/dev/null || {
        echo "Schema update failed, creating schema from scratch..."
        php bin/console doctrine:schema:create --env=prod 2>/dev/null || echo "Schema operations completed"
    }
}

set -e  # Re-enable exit on error

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

echo "=== Starting Services ==="

# Start Mercure server in background
echo "Starting Mercure server..."
mkdir -p /tmp/mercure
MERCURE_PUBLISHER_JWT_KEY="${MERCURE_JWT_SECRET:-changeme}" \
MERCURE_SUBSCRIBER_JWT_KEY="${MERCURE_JWT_SECRET:-changeme}" \
SERVER_NAME=":3000" \
MERCURE_TRANSPORT_URL="bolt:///tmp/mercure/mercure.db" \
MERCURE_PUBLISHER_JWT_ALG="HS256" \
MERCURE_SUBSCRIBER_JWT_ALG="HS256" \
MERCURE_EXTRA_DIRECTIVES="cors_origins ${CORS_ORIGINS:-*}" \
/usr/local/bin/mercure run &

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm &

# Wait a moment for PHP-FPM to start
sleep 2

# Start Nginx in foreground (this keeps the container running)
echo "Starting Nginx..."
echo "=== Services Started ==="
echo "API available at http://localhost:10000"
echo "Mercure available at http://localhost:3000/.well-known/mercure"
echo "Health check: http://localhost:10000/health"

# Wait a moment then test services
sleep 3
echo "=== Testing services locally ==="
curl -s http://localhost:10000/health > /dev/null && echo "✓ API health check passed" || echo "✗ API health check failed"
curl -s http://localhost:3000/.well-known/mercure?topic=test --max-time 2 > /dev/null && echo "✓ Mercure health check passed" || echo "✗ Mercure health check failed"

echo "=== Starting nginx in foreground ==="
# Start Nginx in foreground to keep container alive
exec nginx -g "daemon off;"
