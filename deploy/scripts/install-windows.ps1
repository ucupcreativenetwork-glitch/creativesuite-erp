# CreativeSuite ERP — Instalasi production (Windows Server + XAMPP)
# Jalankan sebagai Administrator di PowerShell:
#   Set-ExecutionPolicy Bypass -Scope Process -Force
#   .\install-windows.ps1 -ServerIp "10.110.1.15"

param(
    [string]$ServerIp = "192.168.1.102",
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$MysqlPath = "C:\xampp\mysql\bin\mysql.exe",
    [string]$InstallRoot = "C:\inetpub\creativesuite"
)

$ErrorActionPreference = "Stop"
$DeployDir = Split-Path $PSScriptRoot -Parent
$ReleaseDir = Split-Path $DeployDir -Parent

$BackendDir = Join-Path $InstallRoot "backend"
$FrontendDir = Join-Path $InstallRoot "frontend"
$DbName = "creativesuite_erp"
$DbUser = "creativesuite"
$DbPass = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })

Write-Host "=== CreativeSuite ERP — Instalasi Windows ===" -ForegroundColor Cyan

if (-not (Test-Path $PhpPath)) {
    throw "PHP tidak ditemukan di $PhpPath. Install XAMPP atau sesuaikan -PhpPath."
}

Write-Host "[1/8] Buat folder instalasi..."
New-Item -ItemType Directory -Force -Path $BackendDir, $FrontendDir | Out-Null

Write-Host "[2/8] Copy backend..."
$releaseBackend = Join-Path $ReleaseDir "backend"
if (-not (Test-Path $releaseBackend)) {
    throw "Folder release/backend tidak ditemukan. Jalankan pack-release.ps1 dulu."
}
robocopy $releaseBackend $BackendDir /MIR /XD .git node_modules vendor storage\logs /XF .env database.sqlite /NFL /NDL /NJH /NJS /nc /ns /np
if ($LASTEXITCODE -ge 8) { throw "robocopy backend gagal" }

Write-Host "[3/8] Composer install..."
Set-Location $BackendDir
& composer install --no-dev --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { throw "composer install gagal" }

Write-Host "[4/8] Setup .env & database..."
$envFile = Join-Path $BackendDir ".env"
if (-not (Test-Path $envFile)) {
    Copy-Item (Join-Path $DeployDir "env\backend.env.production") $envFile
    (Get-Content $envFile) `
        -replace 'APP_URL=.*', "APP_URL=http://${ServerIp}:8000" `
        -replace 'FRONTEND_URL=.*', "FRONTEND_URL=http://${ServerIp}:3000" `
        -replace 'DB_PASSWORD=.*', "DB_PASSWORD=$DbPass" `
        | Set-Content $envFile
    & $PhpPath artisan key:generate --force
    & $PhpPath artisan jwt:secret --force
}

$createDb = "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass'; GRANT ALL ON ${DbName}.* TO '$DbUser'@'localhost'; FLUSH PRIVILEGES;"
& $MysqlPath -u root -e $createDb 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "  (gunakan root tanpa password — sesuaikan jika perlu)" -ForegroundColor Yellow
    & $MysqlPath -u root --password= -e $createDb
}

(Get-Content $envFile) `
    -replace 'DB_USERNAME=.*', "DB_USERNAME=$DbUser" `
    -replace 'DB_PASSWORD=.*', "DB_PASSWORD=$DbPass" `
    | Set-Content $envFile

& $PhpPath artisan migrate --force
& $PhpPath artisan db:seed --force
& $PhpPath artisan storage:link --force
& $PhpPath artisan config:cache
& $PhpPath artisan route:cache

Write-Host "[5/8] Copy & build frontend..."
$releaseFrontend = Join-Path $ReleaseDir "frontend"
robocopy $releaseFrontend $FrontendDir /MIR /XD .git node_modules /NFL /NDL /NJH /NJS /nc /ns /np
if ($LASTEXITCODE -ge 8) { throw "robocopy frontend gagal" }

$feEnv = Join-Path $FrontendDir ".env.production"
if (-not (Test-Path $feEnv)) {
    Copy-Item (Join-Path $DeployDir "env\frontend.env.production") $feEnv
    (Get-Content $feEnv) -replace 'NEXT_PUBLIC_API_URL=.*', "NEXT_PUBLIC_API_URL=http://${ServerIp}:8000/api/v1" | Set-Content $feEnv
}

Set-Location $FrontendDir
npm ci --omit=dev 2>$null
if ($LASTEXITCODE -ne 0) { npm install --omit=dev }

Write-Host "[6/8] Buat startup scripts..."
$startBackend = Join-Path $InstallRoot "start-backend.ps1"
$startFrontend = Join-Path $InstallRoot "start-frontend.ps1"
$startScheduler = Join-Path $InstallRoot "start-scheduler.ps1"
$startQueue = Join-Path $InstallRoot "start-queue.ps1"

@"
Set-Location '$BackendDir'
& '$PhpPath' artisan serve --host=0.0.0.0 --port=8000
"@ | Set-Content $startBackend

@"
Set-Location '$FrontendDir'
`$env:NODE_ENV='production'
npm run start -- -p 3000 -H 0.0.0.0
"@ | Set-Content $startFrontend

@"
Set-Location '$BackendDir'
& '$PhpPath' artisan schedule:work
"@ | Set-Content $startScheduler

@"
Set-Location '$BackendDir'
& '$PhpPath' artisan queue:work database --sleep=3 --tries=3
"@ | Set-Content $startQueue

Write-Host "[7/8] Buat Task Scheduler (opsional)..."
$taskName = "CreativeSuite-Scheduler"
schtasks /Create /TN $taskName /TR "powershell -File `"$startScheduler`"" /SC MINUTE /MO 1 /F 2>$null

Write-Host "[8/8] Selesai!" -ForegroundColor Green
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  CreativeSuite ERP siap digunakan"
Write-Host "=========================================="
Write-Host "  Backend:  http://${ServerIp}:8000"
Write-Host "  Frontend: http://${ServerIp}:3000"
Write-Host "  API:      http://${ServerIp}:8000/api/v1"
Write-Host ""
Write-Host "  Login demo:"
Write-Host "    Perusahaan: Demo Agency"
Write-Host "    Email:      admin@demo.id"
Write-Host "    Password:   Password123"
Write-Host ""
Write-Host "  MySQL user: $DbUser"
Write-Host "  MySQL pass: $DbPass"
Write-Host ""
Write-Host "  Jalankan di terminal terpisah:"
Write-Host "    powershell -File `"$startBackend`""
Write-Host "    powershell -File `"$startFrontend`""
Write-Host "    powershell -File `"$startQueue`""
Write-Host "=========================================="