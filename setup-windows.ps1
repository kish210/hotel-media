#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — Windows Setup Installer
    سماع رایانه کیش | kishwifi.com
.DESCRIPTION
    نصب و راه‌اندازی کامل SignageCMS روی Windows
    با Docker Desktop، MySQL، Redis، WebSocket و phpMyAdmin
.EXAMPLE
    .\setup-windows.ps1
    .\setup-windows.ps1 -Port 8080 -Silent
#>
param(
    [int]   $Port     = 80,
    [int]   $WsPort   = 8080,
    [switch]$Silent,
    [switch]$Uninstall
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding  = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = 'SignageCMS Setup — سماع رایانه کیش'

# ── رنگ‌ها ────────────────────────────────────────────────────────────────────
function Write-Color {
    param([string]$Text, [string]$Color = 'White', [switch]$NoNewline)
    if ($NoNewline) { Write-Host $Text -ForegroundColor $Color -NoNewline }
    else            { Write-Host $Text -ForegroundColor $Color }
}
function OK    { param($m) Write-Color "  [✓] $m" 'Green'  }
function WARN  { param($m) Write-Color "  [!] $m" 'Yellow' }
function ERR   { param($m) Write-Color "  [✗] $m" 'Red'    }
function INFO  { param($m) Write-Color "  [»] $m" 'Cyan'   }
function STEP  { param($m) Write-Color "`n  ── $m " 'Blue' -NoNewline; Write-Color '─' 'DarkBlue' }

# ── Header ────────────────────────────────────────────────────────────────────
Clear-Host
Write-Color @'

  ╔══════════════════════════════════════════════════════════════╗
  ║                                                              ║
  ║          SignageCMS  —  Digital Signage Platform             ║
  ║                                                              ║
  ║          سماع رایانه کیش  |  kishwifi.com                   ║
  ║                                      v1.6.0                  ║
  ╚══════════════════════════════════════════════════════════════╝

'@ 'Cyan'

$ScriptDir = Split-Path $MyInvocation.MyCommand.Path -Parent
Set-Location $ScriptDir
INFO "پوشه نصب: $ScriptDir"

# ── Uninstall mode ────────────────────────────────────────────────────────────
if ($Uninstall) {
    Write-Color "`n  حذف SignageCMS..." 'Yellow'
    try {
        docker compose down -v --remove-orphans 2>$null
        OK "Containers و volumes حذف شدند"
    } catch { WARN "Docker در دسترس نبود یا container ای وجود نداشت" }
    $confirm = Read-Host "`n  فایل .env هم حذف شود؟ (y/N)"
    if ($confirm -eq 'y') { Remove-Item '.env' -Force -ErrorAction SilentlyContinue; OK ".env حذف شد" }
    Write-Color "`n  SignageCMS حذف شد.`n" 'Yellow'
    exit 0
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 1 — پیش‌نیازها
# ─────────────────────────────────────────────────────────────────────────────
STEP "بررسی پیش‌نیازها"
Write-Color ""

# Windows version
$winVer = [System.Environment]::OSVersion.Version
if ($winVer.Major -lt 10) {
    ERR "Windows 10 یا بالاتر نیاز است (نسخه فعلی: $($winVer.ToString()))"
    exit 1
}
OK "Windows $($winVer.Major).$($winVer.Minor)"

# Virtualization
$virt = (Get-WmiObject -Class Win32_Processor -ErrorAction SilentlyContinue).VirtualizationFirmwareEnabled
if ($virt -eq $false) {
    WARN "Virtualization در BIOS غیرفعال است — Docker ممکن است کار نکند"
    WARN "آموزش فعال‌سازی: https://support.microsoft.com/en-us/windows/virtualization"
} else {
    OK "Virtualization فعال است"
}

# WSL2 / Hyper-V
$wsl = Get-WindowsOptionalFeature -Online -FeatureName Microsoft-Windows-Subsystem-Linux -ErrorAction SilentlyContinue
if ($wsl -and $wsl.State -eq 'Enabled') { OK "WSL2 فعال است" }
else { WARN "WSL2 غیرفعال است — Docker Desktop در حالت Hyper-V اجرا می‌شود" }

# ─────────────────────────────────────────────────────────────────────────────
# STEP 2 — Docker Desktop
# ─────────────────────────────────────────────────────────────────────────────
STEP "بررسی Docker Desktop"
Write-Color ""

$dockerOk = $false
try {
    $dockerVer = docker version --format '{{.Server.Version}}' 2>$null
    if ($LASTEXITCODE -eq 0 -and $dockerVer) {
        OK "Docker Engine $dockerVer"
        $dockerOk = $true
    }
} catch {}

if (-not $dockerOk) {
    WARN "Docker Desktop نصب نشده یا اجرا نمی‌شود"
    Write-Color ""

    $install = if ($Silent) { 'y' } else { Read-Host "  دانلود و نصب Docker Desktop؟ (y/N)" }
    if ($install -ne 'y') {
        Write-Color @'

  لطفاً Docker Desktop را به‌صورت دستی نصب کنید:
  https://www.docker.com/products/docker-desktop

  سپس این اسکریپت را دوباره اجرا کنید.

'@ 'Yellow'
        exit 1
    }

    $dockerInstaller = "$env:TEMP\DockerDesktopInstaller.exe"
    INFO "دانلود Docker Desktop (~600MB) ..."
    $ProgressPreference = 'SilentlyContinue'
    try {
        Invoke-WebRequest -Uri "https://desktop.docker.com/win/main/amd64/Docker%20Desktop%20Installer.exe" `
            -OutFile $dockerInstaller -UseBasicParsing
        OK "دانلود کامل شد"
    } catch {
        ERR "دانلود ناموفق: $_"
        Write-Color "  لطفاً از https://docker.com/products/docker-desktop دستی دانلود کنید" 'Yellow'
        exit 1
    }

    INFO "نصب Docker Desktop (این ممکن است چند دقیقه طول بکشد)..."
    Start-Process -Wait -FilePath $dockerInstaller -ArgumentList 'install', '--quiet', '--accept-license'

    Write-Color @'

  ╔════════════════════════════════════════════════════════╗
  ║  Docker Desktop نصب شد!                               ║
  ║  لطفاً Windows را Restart کنید،                       ║
  ║  سپس این اسکریپت را دوباره اجرا کنید.                ║
  ╚════════════════════════════════════════════════════════╝

'@ 'Green'
    $r = Read-Host "  الان Restart شود؟ (y/N)"
    if ($r -eq 'y') { Restart-Computer -Force }
    exit 0
}

# docker compose check
try {
    docker compose version 2>$null | Out-Null
    OK "Docker Compose v2 موجود است"
} catch {
    ERR "Docker Compose v2 پیدا نشد — Docker Desktop را آپدیت کنید"
    exit 1
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 3 — بررسی پورت‌ها
# ─────────────────────────────────────────────────────────────────────────────
STEP "بررسی پورت‌ها"
Write-Color ""

function Test-PortFree {
    param([int]$P)
    $used = Get-NetTCPConnection -LocalPort $P -State Listen -ErrorAction SilentlyContinue
    return ($null -eq $used)
}

function Find-FreePort {
    param([int]$Start)
    $p = $Start
    while (-not (Test-PortFree $p)) { $p++ }
    return $p
}

# Web port
if (-not (Test-PortFree $Port)) {
    $suggested = Find-FreePort ($Port + 1)
    WARN "پورت $Port در حال استفاده است"
    if (-not $Silent) {
        $inp = Read-Host "  پورت جایگزین [$suggested]"
        if ($inp -match '^\d+$') { $Port = [int]$inp } else { $Port = $suggested }
    } else { $Port = $suggested }
}
OK "پورت وب: $Port"

# WS port
if (-not (Test-PortFree $WsPort)) {
    $suggested = Find-FreePort ($WsPort + 1)
    WARN "پورت WebSocket $WsPort در حال استفاده است → $suggested"
    $WsPort = $suggested
}
OK "پورت WebSocket: $WsPort"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 4 — تنظیمات
# ─────────────────────────────────────────────────────────────────────────────
STEP "تنظیمات نصب"
Write-Color ""

# دریافت IP محلی برای دسترسی از شبکه
$localIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notmatch '^127\.' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Sort-Object InterfaceMetric | Select-Object -First 1).IPAddress
if (-not $localIp) { $localIp = 'localhost' }

if (-not $Silent) {
    Write-Color "  (Enter = مقدار پیش‌فرض داخل پرانتز)" 'DarkGray'
    Write-Color ""

    $appUrl = Read-Host "  آدرس سایت (پیش‌فرض: http://localhost:$Port)"
    if (-not $appUrl) {
        $appUrl = if ($Port -eq 80) { "http://localhost" } else { "http://localhost:$Port" }
    }

    $dbPass = Read-Host "  رمز دیتابیس (پیش‌فرض: auto-generated)"
    if (-not $dbPass) { $dbPass = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 20 | ForEach-Object { [char]$_ }) }

    $adminEmail = Read-Host "  ایمیل مدیر (پیش‌فرض: admin@signagecms.com)"
    if (-not $adminEmail) { $adminEmail = 'admin@signagecms.com' }

    $adminPass = Read-Host "  رمز مدیر (پیش‌فرض: Admin@123456)"
    if (-not $adminPass) { $adminPass = 'Admin@123456' }
} else {
    $appUrl    = if ($Port -eq 80) { "http://localhost" } else { "http://localhost:$Port" }
    $dbPass    = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 20 | ForEach-Object { [char]$_ })
    $adminEmail = 'admin@signagecms.com'
    $adminPass  = 'Admin@123456'
}

$rootPass  = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 20 | ForEach-Object { [char]$_ })
$appKey    = "base64:$([Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)))"
$jwtSecret = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 48 | ForEach-Object { [char]$_ })

OK "آدرس: $appUrl"
OK "پورت: $Port / WS: $WsPort"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 5 — ساخت فایل .env
# ─────────────────────────────────────────────────────────────────────────────
STEP "ساخت فایل .env"
Write-Color ""

if (Test-Path '.env') {
    if (-not $Silent) {
        $overwrite = Read-Host "  فایل .env موجود است. بازنویسی شود؟ (y/N)"
        if ($overwrite -ne 'y') {
            OK "فایل .env موجود — از مقادیر قبلی استفاده می‌شود"
            goto SkipEnv
        }
    }
    Copy-Item '.env' ".env.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')" -Force
    WARN "نسخه قبلی .env به .env.backup ذخیره شد"
}

@"
# ─── SignageCMS Environment ────────────────────────────────────────────────
# Generated by setup-windows.ps1 on $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
# سماع رایانه کیش | kishwifi.com
# ──────────────────────────────────────────────────────────────────────────

# Application
APP_NAME=SignageCMS
APP_ENV=production
APP_DEBUG=false
APP_URL=$appUrl
APP_KEY=$appKey
APP_TIMEZONE=Asia/Tehran
APP_PORT=$Port

# Database
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=signage_cms
DB_USERNAME=signage_user
DB_PASSWORD=$dbPass
DB_ROOT_PASSWORD=$rootPass
DB_CHARSET=utf8mb4

# JWT
JWT_SECRET=$jwtSecret
JWT_EXPIRY=86400
JWT_REFRESH_EXPIRY=604800

# WebSocket
WS_HOST=0.0.0.0
WS_PORT=$WsPort
WS_ALLOWED_ORIGINS=*

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# Session
SESSION_LIFETIME=7200
SESSION_DRIVER=file

# Admin account (used by installer)
ADMIN_EMAIL=$adminEmail
ADMIN_PASSWORD=$adminPass

# Storage
UPLOAD_MAX_SIZE=512M
UPLOAD_PATH=public/uploads
"@ | Set-Content '.env' -Encoding UTF8

OK "فایل .env ساخته شد"

:SkipEnv

# ─────────────────────────────────────────────────────────────────────────────
# STEP 6 — ساخت پوشه‌های مورد نیاز
# ─────────────────────────────────────────────────────────────────────────────
STEP "آماده‌سازی پوشه‌ها"
Write-Color ""

$dirs = @(
    'storage/logs',
    'storage/cache',
    'storage/sessions',
    'storage/temp',
    'public/uploads/media',
    'public/uploads/thumbnails',
    'public/apk'
)
foreach ($d in $dirs) {
    if (-not (Test-Path $d)) {
        New-Item -ItemType Directory -Path $d -Force | Out-Null
        OK "ساخته شد: $d"
    }
}
OK "همه پوشه‌ها آماده‌اند"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 7 — Docker build & up
# ─────────────────────────────────────────────────────────────────────────────
STEP "اجرای Docker Compose"
Write-Color ""

INFO "دانلود image‌ها و ساخت containers (اولین بار ممکن است چند دقیقه طول بکشد)..."
Write-Color ""

try {
    docker compose pull --quiet 2>&1 | ForEach-Object { Write-Color "    $_" 'DarkGray' }
    docker compose build --quiet 2>&1 | ForEach-Object { Write-Color "    $_" 'DarkGray' }
    docker compose up -d --remove-orphans 2>&1 | ForEach-Object { Write-Color "    $_" 'DarkGray' }
    OK "Containers راه‌اندازی شدند"
} catch {
    ERR "خطا در راه‌اندازی Docker: $_"
    Write-Color "  لاگ کامل: docker compose logs" 'Yellow'
    exit 1
}

# نمایش وضعیت containers
Write-Color ""
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>$null

# ─────────────────────────────────────────────────────────────────────────────
# STEP 8 — انتظار برای MySQL
# ─────────────────────────────────────────────────────────────────────────────
STEP "انتظار برای راه‌اندازی سرویس‌ها"
Write-Color ""

INFO "انتظار برای MySQL ..."
$tries = 0; $ready = $false
while ($tries -lt 40 -and -not $ready) {
    $tries++
    Start-Sleep -Seconds 3
    try {
        $ping = docker exec signage_mysql mysqladmin ping -h localhost -u root -p"$rootPass" --silent 2>$null
        if ($LASTEXITCODE -eq 0) { $ready = $true }
    } catch {}
    Write-Host "    تلاش $tries/40 ..." -ForegroundColor DarkGray
}

if ($ready) { OK "MySQL آماده است" }
else {
    WARN "MySQL در زمان مورد انتظار آماده نشد"
    WARN "نصب به صورت دستی: http://localhost:$Port/install.php"
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 9 — نصب دیتابیس
# ─────────────────────────────────────────────────────────────────────────────
STEP "نصب دیتابیس"
Write-Color ""

if ($ready) {
    INFO "اجرای installer..."
    try {
        $result = docker exec signage_php php /var/www/html/public/install.php 2>&1
        if ($LASTEXITCODE -eq 0) { OK "دیتابیس و جداول با موفقیت ایجاد شدند" }
        else {
            WARN "installer پیام خطا داشت:"
            Write-Color "    $result" 'Yellow'
            WARN "آدرس http://localhost:$Port/install.php را در مرورگر باز کنید"
        }
    } catch {
        WARN "خطا در اجرای installer: $_"
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 10 — Firewall
# ─────────────────────────────────────────────────────────────────────────────
STEP "تنظیمات Firewall"
Write-Color ""

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if ($isAdmin) {
    $ruleName = "SignageCMS Web ($Port)"
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if (-not $existing) {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP `
            -LocalPort $Port -Action Allow -Profile Any | Out-Null
        OK "قانون Firewall برای پورت $Port اضافه شد"
    } else { OK "قانون Firewall از قبل وجود دارد" }

    $wsRule = "SignageCMS WebSocket ($WsPort)"
    if (-not (Get-NetFirewallRule -DisplayName $wsRule -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $wsRule -Direction Inbound -Protocol TCP `
            -LocalPort $WsPort -Action Allow -Profile Any | Out-Null
        OK "قانون Firewall برای WebSocket پورت $WsPort اضافه شد"
    }
} else {
    WARN "برای تنظیم Firewall نیاز به دسترسی Administrator است"
    WARN "پورت‌های $Port و $WsPort را در Windows Defender Firewall مجاز کنید"
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 11 — میانبر Desktop
# ─────────────────────────────────────────────────────────────────────────────
STEP "ساخت میانبر"
Write-Color ""

try {
    $wshShell  = New-Object -ComObject WScript.Shell
    $shortcut  = $wshShell.CreateShortcut("$env:USERPROFILE\Desktop\SignageCMS.lnk")
    $shortcut.TargetPath       = "http://localhost:$Port/admin"
    $shortcut.Description      = "SignageCMS Dashboard — سماع رایانه کیش"
    $shortcut.WorkingDirectory = $ScriptDir
    $shortcut.Save()
    OK "میانبر روی Desktop ساخته شد"
} catch { WARN "ساخت میانبر ناموفق — می‌توانید دستی اضافه کنید" }

# ─────────────────────────────────────────────────────────────────────────────
# نمایش اطلاعات نهایی
# ─────────────────────────────────────────────────────────────────────────────
$dashUrl = if ($Port -eq 80) { "http://localhost/admin" } else { "http://localhost:$Port/admin" }
$pmaUrl  = "http://localhost:8081"
$netUrl  = if ($Port -eq 80) { "http://$localIp/admin" } else { "http://${localIp}:$Port/admin" }

Write-Color @"

  ╔══════════════════════════════════════════════════════════════╗
  ║                    نصب کامل شد! ✓                           ║
  ╠══════════════════════════════════════════════════════════════╣
  ║                                                              ║
  ║  🌐 داشبورد (این کامپیوتر):  $($dashUrl.PadRight(28))║
  ║  🌐 داشبورد (از شبکه):       $($netUrl.PadRight(28))║
  ║  🛢  phpMyAdmin:              $($pmaUrl.PadRight(28))║
  ║  🔌 WebSocket:               ws://localhost:$($WsPort.ToString().PadRight(21))║
  ║                                                              ║
  ╠══════════════════════════════════════════════════════════════╣
  ║                                                              ║
  ║  👤 ایمیل:    $($adminEmail.PadRight(46))║
  ║  🔑 رمز:      $($adminPass.PadRight(46))║
  ║                                                              ║
  ╠══════════════════════════════════════════════════════════════╣
  ║  ⚠  بعد از اولین ورود، رمز را تغییر دهید                   ║
  ║                                                              ║
  ║  📱 اپ Android:  دانلود از پنل → Android App                ║
  ║  🖥  پلیر Windows: دانلود از پنل → Windows Player           ║
  ║                                                              ║
  ╠══════════════════════════════════════════════════════════════╣
  ║  سماع رایانه کیش  |  kishwifi.com                          ║
  ╚══════════════════════════════════════════════════════════════╝

"@ 'Green'

# ── دستورات مفید ──────────────────────────────────────────────────────────────
Write-Color "  دستورات مفید:" 'Cyan'
Write-Color "  ┌─────────────────────────────────────────────────────────┐" 'DarkGray'
Write-Color "  │ توقف سرویس‌ها:    docker compose stop                  │" 'DarkGray'
Write-Color "  │ شروع مجدد:        docker compose start                 │" 'DarkGray'
Write-Color "  │ مشاهده لاگ‌ها:    docker compose logs -f               │" 'DarkGray'
Write-Color "  │ آپدیت سیستم:      docker compose pull && docker compose up -d │" 'DarkGray'
Write-Color "  │ حذف کامل:         .\setup-windows.ps1 -Uninstall       │" 'DarkGray'
Write-Color "  └─────────────────────────────────────────────────────────┘" 'DarkGray'
Write-Color ""

# ── باز کردن مرورگر ──────────────────────────────────────────────────────────
if (-not $Silent) {
    $open = Read-Host "  داشبورد را در مرورگر باز کنم؟ (Y/n)"
    if ($open -ne 'n') {
        Start-Process $dashUrl
    }
}

Write-Color ""
