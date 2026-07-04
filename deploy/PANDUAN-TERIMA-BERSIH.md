# CreativeSuite ERP — Panduan Terima Bersih

**Server:** `192.168.1.102` (Ubuntu 22.04)  
**Versi:** 1.0 | **Tanggal:** Juni 2026

Panduan ini untuk Anda yang ingin **tinggal klik deploy** — tanpa manual upload/extract/SSH berulang. Termasuk alur **perbaikan source code** dan deploy ulang.

---

## Daftar Isi

1. [Ringkasan — Apa yang Anda Terima](#1-ringkasan--apa-yang-anda-terima)
2. [Prasyarat (Sekali Saja)](#2-prasyarat-sekali-saja)
3. [Deploy Pertama — Terima Bersih](#3-deploy-pertama--terima-bersih)
4. [Setelah Deploy — Cek & Login](#4-setelah-deploy--cek--login)
5. [Perbaikan Source Code & Deploy Ulang](#5-perbaikan-source-code--deploy-ulang)
6. [Struktur Project Development](#6-struktur-project-development)
7. [Script & Perintah Referensi](#7-script--perintah-referensi)
8. [Troubleshooting](#8-troubleshooting)
9. [Checklist Production](#9-checklist-production)

---

## 1. Ringkasan — Apa yang Anda Terima

| File / Script | Fungsi |
|---------------|--------|
| `TERIMA-BERSIH.bat` | **Double-click** → deploy pertama ke server kosong |
| `UPDATE-SERVER.bat` | **Double-click** → update setelah perbaikan kode |
| `scripts/auto-deploy.ps1` | Script utama: pack + upload + install/update otomatis |
| `scripts/deploy-config.ps1` | Konfigurasi IP, user SSH, path project |
| `scripts/pack-release.ps1` | Buat ZIP release dari source code |
| `creativesuite-erp-release-ubuntu22.zip` | Paket siap production |
| `CreativeSuite-ERP-Manual-Book.docx` | Manual penggunaan aplikasi |
| `PANDUAN-LENGKAP.md` | Panduan fitur lengkap |

**Hasil deploy di server:**

| URL | Keterangan |
|-----|------------|
| http://192.168.1.102 | Web ERP |
| http://192.168.1.102/api/v1 | API |
| http://192.168.1.102/up | Health check |

**Login demo:**

| Field | Nilai |
|-------|-------|
| Perusahaan | Demo Agency |
| Email | admin@demo.id |
| Password | Password123 |

---

## 2. Prasyarat (Sekali Saja)

### 2.1 Server Ubuntu 22.04 (`192.168.1.102`)

- Server **kosong** (belum ada MySQL/Nginx/PHP) — script instalasi mengurus semuanya
- RAM minimal 2 GB (4 GB disarankan)
- Disk kosong minimal 10 GB
- SSH aktif (port 22)
- User dengan akses `sudo` (contoh: `ubuntu`)

**Cek dari Windows:**

```powershell
ssh ubuntu@192.168.1.102 "echo OK && lsb_release -ds"
```

Jika diminta password, masukkan password user SSH server.

### 2.2 Komputer Development (Windows)

| Tool | Path / Versi |
|------|----------------|
| Node.js 20+ | `node -v` |
| PHP 8.2 (XAMPP) | `C:\xampp\php\php.exe` |
| OpenSSH | Sudah ada di Windows 10/11 |
| Project | `C:\Users\TNN IT\projects\` |

### 2.3 Edit Konfigurasi Deploy (Wajib Sekali)

Buka file:

```
C:\Users\TNN IT\projects\creativesuite-deploy\scripts\deploy-config.ps1
```

Sesuaikan:

```powershell
ServerIp   = "192.168.1.102"    # IP server Anda
SshUser    = "ubuntu"           # GANTI jika user SSH berbeda
SshKeyPath = ""                 # Isi path kunci SSH jika pakai key
                                # contoh: "C:\Users\TNN IT\.ssh\id_rsa"
```

**Opsional — jalankan test sebelum pack:**

```powershell
RunTestsBeforePack = $true   # jalankan php artisan test dulu
```

### 2.4 SSH Key (Disarankan, Opsional)

Agar tidak diminta password setiap deploy:

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\id_ed25519" -N '""'
type "$env:USERPROFILE\.ssh\id_ed25519.pub" | ssh ubuntu@192.168.1.102 "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
```

Lalu set di `deploy-config.ps1`:

```powershell
SshKeyPath = "C:\Users\TNN IT\.ssh\id_ed25519"
```

---

## 3. Deploy Pertama — Terima Bersih

### Cara A — Double-click (paling mudah)

1. Buka folder `creativesuite-deploy`
2. Double-click **`TERIMA-BERSIH.bat`**
3. Tunggu ~15–25 menit (pack + upload + install di server)
4. Selesai — buka http://192.168.1.102

### Cara B — PowerShell

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-deploy\scripts"
.\auto-deploy.ps1 -Mode Fresh
```

### Apa yang Dilakukan Script Otomatis

| Langkah | Di Windows | Di Server Ubuntu |
|---------|------------|------------------|
| 1 | Build frontend production | — |
| 2 | Copy backend + frontend + APK + scripts | — |
| 3 | Buat ZIP release | — |
| 4 | Upload ZIP via SCP | — |
| 5 | — | Extract ke `/tmp/creativesuite-release` |
| 6 | — | Install PHP 8.2, MySQL 8, Node 20, Nginx |
| 7 | — | Deploy ke `/var/www/creativesuite-erp` & `frontend` |
| 8 | — | Migrate + seed demo |
| 9 | — | Aktifkan queue, scheduler, firewall |
| 10 | — | Post-install check |

**Kredensial MySQL** disimpan otomatis di server:

```bash
ssh ubuntu@192.168.1.102 "sudo cat /root/creativesuite-credentials.txt"
```

---

## 4. Setelah Deploy — Cek & Login

### 4.1 Cek dari Browser

1. http://192.168.1.102/up → harus tampil `{"status":"ok"}` atau serupa
2. http://192.168.1.102 → halaman login ERP

### 4.2 Cek dari Server

```bash
ssh ubuntu@192.168.1.102
sudo SERVER_IP=192.168.1.102 bash /tmp/creativesuite-release/deploy/scripts/post-install-check.sh
sudo systemctl status nginx php8.2-fpm creativesuite-frontend creativesuite-queue creativesuite-scheduler
```

### 4.3 Mobile APK

1. Ambil APK dari: `creativesuite-deploy\release\mobile\CreativeSuite-HR.apk`
2. Install di HP Android
3. URL API: `http://192.168.1.102/api/v1`

HP harus berada di **jaringan yang sama** (LAN) dengan server agar IP `192.168.1.102` bisa diakses.

---

## 5. Perbaikan Source Code & Deploy Ulang

Ini alur lengkap jika ada **bug fix, fitur baru, atau penyesuaian**.

### 5.1 Workflow Umum

```
Edit source → Test lokal → Pack release → Deploy update → Cek production
```

### 5.2 Backend (Laravel API)

**Lokasi:** `C:\Users\TNN IT\projects\creativesuite-erp`

| Jenis perubahan | File / folder umum |
|-----------------|-------------------|
| API endpoint baru | `routes/api.php`, `app/Http/Controllers/` |
| Logika bisnis HR | `app/Services/Hr/`, `app/Models/` |
| Database baru | `database/migrations/` |
| Konfigurasi HR | `config/hr.php`, `.env` |
| Permission | `database/seeders/`, migration permission |

**Test lokal:**

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-erp"
C:\xampp\php\php.exe artisan test
C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000
```

**Setelah deploy update**, migration otomatis dijalankan di server.

### 5.3 Frontend (Next.js Web)

**Lokasi:** `C:\Users\TNN IT\projects\creativesuite-erp-frontend`

| Jenis perubahan | File / folder umum |
|-----------------|-------------------|
| Halaman UI | `src/app/`, `src/components/` |
| API client | `src/lib/api.ts` atau serupa |
| Env production | `.env.production` |

**Test lokal:**

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-erp-frontend"
npm run dev
# Buka http://localhost:3000
```

**Build production** (otomatis saat pack, atau manual):

```powershell
npm run build
```

### 5.4 Mobile (Expo React Native)

**Lokasi:** `C:\Users\TNN IT\projects\creativesuite-erp-mobile`

**Build APK baru:**

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-erp-mobile"
npm run build:apk
# Output: dist\CreativeSuite-HR.apk
```

APK ikut masuk ke ZIP release saat pack. Distribusikan ulang ke karyawan setelah update.

### 5.5 Deploy Update ke Server (Setelah Perbaikan)

**Cara A — Double-click:**

```
UPDATE-SERVER.bat
```

**Cara B — PowerShell:**

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-deploy\scripts"
.\auto-deploy.ps1 -Mode Update
```

**Cara C — Dengan test otomatis dulu:**

Edit `deploy-config.ps1` → `RunTestsBeforePack = $true`, lalu:

```powershell
.\auto-deploy.ps1 -Mode Update
```

### 5.6 Yang Dilakukan Mode Update (tanpa reinstall)

- Maintenance mode ON
- Rsync backend & frontend baru
- `composer install`, `migrate --force`
- Rebuild cache config/route/view
- Restart nginx, php-fpm, frontend, queue
- Maintenance mode OFF
- Health check

**Data production (database, upload, .env) TIDAK dihapus.**

### 5.7 Perubahan yang Butuh Langkah Manual di Server

| Perubahan | Tindakan tambahan |
|-----------|-------------------|
| Ubah `APP_URL` / domain | Edit `/var/www/creativesuite-erp/.env`, lalu `php artisan config:cache` |
| Ubah geofence default | Edit `.env` atau lewat UI Settings → Branches |
| Tambah env variable baru | Tambah di `.env` server, jangan overwrite dari template |
| SSL / HTTPS | `sudo DOMAIN=erp.domain.id bash deploy/scripts/setup-ssl.sh` |

---

## 6. Struktur Project Development

```
C:\Users\TNN IT\projects\
├── creativesuite-erp\              # Backend Laravel (API)
├── creativesuite-erp-frontend\     # Web Next.js
├── creativesuite-erp-mobile\       # Mobile Expo (APK)
└── creativesuite-deploy\         # Script deploy & dokumentasi
    ├── TERIMA-BERSIH.bat           # Deploy pertama (klik)
    ├── UPDATE-SERVER.bat           # Update server (klik)
    ├── scripts\
    │   ├── deploy-config.ps1       # Konfigurasi server
    │   ├── auto-deploy.ps1         # Auto deploy utama
    │   ├── pack-release.ps1        # Buat ZIP
    │   ├── install-linux.sh        # Install di Ubuntu
    │   ├── update-linux.sh         # Update di Ubuntu
    │   ├── post-install-check.sh   # Verifikasi
    │   ├── backup-mysql.sh         # Backup database
    │   └── setup-ssl.sh            # HTTPS Certbot
    ├── creativesuite-erp-release-ubuntu22.zip
    └── PANDUAN-TERIMA-BERSIH.md    # File ini
```

**Di server setelah install:**

```
/var/www/creativesuite-erp/         # Backend production
/var/www/creativesuite-frontend/    # Frontend production
/root/creativesuite-credentials.txt # Kredensial MySQL + login
/var/log/creativesuite-install.log  # Log instalasi
```

---

## 7. Script & Perintah Referensi

### Mode auto-deploy.ps1

| Mode | Kapan dipakai |
|------|---------------|
| `Fresh` | Server kosong / install ulang penuh |
| `Update` | Perbaikan source code, deploy incremental |
| `PackOnly` | Hanya buat ZIP, tanpa upload |
| `DeployOnly` | Upload ZIP yang sudah ada (`-ZipPath "..."`) |

### Contoh perintah

```powershell
# Deploy pertama
.\auto-deploy.ps1 -Mode Fresh

# Update setelah fix bug
.\auto-deploy.ps1 -Mode Update

# Hanya pack (tanpa upload)
.\auto-deploy.ps1 -Mode PackOnly

# Upload ZIP tertentu saja
.\auto-deploy.ps1 -Mode DeployOnly -ZipPath "C:\...\creativesuite-erp-release-ubuntu22.zip"

# Pack manual dengan IP custom
.\pack-release.ps1 -ServerIp "192.168.1.102"
```

### Perintah di server (manual)

```bash
# Cek log instalasi
sudo tail -100 /var/log/creativesuite-install.log

# Restart semua service
sudo systemctl restart nginx php8.2-fpm creativesuite-frontend creativesuite-queue creativesuite-scheduler

# Backup database
sudo bash /tmp/creativesuite-release/deploy/scripts/backup-mysql.sh

# Lihat log Laravel
sudo tail -50 /var/www/creativesuite-erp/storage/logs/laravel.log
```

---

## 8. Troubleshooting

### SSH gagal / connection timed out

- Pastikan server `192.168.1.102` nyala dan PC Anda di jaringan yang sama
- Cek: `ping 192.168.1.102`
- Cek SSH: `ssh -v ubuntu@192.168.1.102`
- Pastikan `SshUser` di `deploy-config.ps1` benar

### Diminta password SSH terus

- Setup SSH key (lihat [bagian 2.4](#24-ssh-key-disarankan-opsional))
- Atau isi `SshKeyPath` di config

### npm run build gagal saat pack

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-erp-frontend"
npm ci
npm run build
```

### php artisan test gagal

```powershell
cd "C:\Users\TNN IT\projects\creativesuite-erp"
C:\xampp\php\php.exe artisan test --filter=NamaTest
```

Perbaiki test yang gagal sebelum deploy.

### 502 Bad Gateway di browser

```bash
ssh ubuntu@192.168.1.102
sudo systemctl restart php8.2-fpm creativesuite-frontend nginx
sudo journalctl -u creativesuite-frontend -n 50
```

### Mobile tidak bisa connect API

- HP harus di LAN yang sama dengan server
- URL API: `http://192.168.1.102/api/v1` (bukan https kecuali SSL sudah dipasang)
- Cek firewall server: port 80 terbuka

### Install ulang dari awal

```bash
ssh ubuntu@192.168.1.102
sudo rm -rf /var/www/creativesuite-erp /var/www/creativesuite-frontend
```

Lalu jalankan ulang `TERIMA-BERSIH.bat` dari Windows.

---

## 9. Checklist Production

Setelah sistem jalan stabil:

- [ ] Ganti password `admin@demo.id`
- [ ] Set `SEED_DEMO=false` di `/var/www/creativesuite-erp/.env`
- [ ] `sudo -u www-data php artisan config:cache`
- [ ] Simpan `/root/creativesuite-credentials.txt` di tempat aman
- [ ] Setup backup harian (`backup-mysql.sh` + cron)
- [ ] Konfigurasi geofence cabang di UI
- [ ] Distribusi APK ke karyawan
- [ ] Pasang SSL jika punya domain publik
- [ ] Bagikan `CreativeSuite-ERP-Manual-Book.docx` ke tim

Detail lengkap: `CHECKLIST-PRODUCTION.md`

---

## Ringkasan Satu Baris

| Situasi | Yang Anda Lakukan |
|---------|-------------------|
| Deploy pertama | Double-click `TERIMA-BERSIH.bat` |
| Ada perbaikan kode | Edit source → test → double-click `UPDATE-SERVER.bat` |
| Ganti user SSH | Edit `scripts/deploy-config.ps1` |
| Cek sistem | Buka http://192.168.1.102/up |

**Anda tinggal terima bersih — sisanya diurus script.**
