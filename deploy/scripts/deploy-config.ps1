# CreativeSuite ERP — Konfigurasi deploy otomatis
# Edit file ini sekali sebelum deploy pertama.

$ServerIp       = "192.168.1.102"
$SshUser        = "ubuntu"          # ganti: root / nama user SSH server
$SshPort        = 22
$SshKeyPath     = ""                # kosong = pakai kunci default Windows (~/.ssh/id_rsa)
                                    # contoh: "C:\Users\TNN IT\.ssh\id_ed25519"

$ProjectsRoot   = "C:\Users\TNN IT\projects"
$PhpPath        = "C:\xampp\php\php.exe"
$NodePath       = ""                # kosong = pakai node dari PATH

$RemoteTmpDir   = "/tmp"
$RemoteRelease  = "/tmp/creativesuite-release"
$ZipRemoteName  = "creativesuite-erp-release.zip"

$RunTestsBeforePack = $false        # $true = jalankan php artisan test sebelum pack
$SkipFrontendBuild  = $false        # $true = skip npm build (pakai build lama)
$KitMode            = $false        # $true = paket deploy-kit (tanpa source code)