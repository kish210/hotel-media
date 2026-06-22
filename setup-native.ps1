#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — In-place native setup (NO Docker).
    سماع رایانه کیش | kishwifi.com

.DESCRIPTION
    Installs and runs SignageCMS directly in THIS folder, with no Docker / WSL2.
    Use this when you extracted the server files (SignageCMS-server.zip) and just
    want it running on the machine. It:

        1. Downloads a portable PHP + MariaDB + NSSM into .\runtime
        2. Writes a production php.ini
        3. Runs provision.ps1 — creates the DB, imports the schema, registers the
           web + websocket + database as auto-start Windows services, opens the
           firewall, and launches the dashboard.

    This is the same engine used by the all-in-one installer .exe, but it runs
    from the current folder instead of being compiled into an installer.

.EXAMPLE
    # Right-click → Run with PowerShell  (it will ask for admin), or:
    powershell -ExecutionPolicy Bypass -File setup-native.ps1
.EXAMPLE
    .\setup-native.ps1 -Port 8080
.EXAMPLE
    .\setup-native.ps1 -Uninstall
#>
param(
    [int]    $Port      = 80,
    [int]    $WsPort    = 8080,
    [string] $PhpZipUrl = "",
    [string] $MariaDbZipUrl = "https://archive.mariadb.org/mariadb-11.4.4/winx64-packages/mariadb-11.4.4-winx64.zip",
    [string] $NssmZipUrl    = "https://nssm.cc/release/nssm-2.24.zip",
    [switch] $Uninstall,
    [switch] $NoElevate
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$App        = $PSScriptRoot
$Runtime    = Join-Path $App 'runtime'
$DownloadD  = Join-Path $Runtime '_downloads'
$RuntimeSrc = Join-Path $App 'installer\native\runtime'

function Hd  ($m) { Write-Host "`n== $m" -ForegroundColor Cyan }
function Ok  ($m) { Write-Host "   [OK] $m" -ForegroundColor Green }
function Info($m) { Write-Host "   [..] $m" -ForegroundColor Gray }
function Warn($m) { Write-Host "   [!]  $m" -ForegroundColor Yellow }

# ── Require admin (services + firewall) ───────────────────────────────────────
$isAdmin = ([Security.Principal.WindowsPrincipal] `
    [Security.Principal.WindowsIdentity]::GetCurrent()
    ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    if ($NoElevate) { throw "Administrator rights are required." }
    Write-Host "  Requesting administrator rights..." -ForegroundColor Yellow
    $argList = @('-NoProfile','-ExecutionPolicy','Bypass','-File',"`"$PSCommandPath`"",
                 '-Port',$Port,'-WsPort',$WsPort,'-NoElevate')
    if ($Uninstall) { $argList += '-Uninstall' }
    Start-Process powershell.exe -Verb RunAs -ArgumentList $argList
    return
}

Write-Host ""
Write-Host "  ====================================================" -ForegroundColor Cyan
Write-Host "    SignageCMS — Native setup (no Docker)" -ForegroundColor Cyan
Write-Host "    سماع رایانه کیش | kishwifi.com" -ForegroundColor Cyan
Write-Host "  ====================================================" -ForegroundColor Cyan
Write-Host "    Folder: $App" -ForegroundColor Gray

# ── Copy runtime scripts next to where provision expects them ──────────────────
$RtScr = Join-Path $Runtime 'scripts'
if (-not (Test-Path $RuntimeSrc)) { throw "Missing installer\native\runtime — incomplete download of the server files." }
New-Item -ItemType Directory -Path $RtScr -Force | Out-Null
Copy-Item (Join-Path $RuntimeSrc '*') $RtScr -Recurse -Force

# ── Uninstall path ────────────────────────────────────────────────────────────
if ($Uninstall) {
    & (Join-Path $RtScr 'uninstall.ps1') -InstallDir $App
    Write-Host "`n  Done. (Files in this folder were left in place.)`n" -ForegroundColor Green
    return
}

# ── Download helpers ──────────────────────────────────────────────────────────
New-Item -ItemType Directory -Path $DownloadD -Force | Out-Null
function Get-Cached {
    param([string[]]$Urls, [string]$Name)
    $dest = Join-Path $DownloadD $Name
    if (Test-Path $dest) { Info "cached: $Name"; return $dest }
    foreach ($u in $Urls) {
        try {
            Info "downloading $Name"
            Invoke-WebRequest -Uri $u -OutFile $dest -UseBasicParsing
            Ok "got $Name ($([math]::Round((Get-Item $dest).Length/1MB,1)) MB)"
            return $dest
        } catch {
            Warn "not available: $u"
            if (Test-Path $dest) { Remove-Item $dest -Force }
        }
    }
    throw "Could not download $Name. Check your internet connection."
}
function Get-PhpUrls {
    param([string]$Override)
    if ($Override) { return @($Override) }
    $b = 'https://windows.php.net/downloads/releases'
    $p = 18,17,16,15,14,13,12,11,10
    (($p | ForEach-Object { "$b/php-8.3.$_-nts-Win32-vs16-x64.zip" }) +
     ($p | ForEach-Object { "$b/archives/php-8.3.$_-nts-Win32-vs16-x64.zip" }))
}
function Expand-Fresh {
    param([string]$Zip, [string]$Dest)
    if (Test-Path $Dest) { Remove-Item $Dest -Recurse -Force }
    New-Item -ItemType Directory -Path $Dest -Force | Out-Null
    Expand-Archive -Path $Zip -DestinationPath $Dest -Force
}

# ── 1. PHP ────────────────────────────────────────────────────────────────────
Hd "Setting up PHP"
$RtPhp = Join-Path $Runtime 'php'
if (Test-Path (Join-Path $RtPhp 'php.exe')) {
    Info "PHP already present"
} else {
    Expand-Fresh (Get-Cached (Get-PhpUrls $PhpZipUrl) 'php.zip') $RtPhp
    Ok "PHP ready"
}
$phpIni = @'
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
cgi.fix_pathinfo = 0
realpath_cache_size = 4M
'@
Set-Content -Path (Join-Path $RtPhp 'php.ini') -Value $phpIni -Encoding ASCII
Ok "php.ini written"

# ── 2. MariaDB ────────────────────────────────────────────────────────────────
Hd "Setting up MariaDB"
$RtMaria = Join-Path $Runtime 'mariadb'
if (Get-ChildItem $RtMaria -Filter 'mariadbd.exe' -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1) {
    Info "MariaDB already present"
} else {
    $tmp = Join-Path $DownloadD 'maria_x'
    Expand-Fresh (Get-Cached @($MariaDbZipUrl) 'mariadb.zip') $tmp
    $inner = Get-ChildItem $tmp -Directory | Select-Object -First 1
    if (-not $inner) { throw "Unexpected MariaDB zip layout" }
    if (Test-Path $RtMaria) { Remove-Item $RtMaria -Recurse -Force }
    Move-Item $inner.FullName $RtMaria
    foreach ($junk in @('share\doc','share\man','mysql-test','sql-bench')) {
        $jp = Join-Path $RtMaria $junk
        if (Test-Path $jp) { Remove-Item $jp -Recurse -Force }
    }
    Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
    Ok "MariaDB ready"
}

# ── 3. NSSM ───────────────────────────────────────────────────────────────────
Hd "Setting up NSSM"
$RtNssm = Join-Path $Runtime 'nssm'
$nssmExe = Join-Path $RtNssm 'nssm.exe'
if (Test-Path $nssmExe) {
    Info "NSSM already present"
} else {
    New-Item -ItemType Directory -Path $RtNssm -Force | Out-Null
    # Prefer a choco-installed nssm.exe if available (avoids the flaky nssm.cc).
    $choco = $env:ChocolateyInstall
    $found = $null
    if ($choco -and (Test-Path "$choco\lib\nssm")) {
        $found = Get-ChildItem "$choco\lib\nssm" -Filter nssm.exe -Recurse -ErrorAction SilentlyContinue |
                    Where-Object { $_.DirectoryName -match 'win64' } | Select-Object -First 1
    }
    if ($found) {
        Copy-Item $found.FullName $nssmExe -Force
        Ok "NSSM taken from Chocolatey"
    } else {
        $tmp = Join-Path $DownloadD 'nssm_x'
        Expand-Fresh (Get-Cached @($NssmZipUrl) 'nssm.zip') $tmp
        $n = Get-ChildItem $tmp -Filter nssm.exe -Recurse |
                Where-Object { $_.DirectoryName -match 'win64' } | Select-Object -First 1
        if (-not $n) { $n = Get-ChildItem $tmp -Filter nssm.exe -Recurse | Select-Object -First 1 }
        Copy-Item $n.FullName $nssmExe -Force
        Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
        Ok "NSSM ready"
    }
}

# ── 4. Provision (DB + services + firewall) ───────────────────────────────────
Hd "Provisioning database and services"
& (Join-Path $RtScr 'provision.ps1') -InstallDir $App -Port $Port -WsPort $WsPort
$code = $LASTEXITCODE
if ($code -ne 0) { throw "Provisioning failed (exit $code). See storage\logs\install.log" }

Write-Host ""
Write-Host "  SignageCMS is set up WITHOUT Docker. You can close this window." -ForegroundColor Green
Write-Host ""
Read-Host "  Press Enter to exit" | Out-Null
