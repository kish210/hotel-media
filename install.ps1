#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — نصب‌کننده ویندوز
    سماع رایانه کیش | kishwifi.com
.DESCRIPTION
    نصب کامل روی همین سیستم، بدون هیچ پیش‌نیاز بیرونی:
    PHP و MariaDB قابل‌حمل نصب می‌شوند، دیتابیس و کاربر ادمین ساخته می‌شود و
    وب‌سرور + سرور real-time + دیتابیس به‌عنوان «سرویس ویندوز» ثبت می‌شوند.

    این فایل صرفاً یک پوسته است و کار اصلی را setup-native.ps1 انجام می‌دهد.
    اگر دسترسی Administrator نداشته باشید، به‌صورت خودکار UAC درخواست می‌شود.
.EXAMPLE
    Set-ExecutionPolicy Bypass -Scope Process -Force
    .\install.ps1
.EXAMPLE
    .\install.ps1 -Port 8080 -Silent
.EXAMPLE
    .\install.ps1 -Uninstall
#>
param(
    [int]   $Port   = 80,
    [int]   $WsPort = 8080,
    [switch]$Silent,
    [switch]$Uninstall,
    [switch]$NoElevate   # داخلی: جلوگیری از حلقه بی‌نهایت UAC
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding  = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = 'SignageCMS Installer — سماع رایانه کیش'

function Write-Color {
    param([string]$Text, [string]$Color = 'White', [switch]$NoNewline)
    if ($NoNewline) { Write-Host $Text -ForegroundColor $Color -NoNewline }
    else            { Write-Host $Text -ForegroundColor $Color }
}
function OK   { param($m) Write-Color "  [OK] $m" 'Green'  }
function ERR  { param($m) Write-Color "  [X ] $m" 'Red'    }
function INFO { param($m) Write-Color "  [>>] $m" 'Cyan'   }

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition

Write-Color ""
Write-Color "  ============================================================" 'Cyan'
Write-Color "    SignageCMS — نصب‌کننده ویندوز" 'Cyan'
Write-Color "    kishwifi.com" 'DarkCyan'
Write-Color "  ============================================================" 'Cyan'
Write-Color ""

# ── بالا بردن سطح دسترسی در صورت نیاز ────────────────────────────────────────
$isAdmin = ([Security.Principal.WindowsPrincipal] `
            [Security.Principal.WindowsIdentity]::GetCurrent()
           ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin -and -not $NoElevate) {
    INFO "نیاز به دسترسی Administrator — پنجره جدید باز می‌شود..."
    $argList = @(
        '-NoProfile', '-ExecutionPolicy', 'Bypass',
        '-File', "`"$($MyInvocation.MyCommand.Definition)`"",
        '-NoElevate', '-Port', $Port, '-WsPort', $WsPort
    )
    if ($Silent)    { $argList += '-Silent' }
    if ($Uninstall) { $argList += '-Uninstall' }

    Start-Process powershell.exe -ArgumentList $argList -Verb RunAs
    exit 0
}

if (-not $isAdmin) {
    ERR "این اسکریپت باید با دسترسی Administrator اجرا شود."
    exit 1
}

# ── اجرای نصب‌کننده بومی ─────────────────────────────────────────────────────
$target = Join-Path $ScriptDir 'setup-native.ps1'
if (-not (Test-Path $target)) {
    ERR "فایل setup-native.ps1 پیدا نشد: $target"
    exit 1
}

INFO "اجرای نصب‌کننده: setup-native.ps1"
Write-Color ""

# setup-native.ps1 پارامتر -Silent ندارد؛ فقط موارد پشتیبانی‌شده پاس داده می‌شود
$params = @{ Port = $Port; WsPort = $WsPort; NoElevate = $true }
if ($Uninstall) { $params['Uninstall'] = $true }

& $target @params
$code = $LASTEXITCODE

Write-Color ""
if ($code -eq 0 -or $null -eq $code) {
    OK "نصب با موفقیت به پایان رسید."
} else {
    ERR "نصب ناموفق بود (کد $code) — فایل لاگ: storage\logs\install.log"
}

exit $code
