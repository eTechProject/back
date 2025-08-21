#!/usr/bin/env bash
set -euo pipefail

echo "[entrypoint] Starting container in $APP_ENV mode"

# Configure nginx to listen on provided PORT
: "${PORT:=8080}"
if [ -f /etc/nginx/conf.d/default.conf.template ]; then
  envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf
fi

# Provide default Messenger DSN if not supplied by environment (prevents migration failure)
: "${MESSENGER_TRANSPORT_DSN:=doctrine://default?auto_setup=0}"
export MESSENGER_TRANSPORT_DSN

# Run database migrations (ignore failures if DB not reachable yet)
if [ -n "${RUN_MIGRATIONS:-1}" ]; then
  if [ ! -d migrations ]; then
    echo "[entrypoint] 'migrations' directory missing; skipping migrations."
  elif ls migrations/*.php >/dev/null 2>&1; then
    echo "[entrypoint] Running migrations"
    php bin/console doctrine:migrations:migrate --no-interaction || echo "[entrypoint] Migrations skipped/failed (DB not ready?)"
  else
    echo "[entrypoint] No migration files present."
    if [ "${AUTO_GENERATE_MIGRATION:-0}" = "1" ]; then
      echo "[entrypoint] AUTO_GENERATE_MIGRATION=1 -> generating initial migration (diff)"
      if php bin/console doctrine:migrations:diff --no-interaction; then
        echo "[entrypoint] Diff generated; migrating"
        php bin/console doctrine:migrations:migrate --no-interaction || echo "[entrypoint] Generated migration failed to run."
      else
        echo "[entrypoint] Could not generate migration (possibly DB not reachable yet)."
      fi
    else
      echo "[entrypoint] Set AUTO_GENERATE_MIGRATION=1 to auto-create an initial migration."
    fi
  fi
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
