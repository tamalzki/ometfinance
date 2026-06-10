#!/usr/bin/env bash
#
# Onemark Finance — Droplet provisioning, Phase 4.
# Disbursements module rollout: applies the new schema (vouchers, voucher
# payments/attachments, payees, project_expenses.voucher_id link, user roles)
# and populates the Project + Payee dropdowns from the Excel issuance log.
#
# Idempotent: safe to re-run. Only ADDS data — the `users` table (existing
# admin/cfo accounts) is never touched by this script.
#
# Prerequisite: upload the issuance log to the app root first, e.g.
#   scp Onemark-Davao-Daily-Issuances-2026.xlsx root@<droplet>:/var/www/ometfinance/
#
# Usage (run as root on the droplet, from anywhere):
#   sudo bash /var/www/ometfinance/deploy/phase4.sh

set -euo pipefail

APP_DIR="/var/www/ometfinance"
EXCEL_FILE="${APP_DIR}/Onemark-Davao-Daily-Issuances-2026.xlsx"

cd "${APP_DIR}"

# Sanity check
if [ ! -f composer.json ]; then
  echo "!! ${APP_DIR} does not look like the app — is the repo cloned here?" >&2
  exit 1
fi

# 1. Apply new migrations — additive only (new tables + new columns).
#    Existing rows in `users`, `projects`, `bank_accounts`, etc. are untouched.
echo "== Running migrations =="
sudo -u www-data php artisan migrate --force

# 2. Seed the core projects (Croc Park, Josefina, APMC, ESCO, AMOMC) —
#    idempotent on `code`, won't duplicate if already present.
echo "== Seeding core projects =="
sudo -u www-data php artisan db:seed --class=ProjectSeeder --force

# 3. Populate the Project + Payee dropdowns (voucher modal) from the Excel
#    issuance log — creates the "Admin - X" in-house cost centers and
#    external client projects, plus the distinct payee list.
if [ -f "${EXCEL_FILE}" ]; then
  echo "== Syncing projects from Excel =="
  sudo -u www-data php artisan projects:sync-from-excel

  echo "== Syncing payees from Excel =="
  sudo -u www-data php artisan payees:sync-from-excel
else
  echo "!! ${EXCEL_FILE} not found — skipping dropdown sync."
  echo "   Upload it, then re-run this script:"
  echo "   scp Onemark-Davao-Daily-Issuances-2026.xlsx root@<droplet>:${APP_DIR}/"
fi

# 4. Rebuild caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo
echo "========================================================"
echo "PHASE 4 DONE."
echo "Existing accounts in 'users' (admin/cfo) were not modified."
echo "========================================================"
