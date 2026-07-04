@echo off
title CreativeSuite ERP - Deploy Pertama
cd /d "%~dp0scripts"
echo.
echo ==========================================
echo   CreativeSuite ERP - TERIMA BERSIH
echo   Server: 192.168.1.102
echo ==========================================
echo.
echo Langkah otomatis: cek koneksi -^> pack -^> upload -^> install
echo Edit deploy-config.ps1 jika user SSH bukan "ubuntu"
echo.
pause
powershell -NoProfile -ExecutionPolicy Bypass -File ".\auto-deploy.ps1" -Mode Fresh
echo.
pause