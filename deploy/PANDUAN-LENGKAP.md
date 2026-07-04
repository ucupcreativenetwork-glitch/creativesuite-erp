# CreativeSuite ERP — Panduan Lengkap

**Versi:** 1.0 | **Tanggal:** Juni 2026  
**Manual Book Word:** `CreativeSuite-ERP-Manual-Book.docx`

---

## Daftar Isi

1. [Pengenalan Sistem](#1-pengenalan-sistem)
2. [Memulai & Login](#2-memulai--login)
3. [Instalasi Server Ubuntu 22](#3-instalasi-server-ubuntu-2204)
4. [Dashboard & Navigasi](#4-dashboard--navigasi)
5. [Modul Keuangan](#5-modul-keuangan)
6. [Modul HR — Absensi](#6-modul-hr--absensi)
7. [Modul HR — Cuti & Izin](#7-modul-hr--cuti--izin)
8. [Modul HR — Payroll](#8-modul-hr--payroll)
9. [Pengaturan HR & Geofence](#9-pengaturan-hr--geofence)
10. [Integrasi & Mesin Biometrik](#10-integrasi--mesin-biometrik)
11. [Aplikasi Mobile HR](#11-aplikasi-mobile-hr)
12. [Peran & Hak Akses](#12-peran--hak-akses)
13. [Modul Bisnis Lainnya](#13-modul-bisnis-lainnya)
14. [Troubleshooting](#14-troubleshooting)
15. [Lampiran](#15-lampiran)

---

## 1. Pengenalan Sistem

### 1.1 Apa itu CreativeSuite ERP?

CreativeSuite ERP adalah sistem ERP (Enterprise Resource Planning) untuk perusahaan Indonesia yang menggabungkan:

- **Keuangan & Akuntansi** — COA, jurnal, invoice, pajak PPN/PPh21
- **HR** — Absensi GPS/selfie, cuti, payroll, geofence
- **CRM & Penjualan** — Pelanggan, penawaran harga
- **Operasional** — Tiket, work order, proyek
- **Rantai Pasok** — Inventori, purchase order
- **Integrasi** — API key, webhook, mesin absensi ZKTeco/Hikvision

### 1.2 Komponen Teknis

| Komponen | Teknologi | Lokasi |
|----------|-----------|--------|
| Backend API | Laravel PHP 8.2 | `/var/www/creativesuite-erp` |
| Web Frontend | Next.js 16 | `/var/www/creativesuite-frontend` |
| Mobile HR | Expo React Native | APK `CreativeSuite-HR.apk` |
| Database | MySQL 8.0 | `creativesuite_erp` |
| Web Server | Nginx | Port 80 |

### 1.3 Konsep Multi-Tenant

```
Tenant (organisasi)
 └── Company (perusahaan hukum)
      └── Branch (cabang + geofence)
           └── Employee (karyawan)
                └── User (akun login)
```

- **Login Web:** slug tenant (contoh: `pt-demo`) atau nama perusahaan
- **Login Mobile:** nama perusahaan (trade name), contoh: `Demo Agency`
- Semua API membutuhkan header `X-Company-ID` untuk konteks perusahaan aktif

---

## 2. Memulai & Login

### 2.1 Login Web

1. Buka `http://IP-SERVER` di browser
2. Masukkan kredensial:

| Field | Demo |
|-------|------|
| Perusahaan | Demo Agency |
| Email | admin@demo.id |
| Password | Password123 |

3. Setelah login, pilih perusahaan aktif (jika multi-company)

### 2.2 Registrasi Perusahaan Baru

Menu **Register** → isi data perusahaan, NPWP, timezone `Asia/Jakarta` → sistem membuat tenant, company, cabang pusat, dan akun owner otomatis.

### 2.3 Keamanan

| Fitur | Keterangan |
|-------|------------|
| JWT Token | Expire 8 jam, refresh otomatis |
| 2FA | TOTP via authenticator app |
| Reset Password | Via email |
| Aktivasi User | OTP + set password |

### 2.4 Ganti Password Pertama Kali

Menu profil → **Ganti Password** → wajib dilakukan setelah deploy production.

---

## 3. Instalasi Server Ubuntu 22.04

> Server kosong (belum ada MySQL/nginx) — semua diinstal otomatis.

### 3.1 Persyaratan

| Item | Minimum |
|------|---------|
| OS | Ubuntu 22.04 LTS |
| RAM | 2 GB (4 GB disarankan) |
| Disk | 10 GB kosong |
| Akses | SSH + sudo |

### 3.2 File Release

```
creativesuite-erp-release-ubuntu22.zip  (~43 MB)
├── backend/      Laravel API
├── frontend/     Next.js (sudah di-build)
├── mobile/       CreativeSuite-HR.apk
└── deploy/       Script & konfigurasi
```

### 3.3 Langkah Instalasi

**Dari Windows — upload ZIP:**
```powershell
scp "C:\Users\TNN IT\projects\creativesuite-deploy\creativesuite-erp-release-ubuntu22.zip" user@IP-SERVER:/tmp/
```

**Di server Ubuntu:**
```bash
ssh user@IP-SERVER
sudo apt update && sudo apt install -y unzip
cd /tmp
unzip creativesuite-erp-release-ubuntu22.zip -d creativesuite
cd creativesuite
sudo SERVER_IP=IP-SERVER bash deploy/scripts/install-linux.sh
```

### 3.4 Yang Diinstal Otomatis

1. PHP 8.2 + ekstensi (mysql, gd, zip, bcmath, dll.)
2. MySQL 8.0 + database `creativesuite_erp`
3. Node.js 20
4. Nginx (port 80)
5. Deploy backend & frontend ke `/var/www/`
6. Migrate + seed data demo
7. Systemd: frontend, queue worker, scheduler
8. Firewall port 80

### 3.5 Verifikasi

```bash
curl http://IP-SERVER/up
sudo systemctl status nginx php8.2-fpm creativesuite-frontend creativesuite-queue creativesuite-scheduler
sudo cat /root/creativesuite-credentials.txt
```

### 3.6 Setelah Production

Edit `/var/www/creativesuite-erp/.env`:
```env
SEED_DEMO=false
APP_DEBUG=false
```

```bash
cd /var/www/creativesuite-erp
sudo -u www-data php artisan config:cache
```

---

## 4. Dashboard & Navigasi

### 4.1 Dashboard Utama (`/dashboard`)

Menampilkan ringkasan KPI:
- Jurnal, invoice, pendapatan
- Tiket terbuka, jumlah akun CRM
- Kartu HR (untuk pimpinan)

### 4.2 Menu Utama

| Menu | Path | Modul |
|------|------|-------|
| Dashboard | `/dashboard` | Overview |
| Absensi | `/attendance` | HR |
| Cuti | `/leave` | HR |
| Payroll | `/payroll` | HR |
| HR Saya | `/my-hr` | Self-service |
| Keuangan | `/finance/*` | Finance |
| CRM | `/crm/accounts` | CRM |
| Penjualan | `/sales/quotations` | Sales |
| Proyek | `/projects` | Projects |
| Operasional | `/operations/*` | Ops |
| Inventori | `/inventory` | Inventory |
| Pembelian | `/purchasing` | Purchasing |
| Laporan | `/reports` | Reports |
| Pengaturan | `/settings/*` | Admin |

---

## 5. Modul Keuangan

### 5.1 Chart of Accounts (`/finance/coa`)

- Struktur akun bertingkat (asset, liability, equity, revenue, expense)
- COA standar Indonesia dibuat otomatis saat registrasi

### 5.2 Jurnal (`/finance/journals`)

- Buat jurnal manual → Posting → masuk buku besar
- Void jurnal dengan jurnal pembalik
- Jurnal otomatis dari invoice, payment, payroll

### 5.3 Invoice (`/finance/invoices`)

| Tipe | Keterangan |
|------|------------|
| SALES | Faktur penjualan → piutang |
| PURCHASE | Faktur pembelian → hutang |

Workflow: Draft → Posted → Paid

### 5.4 Pembayaran (`/finance/payments`)

- AR Receipt — terima pembayaran dari pelanggan
- AP Disbursement — bayar hutang ke vendor

### 5.5 Laporan Keuangan

| Laporan | Path |
|---------|------|
| Neraca Saldo | `/finance/reports/trial-balance` |
| Buku Besar | `/finance/reports/general-ledger` |
| Laba Rugi | `/finance/reports/profit-loss` |
| Neraca | `/finance/reports/balance-sheet` |
| AR Aging | `/finance/reports/ar-aging` |
| AP Aging | `/finance/reports/ap-aging` |

### 5.6 Pajak Indonesia

| Fitur | Path |
|-------|------|
| PPN | `/finance/tax/ppn` |
| e-Faktur | `/finance/tax/efaktur` |
| SPT Masa PPN | `/finance/tax/spt-ppn` |
| PPh 23 | `/finance/tax/pph23` |
| e-Bupot | `/finance/tax/ebupot` |

---

## 6. Modul HR — Absensi

### 6.1 Clock In / Clock Out

**Web:** Menu Absensi → tombol Clock In / Clock Out

**Mobile:** Tab Absensi → tombol besar Clock In/Out

| Status | Keterangan |
|--------|------------|
| PRESENT | Hadir tepat waktu |
| LATE | Hadir terlambat |
| ABSENT | Tidak hadir (auto-mark) |
| LEAVE | Sedang cuti/izin |
| HALF_DAY | Setengah hari |

### 6.2 GPS & Selfie (Mobile)

| Aturan | Default |
|--------|---------|
| Wajib GPS | Ya |
| Wajib Selfie | Ya |
| Akurasi GPS maks | 80 meter |
| Hanya mobile | Ya (web tidak wajib GPS) |

**Alur mobile:**
1. Izinkan lokasi & kamera
2. Validasi GPS accuracy
3. Validasi geofence
4. Ambil foto selfie
5. Kirim ke API

### 6.3 Geofence

**Settings → Cabang & Geofence:**
- Aktifkan geofence per cabang
- Set latitude, longitude, radius (meter)
- Link Google Maps untuk preview lokasi

### 6.4 Live Dashboard (`/attendance` → tab Live)

- Bucket: Hadir, Telat, Belum Absen, Alpa, Cuti
- Refresh real-time
- Export CSV riwayat

### 6.5 Koreksi Absensi (HRD)

- Tombol **Detail** di riwayat → lihat GPS & selfie
- **Koreksi** jam masuk/pulang (permission `hr.attendance.manage`)
- Input manual absensi jika diperlukan

### 6.6 Auto-Absent & Reminder

| Job | Jadwal | Fungsi |
|-----|--------|--------|
| `hr:mark-daily-absent` | Per jam | Tandai alpa otomatis |
| `hr:send-clock-in-reminders` | 15 menit | Push pengingat |
| Skip libur | Otomatis | Libur nasional & perusahaan |

---

## 7. Modul HR — Cuti & Izin

### 7.1 Jenis Cuti

| Kode | Label |
|------|-------|
| ANNUAL | Cuti Tahunan |
| PERMISSION | Izin |
| SICK | Sakit |
| UNPAID | Tanpa Gaji |
| MATERNITY | Cuti Melahirkan |
| MARRIAGE | Cuti Menikah |
| BEREAVEMENT | Cuti Duka |

### 7.2 Pengajuan Cuti

**Web:** `/leave` → **Ajukan Cuti**  
**Mobile:** Tab Cuti → form pengajuan

1. Pilih jenis cuti
2. Pilih tanggal mulai & selesai
3. Sistem hitung hari kerja (exclude weekend & libur)
4. Cek saldo cuti tahunan
5. Submit → status PENDING

### 7.3 Approval (Pimpinan)

1. Buka `/leave` atau notifikasi push
2. Review pengajuan PENDING
3. **Setujui** atau **Tolak** (dengan alasan)
4. Jika disetujui → absensi otomatis tercatat sebagai LEAVE

### 7.4 Saldo Cuti

| Field | Keterangan |
|-------|------------|
| Entitlement | Hak cuti tahunan (default 12 hari) |
| Accrued | Diakrual (mode MONTHLY) |
| Carried Forward | Sisa tahun lalu (maks 6 hari) |
| Used | Sudah dipakai |
| Pending | Dalam pengajuan |
| Remaining | Sisa tersedia |

**Mode akrual:**
- **ANNUAL** — full entitlement langsung
- **MONTHLY** — akrual 1/12 per bulan (job otomatis tanggal 1)

### 7.5 Libur

**Libur Nasional:** otomatis dari database Indonesia 2026+  
**Libur Perusahaan:** Settings → HR → daftar custom  
Toggle: `include_national_holidays`

---

## 8. Modul HR — Payroll

### 8.1 Data Karyawan (`/payroll` → tab Karyawan)

| Field | Keterangan |
|-------|------------|
| No. Karyawan | Auto-generate jika kosong |
| Gaji Pokok | Base salary |
| Tunjangan Tetap | Allowance amount |
| TER PPh21 | Kategori A/B/C |
| No. BPJS | Untuk export BPJS |
| PIN Absensi | Untuk mesin biometrik |
| Kontrak | Tipe, tanggal mulai/berakhir |

### 8.2 Siklus Payroll

```
Buat Payroll Run → Hitung → Posting → Cairkan
     (DRAFT)      (CALCULATED)  (POSTED)   (jurnal cash-out)
```

| Langkah | Aksi | Permission |
|---------|------|------------|
| 1. Buat | Pilih tahun & bulan | `hr.payroll.create` |
| 2. Hitung | Kalkulasi gaji semua karyawan aktif | `hr.payroll.calculate` |
| 3. Posting | Finalisasi + jurnal akuntansi | `hr.payroll.post` |
| 4. Cairkan | Transfer gaji (pilih rekening bank) | `fin.payment.create` |

### 8.3 Komponen Gaji

| Komponen | Perhitungan |
|----------|-------------|
| Gross | Gaji pokok + tunjangan |
| Lembur | Jam lembur × multiplier (default 1.5×) |
| Potongan telat | Per 15 menit (default Rp 25.000) |
| Potongan alpa | Hari alpa × tarif harian |
| BPJS karyawan | 2% dari gross |
| PPh21 TER | Tarif efektif kategori A/B/C |
| Cuti tanpa gaji | Hari UNPAID × tarif harian |
| **Net** | Gross + lembur - semua potongan |

### 8.4 Slip Gaji

- **Web:** Payroll Run → expand → tombol **Slip**
- **Mobile:** Tab Gaji → pilih periode → detail + **Bagikan**
- **Cetak:** `/print/payslips/{runPublicId}`

### 8.5 Export BPJS

Tombol **BPJS** di payroll run → download CSV untuk pelaporan BPJS.

---

## 9. Pengaturan HR & Geofence

### 9.1 Settings → Pengaturan HR (`/settings/hr`)

| Pengaturan | Default |
|------------|---------|
| Jam kerja | 08:00 – 17:00 |
| Grace telat | 15 menit |
| Wajib GPS | Ya |
| Wajib Selfie | Ya |
| Auto-mark absent | Ya |
| Reminder clock-in | 15 menit sebelum |
| Libur nasional | Include |
| Cuti tahunan | 12 hari |
| Carry forward | 6 hari |
| Mode akrual | ANNUAL |

**Pengaturan Payroll:**
- Tarif BPJS karyawan & perusahaan
- Potongan telat per 15 menit
- Multiplier lembur & alpa
- Hari kerja per bulan

### 9.2 Settings → Cabang & Geofence (`/settings/branches`)

1. Pilih cabang
2. Toggle **Aktifkan Geofence**
3. Isi koordinat (latitude, longitude)
4. Set radius (50–5000 meter)
5. Simpan

---

## 10. Integrasi & Mesin Biometrik

### 10.1 Settings → Integrasi (`/settings/integrations`)

| Fitur | Fungsi |
|-------|--------|
| API Key | Akses API eksternal |
| Webhook | Notifikasi ke sistem lain |
| Connector | Mesin absensi biometrik |
| PIN Mapping | Tabel mapping PIN karyawan |

### 10.2 Setup Connector ZKTeco

1. **Buat Connector** → tipe ZKTeco
2. Pilih field match: **PIN Mesin Absensi**
3. Salin **Ingest Token** dan **Push URL**
4. Konfigurasi di mesin ZKTeco:
   - URL: `http://IP-SERVER/api/v1/external/connectors/push`
   - Header: `X-Connector-Token: {token}`
   - Field: `PIN`, `DateTime`, `Status`

### 10.3 Setup Connector Hikvision

- Field match: `device_pin` atau `employee_number`
- Payload: `AccessControllerEvent.employeeNoString`

### 10.4 Mapping PIN Karyawan

**Cara 1:** Settings → Integrasi → tabel PIN Mapping (bulk)  
**Cara 2:** Payroll → Edit Karyawan → field PIN Absensi

### 10.5 Log & Troubleshooting

- Expand **Log Ingest** di card connector
- Lihat processed count & error messages
- Expand **Pengiriman** di card webhook

---

## 11. Aplikasi Mobile HR

### 11.1 Instalasi

1. Copy `mobile/CreativeSuite-HR.apk` ke HP
2. Aktifkan instalasi dari sumber tidak dikenal
3. Install & buka app

### 11.2 Konfigurasi

| Setting | Production Ubuntu |
|---------|-----------------|
| URL API | `http://IP-SERVER/api/v1` |
| Perusahaan | Demo Agency |
| Email | admin@demo.id |
| Password | Password123 |

### 11.3 Fitur per Tab

| Tab | Fitur |
|-----|-------|
| **Absensi** | Clock in/out, GPS, selfie, geofence |
| **Riwayat** | 30 hari terakhir, badge GPS/selfie |
| **Cuti** | Ajukan, preview hari kerja, saldo, batalkan |
| **Gaji** | Slip gaji, TER, bagikan |
| **Notifikasi** | Push HR dengan deep link |

### 11.4 Push Notifikasi

| Event | Penerima |
|-------|----------|
| Cuti pending | Pimpinan |
| Cuti approved/rejected | Pemohon |
| Reminder absen | Karyawan |
| Karyawan telat | Pimpinan |
| Kontrak hampir habis | Pimpinan |

---

## 12. Peran & Hak Akses

### 12.1 Peran Pimpinan (Leader)

Dapat mengelola payroll, approve cuti, lihat live absensi:

`DIRECTOR`, `GENERAL_MANAGER`, `HEAD_HRD`, `HEAD_FINANCE`, dll.

### 12.2 Permission HR

| Permission | Akses |
|------------|-------|
| `hr.attendance.read` | Lihat absensi sendiri |
| `hr.attendance.manage` | Koreksi, live, export |
| `hr.leave.create` | Ajukan cuti |
| `hr.leave.approve` | Approve/reject cuti |
| `hr.leave.manage` | Adjust saldo cuti |
| `hr.employee.read` | Lihat data karyawan |
| `hr.employee.update` | Edit karyawan |
| `hr.payroll.create` | Buat payroll run |
| `hr.payroll.calculate` | Hitung gaji |
| `hr.payroll.post` | Posting payroll |
| `core.company.update` | Pengaturan HR & geofence |

### 12.3 IAM — Permintaan User Baru

**Settings → Permintaan User:**
1. Kepala divisi ajukan akun baru
2. Workflow approval multi-tahap
3. Setelah disetujui → akun aktif

---

## 13. Modul Bisnis Lainnya

### 13.1 CRM (`/crm/accounts`)

- Master pelanggan, vendor, partner
- NPWP, kontak, status aktif

### 13.2 Penjualan (`/sales/quotations`)

- Buat penawaran → Kirim → Terima
- Line items, diskon, PPN

### 13.3 Proyek (`/projects`)

- CRUD proyek, anggaran
- Timesheet, milestone
- Generate invoice dari milestone

### 13.4 Operasional

- **Tiket** (`/operations/tickets`) — support ticket
- **Work Order** (`/operations/work-orders`) — perintah kerja lapangan

### 13.5 Inventori & Pembelian

- **Inventori** — master barang, gudang, stok, pergerakan
- **Pembelian** — PO: buat → submit → approve → receive
- Auto-reorder saat stok minimum

---

## 14. Troubleshooting

### Server

| Masalah | Solusi |
|---------|--------|
| 502 Bad Gateway | `sudo systemctl restart nginx php8.2-fpm creativesuite-frontend` |
| Frontend mati | `sudo journalctl -u creativesuite-frontend -n 50` |
| Queue tidak jalan | `sudo systemctl restart creativesuite-queue` |
| Scheduler mati | `sudo systemctl restart creativesuite-scheduler` |
| MySQL error | Cek `/root/creativesuite-credentials.txt` |

### Aplikasi

| Masalah | Solusi |
|---------|--------|
| Login gagal | Cek nama perusahaan & password |
| API 401 | Login ulang; token expired |
| GPS ditolak | Coba di luar ruangan; cek akurasi |
| Geofence error | Cek koordinat cabang |
| Saldo cuti 0 | Cek mode akrual di Settings HR |
| Push tidak masuk | Izinkan notifikasi di HP |

### Reset Instalasi

```bash
sudo rm -rf /var/www/creativesuite-erp /var/www/creativesuite-frontend
cd /tmp/creativesuite
sudo SERVER_IP=IP-SERVER bash deploy/scripts/install-linux.sh
```

---

## 15. Lampiran

### A. Service Systemd

| Service | Fungsi |
|---------|--------|
| `nginx` | Web server |
| `php8.2-fpm` | PHP Laravel |
| `creativesuite-frontend` | Next.js |
| `creativesuite-queue` | Queue worker |
| `creativesuite-scheduler` | Cron jobs |
| `mysql` | Database |

### B. API Endpoints Utama

| Grup | Base Path |
|------|-----------|
| Auth | `/api/v1/auth/*` |
| HR | `/api/v1/hr/*` |
| Finance | `/api/v1/finance/*` |
| Integrations | `/api/v1/integrations/*` |
| External | `/api/v1/external/*` |

### C. Akun Demo

| Item | Nilai |
|------|-------|
| Perusahaan | Demo Agency |
| Email | admin@demo.id |
| Password | Password123 |
| Tenant slug | pt-demo |

### D. File Dokumentasi

| File | Isi |
|------|-----|
| `CreativeSuite-ERP-Manual-Book.docx` | Manual book Word |
| `PANDUAN-LENGKAP.md` | Panduan ini |
| `DEPLOY.md` | Deploy singkat |
| `UBUNTU-22.md` | Ubuntu 22 khusus |

---

*© 2026 CreativeSuite ERP — Panduan Lengkap v1.0*