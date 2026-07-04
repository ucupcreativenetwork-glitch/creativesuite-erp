# CreativeSuite ERP

Sistem ERP multi-tenant untuk manajemen HR, keuangan, dan operasional bisnis.

## Struktur Project

```
backend/     Laravel API (PHP 8.2+)
frontend/    Next.js web app
deploy/      Skrip instalasi & konfigurasi production
mobile/      APK Android (CreativeSuite HR)
```

## Server Production

| Item | Value |
|------|-------|
| Server | `192.168.1.102` (Ubuntu 22.04) |
| Web | http://192.168.1.102 |
| API | http://192.168.1.102/api/v1 |
| Health | http://192.168.1.102/up |

### Login Demo

- **Perusahaan:** Demo Agency
- **Email:** admin@demo.id
- **Password:** Password123

## Panduan Instalasi & Konfigurasi

| Dokumen | Isi |
|---------|-----|
| [deploy/PANDUAN-TERIMA-BERSIH.md](deploy/PANDUAN-TERIMA-BERSIH.md) | Deploy pertama — tinggal klik (Windows/Ubuntu) |
| [deploy/PANDUAN-LENGKAP.md](deploy/PANDUAN-LENGKAP.md) | Panduan lengkap development & production |
| [deploy/DEPLOY.md](deploy/DEPLOY.md) | Referensi deploy manual |
| [deploy/UBUNTU-22.md](deploy/UBUNTU-22.md) | Spesifik Ubuntu 22.04 |
| [deploy/CHECKLIST-PRODUCTION.md](deploy/CHECKLIST-PRODUCTION.md) | Checklist sebelum go-live |
| [deploy/GITHUB-SECRETS.md](deploy/GITHUB-SECRETS.md) | Setup auto-deploy via GitHub Actions |

## Deploy Manual

```bash
# Dari PC Ubuntu/Linux
cd deploy/scripts
chmod +x *.sh
./deploy-from-ubuntu.sh fresh   # instalasi pertama
./deploy-from-ubuntu.sh update  # update
```

Windows: double-click `deploy/TERIMA-BERSIH.bat` (deploy pertama) atau `deploy/UPDATE-SERVER.bat` (update).

## Deploy via GitHub Actions

Push ke branch `main` akan otomatis deploy ke server production. Lihat [deploy/GITHUB-SECRETS.md](deploy/GITHUB-SECRETS.md).

## Development

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret --force
php artisan migrate --seed

# Frontend
cd frontend
npm install
npm run dev
```