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

# Generate JWT keys if they don't exist (needed for Render deployment)
JWT_DIR="config/jwt"
if [ ! -d "$JWT_DIR" ]; then
  echo "[entrypoint] Creating JWT directory"
  mkdir -p "$JWT_DIR"
fi

if [ ! -f "$JWT_DIR/private.pem" ] || [ ! -f "$JWT_DIR/public.pem" ]; then
  echo "[entrypoint] Generating JWT keypair (missing on deployment)"
  
  # Always generate a new random passphrase (override Render environment)
  JWT_PASSPHRASE_GENERATED="f9e11c19af96431f7a36774b0efd2553db6d13232e1191fe829a665627d11830"
  echo "[entrypoint] Generated new JWT passphrase (overriding Render environment)"
  
  # Generate private key using genrsa (more compatible)
  openssl genrsa -aes256 -passout pass:"$JWT_PASSPHRASE_GENERATED" -out "$JWT_DIR/private.pem" 4096
  
  # Generate public key from private key
  openssl rsa -in "$JWT_DIR/private.pem" -passin pass:"$JWT_PASSPHRASE_GENERATED" -pubout -out "$JWT_DIR/public.pem"
  
  # OVERRIDE all JWT environment variables from Render with generated values
  export JWT_PASSPHRASE="$JWT_PASSPHRASE_GENERATED"
  export JWT_SECRET_KEY="/var/www/app/$JWT_DIR/private.pem"
  export JWT_PUBLIC_KEY="/var/www/app/$JWT_DIR/public.pem"

  # Ensure a minimal .env exists (Symfony Dotenv will fatal otherwise if it expects the file)
  if [ ! -f .env ]; then
    echo "[entrypoint] Creating fallback .env file (was missing)"
    {
      echo "APP_ENV=${APP_ENV:-prod}";
      echo "APP_DEBUG=0";
      echo "MESSENGER_TRANSPORT_DSN=${MESSENGER_TRANSPORT_DSN}";
    } > .env
  fi
  
  echo "[entrypoint] JWT keypair generated successfully"
else
  echo "[entrypoint] JWT keypair already exists"
fi


# Clear Symfony cache to ensure fresh start
echo "[entrypoint] Clearing Symfony cache"
php bin/console cache:clear --env=prod --no-debug || echo "[entrypoint] Cache clear failed, continuing..."

# Run database migrations (ignore failures if DB not reachable yet)
if [ -n "${RUN_MIGRATIONS:-1}" ]; then
  # Ensure migrations directory exists
  if [ ! -d migrations ]; then
    echo "[entrypoint] 'migrations' directory missing; creating it."
    mkdir -p migrations
  fi

  if ls migrations/*.php >/dev/null 2>&1; then
    echo "[entrypoint] Running migrations"
    php bin/console doctrine:migrations:migrate --no-interaction || echo "[entrypoint] Migrations skipped/failed (DB not ready?)"
  else
    echo "[entrypoint] No migration files present."
    if [ "${AUTO_GENERATE_MIGRATION:-0}" = "1" ]; then
      MIGRATION_GEN_RETRIES=${MIGRATION_GEN_RETRIES:-5}
      MIGRATION_GEN_DELAY=${MIGRATION_GEN_DELAY:-4}
      attempt=1
      echo "[entrypoint] AUTO_GENERATE_MIGRATION=1 -> generating initial migration (diff) with up to $MIGRATION_GEN_RETRIES attempts."
      while [ $attempt -le $MIGRATION_GEN_RETRIES ]; do
        echo "[entrypoint] Migration diff attempt $attempt"
        if php bin/console doctrine:migrations:diff --no-interaction; then
          echo "[entrypoint] Diff generated; migrating"
          if php bin/console doctrine:migrations:migrate --no-interaction; then
            echo "[entrypoint] Initial migration applied successfully."
          else
            echo "[entrypoint] Generated migration failed to run (attempt $attempt)."
          fi
          break
        else
          echo "[entrypoint] Diff generation failed (DB not ready yet?)."
        fi
        attempt=$((attempt+1))
        if [ $attempt -le $MIGRATION_GEN_RETRIES ]; then
          echo "[entrypoint] Waiting $MIGRATION_GEN_DELAY seconds before retry..."
          sleep $MIGRATION_GEN_DELAY
        fi
      done
      if [ $attempt -gt $MIGRATION_GEN_RETRIES ]; then
        echo "[entrypoint] Gave up generating initial migration after $MIGRATION_GEN_RETRIES attempts."
      fi
    else
      echo "[entrypoint] Set AUTO_GENERATE_MIGRATION=1 to auto-create an initial migration."
    fi
  fi
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
