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

## Deploy Manual

```bash
# Dari PC Ubuntu/Linux
cd deploy/scripts
chmod +x *.sh
./deploy-from-ubuntu.sh fresh   # instalasi pertama
./deploy-from-ubuntu.sh update  # update
```

## Deploy via GitHub Actions

Push ke branch `main` akan otomatis deploy ke server production (butuh secrets GitHub).

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