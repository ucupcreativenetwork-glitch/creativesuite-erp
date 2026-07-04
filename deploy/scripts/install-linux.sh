#!/usr/bin/env bash
# CreativeSuite ERP - Instalasi production Ubuntu 22.04
# sudo SERVER_IP=192.168.1.102 bash deploy/scripts/install-linux.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
RELEASE_DIR="$(cd "${DEPLOY_DIR}/.." && pwd)"
WEB_ROOT="/var/www"
BACKEND_DIR="${WEB_ROOT}/creativesuite-erp"
FRONTEND_DIR="${WEB_ROOT}/creativesuite-frontend"
DB_NAME="creativesuite_erp"
DB_USER="creativesuite"
SERVER_IP="${SERVER_IP:-192.168.1.102}"
LOG_FILE="/var/log/creativesuite-install.log"
FORCE_FRESH="${FORCE_FRESH:-false}"

exec > >(tee -a "${LOG_FILE}") 2>&1

echo "=== CreativeSuite ERP - Instalasi Ubuntu 22.04 ==="
echo "Release: ${RELEASE_DIR}"
echo "Server IP: ${SERVER_IP}"

if [[ $EUID -ne 0 ]]; then
  echo "Jalankan dengan sudo."
  exit 1
fi

if [[ ! -d "${RELEASE_DIR}/backend" ]]; then
  echo "ERROR: folder backend tidak ditemukan di ${RELEASE_DIR}"
  exit 1
fi

if [[ -f "${BACKEND_DIR}/.env" && "${FORCE_FRESH}" != "true" ]]; then
  echo ""
  echo "ERP sudah terinstall di ${BACKEND_DIR}"
  echo "Gunakan: sudo bash deploy/scripts/update-linux.sh"
  echo "Atau reinstall paksa: sudo FORCE_FRESH=true SERVER_IP=${SERVER_IP} bash deploy/scripts/install-linux.sh"
  exit 1
fi

echo "[1/13] Update sistem & install tools dasar..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq software-properties-common curl gnupg unzip rsync git

echo "[2/13] Install PHP 8.2..."
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y -qq \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring \
  php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-readline

# Upload selfie absensi (max 32MB)
for INI in /etc/php/8.2/fpm/php.ini /etc/php/8.2/cli/php.ini; do
  sed -i 's/^upload_max_filesize.*/upload_max_filesize = 32M/' "${INI}"
  sed -i 's/^post_max_size.*/post_max_size = 32M/' "${INI}"
  sed -i 's/^memory_limit.*/memory_limit = 256M/' "${INI}"
done

echo "[3/13] Install MySQL 8..."
apt-get install -y -qq mysql-server
systemctl enable mysql
systemctl start mysql

echo "[4/13] Install Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y -qq nodejs

echo "[5/13] Install Nginx & Composer..."
apt-get install -y -qq nginx
if ! command -v composer &>/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "[6/13] Setup database MySQL..."
DB_PASS="${DB_PASS:-$(openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 24)}"
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "[7/13] Deploy backend..."
mkdir -p "${BACKEND_DIR}"
rsync -a --delete \
  --exclude='.git' --exclude='node_modules' --exclude='vendor' \
  --exclude='storage/logs/*' --exclude='.env' --exclude='database/database.sqlite' \
  "${RELEASE_DIR}/backend/" "${BACKEND_DIR}/"

cd "${BACKEND_DIR}"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

FRESH_INSTALL=false
if [[ ! -f .env ]]; then
  FRESH_INSTALL=true
  cp "${DEPLOY_DIR}/env/backend.env.production" .env
  sed -i "s|APP_URL=.*|APP_URL=http://${SERVER_IP}|" .env
  sed -i "s|FRONTEND_URL=.*|FRONTEND_URL=http://${SERVER_IP}|" .env
  sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
  sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
  sed -i "s|SEED_DEMO=.*|SEED_DEMO=true|" .env
  php artisan key:generate --force
  php artisan jwt:secret --force
fi

php artisan migrate --force
if [[ "${FRESH_INSTALL}" == "true" ]]; then
  php artisan db:seed --force
else
  echo "  Skip seed (instalasi ulang, database sudah ada)"
fi
php artisan storage:link --force
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[8/13] Deploy frontend..."
mkdir -p "${FRONTEND_DIR}"
rsync -a --delete \
  --exclude='.git' --exclude='node_modules' \
  "${RELEASE_DIR}/frontend/" "${FRONTEND_DIR}/"

cd "${FRONTEND_DIR}"
cp "${DEPLOY_DIR}/env/frontend.env.production" .env.production
sed -i "s|NEXT_PUBLIC_API_URL=.*|NEXT_PUBLIC_API_URL=http://${SERVER_IP}/api/v1|" .env.production
npm ci --omit=dev 2>/dev/null || npm install --omit=dev
chown -R www-data:www-data "${FRONTEND_DIR}"

echo "[9/13] Konfigurasi Nginx..."
cp "${DEPLOY_DIR}/nginx/creativesuite.conf" /etc/nginx/sites-available/creativesuite
sed -i "s|server_name .*|server_name ${SERVER_IP} _;|" /etc/nginx/sites-available/creativesuite
ln -sf /etc/nginx/sites-available/creativesuite /etc/nginx/sites-enabled/creativesuite
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable nginx php8.2-fpm
systemctl restart nginx php8.2-fpm

echo "[10/13] Systemd services..."
cp "${DEPLOY_DIR}/systemd/"*.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable creativesuite-frontend creativesuite-queue creativesuite-scheduler
systemctl restart creativesuite-frontend creativesuite-queue creativesuite-scheduler

echo "[11/13] Firewall..."
if command -v ufw &>/dev/null; then
  ufw allow OpenSSH
  ufw allow 80/tcp
  ufw --force enable || true
fi

echo "[12/13] Backup harian MySQL..."
mkdir -p /opt/creativesuite
cp "${DEPLOY_DIR}/scripts/backup-mysql.sh" /opt/creativesuite/backup-mysql.sh
chmod +x /opt/creativesuite/backup-mysql.sh
CRON_LINE="0 2 * * * root /opt/creativesuite/backup-mysql.sh >> /var/log/creativesuite-backup.log 2>&1"
grep -qF "creativesuite/backup-mysql.sh" /etc/crontab 2>/dev/null || echo "${CRON_LINE}" >> /etc/crontab

echo "[13/13] Simpan kredensial..."
CREDS_FILE="/root/creativesuite-credentials.txt"
cat > "${CREDS_FILE}" <<EOF
CreativeSuite ERP - Kredensial Instalasi
Tanggal: $(date)
Server:  http://${SERVER_IP}
API:     http://${SERVER_IP}/api/v1

MySQL Database: ${DB_NAME}
MySQL User:     ${DB_USER}
MySQL Password: ${DB_PASS}

Login Demo:
  Perusahaan: Demo Agency
  Email:      admin@demo.id
  Password:   Password123

Mobile APK API URL: http://${SERVER_IP}/api/v1
EOF
chmod 600 "${CREDS_FILE}"

echo ""
echo "=========================================="
echo "  INSTALASI SELESAI"
echo "=========================================="
echo "  Web:    http://${SERVER_IP}"
echo "  API:    http://${SERVER_IP}/api/v1"
echo "  Health: http://${SERVER_IP}/up"
echo "  Login:  Demo Agency / admin@demo.id / Password123"
echo "  MySQL:  ${DB_USER} / ${DB_PASS}"
echo "  File:   ${CREDS_FILE}"
echo "=========================================="