@echo off
title CreativeSuite ERP - Cek Koneksi Server
cd /d "%~dp0scripts"
powershell -NoProfile -ExecutionPolicy Bypass -File ".\preflight-check.ps1"
echo.
pause