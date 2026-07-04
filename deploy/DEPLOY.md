# CreativeSuite ERP - Panduan Deploy Production

**Server:** `192.168.1.102` | **Ubuntu 22.04**

## Cara Tercepat (Terima Bersih)

### PC yang bisa akses server

1. Extract `creativesuite-deploy-kit-192.168.1.102.zip`
2. Edit `scripts\deploy-config.ps1` (user SSH)
3. Double-click `CEK-KONEKSI.bat` (opsional)
4. Double-click `TERIMA-BERSIH.bat`

### PC development (punya source code)

1. Edit `scripts\deploy-config.ps1`
2. Double-click `TERIMA-BERSIH.bat` di folder `creativesuite-deploy`

---

## Isi Paket Release

| Folder | Isi |
|--------|-----|
| `backend/` | Laravel API |
| `frontend/` | Next.js (sudah di-build) |
| `mobile/` | APK Android |
| `deploy/` | Script instalasi, nginx, env |

---

## Script Utama

| Script | Fungsi |
|--------|--------|
| `TERIMA-BERSIH.bat` | Deploy pertama |
| `UPDATE-SERVER.bat` | Update setelah perbaikan kode |
| `CEK-KONEKSI.bat` | Cek ping + SSH + ZIP |
| `scripts/auto-deploy.ps1` | Otomasi lengkap |
| `scripts/pack-release.ps1` | Buat ZIP dari source |
| `scripts/pack-deploy-kit.ps1` | Buat kit untuk PC lain |
| `scripts/preflight-check.ps1` | Validasi sebelum deploy |

---

## Perintah Manual

```powershell
# Pack release
cd scripts
.\pack-release.ps1 -ServerIp "192.168.1.102"

# Deploy pertama
.\auto-deploy.ps1 -Mode Fresh

# Update
.\auto-deploy.ps1 -Mode Update

# Buat kit untuk PC lain
.\pack-deploy-kit.ps1 -ServerIp "192.168.1.102"
```

```bash
# Di server (manual)
sudo SERVER_IP=192.168.1.102 bash deploy/scripts/install-linux.sh
sudo SERVER_IP=192.168.1.102 bash deploy/scripts/update-linux.sh
sudo SERVER_IP=192.168.1.102 bash deploy/scripts/post-install-check.sh
```

---

## Akses Setelah Instalasi

| URL | Keterangan |
|-----|------------|
| http://192.168.1.102 | Web ERP |
| http://192.168.1.102/api/v1 | API |
| http://192.168.1.102/up | Health check |

**Login demo:** Demo Agency / admin@demo.id / Password123

**Mobile API:** http://192.168.1.102/api/v1

---

## Setelah Production Stabil

1. Ganti password admin
2. Set `SEED_DEMO=false` di `/var/www/creativesuite-erp/.env`
3. `sudo -u www-data php artisan config:cache`
4. Pasang SSL: `sudo DOMAIN=erp.domain.id bash deploy/scripts/setup-ssl.sh`

Lihat `CHECKLIST-PRODUCTION.md` untuk detail lengkap.

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Preflight SSH gagal | Cek user/password, `SshKeyPath`, firewall |
| ERP sudah terinstall | Pakai `UPDATE-SERVER.bat` bukan Fresh |
| Reinstall paksa | `sudo FORCE_FRESH=true SERVER_IP=... bash install-linux.sh` |
| 502 Bad Gateway | `sudo systemctl restart php8.2-fpm creativesuite-frontend nginx` |
| Upload selfie gagal | PHP upload_max_filesize sudah 32MB di install script |