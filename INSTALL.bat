@echo off
REM ============================================================
REM   Hotel Media - Universal Installer (one-click)
REM   ساماع رایانه کیش | kishwifi.com
REM   روی این فایل دوبار کلیک کنید (یا Run as administrator)
REM ============================================================
setlocal
cd /d "%~dp0"
title Hotel Media Installer - kishwifi.com

echo.
echo   ============================================================
echo     Hotel Media - Universal Installer
echo     Detecting your Windows edition and installing everything...
echo   ============================================================
echo.

REM اسکریپت‌های پشتیبان به installer\ منتقل شدند تا ریشه‌ی پروژه فقط
REM همین دو فایلِ دابل‌کلیکی را داشته باشد.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0installer\install.ps1" %*

echo.
pause
endlocal
