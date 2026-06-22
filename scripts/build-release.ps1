#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — ساخت بسته release (ZIP)
.DESCRIPTION
    فایل‌های پروژه را بدون .git و فایل‌های بزرگ فشرده می‌کند.
.EXAMPLE
    .\scripts\build-release.ps1
    .\scripts\build-release.ps1 -Version "1.3.0" -Output "C:\Releases"
#>
param(
    [string]$Version = "1.3.0",
    [string]$Output  = $null   # پیش‌فرض: کنار پوشه پروژه
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectDir = Split-Path $PSScriptRoot -Parent
$ZipName    = "SignageCMS-v${Version}.zip"
$OutDir     = if ($Output) { $Output } else { Split-Path $ProjectDir -Parent }
$ZipPath    = Join-Path $OutDir $ZipName
$TempDir    = Join-Path $env:TEMP "signage_release_$(Get-Random)"

Write-Host ""
Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   SignageCMS Release Builder v1.0       ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""
Write-Host "  پروژه:  $ProjectDir"  -ForegroundColor Gray
Write-Host "  خروجی: $ZipPath"      -ForegroundColor Gray
Write-Host ""

# ── پاک‌سازی قبلی ─────────────────────────────────────────────
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
    Write-Host "  ♻  فایل ZIP قبلی حذف شد" -ForegroundColor DarkYellow
}
if (Test-Path $TempDir) {
    Remove-Item $TempDir -Recurse -Force
}
New-Item -ItemType Directory $TempDir | Out-Null

# ── الگوهای حذف ──────────────────────────────────────────────
$ExcludeDirs = @(
    '\.git',
    '\\node_modules\\',
    '\\vendor\\',
    '\\\.claude\\',
    '\\storage\\cache\\',
    '\\storage\\sessions\\',
    '\\storage\\logs\\',
    '\\storage\\temp\\',
    '\\public\\uploads\\media\\',
    '\\public\\uploads\\thumbnails\\',
    '\\public\\apk\\',
    '\\\{app\\\}',        # invalid folder name
    '\\tests\\'
)

$ExcludeFiles = @(
    '\.env$',
    '\.log$',
    '\.zip$',
    'post_debug\.log',
    'composer\.lock$'
)

# ── کپی فایل‌ها ───────────────────────────────────────────────
Write-Host "  📁  در حال کپی فایل‌ها ..." -ForegroundColor Yellow

$AllFiles = Get-ChildItem $ProjectDir -Recurse -File -ErrorAction SilentlyContinue

$Copied = 0
$Skipped = 0

foreach ($file in $AllFiles) {
    $rel = $file.FullName.Substring($ProjectDir.Length + 1)

    # بررسی exclude patterns
    $skip = $false
    foreach ($pattern in $ExcludeDirs) {
        if ($file.FullName -match $pattern) { $skip = $true; break }
    }
    if (-not $skip) {
        foreach ($pattern in $ExcludeFiles) {
            if ($rel -match $pattern) { $skip = $true; break }
        }
    }
    # فایل‌های خیلی بزرگ (بیشتر از 50MB)
    if (-not $skip -and $file.Length -gt 52428800) {
        Write-Host "    ⚠  حذف (بزرگ): $rel ($([math]::Round($file.Length/1MB,1))MB)" -ForegroundColor DarkYellow
        $skip = $true
    }

    if ($skip) { $Skipped++; continue }

    # کپی با حفظ ساختار
    $dest = Join-Path $TempDir $rel
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    Copy-Item $file.FullName $dest
    $Copied++
}

Write-Host "  ✅  $Copied فایل کپی شد  ($Skipped فایل حذف شد)" -ForegroundColor Green

# ── ساخت پوشه‌های ضروری خالی ─────────────────────────────────
$EmptyDirs = @(
    "storage\logs",
    "storage\cache",
    "storage\cache\fids",
    "storage\sessions",
    "storage\temp",
    "public\uploads\media",
    "public\uploads\thumbnails",
    "public\uploads\apk"
)
foreach ($d in $EmptyDirs) {
    $p = Join-Path $TempDir $d
    if (-not (Test-Path $p)) {
        New-Item -ItemType Directory -Path $p -Force | Out-Null
    }
    # .gitkeep برای حفظ پوشه در ZIP
    $gk = Join-Path $p ".gitkeep"
    if (-not (Test-Path $gk)) {
        New-Item -ItemType File $gk | Out-Null
    }
}
Write-Host "  ✅  پوشه‌های خالی ضروری ساخته شدند" -ForegroundColor Green

# ── اضافه کردن .env.example → .env (اگر .env نیست) ──────────
$envSrc = Join-Path $TempDir ".env.example"
$envDst = Join-Path $TempDir ".env.example"  # فقط example رو نگه می‌داریم، .env نه

# ── فشرده‌سازی ────────────────────────────────────────────────
Write-Host ""
Write-Host "  🗜  در حال فشرده‌سازی ..." -ForegroundColor Yellow

if (-not (Test-Path $OutDir)) {
    New-Item -ItemType Directory -Path $OutDir -Force | Out-Null
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $TempDir,
    $ZipPath,
    [System.IO.Compression.CompressionLevel]::Optimal,
    $false
)

# ── پاک‌سازی temp ─────────────────────────────────────────────
Remove-Item $TempDir -Recurse -Force

# ── اطلاعات نهایی ─────────────────────────────────────────────
$zipInfo = Get-Item $ZipPath
$zipMB   = [math]::Round($zipInfo.Length / 1MB, 2)

Write-Host ""
Write-Host "╔══════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║      بسته‌بندی کامل شد!                 ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "  📦  فایل: $ZipPath" -ForegroundColor White
Write-Host "  📏  حجم:  ${zipMB} MB"  -ForegroundColor White
Write-Host "  📂  فایل‌ها: $Copied فایل" -ForegroundColor White
Write-Host ""
Write-Host "  نحوه استفاده روی سیستم جدید:" -ForegroundColor Cyan
Write-Host "    1. فایل ZIP را extract کنید"      -ForegroundColor Gray
Write-Host "    2. Windows: روی START.bat دابل‌کلیک کنید (یا setup-windows.ps1)" -ForegroundColor Gray
Write-Host "    3. Linux:   bash setup.sh"          -ForegroundColor Gray
Write-Host ""

# بازکردن پوشه خروجی
Start-Process explorer.exe -ArgumentList (Split-Path $ZipPath -Parent) -ErrorAction SilentlyContinue
