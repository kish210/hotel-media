#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — Universal Installer (نصب‌کننده یکپارچه)
    سماع رایانه کیش | kishwifi.com
.DESCRIPTION
    یک نصب‌کننده واحد که سیستم‌عامل را تشخیص می‌دهد و نصب کامل را خودکار انجام می‌دهد.
    این اسکریپت بر اساس نسخه ویندوز، اسکریپت درست را انتخاب و اجرا می‌کند و
    همه پیش‌نیازها (Docker / WSL2) را دانلود، نصب و بررسی می‌کند:

      • Windows Server 2019/2022/2025  ->  setup-server2022.ps1 (WSL2 + Docker Engine)
      • Windows 10 / 11                ->  setup-windows.ps1     (Docker Desktop)
      • Linux / macOS                  ->  راهنمای اجرای setup.sh

    اگر دسترسی Administrator نداشته باشید، اسکریپت به‌صورت خودکار درخواست بالا بردن
    سطح دسترسی (UAC) می‌کند.
.EXAMPLE
    # PowerShell را با Run as Administrator باز کنید:
    Set-ExecutionPolicy Bypass -Scope Process -Force
    .\install.ps1
.EXAMPLE
    .\install.ps1 -Port 8080 -Silent
.EXAMPLE
    .\install.ps1 -Uninstall
#>
param(
    [int]   $Port     = 80,
    [int]   $WsPort   = 8080,
    [string]$Distro   = 'Ubuntu',
    [switch]$Silent,
    [switch]$Uninstall,
    [switch]$NoElevate   # داخلی: جلوگیری از حلقه بی‌نهایت UAC
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding  = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = 'SignageCMS Universal Installer — سماع رایانه کیش'

function Write-Color {
    param([string]$Text, [string]$Color = 'White', [switch]$NoNewline)
    if ($NoNewline) { Write-Host $Text -ForegroundColor $Color -NoNewline }
    else            { Write-Host $Text -ForegroundColor $Color }
}
function OK   { param($m) Write-Color "  [OK] $m" 'Green'  }
function WARN { param($m) Write-Color "  [! ] $m" 'Yellow' }
function ERR  { param($m) Write-Color "  [X ] $m" 'Red'    }
function INFO { param($m) Write-Color "  [>>] $m" 'Cyan'   }

Clear-Host
Write-Color @'

  +==============================================================+
  |                                                              |
  |        SignageCMS  --  Universal Installer / نصب یکپارچه      |
  |                                                              |
  |        سماع رایانه کیش  |  kishwifi.com          v1.6.0       |
  +==============================================================+

'@ 'Cyan'

$ScriptDir = Split-Path $MyInvocation.MyCommand.Path -Parent
Set-Location $ScriptDir
INFO "پوشه نصب: $ScriptDir"

# ─────────────────────────────────────────────────────────────────────────────
# تشخیص سیستم‌عامل
# ─────────────────────────────────────────────────────────────────────────────
$os        = Get-CimInstance Win32_OperatingSystem
$caption   = $os.Caption
$isServer  = $caption -match 'Server'
$winVer    = [System.Environment]::OSVersion.Version

INFO "سیستم‌عامل: $caption (Build $($os.BuildNumber))"

if ($winVer.Major -lt 10) {
    ERR "Windows 10 یا بالاتر نیاز است (نسخه فعلی: $($winVer.ToString()))."
    Write-Color "  برای نسخه‌های قدیمی‌تر، نصب دستی با Docker لازم است.`n" 'Yellow'
    exit 1
}

# ─────────────────────────────────────────────────────────────────────────────
# انتخاب اسکریپت مناسب
# ─────────────────────────────────────────────────────────────────────────────
if ($isServer) {
    $target = Join-Path $ScriptDir 'setup-server2022.ps1'
    OK "نسخه سرور شناسایی شد  ->  WSL2 + Docker Engine"
} else {
    $target = Join-Path $ScriptDir 'setup-windows.ps1'
    OK "ویندوز ۱۰/۱۱ شناسایی شد  ->  Docker Desktop"
}

if (-not (Test-Path $target)) {
    ERR "اسکریپت نصب پیدا نشد: $target"
    Write-Color "  مطمئن شوید کل پروژه را clone/extract کرده‌اید.`n" 'Yellow'
    exit 1
}
INFO "اجرای: $(Split-Path $target -Leaf)"

# ─────────────────────────────────────────────────────────────────────────────
# بررسی دسترسی Administrator (لازم برای WSL2 / Firewall / Scheduled Task)
# ─────────────────────────────────────────────────────────────────────────────
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    if ($NoElevate) {
        WARN "بدون دسترسی Administrator ادامه می‌دهیم — برخی مراحل (Firewall/WSL) ممکن است ناقص بمانند."
    } else {
        WARN "این نصب نیاز به دسترسی Administrator دارد — در حال بالا بردن سطح دسترسی (UAC)..."

        # ساخت لیست آرگومان‌ها برای اجرای مجدد به‌صورت elevated
        $argList = @(
            '-NoProfile','-ExecutionPolicy','Bypass',
            '-File', "`"$($MyInvocation.MyCommand.Path)`"",
            '-Port', $Port, '-WsPort', $WsPort, '-Distro', $Distro, '-NoElevate'
        )
        if ($Silent)    { $argList += '-Silent' }
        if ($Uninstall) { $argList += '-Uninstall' }

        try {
            Start-Process -FilePath 'powershell.exe' -ArgumentList $argList -Verb RunAs
            Write-Color "  پنجره جدید با دسترسی Administrator باز شد. این پنجره را می‌توانید ببندید.`n" 'Cyan'
            exit 0
        } catch {
            ERR "بالا بردن سطح دسترسی لغو شد. لطفاً PowerShell را با Run as Administrator باز کنید."
            exit 1
        }
    }
}

# ─────────────────────────────────────────────────────────────────────────────
# تحویل به اسکریپت تخصصی سیستم‌عامل (همه پیش‌نیازها همان‌جا بررسی/نصب می‌شوند)
# ─────────────────────────────────────────────────────────────────────────────
$params = @{
    Port   = $Port
    WsPort = $WsPort
}
if ($Silent)    { $params['Silent']    = $true }
if ($Uninstall) { $params['Uninstall'] = $true }
if ($isServer)  { $params['Distro']    = $Distro }

Write-Color ""
& $target @params
exit $LASTEXITCODE
