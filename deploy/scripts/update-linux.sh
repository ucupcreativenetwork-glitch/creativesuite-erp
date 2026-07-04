#!/usr/bin/env bash
# Update CreativeSuite ERP tanpa reinstall penuh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
RELEASE_DIR="$(cd "${DEPLOY_DIR}/.." && pwd)"
BACKEND="/var/www/creativesuite-erp"
FRONTEND="/var/www/creativesuite-frontend"
SERVER_IP="${SERVER_IP:-192.168.1.102}"

echo "=== CreativeSuite ERP - Update ==="
echo "Server IP: ${SERVER_IP}"

if [[ $EUID -ne 0 ]]; then echo "Jalankan dengan sudo."; exit 1; fi

if [[ ! -f "${BACKEND}/.env" ]]; then
  echo "ERROR: ERP belum terinstall. Jalankan install-linux.sh dulu."
  exit 1
fi

echo "[1/7] Maintenance mode..."
cd "${BACKEND}"
sudo -u www-data php artisan down --retry=60 || true

echo "[2/7] Update backend..."
rsync -a --delete \
  --exclude='.env' --exclude='storage/logs/*' --exclude='vendor' \
  --exclude='storage/app/public/*' \
  "${RELEASE_DIR}/backend/" "${BACKEND}/"
cd "${BACKEND}"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link --force 2>/dev/null || true

echo "[3/7] Update frontend..."
rsync -a --delete --exclude='node_modules' "${RELEASE_DIR}/frontend/" "${FRONTEND}/"
cd "${FRONTEND}"
if [[ -f "${DEPLOY_DIR}/env/frontend.env.production" ]]; then
  cp "${DEPLOY_DIR}/env/frontend.env.production" .env.production
  sed -i "s|NEXT_PUBLIC_API_URL=.*|NEXT_PUBLIC_API_URL=http://${SERVER_IP}/api/v1|" .env.production
fi
npm ci --omit=dev 2>/dev/null || npm install --omit=dev

echo "[4/7] Sync nginx..."
if [[ -f "${DEPLOY_DIR}/nginx/creativesuite.conf" ]]; then
  cp "${DEPLOY_DIR}/nginx/creativesuite.conf" /etc/nginx/sites-available/creativesuite
  sed -i "s|server_name .*|server_name ${SERVER_IP} _;|" /etc/nginx/sites-available/creativesuite
  nginx -t
fi

echo "[5/7] Cache & up..."
cd "${BACKEND}"
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan up

echo "[6/7] Restart services..."
systemctl restart creativesuite-frontend creativesuite-queue creativesuite-scheduler php8.2-fpm nginx

echo "[7/7] Health check..."
sleep 3
curl -sf "http://127.0.0.1/up" && echo " API OK"
curl -sf -o /dev/null "http://127.0.0.1/" && echo " Web OK"
echo "Update selesai!"