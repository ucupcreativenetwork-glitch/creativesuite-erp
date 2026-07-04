# Cek kesiapan sebelum deploy (PC Windows -> Ubuntu server)
param(
    [string]$ConfigFile = ""
)

$ErrorActionPreference = "Stop"
$ScriptDir = $PSScriptRoot
if ($ConfigFile -eq "") {
    $ConfigFile = Join-Path $ScriptDir "deploy-config.ps1"
}
. $ConfigFile
if (-not (Get-Variable KitMode -ErrorAction SilentlyContinue)) { $KitMode = $false }

$ok = 0
$fail = 0

function Test-ItemOk([string]$Name, [bool]$Pass, [string]$Hint = "") {
    if ($Pass) {
        Write-Host "[OK]   $Name" -ForegroundColor Green
        $script:ok++
    } else {
        Write-Host "[FAIL] $Name" -ForegroundColor Red
        if ($Hint) { Write-Host "       $Hint" -ForegroundColor Yellow }
        $script:fail++
    }
}

Write-Host "=== Preflight Check ===" -ForegroundColor Cyan
Write-Host "Server: $SshUser@$ServerIp`:$SshPort"
Write-Host ""

Test-ItemOk "OpenSSH (ssh)" (Get-Command ssh -ErrorAction SilentlyContinue) "Install: Settings > Apps > Optional Features > OpenSSH Client"
Test-ItemOk "OpenSSH (scp)" (Get-Command scp -ErrorAction SilentlyContinue) "Sama seperti ssh"

if ($KitMode) {
    $kitRoot = Split-Path $ScriptDir -Parent
    $zip = Join-Path $kitRoot "release\creativesuite-erp-release.zip"
    Test-ItemOk "Release ZIP kit" (Test-Path $zip) "File harus ada di release\creativesuite-erp-release.zip"
} else {
    $deployDir = Split-Path $ScriptDir -Parent
    $zip = Join-Path $deployDir "creativesuite-erp-release-ubuntu22.zip"
    Test-ItemOk "Release ZIP" (Test-Path $zip) "Jalankan pack-release.ps1 dulu"
    if (-not $KitMode -and -not (Test-Path $PhpPath)) {
        Test-ItemOk "PHP (dev pack)" $false "Edit PhpPath di deploy-config.ps1"
    } else {
        Test-ItemOk "PHP (dev pack)" $true
    }
}

try {
    $ping = Test-Connection -ComputerName $ServerIp -Count 1 -Quiet -ErrorAction Stop
    Test-ItemOk "Ping $ServerIp" $ping "PC dan server harus satu jaringan"
} catch {
    Test-ItemOk "Ping $ServerIp" $false "Cek kabel/WiFi/firewall"
}

$sshArgs = @("-p", $SshPort, "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", "-o", "StrictHostKeyChecking=accept-new")
if ($SshKeyPath -and (Test-Path $SshKeyPath)) { $sshArgs += @("-i", $SshKeyPath) }
$target = "$SshUser@$ServerIp"
& ssh @sshArgs $target "echo preflight-ok" 2>$null
Test-ItemOk "SSH login $target" ($LASTEXITCODE -eq 0) "Cek user/password/SSH key di deploy-config.ps1"

Write-Host ""
Write-Host "Lolos: $ok | Gagal: $fail"
if ($fail -gt 0) { exit 1 }
exit 0