#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — Windows Server 2022 Setup (WSL2 + Docker Engine)
    سماع رایانه کیش | kishwifi.com
.DESCRIPTION
    نصب خودکار SignageCMS روی Windows Server 2022 بدون Docker Desktop.
    این اسکریپت WSL2 + Ubuntu را نصب می‌کند، Docker Engine را داخل آن
    راه‌اندازی می‌کند و استک کامل (PHP, MySQL, Redis, WebSocket, phpMyAdmin)
    را با Docker Compose بالا می‌آورد. کانتینرها لینوکسی هستند و native اجرا می‌شوند.

    اسکریپت resumable است: اگر WSL2 نصب نباشد، آن را نصب کرده و درخواست
    Restart می‌دهد؛ بعد از Restart دوباره اجرا کنید تا ادامه نصب انجام شود.
.EXAMPLE
    # PowerShell را به‌عنوان Administrator باز کنید:
    Set-ExecutionPolicy Bypass -Scope Process -Force
    .\setup-server2022.ps1
.EXAMPLE
    .\setup-server2022.ps1 -Port 80 -WsPort 8080 -Silent
.EXAMPLE
    .\setup-server2022.ps1 -Uninstall
#>
param(
    [int]   $Port    = 80,
    [int]   $WsPort  = 8080,
    [string]$Distro  = 'Ubuntu',
    [switch]$Silent,
    [switch]$Uninstall
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding  = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = 'SignageCMS Server 2022 Setup — سماع رایانه کیش'

# ── رنگ‌ها ────────────────────────────────────────────────────────────────────
function Write-Color {
    param([string]$Text, [string]$Color = 'White', [switch]$NoNewline)
    if ($NoNewline) { Write-Host $Text -ForegroundColor $Color -NoNewline }
    else            { Write-Host $Text -ForegroundColor $Color }
}
function OK    { param($m) Write-Color "  [OK] $m"   'Green'  }
function WARN  { param($m) Write-Color "  [! ] $m"   'Yellow' }
function ERR   { param($m) Write-Color "  [X ] $m"   'Red'    }
function INFO  { param($m) Write-Color "  [>>] $m"   'Cyan'   }
function STEP  { param($m) Write-Color "`n  -- $m " 'Blue' -NoNewline; Write-Color ('-' * 30) 'DarkBlue' }

# ── Header ────────────────────────────────────────────────────────────────────
Clear-Host
Write-Color @'

  +==============================================================+
  |                                                              |
  |     SignageCMS  --  Windows Server 2022 (WSL2 + Docker)      |
  |                                                              |
  |     سماع رایانه کیش  |  kishwifi.com           v1.6.0       |
  +==============================================================+

'@ 'Cyan'

$ScriptDir = Split-Path $MyInvocation.MyCommand.Path -Parent
Set-Location $ScriptDir
INFO "پوشه نصب: $ScriptDir"

# مسیر معادل WSL برای پوشه پروژه:  D:\duc\signage-cms  ->  /mnt/d/duc/signage-cms
$drive   = $ScriptDir.Substring(0,1).ToLower()
$rest    = $ScriptDir.Substring(2) -replace '\\','/'
$WslPath = "/mnt/$drive$rest"
INFO "مسیر WSL: $WslPath"

# ── بررسی دسترسی Administrator ──────────────────────────────────────────────────
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    ERR "این اسکریپت نیاز به دسترسی Administrator دارد."
    Write-Color "  PowerShell را با Run as Administrator باز کنید و دوباره اجرا کنید." 'Yellow'
    exit 1
}

# ── helper: اجرای دستور داخل WSL به‌عنوان root ───────────────────────────────────
function Invoke-Wsl {
    param([string]$Command)
    & wsl.exe -d $Distro -u root -- bash -lc $Command
    return $LASTEXITCODE
}

# ─────────────────────────────────────────────────────────────────────────────
# Uninstall mode
# ─────────────────────────────────────────────────────────────────────────────
if ($Uninstall) {
    Write-Color "`n  حذف SignageCMS..." 'Yellow'
    try {
        Invoke-Wsl "cd '$WslPath' && docker compose down -v --remove-orphans" | Out-Null
        OK "Containers و volumes حذف شدند"
    } catch { WARN "Docker یا distro در دسترس نبود" }

    schtasks /Query /TN "SignageCMS Autostart" 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) {
        schtasks /Delete /TN "SignageCMS Autostart" /F | Out-Null
        OK "Scheduled Task حذف شد"
    }
    Write-Color "`n  توجه: WSL distro و Docker حذف نشدند (ممکن است برنامه‌های دیگری از آن‌ها استفاده کنند)." 'Yellow'
    Write-Color "  برای حذف کامل distro:  wsl --unregister $Distro`n" 'DarkGray'
    exit 0
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 1 — بررسی سیستم‌عامل
# ─────────────────────────────────────────────────────────────────────────────
STEP "بررسی سیستم‌عامل"
Write-Color ""

$os = Get-CimInstance Win32_OperatingSystem
INFO "$($os.Caption) (Build $($os.BuildNumber))"
if ($os.Caption -notmatch 'Server') {
    WARN "این اسکریپت برای Windows Server طراحی شده — برای ویندوز ۱۰/۱۱ از setup-windows.ps1 استفاده کنید."
    if (-not $Silent) {
        $c = Read-Host "  ادامه می‌دهید؟ (y/N)"
        if ($c -ne 'y') { exit 0 }
    }
} else {
    OK "Windows Server شناسایی شد"
}

# Virtualization (لازم برای WSL2)
$virt = (Get-CimInstance Win32_Processor).VirtualizationFirmwareEnabled
if ($virt -contains $false) {
    WARN "Virtualization در BIOS غیرفعال است — WSL2 بدون آن کار نمی‌کند."
    WARN "لطفاً در BIOS/Hyper-V، Virtualization (VT-x/AMD-V) را فعال کنید."
} else {
    OK "Virtualization فعال است"
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 2 — نصب / فعال‌سازی WSL2
# ─────────────────────────────────────────────────────────────────────────────
STEP "بررسی WSL2"
Write-Color ""

function Test-Feature {
    param([string]$Name)
    $f = Get-WindowsOptionalFeature -Online -FeatureName $Name -ErrorAction SilentlyContinue
    return ($f -and $f.State -eq 'Enabled')
}

$wslFeature = Test-Feature 'Microsoft-Windows-Subsystem-Linux'
$vmFeature  = Test-Feature 'VirtualMachinePlatform'

if (-not $wslFeature -or -not $vmFeature) {
    WARN "ویژگی‌های لازم WSL2 فعال نیستند — در حال فعال‌سازی..."

    INFO "فعال‌سازی Microsoft-Windows-Subsystem-Linux ..."
    Enable-WindowsOptionalFeature -Online -FeatureName Microsoft-Windows-Subsystem-Linux -All -NoRestart | Out-Null

    INFO "فعال‌سازی VirtualMachinePlatform ..."
    Enable-WindowsOptionalFeature -Online -FeatureName VirtualMachinePlatform -All -NoRestart | Out-Null

    OK "ویژگی‌ها فعال شدند"

    Write-Color @'

  +========================================================+
  |  ویژگی‌های WSL2 فعال شدند.                            |
  |  لطفاً سرور را Restart کنید،                          |
  |  سپس این اسکریپت را دوباره اجرا کنید تا نصب ادامه یابد.|
  +========================================================+

'@ 'Yellow'
    if (-not $Silent) {
        $r = Read-Host "  الان Restart شود؟ (y/N)"
        if ($r -eq 'y') { Restart-Computer -Force }
    }
    exit 0
}
OK "ویژگی‌های WSL2 فعال هستند"

# نصب kernel و تنظیم نسخه پیش‌فرض ۲
INFO "به‌روزرسانی WSL kernel و تنظیم نسخه پیش‌فرض ۲ ..."
& wsl.exe --update 2>$null | Out-Null
& wsl.exe --set-default-version 2 2>$null | Out-Null
OK "WSL2 آماده است"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 3 — نصب distro لینوکس (Ubuntu)
# ─────────────────────────────────────────────────────────────────────────────
STEP "نصب توزیع لینوکس ($Distro)"
Write-Color ""

$installed = (& wsl.exe -l -q 2>$null) -replace "`0","" | ForEach-Object { $_.Trim() } | Where-Object { $_ }
if ($installed -contains $Distro) {
    OK "$Distro از قبل نصب است"
} else {
    INFO "نصب $Distro (ممکن است چند دقیقه طول بکشد)..."
    & wsl.exe --install -d $Distro --no-launch 2>$null
    if ($LASTEXITCODE -ne 0) {
        # fallback برای نسخه‌های قدیمی‌تر WSL
        & wsl.exe --install -d $Distro 2>$null
    }
    OK "$Distro نصب شد"
}

# اطمینان از بالا آمدن distro (به‌عنوان root، بدون نیاز به ساخت یوزر تعاملی)
INFO "آماده‌سازی distro ..."
$tries = 0; $ready = $false
while ($tries -lt 15 -and -not $ready) {
    $tries++
    & wsl.exe -d $Distro -u root -- echo ok 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Start-Sleep -Seconds 3
}
if (-not $ready) {
    ERR "distro بالا نیامد. یک‌بار دستی اجرا کنید:  wsl -d $Distro -u root -- echo ok"
    exit 1
}
OK "distro آماده است"

# فعال‌سازی systemd (برای auto-start سرویس docker)
INFO "فعال‌سازی systemd داخل $Distro ..."
Invoke-Wsl "grep -q '\[boot\]' /etc/wsl.conf 2>/dev/null || printf '[boot]\nsystemd=true\n' > /etc/wsl.conf" | Out-Null
OK "systemd فعال شد"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 4 — نصب Docker Engine داخل WSL
# ─────────────────────────────────────────────────────────────────────────────
STEP "نصب Docker Engine"
Write-Color ""

& wsl.exe -d $Distro -u root -- bash -lc "command -v docker" 2>$null | Out-Null
if ($LASTEXITCODE -eq 0) {
    OK "Docker از قبل نصب است"
} else {
    INFO "نصب Docker Engine (دانلود و نصب از get.docker.com)..."
    $rc = Invoke-Wsl "export DEBIAN_FRONTEND=noninteractive; apt-get update -qq && apt-get install -y -qq curl ca-certificates && curl -fsSL https://get.docker.com | sh"
    if ($rc -ne 0) {
        ERR "نصب Docker ناموفق بود. خروجی بالا را بررسی کنید."
        exit 1
    }
    OK "Docker Engine نصب شد"
}

# راه‌اندازی سرویس docker (systemd یا fallback به service)
INFO "راه‌اندازی سرویس Docker ..."
Invoke-Wsl "systemctl enable docker 2>/dev/null; systemctl start docker 2>/dev/null || service docker start" | Out-Null
Start-Sleep -Seconds 3
Invoke-Wsl "docker version >/dev/null 2>&1" | Out-Null
if ($LASTEXITCODE -ne 0) {
    # احتمالاً systemd هنوز بالا نیامده — یک shutdown/restart distro
    INFO "ری‌استارت distro برای فعال‌سازی systemd ..."
    & wsl.exe --shutdown 2>$null
    Start-Sleep -Seconds 5
    Invoke-Wsl "systemctl start docker 2>/dev/null || service docker start; sleep 3; docker version" | Out-Null
}
OK "Docker در حال اجراست"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 5 — ساخت فایل .env
# ─────────────────────────────────────────────────────────────────────────────
STEP "ساخت فایل .env"
Write-Color ""

# IP محلی برای دسترسی از شبکه
$localIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notmatch '^127\.' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Sort-Object InterfaceMetric | Select-Object -First 1).IPAddress
if (-not $localIp) { $localIp = 'localhost' }

$skipEnv = $false
if (Test-Path '.env') {
    $overwrite = if ($Silent) { 'n' } else { Read-Host "  فایل .env موجود است. بازنویسی شود؟ (y/N)" }
    if ($overwrite -ne 'y') {
        OK "از فایل .env موجود استفاده می‌شود"
        $skipEnv = $true
    } else {
        Copy-Item '.env' ".env.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')" -Force
        WARN "نسخه قبلی .env بکاپ گرفته شد"
    }
}

if (-not $skipEnv) {
    if (-not $Silent) {
        Write-Color "  (Enter = مقدار پیش‌فرض داخل پرانتز)" 'DarkGray'
        Write-Color ""
        $appUrl = Read-Host "  آدرس سایت (پیش‌فرض: http://$localIp$(if($Port -ne 80){":$Port"}))"
        if (-not $appUrl) { $appUrl = "http://$localIp$(if($Port -ne 80){":$Port"})" }
        $adminEmail = Read-Host "  ایمیل مدیر (پیش‌فرض: admin@signagecms.com)"
        if (-not $adminEmail) { $adminEmail = 'admin@signagecms.com' }
        $adminPass = Read-Host "  رمز مدیر (پیش‌فرض: Admin@123456)"
        if (-not $adminPass) { $adminPass = 'Admin@123456' }
    } else {
        $appUrl     = "http://$localIp$(if($Port -ne 80){":$Port"})"
        $adminEmail = 'admin@signagecms.com'
        $adminPass  = 'Admin@123456'
    }

    $dbPass    = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 20 | ForEach-Object { [char]$_ })
    $rootPass  = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 20 | ForEach-Object { [char]$_ })
    $appKey    = "base64:$([Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)))"
    $jwtSecret = -join ((65..90)+(97..122)+(48..57) | Get-Random -Count 48 | ForEach-Object { [char]$_ })

@"
# ─── SignageCMS Environment (Windows Server 2022 / WSL2) ───────────────────
# Generated by setup-server2022.ps1 on $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
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
MYSQL_ROOT_PASSWORD=$rootPass
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
} else {
    # مقادیر را برای نمایش پایانی از .env موجود بخوان
    $envRaw     = Get-Content '.env' -Raw
    $adminEmail = if ($envRaw -match '(?m)^ADMIN_EMAIL=(.*)$')    { $Matches[1].Trim() } else { 'admin@signagecms.com' }
    $adminPass  = if ($envRaw -match '(?m)^ADMIN_PASSWORD=(.*)$') { $Matches[1].Trim() } else { 'Admin@123456' }
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 6 — ساخت پوشه‌های لازم
# ─────────────────────────────────────────────────────────────────────────────
STEP "آماده‌سازی پوشه‌ها"
Write-Color ""

$dirs = @(
    'storage/logs','storage/cache','storage/cache/fids','storage/sessions','storage/temp',
    'public/uploads/media','public/uploads/thumbnails','public/uploads/apk',
    'public/uploads/vod','public/uploads/vod/thumbs'
)
foreach ($d in $dirs) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Path $d -Force | Out-Null }
}
OK "همه پوشه‌ها آماده‌اند"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 7 — اجرای Docker Compose
# ─────────────────────────────────────────────────────────────────────────────
STEP "اجرای Docker Compose"
Write-Color ""

INFO "دانلود image‌ها و ساخت containers (اولین بار ممکن است چند دقیقه طول بکشد)..."
$rc = Invoke-Wsl "cd '$WslPath' && docker compose pull --quiet; docker compose build && docker compose up -d --remove-orphans"
if ($rc -ne 0) {
    ERR "خطا در راه‌اندازی Docker Compose."
    Write-Color "  لاگ:  wsl -d $Distro -u root -- bash -lc `"cd '$WslPath' && docker compose logs`"" 'Yellow'
    exit 1
}
OK "Containers راه‌اندازی شدند"
Write-Color ""
Invoke-Wsl "cd '$WslPath' && docker compose ps" | Out-Null

# ─────────────────────────────────────────────────────────────────────────────
# STEP 8 — انتظار برای MySQL + نصب دیتابیس
# ─────────────────────────────────────────────────────────────────────────────
STEP "نصب دیتابیس"
Write-Color ""

INFO "انتظار برای آماده شدن MySQL ..."
$tries = 0; $ready = $false
while ($tries -lt 40 -and -not $ready) {
    $tries++
    Start-Sleep -Seconds 3
    Invoke-Wsl "cd '$WslPath' && docker exec signage_mysql mysqladmin ping -h localhost --silent" 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Write-Host "    تلاش $tries/40 ..." -ForegroundColor DarkGray
}

if ($ready) {
    OK "MySQL آماده است"
    INFO "اجرای installer ..."
    Invoke-Wsl "cd '$WslPath' && docker exec signage_php php /var/www/html/public/install.php" | Out-Null
    if ($LASTEXITCODE -eq 0) { OK "دیتابیس و جداول ایجاد شدند" }
    else { WARN "installer پیام خطا داشت — آدرس http://$localIp`:$Port/install.php را باز کنید" }
} else {
    WARN "MySQL در زمان مورد انتظار آماده نشد — installer را دستی اجرا کنید"
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 9 — Firewall
# ─────────────────────────────────────────────────────────────────────────────
STEP "تنظیمات Firewall"
Write-Color ""

foreach ($p in @($Port, $WsPort, 8081)) {
    $ruleName = "SignageCMS Port $p"
    if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP `
            -LocalPort $p -Action Allow -Profile Any | Out-Null
        OK "پورت $p در Firewall باز شد"
    } else { OK "قانون Firewall پورت $p از قبل وجود دارد" }
}

# ─────────────────────────────────────────────────────────────────────────────
# STEP 10 — Auto-start هنگام بوت سرور
# ─────────────────────────────────────────────────────────────────────────────
STEP "تنظیم اجرای خودکار هنگام بوت"
Write-Color ""

$taskName = "SignageCMS Autostart"
$action   = "wsl.exe -d $Distro -u root -- bash -lc `"cd '$WslPath' && docker compose up -d`""
schtasks /Query /TN $taskName 2>$null | Out-Null
if ($LASTEXITCODE -eq 0) { schtasks /Delete /TN $taskName /F | Out-Null }
schtasks /Create /TN $taskName /TR $action /SC ONSTART /RU SYSTEM /RL HIGHEST /F | Out-Null
if ($LASTEXITCODE -eq 0) { OK "Scheduled Task برای اجرای خودکار ساخته شد" }
else { WARN "ساخت Scheduled Task ناموفق — می‌توانید دستی اضافه کنید" }

# ─────────────────────────────────────────────────────────────────────────────
# نمایش اطلاعات نهایی
# ─────────────────────────────────────────────────────────────────────────────
$portSuffix = if ($Port -eq 80) { "" } else { ":$Port" }
Write-Color @"

  +==============================================================+
  |                     نصب کامل شد!                            |
  +==============================================================+
  |                                                              |
  |  داشبورد (این سرور):  http://localhost$portSuffix/admin
  |  داشبورد (از شبکه):   http://${localIp}$portSuffix/admin
  |  phpMyAdmin:           http://${localIp}:8081
  |  WebSocket:            ws://${localIp}:$WsPort
  |                                                              |
  +==============================================================+
  |                                                              |
  |  ایمیل:  $adminEmail
  |  رمز:    $adminPass
  |                                                              |
  |  ⚠  بعد از اولین ورود، رمز را تغییر دهید                   |
  |                                                              |
  +==============================================================+
  |  سماع رایانه کیش  |  kishwifi.com                          |
  +==============================================================+

"@ 'Green'

Write-Color "  دستورات مفید (از PowerShell):" 'Cyan'
Write-Color "  +-------------------------------------------------------------------+" 'DarkGray'
Write-Color "  | وضعیت:    wsl -d $Distro -u root -- bash -lc `"cd '$WslPath' && docker compose ps`"" 'DarkGray'
Write-Color "  | لاگ‌ها:    ... docker compose logs -f" 'DarkGray'
Write-Color "  | توقف:     ... docker compose stop" 'DarkGray'
Write-Color "  | شروع:     ... docker compose start" 'DarkGray'
Write-Color "  | آپدیت:    ... docker compose pull && docker compose up -d" 'DarkGray'
Write-Color "  | حذف:      .\setup-server2022.ps1 -Uninstall" 'DarkGray'
Write-Color "  +-------------------------------------------------------------------+" 'DarkGray'
Write-Color ""
