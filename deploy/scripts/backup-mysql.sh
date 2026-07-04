#!/usr/bin/env bash
# Backup database CreativeSuite ERP
# Cron harian: 0 2 * * * /opt/creativesuite/backup-mysql.sh
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/creativesuite}"
DB_NAME="${DB_NAME:-creativesuite_erp}"
DB_USER="${DB_USER:-creativesuite}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
STAMP=$(date +%Y%m%d-%H%M)
CREDS="/root/creativesuite-credentials.txt"

mkdir -p "${BACKUP_DIR}"

if [[ -f "${CREDS}" ]]; then
  DB_PASS=$(grep -i 'MySQL Password' "${CREDS}" | awk '{print $NF}')
else
  DB_PASS="${DB_PASS:?Set DB_PASS environment variable}"
fi

FILE="${BACKUP_DIR}/${DB_NAME}-${STAMP}.sql.gz"
mysqldump -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" | gzip > "${FILE}"
find "${BACKUP_DIR}" -name "${DB_NAME}-*.sql.gz" -mtime +${RETAIN_DAYS} -delete
echo "Backup OK: ${FILE}"