@echo off
chcp 65001 >nul
title SignageCMS — Windows Server 2022 Setup

:: نیاز به دسترسی Administrator
net session >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo  [خطا] لطفاً این فایل را با Run as Administrator اجرا کنید.
    echo.
    pause
    exit /b 1
)

:: اجرای اسکریپت نصب Server 2022 با bypass policy
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-server2022.ps1"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo  [خطا] راه‌اندازی ناتمام ماند.
    echo  اگر پیام Restart دیدید، سرور را Restart کنید و دوباره همین فایل را اجرا کنید.
    echo.
    pause
)
