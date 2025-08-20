#!/bin/bash

echo " Starting deployment..."

# Set production environment
export APP_ENV=prod
export APP_DEBUG=0

echo " Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo " Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

echo " Deployment completed!"
