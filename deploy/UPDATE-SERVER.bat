@echo off
title CreativeSuite ERP - Update Server
cd /d "%~dp0scripts"
echo.
echo ==========================================
echo   CreativeSuite ERP - UPDATE SERVER
echo   Setelah perbaikan source code
echo ==========================================
echo.
pause
powershell -NoProfile -ExecutionPolicy Bypass -File ".\auto-deploy.ps1" -Mode Update
echo.
pause