#!/usr/bin/env bash
#
# Onemark Finance — Phase 3a: install nginx vhost (HTTP only), reload.
# Once DNS resolves, run phase3b.sh to obtain a TLS cert via certbot.

set -euo pipefail

APP_DIR="/var/www/ometfinance"
SITE_NAME="ometfinance"
DOMAIN="ometfinance.com"

# Copy template into sites-available
sudo install -o root -g root -m 0644 \
  "${APP_DIR}/deploy/nginx-ometfinance.conf" \
  "/etc/nginx/sites-available/${SITE_NAME}"

# Enable
sudo ln -sf "/etc/nginx/sites-available/${SITE_NAME}" "/etc/nginx/sites-enabled/${SITE_NAME}"

# Drop the default 'welcome to nginx' site if it's still enabled
sudo rm -f /etc/nginx/sites-enabled/default

# Validate config + reload
sudo nginx -t
sudo systemctl reload nginx

echo
echo "========================================================"
echo "PHASE 3a DONE."
echo "Nginx serves ${DOMAIN} on port 80 from ${APP_DIR}/public."
echo
echo "Next:"
echo "  1. Add A records at GoDaddy (apex + www) pointing to this droplet."
echo "  2. Verify DNS:   dig +short ometfinance.com  (should print this droplet's IP)"
echo "  3. Run:          sudo bash ${APP_DIR}/deploy/phase3b.sh"
echo "========================================================"
