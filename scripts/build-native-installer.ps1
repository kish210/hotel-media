#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — build the NATIVE (no-Docker) Windows installer .exe.
    سماع رایانه کیش | kishwifi.com

.DESCRIPTION
    One command turns this repo into a single double-click installer:

        1. Downloads portable PHP, MariaDB and NSSM (cached in dist\downloads).
        2. Assembles dist\native-payload = app files + runtime\{php,mariadb,nssm,scripts}.
        3. Writes a production php.ini with all extensions the app needs.
        4. Compiles installer\native\SignageCMS-Native.iss with Inno Setup (ISCC).

    Output: installer\native\Output\SignageCMS-v<version>-setup.exe

    Run this on a Windows build machine that has Inno Setup 6 installed
    (https://jrsoftware.org/isdl.php). PHP / MariaDB / NSSM are downloaded
    automatically — nothing else is required.

.EXAMPLE
    .\scripts\build-native-installer.ps1
    .\scripts\build-native-installer.ps1 -Version 1.7.0
#>
param(
    [string]$Version    = "1.6.2",

    # Bundled runtime versions. Override if you want newer builds; the URLs are
    # derived from these. PHP must be the x64 *NTS* (non-thread-safe) Windows zip.
    [string]$PhpZipUrl    = "https://windows.php.net/downloads/releases/php-8.3.14-nts-Win32-vs16-x64.zip",
    [string]$MariaDbZipUrl= "https://archive.mariadb.org/mariadb-11.4.4/winx64-packages/mariadb-11.4.4-winx64.zip",
    [string]$NssmZipUrl   = "https://nssm.cc/release/nssm-2.24.zip",

    [string]$Iscc        = $null   # path to ISCC.exe; auto-detected if omitted
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding   = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$ProjectDir  = Split-Path $PSScriptRoot -Parent
$DistDir     = Join-Path $ProjectDir 'dist'
$DownloadDir = Join-Path $DistDir 'downloads'
$StageDir    = Join-Path $DistDir 'stage'
$Payload     = Join-Path $DistDir 'native-payload'
$IssFile     = Join-Path $ProjectDir 'installer\native\SignageCMS-Native.iss'
$RuntimeSrc  = Join-Path $ProjectDir 'installer\native\runtime'

function Hd  ($m) { Write-Host "`n== $m" -ForegroundColor Cyan }
function Ok  ($m) { Write-Host "   [OK] $m" -ForegroundColor Green }
function Info($m) { Write-Host "   [..] $m" -ForegroundColor Gray }

Write-Host ""
Write-Host "  =====================================================" -ForegroundColor Cyan
Write-Host "    SignageCMS — Native installer builder  v$Version" -ForegroundColor Cyan
Write-Host "  =====================================================" -ForegroundColor Cyan

foreach ($d in @($DistDir, $DownloadDir, $StageDir)) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Path $d -Force | Out-Null }
}

# ── 0. Locate ISCC ───────────────────────────────────────────────────────────
if (-not $Iscc) {
    foreach ($c in @(
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "$env:ProgramFiles\Inno Setup 6\ISCC.exe")) {
        if (Test-Path $c) { $Iscc = $c; break }
    }
}
if (-not $Iscc -or -not (Test-Path $Iscc)) {
    Write-Host ""
    Write-Host "  [X] Inno Setup (ISCC.exe) not found." -ForegroundColor Red
    Write-Host "      Install it from https://jrsoftware.org/isdl.php and re-run," -ForegroundColor Yellow
    Write-Host "      or pass -Iscc 'C:\path\to\ISCC.exe'." -ForegroundColor Yellow
    throw "ISCC not found"
}
Ok "Using ISCC: $Iscc"

# ── helpers ──────────────────────────────────────────────────────────────────
function Get-Cached {
    param([string]$Url, [string]$Name)
    $dest = Join-Path $DownloadDir $Name
    if (Test-Path $dest) { Info "cached: $Name"; return $dest }
    Info "downloading $Name ..."
    Invoke-WebRequest -Uri $Url -OutFile $dest -UseBasicParsing
    Ok "downloaded $Name ($([math]::Round((Get-Item $dest).Length/1MB,1)) MB)"
    return $dest
}
function Expand-Fresh {
    param([string]$Zip, [string]$Dest)
    if (Test-Path $Dest) { Remove-Item $Dest -Recurse -Force }
    New-Item -ItemType Directory -Path $Dest -Force | Out-Null
    Expand-Archive -Path $Zip -DestinationPath $Dest -Force
}

# ══════════════════════════════════════════════════════════════════════════════
# 1. Download runtimes
# ══════════════════════════════════════════════════════════════════════════════
Hd "Downloading runtimes (cached after first run)"
$phpZip   = Get-Cached $PhpZipUrl     "php.zip"
$mariaZip = Get-Cached $MariaDbZipUrl "mariadb.zip"
$nssmZip  = Get-Cached $NssmZipUrl    "nssm.zip"

# ══════════════════════════════════════════════════════════════════════════════
# 2. Assemble the app payload (repo minus dev/heavy files)
# ══════════════════════════════════════════════════════════════════════════════
Hd "Assembling application files"
if (Test-Path $Payload) { Remove-Item $Payload -Recurse -Force }
New-Item -ItemType Directory -Path $Payload -Force | Out-Null

$ExcludeDirs = @(
    '\.git\\', '\\node_modules\\', '\\vendor\\', '\\\.claude\\', '\\dist\\',
    '\\storage\\cache\\', '\\storage\\sessions\\', '\\storage\\logs\\', '\\storage\\temp\\',
    '\\public\\uploads\\media\\', '\\public\\uploads\\thumbnails\\', '\\public\\apk\\',
    '\\android\\', '\\ios\\', '\\fastlane\\', '\\windows-player\\', '\\rpi\\',
    '\\\{app\\}', '\\tests\\', '\\docker\\', '\\nginx\\'
)
$ExcludeFiles = @(
    '\.env$', '\.log$', '\.zip$', 'composer\.lock$', '\.DS_Store$', 'Thumbs\.db$',
    'docker-compose\.yml$', '\.dockerignore$', 'docker-start\.sh$',
    'promo-video\.html$', '\bnul$'
)

$copied = 0
foreach ($file in (Get-ChildItem $ProjectDir -Recurse -File -ErrorAction SilentlyContinue)) {
    $full = $file.FullName
    $rel  = $full.Substring($ProjectDir.Length + 1)
    $skip = $false
    foreach ($p in $ExcludeDirs)  { if ($full -match $p) { $skip = $true; break } }
    if (-not $skip) { foreach ($p in $ExcludeFiles) { if ($rel -match $p) { $skip = $true; break } } }
    if (-not $skip -and $file.Length -gt 52428800) { $skip = $true }   # >50MB
    if ($skip) { continue }

    $dest = Join-Path $Payload $rel
    $dd   = Split-Path $dest -Parent
    if (-not (Test-Path $dd)) { New-Item -ItemType Directory -Path $dd -Force | Out-Null }
    Copy-Item $full $dest
    $copied++
}
Ok "$copied application files copied"

# Empty runtime folders (kept with .gitkeep in repo, recreated here clean).
foreach ($d in @('storage\logs','storage\cache','storage\sessions','storage\temp',
                 'public\uploads\media','public\uploads\thumbnails','public\uploads\apk','data')) {
    New-Item -ItemType Directory -Path (Join-Path $Payload $d) -Force | Out-Null
}

# ══════════════════════════════════════════════════════════════════════════════
# 3. Lay out bundled runtimes under payload\runtime
# ══════════════════════════════════════════════════════════════════════════════
Hd "Bundling PHP + MariaDB + NSSM"
$RtPhp   = Join-Path $Payload 'runtime\php'
$RtMaria = Join-Path $Payload 'runtime\mariadb'
$RtNssm  = Join-Path $Payload 'runtime\nssm'
$RtScr   = Join-Path $Payload 'runtime\scripts'

# PHP — zip extracts flat into runtime\php.
Expand-Fresh $phpZip $RtPhp
Ok "PHP unpacked"

# MariaDB — zip has a nested top folder (mariadb-x.y.z-winx64); flatten it.
$tmpMaria = Join-Path $StageDir 'mariadb'
Expand-Fresh $mariaZip $tmpMaria
$inner = Get-ChildItem $tmpMaria -Directory | Select-Object -First 1
if ($null -eq $inner) { throw "Unexpected MariaDB zip layout" }
if (Test-Path $RtMaria) { Remove-Item $RtMaria -Recurse -Force }
Move-Item $inner.FullName $RtMaria
# Trim things we don't ship (docs/tests) to keep the installer smaller.
foreach ($junk in @('share\doc','share\man','mysql-test','sql-bench')) {
    $jp = Join-Path $RtMaria $junk
    if (Test-Path $jp) { Remove-Item $jp -Recurse -Force }
}
Ok "MariaDB unpacked"

# NSSM — keep only the 64-bit exe.
$tmpNssm = Join-Path $StageDir 'nssm'
Expand-Fresh $nssmZip $tmpNssm
$nssm64 = Get-ChildItem $tmpNssm -Filter 'nssm.exe' -Recurse |
            Where-Object { $_.DirectoryName -match 'win64' } | Select-Object -First 1
if (-not $nssm64) { $nssm64 = Get-ChildItem $tmpNssm -Filter 'nssm.exe' -Recurse | Select-Object -First 1 }
New-Item -ItemType Directory -Path $RtNssm -Force | Out-Null
Copy-Item $nssm64.FullName (Join-Path $RtNssm 'nssm.exe')
Ok "NSSM unpacked"

# Runtime scripts (provision / uninstall / manage / my.ini.template).
New-Item -ItemType Directory -Path $RtScr -Force | Out-Null
Copy-Item (Join-Path $RuntimeSrc '*') $RtScr -Recurse -Force
Ok "Runtime scripts copied"

# ══════════════════════════════════════════════════════════════════════════════
# 4. Production php.ini
# ══════════════════════════════════════════════════════════════════════════════
Hd "Writing php.ini"
$phpIni = @'
; SignageCMS — bundled PHP configuration (native install)
extension_dir = "ext"

extension=pdo_mysql
extension=mysqli
extension=mbstring
extension=openssl
extension=gd
extension=fileinfo
extension=curl
extension=exif
extension=intl

memory_limit = 512M
max_execution_time = 300
post_max_size = 600M
upload_max_filesize = 512M
max_file_uploads = 50

display_errors = Off
log_errors = On
date.timezone = "Asia/Tehran"

; Front-controller / built-in server friendliness.
cgi.fix_pathinfo = 0
realpath_cache_size = 4M
'@
Set-Content -Path (Join-Path $RtPhp 'php.ini') -Value $phpIni -Encoding ASCII
Ok "php.ini written"

# ══════════════════════════════════════════════════════════════════════════════
# 5. Compile the installer
# ══════════════════════════════════════════════════════════════════════════════
Hd "Compiling installer with Inno Setup"
$payloadAbs = (Resolve-Path $Payload).Path
& $Iscc "/DMyAppVersion=$Version" "/DPayloadDir=$payloadAbs" $IssFile
if ($LASTEXITCODE -ne 0) { throw "ISCC failed with exit code $LASTEXITCODE" }

$exe = Join-Path $ProjectDir "installer\native\Output\SignageCMS-v$Version-setup.exe"
Write-Host ""
if (Test-Path $exe) {
    $mb = [math]::Round((Get-Item $exe).Length / 1MB, 1)
    Write-Host "  =====================================================" -ForegroundColor Green
    Write-Host "    Installer built successfully!" -ForegroundColor Green
    Write-Host "  =====================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "    File: $exe" -ForegroundColor White
    Write-Host "    Size: $mb MB" -ForegroundColor White
    Write-Host ""
    Write-Host "    Give this single .exe to the end user. They double-click it," -ForegroundColor Gray
    Write-Host "    click Next a few times, and SignageCMS is installed and running." -ForegroundColor Gray
    Write-Host ""
    Start-Process explorer.exe -ArgumentList "/select,`"$exe`"" -ErrorAction SilentlyContinue
} else {
    throw "Build finished but output .exe was not found at $exe"
}
