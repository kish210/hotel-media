@echo off
REM ============================================================
REM   SignageCMS - Universal Installer (one-click)
REM   ساماع رایانه کیش | kishwifi.com
REM   روی این فایل دوبار کلیک کنید (یا Run as administrator)
REM ============================================================
setlocal
cd /d "%~dp0"
title SignageCMS Installer - kishwifi.com

echo.
echo   ============================================================
echo     SignageCMS - Universal Installer
echo     Detecting your Windows edition and installing everything...
echo   ============================================================
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1" %*

echo.
pause
endlocal
