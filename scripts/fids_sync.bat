@echo off
chcp 65001 >nul
setlocal

REM ─────────────────────────────────────────────────────────────
REM  fids_sync.bat — اجرای sync پروازها
REM  آدرس پروژه را در PROJ_DIR تنظیم کنید
REM ─────────────────────────────────────────────────────────────

set PROJ_DIR=D:\duc\signage-cms

REM ── فرودگاه‌های پیش‌فرض (با کاما جدا کنید)
REM   2=مهرآباد  102=مشهد  1=شیراز  103=تبریز  114=اصفهان  401=اهواز
set AIRPORTS=2,102,1,103,114,401

echo.
echo ====================================================
echo   FIDS Sync — %DATE% %TIME%
echo ====================================================

powershell -ExecutionPolicy Bypass -File "%PROJ_DIR%\scripts\fids_sync.ps1" -Airports %AIRPORTS%

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Script failed with code %ERRORLEVEL%
    exit /b %ERRORLEVEL%
)

echo.
echo Done.
endlocal
