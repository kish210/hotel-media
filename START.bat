@echo off
chcp 65001 >nul
title SignageCMS Setup — سماع رایانه کیش

:: اجرای PowerShell setup با bypass policy
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-windows.ps1"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo  [خطا] راه‌اندازی ناموفق بود.
    echo  لطفاً PowerShell را به عنوان Administrator اجرا کنید
    echo  و دستور زیر را وارد کنید:
    echo.
    echo    Set-ExecutionPolicy Bypass -Scope Process
    echo    .\setup-windows.ps1
    echo.
    pause
)
