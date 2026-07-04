# GitHub Secrets — Auto-Deploy CreativeSuite ERP

Tambahkan di: https://github.com/ucupcreativenetwork-glitch/creativesuite-erp/settings/secrets/actions

| Secret | Value |
|--------|-------|
| `DEPLOY_HOST` | `192.168.1.102` |
| `DEPLOY_USER` | `creative` |
| `DEPLOY_SSH_KEY` | Private key SSH server ERP |

## Deploy Key (opsional, untuk git pull di server)

Public key — tambahkan di GitHub → Settings → Deploy keys:

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAII1iLFgAcgbKbiT9uQXYPlT8+2je8sTotEZ0QfH9vMe2 creative@creative
```

## Setelah secrets diatur

Setiap push ke branch `main` akan:
1. Zip source code
2. Upload ke server `192.168.1.102`
3. Jalankan `deploy/scripts/update-linux.sh`
4. Health check di `/up`