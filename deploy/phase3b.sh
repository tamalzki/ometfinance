#!/usr/bin/env bash
#
# Onemark Finance — Phase 3b: TLS via Let's Encrypt + flip to HTTPS.
# Run only after DNS for ometfinance.com + www.ometfinance.com points to
# this droplet (verify with `dig +short ometfinance.com` first).

set -euo pipefail

APP_DIR="/var/www/ometfinance"
ENV_FILE="${APP_DIR}/.env"
DOMAIN="ometfinance.com"
ADMIN_EMAIL="${ADMIN_EMAIL:-rigietamala@gmail.com}"

# Sanity-check DNS before bothering Let's Encrypt
DROPLET_IP="$(curl -fsS https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"
APEX_IP="$(dig +short ${DOMAIN}     | tail -1)"
WWW_IP="$(dig  +short www.${DOMAIN} | tail -1)"

echo "Droplet IP:    ${DROPLET_IP}"
echo "DNS apex:      ${APEX_IP}"
echo "DNS www:       ${WWW_IP}"

if [ "${APEX_IP}" != "${DROPLET_IP}" ] || [ "${WWW_IP}" != "${DROPLET_IP}" ]; then
  echo "!! DNS does not yet point to this droplet. Wait for propagation and re-run."
  echo "   Tip: 'dig +short ${DOMAIN}' should return ${DROPLET_IP}"
  exit 1
fi

# Get cert + auto-edit nginx for 443 / redirect
sudo apt-get install -y python3-certbot-nginx
sudo certbot --nginx \
  -d "${DOMAIN}" -d "www.${DOMAIN}" \
  --non-interactive --agree-tos --email "${ADMIN_EMAIL}" \
  --redirect

# Flip APP_URL to https and turn on secure-cookies
sudo -u www-data sed -i \
  -e 's|^APP_URL=http://|APP_URL=https://|' \
  "${ENV_FILE}"

# Add session-cookie hardening if not already present
if ! grep -q '^SESSION_SECURE_COOKIE=' "${ENV_FILE}"; then
  sudo -u www-data tee -a "${ENV_FILE}" >/dev/null <<EOF

SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.${DOMAIN}
SANCTUM_STATEFUL_DOMAINS=${DOMAIN},www.${DOMAIN}
EOF
fi

# Rebuild config cache so the new APP_URL/SESSION_* take effect
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Make sure auto-renew is scheduled (certbot package adds a timer on install)
sudo systemctl list-timers 'certbot*' --no-pager || true

echo
echo "========================================================"
echo "PHASE 3b DONE."
echo "Site live at:  https://${DOMAIN}"
echo
echo "Final step: create your first admin invite."
echo "  sudo -u www-data php ${APP_DIR}/artisan admin:invite YOUR_EMAIL"
echo "Open the printed URL to set your password and log in."
echo "========================================================"
