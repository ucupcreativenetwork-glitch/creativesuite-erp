# CreativeSuite ERP — Buat paket release siap upload ke server
# Jalankan: .\pack-release.ps1 [-ServerIp "192.168.1.102"]

param(
    [string]$ServerIp = "192.168.1.102",
    [string]$ProjectsRoot = "C:\Users\TNN IT\projects",
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [switch]$SkipFrontendBuild
)

$ErrorActionPreference = "Stop"
$DeployDir = Split-Path $PSScriptRoot -Parent
$ReleaseDir = Join-Path $DeployDir "release"
$BackendSrc = Join-Path $ProjectsRoot "creativesuite-erp"
$FrontendSrc = Join-Path $ProjectsRoot "creativesuite-erp-frontend"
$MobileSrc = Join-Path $ProjectsRoot "creativesuite-erp-mobile"
$Timestamp = Get-Date -Format "yyyyMMdd-HHmm"
$ZipPath = Join-Path $DeployDir "creativesuite-erp-release-$Timestamp.zip"

Write-Host "=== Pack CreativeSuite ERP Release ===" -ForegroundColor Cyan
Write-Host "Server IP: $ServerIp"

if (Test-Path $ReleaseDir) { Remove-Item $ReleaseDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path `
    (Join-Path $ReleaseDir "backend"), `
    (Join-Path $ReleaseDir "frontend"), `
    (Join-Path $ReleaseDir "mobile"), `
    (Join-Path $ReleaseDir "deploy") | Out-Null

Write-Host "[1/5] Copy backend..."
robocopy $BackendSrc (Join-Path $ReleaseDir "backend") /MIR `
    /XD .git node_modules vendor storage\logs bootstrap\cache `
    /XF .env .env.backup database.sqlite `
    /NFL /NDL /NJH /NJS /nc /ns /np
if ($LASTEXITCODE -ge 8) { throw "robocopy backend gagal: $LASTEXITCODE" }

@("storage\app\public", "storage\framework\cache", "storage\framework\sessions", "storage\framework\views", "storage\logs") | ForEach-Object {
    New-Item -ItemType Directory -Force -Path (Join-Path $ReleaseDir "backend\$_") | Out-Null
}
New-Item -ItemType File -Force -Path (Join-Path $ReleaseDir "backend\storage\logs\.gitkeep") | Out-Null

if ($SkipFrontendBuild) {
    Write-Host "[2/5] Skip frontend build (pakai .next yang ada)..." -ForegroundColor Yellow
    if (-not (Test-Path (Join-Path $FrontendSrc ".next"))) {
        throw "SkipFrontendBuild=true tapi .next tidak ada. Jalankan npm run build dulu."
    }
} else {
    Write-Host "[2/5] Build frontend production..."
    $feEnvProd = Join-Path $FrontendSrc ".env.production"
    $feEnvBackup = $null
    if (Test-Path $feEnvProd) { $feEnvBackup = Get-Content $feEnvProd -Raw }
    @"
NEXT_PUBLIC_API_URL=http://${ServerIp}/api/v1
NEXT_PUBLIC_APP_NAME=CreativeSuite ERP
"@ | Set-Content $feEnvProd
    Set-Location $FrontendSrc
    npm run build
    if ($LASTEXITCODE -ne 0) { throw "npm run build gagal" }
    if ($feEnvBackup) { $feEnvBackup | Set-Content $feEnvProd } else { Remove-Item $feEnvProd -ErrorAction SilentlyContinue }
}

Write-Host "[3/5] Copy frontend build..."
$feDest = Join-Path $ReleaseDir "frontend"
@("package.json", "package-lock.json", "next.config.ts", "next.config.mjs", "next.config.js", "tsconfig.json", "postcss.config.mjs", ".env.production") | ForEach-Object {
    $src = Join-Path $FrontendSrc $_
    if (Test-Path $src) { Copy-Item $src (Join-Path $feDest $_) -Force }
}
if (Test-Path (Join-Path $FrontendSrc "public")) {
    robocopy (Join-Path $FrontendSrc "public") (Join-Path $feDest "public") /E /NFL /NDL /NJH /NJS /nc /ns /np
}
robocopy (Join-Path $FrontendSrc ".next") (Join-Path $feDest ".next") /E /XD cache dev /NFL /NDL /NJH /NJS /nc /ns /np
if ($LASTEXITCODE -ge 8) { throw "robocopy frontend gagal" }

Write-Host "[4/5] Copy mobile APK..."
$apk = Join-Path $MobileSrc "dist\CreativeSuite-HR.apk"
if (Test-Path $apk) {
    Copy-Item $apk (Join-Path $ReleaseDir "mobile\CreativeSuite-HR.apk")
} else {
    Write-Host "  APK tidak ditemukan - lewati" -ForegroundColor Yellow
}

Write-Host "[5/6] Copy deploy scripts dan dokumentasi..."
robocopy $DeployDir (Join-Path $ReleaseDir "deploy") /E /XD release docs creativesuite-deploy-kit-* /XF creativesuite-erp-release-*.zip creativesuite-deploy-kit-*.zip /NFL /NDL /NJH /NJS /nc /ns /np
@("CreativeSuite-ERP-Manual-Book.docx", "PANDUAN-LENGKAP.md", "PANDUAN-TERIMA-BERSIH.md", "UBUNTU-22.md", "DEPLOY.md", "CHECKLIST-PRODUCTION.md") | ForEach-Object {
    $src = Join-Path $DeployDir $_
    if (Test-Path $src) { Copy-Item $src (Join-Path $ReleaseDir "deploy\$_") -Force }
}
@("deploy-config.ps1", "auto-deploy.ps1", "preflight-check.ps1", "pack-deploy-kit.ps1") | ForEach-Object {
    $src = Join-Path $DeployDir "scripts\$_"
    if (Test-Path $src) { Copy-Item $src (Join-Path $ReleaseDir "deploy\scripts\$_") -Force }
}
@("TERIMA-BERSIH.bat", "UPDATE-SERVER.bat") | ForEach-Object {
    $src = Join-Path $DeployDir $_
    if (Test-Path $src) { Copy-Item $src (Join-Path $ReleaseDir "deploy\$_") -Force }
}
$versionFile = Join-Path $ReleaseDir "VERSION.txt"
@"CreativeSuite ERP Release`nBuilt: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")`nServer IP: $ServerIp"@ | Set-Content $versionFile
Write-Host "[6/6] Buat ZIP..."
$beEnv = Join-Path $ReleaseDir "deploy\env\backend.env.production"
$feEnv = Join-Path $ReleaseDir "deploy\env\frontend.env.production"
(Get-Content $beEnv) -replace '192\.168\.1\.102|10\.110\.1\.15', $ServerIp | Set-Content $beEnv
(Get-Content $feEnv) -replace '192\.168\.1\.102|10\.110\.1\.15', $ServerIp | Set-Content $feEnv
(Get-Content (Join-Path $ReleaseDir "deploy\nginx\creativesuite.conf")) -replace '192\.168\.1\.102|10\.110\.1\.15', $ServerIp | Set-Content (Join-Path $ReleaseDir "deploy\nginx\creativesuite.conf")
if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }
Compress-Archive -Path (Join-Path $ReleaseDir "*") -DestinationPath $ZipPath -CompressionLevel Optimal
$zipSize = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Release siap deploy! ZIP: $ZipPath - $zipSize MB"
Write-Host "=========================================="