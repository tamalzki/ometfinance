#!/usr/bin/env bash
#
# Onemark Finance — Droplet provisioning, Phase 2.
# Runs DB + .env + APP_KEY + migrations + cache warmup.
# Idempotent: safe to re-run; reuses an existing .env if present.
#
# Usage (run as root on the droplet, from anywhere):
#   sudo bash /var/www/ometfinance/deploy/phase2.sh

set -euo pipefail

APP_DIR="/var/www/ometfinance"
DB_NAME="ometfinance"
DB_USER="ometfinance"
ENV_FILE="${APP_DIR}/.env"

cd "${APP_DIR}"

# Sanity check
if [ ! -f composer.json ]; then
  echo "!! ${APP_DIR} does not look like the app — is the repo cloned here?" >&2
  exit 1
fi

# Reuse DB password if .env already has one, otherwise generate
if [ -f "${ENV_FILE}" ] && grep -q '^DB_PASSWORD=' "${ENV_FILE}"; then
  DBPASS="$(grep '^DB_PASSWORD=' "${ENV_FILE}" | head -1 | cut -d= -f2-)"
  echo "== Reusing existing DB_PASSWORD from .env =="
else
  DBPASS="$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 28)"
  echo "== Generated new DB password =="
fi

# 1. MySQL — create DB + user (idempotent)
sudo mysql -e "SELECT 'mysql-ok' AS ping" >/dev/null
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DBPASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DBPASS}';
GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "== DB ready =="

# 2. Write .env if missing
if [ ! -f "${ENV_FILE}" ]; then
  sudo -u www-data tee "${ENV_FILE}" >/dev/null <<EOF
APP_NAME="Onemark Finance"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://ometfinance.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DBPASS}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=log
MAIL_FROM_ADDRESS=no-reply@ometfinance.com
MAIL_FROM_NAME="\${APP_NAME}"
EOF
  sudo chmod 640 "${ENV_FILE}"
  sudo chown www-data:www-data "${ENV_FILE}"
  echo "== Wrote new .env =="
else
  echo "== .env already exists, leaving as-is =="
fi

# 3. APP_KEY (only if blank)
if ! grep -qE '^APP_KEY=base64:' "${ENV_FILE}"; then
  sudo -u www-data php artisan key:generate --force
else
  echo "== APP_KEY already set =="
fi

# 4. Migrate + seed (entities only — no demo project data)
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=AccountSeeder --force

# 5. Permissions + storage symlink
sudo chown -R www-data:www-data "${APP_DIR}"
sudo chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
sudo -u www-data php artisan storage:link 2>&1 | grep -v "already exists" || true

# 6. Cache for prod
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo
echo "========================================================"
echo "PHASE 2 DONE."
echo "DB_PASSWORD (also stored in ${ENV_FILE}):"
echo "  ${DBPASS}"
echo "========================================================"
