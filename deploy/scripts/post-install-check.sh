#!/usr/bin/env bash
# Verifikasi instalasi CreativeSuite ERP
set -euo pipefail

SERVER_IP="${SERVER_IP:-192.168.1.102}"
PASS=0
FAIL=0

check() {
  local name="$1"
  local cmd="$2"
  if eval "${cmd}" >/dev/null 2>&1; then
    echo "[OK]   ${name}"
    PASS=$((PASS + 1))
  else
    echo "[FAIL] ${name}"
    FAIL=$((FAIL + 1))
  fi
}

echo "=== Post-Install Check ==="
echo "Server: ${SERVER_IP}"
echo ""

check "Nginx running" "systemctl is-active nginx"
check "PHP-FPM running" "systemctl is-active php8.2-fpm"
check "MySQL running" "systemctl is-active mysql"
check "Frontend service" "systemctl is-active creativesuite-frontend"
check "Queue worker" "systemctl is-active creativesuite-queue"
check "Scheduler" "systemctl is-active creativesuite-scheduler"
check "API health /up" "curl -sf http://${SERVER_IP}/up"
check "Web frontend HTTP 200" "curl -sf -o /dev/null -w '%{http_code}' http://${SERVER_IP}/ | grep -q 200"
check "API login route" "curl -sf -o /dev/null -w '%{http_code}' -X POST http://${SERVER_IP}/api/v1/auth/login -H 'Content-Type: application/json' -d '{}' | grep -qE '4[0-9]{2}'"
check "Backend .env exists" "test -f /var/www/creativesuite-erp/.env"
check "JWT configured" "grep -q JWT_SECRET= /var/www/creativesuite-erp/.env && ! grep -q 'JWT_SECRET=$' /var/www/creativesuite-erp/.env"
check "Storage linked" "test -L /var/www/creativesuite-erp/public/storage"
check "Frontend .env.production" "test -f /var/www/creativesuite-frontend/.env.production"
check "Backup script" "test -x /opt/creativesuite/backup-mysql.sh"
check "Credentials file" "test -f /root/creativesuite-credentials.txt"

echo ""
echo "Passed: ${PASS} | Failed: ${FAIL}"
[[ ${FAIL} -eq 0 ]] && exit 0 || exit 1