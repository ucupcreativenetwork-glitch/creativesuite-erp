# Deploy ke Ubuntu 22.04 (Server Kosong)

Panduan ini untuk server Ubuntu 22 yang **belum ada MySQL/nginx/PHP** — script instalasi mengurus semuanya otomatis.

## Persyaratan

| Item | Minimum |
|------|---------|
| OS | Ubuntu 22.04 LTS |
| RAM | 2 GB (4 GB disarankan) |
| Disk | 10 GB kosong |
| Akses | SSH + sudo |

---

## Langkah 1 — Upload ZIP dari komputer Windows

Di komputer development (sudah ada ZIP):

```
C:\Users\TNN IT\projects\creativesuite-deploy\creativesuite-erp-release-20260622-1720.zip
```

Upload ke server via SCP (ganti `user` dan `IP-SERVER`):

```powershell
# Dari PowerShell di Windows:
scp "C:\Users\TNN IT\projects\creativesuite-deploy\creativesuite-erp-release-20260622-1720.zip" user@IP-SERVER:/tmp/
```

Atau pakai **WinSCP** / **FileZilla** — drag ZIP ke `/tmp/` di server.

---

## Langkah 2 — SSH ke server & extract

```bash
ssh user@IP-SERVER

# Install unzip jika belum ada
sudo apt update && sudo apt install -y unzip

cd /tmp
unzip creativesuite-erp-release-20260622-1720.zip -d creativesuite
cd creativesuite

# Cek struktur (harus ada backend, frontend, deploy)
ls -la
```

---

## Langkah 3 — Jalankan instalasi otomatis

Ganti `IP-SERVER` dengan IP publik/lokal server Anda:

```bash
sudo SERVER_IP=IP-SERVER bash deploy/scripts/install-linux.sh
```

**Durasi:** ~10–15 menit (download PHP, MySQL, Node.js).

Script otomatis:
1. Install PHP 8.2, MySQL 8, Node.js 20, Nginx
2. Buat database `creativesuite_erp`
3. Deploy backend + frontend
4. Migrate + seed data demo
5. Setup nginx (port 80)
6. Aktifkan queue worker + scheduler
7. Buka firewall port 80

---

## Langkah 4 — Cek hasil

```bash
# Health check API
curl http://IP-SERVER/up

# Status services
sudo systemctl status nginx php8.2-fpm creativesuite-frontend creativesuite-queue creativesuite-scheduler
```

Buka browser: **http://IP-SERVER**

| Login | Nilai |
|-------|-------|
| Perusahaan | Demo Agency |
| Email | admin@demo.id |
| Password | Password123 |

---

## Langkah 5 — Mobile APK

1. Copy `mobile/CreativeSuite-HR.apk` ke HP
2. Install APK
3. Set URL API: `http://IP-SERVER/api/v1`

---

## Kredensial MySQL

Disimpan otomatis di server:

```bash
sudo cat /root/creativesuite-credentials.txt
```

---

## Troubleshooting

### Script gagal di PHP 8.2
```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
```

### 502 Bad Gateway
```bash
sudo systemctl restart php8.2-fpm creativesuite-frontend nginx
sudo journalctl -u creativesuite-frontend -n 50
```

### Frontend tidak start
```bash
cd /var/www/creativesuite-frontend
sudo npm install --omit=dev
sudo systemctl restart creativesuite-frontend
```

### Reset & install ulang
```bash
sudo rm -rf /var/www/creativesuite-erp /var/www/creativesuite-frontend
cd /tmp/creativesuite
sudo SERVER_IP=IP-SERVER bash deploy/scripts/install-linux.sh
```

---

## Setelah production stabil

Edit `/var/www/creativesuite-erp/.env`:

```env
SEED_DEMO=false
APP_DEBUG=false
```

Lalu:
```bash
cd /var/www/creativesuite-erp
sudo -u www-data php artisan config:cache
```

Ganti password `admin@demo.id` dari dalam aplikasi.