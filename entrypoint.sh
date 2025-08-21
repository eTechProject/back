#!/usr/bin/env bash
set -euo pipefail

echo "[entrypoint] Starting container in $APP_ENV mode"

# Configure nginx to listen on provided PORT
: "${PORT:=8080}"
if [ -f /etc/nginx/conf.d/default.conf.template ]; then
  envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf
fi

# Run database migrations (ignore failures if DB not reachable yet)
if [ -n "${RUN_MIGRATIONS:-1}" ]; then
  echo "[entrypoint] Running migrations (will ignore errors if DB not ready)"
  php bin/console doctrine:migrations:migrate --no-interaction || echo "[entrypoint] Migrations skipped/failed (DB not ready?)"
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
