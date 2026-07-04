# CreativeSuite ERP - Auto deploy ke Ubuntu server
#
# Deploy pertama:  .\auto-deploy.ps1 -Mode Fresh
# Update kode:      .\auto-deploy.ps1 -Mode Update
# Paket kit only:   .\auto-deploy.ps1 -Mode DeployOnly -ZipPath "..\release\creativesuite-erp-release.zip"

param(
    [ValidateSet("Fresh", "Update", "PackOnly", "DeployOnly")]
    [string]$Mode = "Fresh",

    [ValidateSet("Fresh", "Update")]
    [string]$DeployAction = "",

    [string]$ConfigFile = "",
    [string]$ZipPath = "",
    [switch]$SkipPreflight
)

if ($DeployAction -eq "" -and $Mode -eq "DeployOnly") { $DeployAction = "Fresh" }
if ($DeployAction -eq "" -and $Mode -ne "DeployOnly") { $DeployAction = $Mode }

$ErrorActionPreference = "Stop"
$ScriptDir = $PSScriptRoot
$DeployDir = Split-Path $ScriptDir -Parent

if ($ConfigFile -eq "") {
    $ConfigFile = Join-Path $ScriptDir "deploy-config.ps1"
}
if (-not (Test-Path $ConfigFile)) {
    throw "Config tidak ditemukan: $ConfigFile"
}

. $ConfigFile
if (-not (Get-Variable KitMode -ErrorAction SilentlyContinue)) { $KitMode = $false }

$cfg = @{
    ServerIp           = $ServerIp
    SshUser            = $SshUser
    SshPort            = $SshPort
    SshKeyPath         = $SshKeyPath
    ProjectsRoot       = $ProjectsRoot
    PhpPath            = $PhpPath
    RemoteTmpDir       = $RemoteTmpDir
    RemoteRelease      = $RemoteRelease
    ZipRemoteName      = $ZipRemoteName
    RunTestsBeforePack = $RunTestsBeforePack
    SkipFrontendBuild  = $SkipFrontendBuild
    KitMode            = $KitMode
}

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host ">> $Message" -ForegroundColor Cyan
}

function Get-SshBaseArgs() {
    $args = @(
        "-p", $cfg.SshPort,
        "-o", "BatchMode=no",
        "-o", "ConnectTimeout=20",
        "-o", "ServerAliveInterval=30",
        "-o", "ServerAliveCountMax=120",
        "-o", "StrictHostKeyChecking=accept-new"
    )
    if ($cfg.SshKeyPath -and (Test-Path $cfg.SshKeyPath)) {
        $args += @("-i", $cfg.SshKeyPath)
    }
    return $args
}

function Invoke-Remote([string]$RemoteCommand) {
    $target = "{0}@{1}" -f $cfg.SshUser, $cfg.ServerIp
    $sshArgs = Get-SshBaseArgs
    & ssh @sshArgs $target $RemoteCommand
    if ($LASTEXITCODE -ne 0) {
        throw "SSH gagal (exit $LASTEXITCODE)"
    }
}

function Copy-ToServer([string]$LocalFile, [string]$RemotePath) {
    $target = "{0}@{1}:{2}" -f $cfg.SshUser, $cfg.ServerIp, $RemotePath
    $scpArgs = @(
        "-P", $cfg.SshPort,
        "-o", "ConnectTimeout=20",
        "-o", "ServerAliveInterval=30",
        "-o", "StrictHostKeyChecking=accept-new"
    )
    if ($cfg.SshKeyPath -and (Test-Path $cfg.SshKeyPath)) {
        $scpArgs += @("-i", $cfg.SshKeyPath)
    }
    & scp @scpArgs $LocalFile $target
    if ($LASTEXITCODE -ne 0) {
        throw "SCP gagal upload ke $target"
    }
}

function Resolve-ZipPath([string]$Path) {
    if ([System.IO.Path]::IsPathRooted($Path)) {
        return $Path
    }
    $fromScript = Join-Path $ScriptDir $Path
    if (Test-Path $fromScript) { return (Resolve-Path $fromScript).Path }
    $fromDeploy = Join-Path $DeployDir $Path
    if (Test-Path $fromDeploy) { return (Resolve-Path $fromDeploy).Path }
    return $Path
}

Write-Host "==========================================" -ForegroundColor Green
Write-Host "  CreativeSuite ERP - Auto Deploy" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host "Mode:         $Mode / $DeployAction"
Write-Host "Server:       $($cfg.SshUser)@$($cfg.ServerIp):$($cfg.SshPort)"
Write-Host "=========================================="

if (-not $SkipPreflight -and $Mode -ne "PackOnly") {
    Write-Step "Preflight check..."
    & (Join-Path $ScriptDir "preflight-check.ps1") -ConfigFile $ConfigFile
    if ($LASTEXITCODE -ne 0) {
        throw "Preflight gagal. Perbaiki masalah di atas lalu coba lagi."
    }
}

if ($Mode -ne "DeployOnly") {
    if ($cfg.RunTestsBeforePack) {
        Write-Step "Jalankan backend tests..."
        $backend = Join-Path $cfg.ProjectsRoot "creativesuite-erp"
        if (-not (Test-Path $cfg.PhpPath)) {
            throw "PHP tidak ditemukan: $($cfg.PhpPath)"
        }
        Push-Location $backend
        & $cfg.PhpPath artisan test
        if ($LASTEXITCODE -ne 0) { throw "Backend tests gagal" }
        Pop-Location
    }

    Write-Step "Buat paket release..."
    $packParams = @{
        ServerIp     = $cfg.ServerIp
        ProjectsRoot = $cfg.ProjectsRoot
        PhpPath      = $cfg.PhpPath
    }
    if ($cfg.SkipFrontendBuild) { $packParams.SkipFrontendBuild = $true }
    & (Join-Path $ScriptDir "pack-release.ps1") @packParams

    $latestZip = Get-ChildItem (Join-Path $DeployDir "creativesuite-erp-release-*.zip") |
        Where-Object { $_.Name -notlike "*deploy-kit*" } |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1
    if (-not $latestZip) { throw "ZIP release tidak ditemukan setelah pack" }
    $ZipPath = $latestZip.FullName

    $stableZip = Join-Path $DeployDir "creativesuite-erp-release-ubuntu22.zip"
    Copy-Item $ZipPath $stableZip -Force
    Write-Host "  ZIP: $ZipPath" -ForegroundColor Gray
}

if ($Mode -eq "PackOnly") {
    Write-Host ""
    Write-Host "Pack selesai." -ForegroundColor Green
    Write-Host "  scp `"$ZipPath`" $($cfg.SshUser)@$($cfg.ServerIp):$($cfg.RemoteTmpDir)/"
    exit 0
}

if ($ZipPath) {
    $ZipPath = Resolve-ZipPath $ZipPath
}
if (-not $ZipPath -or -not (Test-Path $ZipPath)) {
    $ZipPath = Join-Path $DeployDir "creativesuite-erp-release-ubuntu22.zip"
    if (-not (Test-Path $ZipPath)) {
        throw "ZIP tidak ditemukan. Jalankan pack-release.ps1 atau set -ZipPath."
    }
    $ZipPath = (Resolve-Path $ZipPath).Path
}

Write-Step "Tes koneksi SSH..."
Invoke-Remote "echo SSH_OK && uname -a"

Write-Step "Upload ZIP (~$([math]::Round((Get-Item $ZipPath).Length / 1MB, 1)) MB)..."
$remoteZip = "{0}/{1}" -f $cfg.RemoteTmpDir, $cfg.ZipRemoteName
Copy-ToServer $ZipPath $remoteZip

Write-Step "Extract release di server..."
$extractCmd = @"
set -e
sudo DEBIAN_FRONTEND=noninteractive apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq unzip rsync
sudo rm -rf '$($cfg.RemoteRelease)'
sudo mkdir -p '$($cfg.RemoteRelease)'
sudo unzip -o '$remoteZip' -d '$($cfg.RemoteRelease)'
if [ ! -d '$($cfg.RemoteRelease)/backend' ]; then
  echo 'ERROR: struktur ZIP salah - folder backend tidak ada'
  ls -la '$($cfg.RemoteRelease)'
  exit 1
fi
sudo chown -R `$(whoami):`$(whoami) '$($cfg.RemoteRelease)' 2>/dev/null || true
echo 'Extract OK'
"@
Invoke-Remote $extractCmd

if ($DeployAction -eq "Fresh") {
    Write-Step "Instalasi production (~10-15 menit)..."
    $installCmd = "cd '$($cfg.RemoteRelease)' && sudo SERVER_IP=$($cfg.ServerIp) bash deploy/scripts/install-linux.sh"
    Invoke-Remote $installCmd
}
else {
    Write-Step "Update production..."
    $updateCmd = "cd '$($cfg.RemoteRelease)' && sudo SERVER_IP=$($cfg.ServerIp) bash deploy/scripts/update-linux.sh"
    Invoke-Remote $updateCmd
}

Write-Step "Post-install check..."
try {
    Invoke-Remote "cd '$($cfg.RemoteRelease)' && sudo SERVER_IP=$($cfg.ServerIp) bash deploy/scripts/post-install-check.sh"
}
catch {
    Write-Host "  Beberapa cek gagal - lihat log di server." -ForegroundColor Yellow
}

Write-Step "Kredensial server..."
try {
    Invoke-Remote "sudo cat /root/creativesuite-credentials.txt 2>/dev/null || echo '(file kredensial belum ada)'"
}
catch { }

$kitRoot = Split-Path $ScriptDir -Parent
$apkCandidates = @(
    (Join-Path $kitRoot "mobile\CreativeSuite-HR.apk"),
    (Join-Path $cfg.ProjectsRoot "creativesuite-deploy\release\mobile\CreativeSuite-HR.apk")
)

Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  DEPLOY SELESAI" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Web:    http://$($cfg.ServerIp)"
Write-Host "  API:    http://$($cfg.ServerIp)/api/v1"
Write-Host "  Health: http://$($cfg.ServerIp)/up"
Write-Host ""
Write-Host "  Login: Demo Agency / admin@demo.id / Password123"
Write-Host "  Mobile API: http://$($cfg.ServerIp)/api/v1"
foreach ($apk in $apkCandidates) {
    if (Test-Path $apk) {
        Write-Host "  APK: $apk"
        break
    }
}
Write-Host "==========================================" -ForegroundColor Green