@echo off
chcp 65001 >nul
setlocal EnableDelayedExpansion
title SignageCMS Setup

echo.
echo  ╔══════════════════════════════════════════════════╗
echo  ║         SignageCMS — راه‌اندازی سریع             ║
echo  ║              Windows Setup v1.0                  ║
echo  ╚══════════════════════════════════════════════════╝
echo.

REM ── بررسی Docker ─────────────────────────────────────────────
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [ERROR] Docker Desktop نصب یا راه‌اندازی نشده است.
    echo.
    echo  لطفاً Docker Desktop را نصب کنید:
    echo  https://www.docker.com/products/docker-desktop
    echo.
    pause
    exit /b 1
)
echo  [OK] Docker در دسترس است

docker compose version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [ERROR] Docker Compose پیدا نشد.
    pause
    exit /b 1
)
echo  [OK] Docker Compose در دسترس است
echo.

REM ── ساخت .env ────────────────────────────────────────────────
if not exist ".env" (
    if exist ".env.example" (
        copy ".env.example" ".env" >nul
        echo  [OK] فایل .env از .env.example ساخته شد
    ) else (
        echo  [ERROR] فایل .env.example پیدا نشد
        pause
        exit /b 1
    )
) else (
    echo  [OK] فایل .env موجود است
)

REM ── تنظیمات ──────────────────────────────────────────────────
echo.
echo  ─────────────────────────────────────────────────
echo  تنظیمات (Enter = مقدار پیش‌فرض داخل پرانتز)
echo  ─────────────────────────────────────────────────
echo.

set /p APP_PORT="  پورت وب (پیش‌فرض: 80): "
if "!APP_PORT!"=="" set APP_PORT=80

set /p WS_PORT="  پورت WebSocket (پیش‌فرض: 8080): "
if "!WS_PORT!"=="" set WS_PORT=8080

set /p APP_URL="  آدرس سایت (پیش‌فرض: http://localhost): "
if "!APP_URL!"=="" set APP_URL=http://localhost

set /p DB_PASS="  رمز دیتابیس (پیش‌فرض: StrongPassword123!): "
if "!DB_PASS!"=="" set DB_PASS=StrongPassword123!

set /p ROOT_PASS="  رمز root MySQL (پیش‌فرض: RootPass123!): "
if "!ROOT_PASS!"=="" set ROOT_PASS=RootPass123!

REM ── تولید کلیدهای تصادفی ─────────────────────────────────────
echo.
echo  [INFO] تولید کلیدهای امنیتی ...
for /f "tokens=*" %%i in ('powershell -Command "[System.Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32))"') do set APP_KEY=base64:%%i
for /f "tokens=*" %%i in ('powershell -Command "-join ((65..90)+(97..122)+(48..57) | Get-Random -Count 48 | ForEach-Object {[char]$_})"') do set JWT_SECRET=%%i

REM ── بروزرسانی .env ───────────────────────────────────────────
powershell -Command ^
  "$c = Get-Content '.env' -Raw;" ^
  "$c = $c -replace '(?m)^APP_URL=.*$', 'APP_URL=%APP_URL%';" ^
  "$c = $c -replace '(?m)^APP_PORT=.*$', 'APP_PORT=%APP_PORT%';" ^
  "$c = $c -replace '(?m)^WS_PORT=.*$', 'WS_PORT=%WS_PORT%';" ^
  "$c = $c -replace '(?m)^APP_KEY=.*$', 'APP_KEY=%APP_KEY%';" ^
  "$c = $c -replace '(?m)^JWT_SECRET=.*$', 'JWT_SECRET=%JWT_SECRET%';" ^
  "$c = $c -replace '(?m)^DB_PASSWORD=.*$', 'DB_PASSWORD=%DB_PASS%';" ^
  "$c = $c -replace '(?m)^MYSQL_ROOT_PASSWORD=.*$', 'MYSQL_ROOT_PASSWORD=%ROOT_PASS%';" ^
  "if (-not ($c -match '(?m)^APP_PORT=')) { $c += \"`nAPP_PORT=%APP_PORT%\" };" ^
  "[System.IO.File]::WriteAllText('.env', $c, [System.Text.Encoding]::UTF8)"

echo  [OK] فایل .env بروزرسانی شد

REM ── ساخت پوشه‌های لازم ───────────────────────────────────────
echo.
echo  [INFO] ساخت پوشه‌های ضروری ...
if not exist "storage\logs"      mkdir "storage\logs"
if not exist "storage\cache"     mkdir "storage\cache"
if not exist "storage\sessions"  mkdir "storage\sessions"
if not exist "storage\temp"      mkdir "storage\temp"
if not exist "storage\cache\fids" mkdir "storage\cache\fids"
if not exist "public\uploads\media"       mkdir "public\uploads\media"
if not exist "public\uploads\thumbnails"  mkdir "public\uploads\thumbnails"
if not exist "public\uploads\apk"         mkdir "public\uploads\apk"
if not exist "public\uploads\vod"         mkdir "public\uploads\vod"
if not exist "public\uploads\vod\thumbs"  mkdir "public\uploads\vod\thumbs"
echo  [OK] پوشه‌ها آماده‌اند

REM ── Docker build و start ─────────────────────────────────────
echo.
echo  ─────────────────────────────────────────────────
echo  [INFO] ساخت و راه‌اندازی Docker containers ...
echo  (این مرحله ممکنه چند دقیقه طول بکشه)
echo  ─────────────────────────────────────────────────
echo.

docker compose down --remove-orphans >nul 2>&1
docker compose build --no-cache
if %ERRORLEVEL% NEQ 0 (
    echo  [ERROR] Docker build ناموفق بود
    pause
    exit /b 1
)

docker compose up -d
if %ERRORLEVEL% NEQ 0 (
    echo  [ERROR] Docker Compose up ناموفق بود
    pause
    exit /b 1
)
echo  [OK] Containers در حال اجرا هستند

REM ── انتظار برای MySQL ─────────────────────────────────────────
echo.
echo  [INFO] انتظار برای آماده شدن MySQL ...
set TRIES=0
:WAIT_MYSQL
set /a TRIES+=1
if %TRIES% GTR 30 (
    echo  [WARN] MySQL آماده نشد — نصب رو به‌صورت دستی اجرا کنید
    goto MANUAL_INSTALL
)
docker exec signage_mysql mysqladmin ping -h localhost -u root -p%ROOT_PASS% --silent >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo  [INFO] تلاش %TRIES%/30 ...
    timeout /t 5 /nobreak >nul
    goto WAIT_MYSQL
)
echo  [OK] MySQL آماده است

REM ── اجرای installer ──────────────────────────────────────────
echo.
echo  ─────────────────────────────────────────────────
echo  [INFO] نصب دیتابیس و ساخت جداول ...
echo  ─────────────────────────────────────────────────
echo.

docker exec signage_php php /var/www/html/public/install.php
if %ERRORLEVEL% NEQ 0 (
    echo  [WARN] installer با مشکل مواجه شد
    echo  آدرس http://localhost:%APP_PORT%/install.php را در مرورگر باز کنید
)

:MANUAL_INSTALL

REM ── نمایش اطلاعات نهایی ──────────────────────────────────────
echo.
echo  ╔══════════════════════════════════════════════════╗
echo  ║            نصب کامل شد!                         ║
echo  ╠══════════════════════════════════════════════════╣
echo  ║  داشبورد:   http://localhost:%APP_PORT%           ║
echo  ║  phpMyAdmin: http://localhost:8081                ║
echo  ║  WebSocket:  ws://localhost:%WS_PORT%              ║
echo  ╠══════════════════════════════════════════════════╣
echo  ║  ایمیل:   admin@signagecms.com                   ║
echo  ║  رمز:     Admin@123456                           ║
echo  ╠══════════════════════════════════════════════════╣
echo  ║  بعد از ورود رمز را تغییر دهید!                 ║
echo  ╚══════════════════════════════════════════════════╝
echo.

set /p OPEN="  آیا داشبورد را در مرورگر باز کنم؟ (y/n): "
if /i "!OPEN!"=="y" start http://localhost:%APP_PORT%

echo.
pause
endlocal
