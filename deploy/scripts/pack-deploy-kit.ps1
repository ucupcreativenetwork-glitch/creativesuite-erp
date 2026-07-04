# Buat paket ZIP siap copy ke PC lain (tanpa perlu source code / Node / PHP)
# Output: creativesuite-deploy-kit-192.168.1.102.zip

param(
    [string]$ServerIp = "192.168.1.102",
    [string]$DeployDir = ""
)

$ErrorActionPreference = "Stop"
if ($DeployDir -eq "") {
    $DeployDir = Split-Path $PSScriptRoot -Parent
}

$KitName = "creativesuite-deploy-kit-$ServerIp"
$KitDir = Join-Path $DeployDir $KitName
$ZipOut = Join-Path $DeployDir "$KitName.zip"

# Pakai release ZIP terbaru
$releaseZip = Join-Path $DeployDir "creativesuite-erp-release-ubuntu22.zip"
if (-not (Test-Path $releaseZip)) {
    $latest = Get-ChildItem (Join-Path $DeployDir "creativesuite-erp-release-*.zip") |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $latest) { throw "Release ZIP tidak ditemukan. Jalankan pack-release.ps1 dulu." }
    $releaseZip = $latest.FullName
}

Write-Host "=== Pack Deploy Kit ===" -ForegroundColor Cyan
Write-Host "Server IP: $ServerIp"
Write-Host "Release:   $releaseZip"

if (Test-Path $KitDir) { Remove-Item $KitDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path `
    (Join-Path $KitDir "scripts"), `
    (Join-Path $KitDir "release"), `
    (Join-Path $KitDir "mobile"), `
    (Join-Path $KitDir "docs") | Out-Null

$apkSrc = Join-Path $DeployDir "release\mobile\CreativeSuite-HR.apk"
if (-not (Test-Path $apkSrc)) {
    $apkSrc = Join-Path (Split-Path $DeployDir -Parent) "creativesuite-erp-mobile\dist\CreativeSuite-HR.apk"
}
if (Test-Path $apkSrc) {
    Copy-Item $apkSrc (Join-Path $KitDir "mobile\CreativeSuite-HR.apk") -Force
    Write-Host "APK mobile disertakan."
} else {
    Write-Host "APK tidak ditemukan - lewati." -ForegroundColor Yellow
}

# Release aplikasi (isi: backend, frontend, mobile, deploy scripts)
Copy-Item $releaseZip (Join-Path $KitDir "release\creativesuite-erp-release.zip") -Force

# Script deploy (mode DeployOnly - tidak butuh source code)
@("auto-deploy.ps1", "preflight-check.ps1", "deploy-config.ps1") | ForEach-Object {
    Copy-Item (Join-Path $DeployDir "scripts\$_") (Join-Path $KitDir "scripts\$_") -Force
}

# Config khusus kit (tanpa butuh source code)
$cfgPath = Join-Path $KitDir "scripts\deploy-config.ps1"
$content = Get-Content $cfgPath -Raw
$content = $content -replace '192\.168\.1\.102|10\.110\.1\.15', $ServerIp
if ($content -notmatch 'KitMode') {
    $content = $content.TrimEnd() + "`n`$KitMode = `$true`n"
} else {
    $content = $content -replace '\$KitMode\s*=\s*\$false', '$KitMode = $true'
}
$content | Set-Content $cfgPath -NoNewline

# VERSION
@"
CreativeSuite ERP Deploy Kit
Server: $ServerIp
Built: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
"@ | Set-Content (Join-Path $KitDir "VERSION.txt") -Encoding UTF8

# Launcher - hanya upload + install (tanpa build)
@'
@echo off
title CreativeSuite ERP - Deploy Pertama
cd /d "%~dp0scripts"
echo.
echo ==========================================
echo   CreativeSuite ERP - TERIMA BERSIH
echo   Deploy ke Ubuntu (paket siap pakai)
echo ==========================================
echo.
echo 1. Edit deploy-config.ps1 jika user SSH bukan "ubuntu"
echo 2. Pastikan PC ini bisa: ping SERVER_IP
echo.
pause
powershell -NoProfile -ExecutionPolicy Bypass -File ".\auto-deploy.ps1" -Mode DeployOnly -ZipPath "..\release\creativesuite-erp-release.zip"
echo.
pause
'@ | Set-Content (Join-Path $KitDir "TERIMA-BERSIH.bat") -Encoding ASCII

@'
@echo off
title CreativeSuite ERP - Update Server
cd /d "%~dp0scripts"
echo.
echo ==========================================
echo   CreativeSuite ERP - UPDATE SERVER
echo   Upload release baru lalu update
echo ==========================================
echo.
pause
powershell -NoProfile -ExecutionPolicy Bypass -File ".\auto-deploy.ps1" -Mode DeployOnly -ZipPath "..\release\creativesuite-erp-release.zip" -DeployAction Update
echo.
pause
'@ | Set-Content (Join-Path $KitDir "UPDATE-SERVER.bat") -Encoding ASCII

@'
@echo off
title CreativeSuite ERP - Cek Koneksi
cd /d "%~dp0scripts"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\preflight-check.ps1"
pause
'@ | Set-Content (Join-Path $KitDir "CEK-KONEKSI.bat") -Encoding ASCII

# Dokumentasi
@(
    "PANDUAN-TERIMA-BERSIH.md",
    "PANDUAN-LENGKAP.md",
    "UBUNTU-22.md",
    "CHECKLIST-PRODUCTION.md",
    "DEPLOY.md",
    "CreativeSuite-ERP-Manual-Book.docx"
) | ForEach-Object {
    $src = Join-Path $DeployDir $_
    if (Test-Path $src) { Copy-Item $src (Join-Path $KitDir "docs\$_") -Force }
}

# Quick start
@"
CreativeSuite ERP - Deploy Kit
Server: $ServerIp
==========================================

CARA PAKAI (PC yang bisa akses server):

1. Extract ZIP ini ke folder mana saja, contoh:
   C:\creativesuite-deploy-kit-$ServerIp\

2. Edit scripts\deploy-config.ps1
   - SshUser = user SSH Ubuntu Anda (default: ubuntu)
   - SshKeyPath = path kunci SSH (opsional)

3. Cek koneksi:
   ping $ServerIp
   ssh ubuntu@$ServerIp

4. Double-click TERIMA-BERSIH.bat
   (deploy pertama, ~15 menit)

5. Buka browser:
   http://$ServerIp

   Login demo:
   Perusahaan : Demo Agency
   Email      : admin@demo.id
   Password   : Password123

6. Mobile APK: folder mobile\CreativeSuite-HR.apk
   URL API di app: http://$ServerIp/api/v1

UPDATE nanti:
- Ganti file release\creativesuite-erp-release.zip dengan ZIP baru
- Double-click UPDATE-SERVER.bat

Dokumentasi lengkap: docs\PANDUAN-TERIMA-BERSIH.md
"@ | Set-Content (Join-Path $KitDir "CARA-PAKAI.txt") -Encoding UTF8

Write-Host "Buat ZIP..."
if (Test-Path $ZipOut) { Remove-Item $ZipOut -Force }
Compress-Archive -Path (Join-Path $KitDir "*") -DestinationPath $ZipOut -CompressionLevel Optimal

$sizeMb = [math]::Round((Get-Item $ZipOut).Length / 1MB, 1)
Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Deploy kit siap copy!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  File: $ZipOut"
Write-Host "  Ukuran: $sizeMb MB"
Write-Host ""
Write-Host "  Copy ZIP ini ke PC yang bisa akses $ServerIp"
Write-Host "  Extract -> double-click TERIMA-BERSIH.bat"
Write-Host "==========================================" -ForegroundColor Green