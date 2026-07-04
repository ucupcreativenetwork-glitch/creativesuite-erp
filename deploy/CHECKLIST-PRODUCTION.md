# Checklist Production — CreativeSuite ERP

Gunakan checklist ini setelah instalasi di Ubuntu 22.

## Instalasi

- [ ] Upload & extract ZIP release
- [ ] Jalankan `sudo SERVER_IP=... bash deploy/scripts/install-linux.sh`
- [ ] Jalankan `sudo SERVER_IP=... bash deploy/scripts/post-install-check.sh` — semua OK
- [ ] Simpan `/root/creativesuite-credentials.txt` di tempat aman

## Keamanan

- [ ] Ganti password `admin@demo.id`
- [ ] Set `SEED_DEMO=false` dan `APP_DEBUG=false` di `.env`
- [ ] `php artisan config:cache`
- [ ] Pasang SSL: `sudo DOMAIN=erp.domain.id bash deploy/scripts/setup-ssl.sh`
- [ ] Firewall: hanya port 22, 80, 443 terbuka

## Operasional

- [ ] Setup backup harian: copy `backup-mysql.sh` ke `/opt/creativesuite/`
- [ ] Cron backup: `0 2 * * * /opt/creativesuite/backup-mysql.sh`
- [ ] Cek scheduler: `systemctl status creativesuite-scheduler`
- [ ] Cek queue: `systemctl status creativesuite-queue`

## HR & Mobile

- [ ] Konfigurasi geofence cabang (`/settings/branches`)
- [ ] Kebijakan HR (`/settings/hr`)
- [ ] Mapping PIN mesin absensi (`/settings/integrations`)
- [ ] Distribusi APK ke karyawan
- [ ] Set URL API mobile: `http://IP/api/v1` atau `https://domain/api/v1`

## Integrasi (opsional)

- [ ] Buat API key jika perlu integrasi eksternal
- [ ] Setup connector ZKTeco/Hikvision
- [ ] Test push ingest log di Settings → Integrasi

## Dokumentasi

- [ ] Bagikan `CreativeSuite-ERP-Manual-Book.docx` ke admin & HRD
- [ ] Bagikan `PANDUAN-LENGKAP.md` ke tim IT