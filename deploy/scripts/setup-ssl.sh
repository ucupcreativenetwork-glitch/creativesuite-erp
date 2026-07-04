#!/usr/bin/env bash
# Pasang SSL Let's Encrypt untuk CreativeSuite ERP
# Jalankan: sudo DOMAIN=erp.perusahaan.id bash setup-ssl.sh
set -euo pipefail

DOMAIN="${DOMAIN:?Set DOMAIN=your.domain.com}"
EMAIL="${EMAIL:-admin@${DOMAIN}}"

apt-get update -qq
apt-get install -y -qq certbot python3-certbot-nginx

certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect

# Update Laravel .env
ENV_FILE="/var/www/creativesuite-erp/.env"
if [[ -f "${ENV_FILE}" ]]; then
  sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|" "${ENV_FILE}"
  sed -i "s|FRONTEND_URL=.*|FRONTEND_URL=https://${DOMAIN}|" "${ENV_FILE}"
  sed -i 's|SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' "${ENV_FILE}"
  cd /var/www/creativesuite-erp
  sudo -u www-data php artisan config:cache
fi

# Update frontend
FE_ENV="/var/www/creativesuite-frontend/.env.production"
if [[ -f "${FE_ENV}" ]]; then
  sed -i "s|NEXT_PUBLIC_API_URL=.*|NEXT_PUBLIC_API_URL=https://${DOMAIN}/api/v1|" "${FE_ENV}"
  systemctl restart creativesuite-frontend
fi

echo "SSL aktif: https://${DOMAIN}"
echo "Renewal otomatis via certbot timer."