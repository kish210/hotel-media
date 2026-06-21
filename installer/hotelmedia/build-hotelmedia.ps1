#Requires -Version 5.1
<#
.SYNOPSIS
    Build Setup-HotelMedia.exe — the Persian, offline, no-Docker installer.
    سماع رایانه کیش | kishwifi.com

.DESCRIPTION
    One command produces the final single-file installer:

        1. Downloads portable PHP + MariaDB + NSSM (cached in dist\downloads).
        2. Generates the app icon (hotelmedia.ico).
        3. Compiles the C# WinForms tray app with the in-box csc.exe.
        4. Assembles dist\hm-payload (app + runtime\{php,mariadb,nssm,scripts,tray}).
        5. Writes a production php.ini.
        6. Compiles Setup-HotelMedia.iss with Inno Setup (ISCC).

    Output: installer\hotelmedia\Output\Setup-HotelMedia.exe

    Requires Inno Setup 6 on the build machine. PHP/MariaDB/NSSM are downloaded.

.EXAMPLE
    .\installer\hotelmedia\build-hotelmedia.ps1 -Version 1.7.2
#>
param(
    [string]$Version       = "1.7.2",
    [string]$PhpZipUrl     = "",
    [string]$MariaDbZipUrl = "https://archive.mariadb.org/mariadb-11.4.4/winx64-packages/mariadb-11.4.4-winx64.zip",
    [string]$NssmZipUrl    = "https://nssm.cc/release/nssm-2.24.zip",
    [string]$NssmExePath   = "",
    [string]$Iscc          = $null
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$HmDir       = $PSScriptRoot                      # installer\hotelmedia
$ProjectDir  = (Resolve-Path (Join-Path $HmDir '..\..')).Path
$DistDir     = Join-Path $ProjectDir 'dist'
$DownloadDir = Join-Path $DistDir 'downloads'
$StageDir    = Join-Path $DistDir 'stage'
$Payload     = Join-Path $DistDir 'hm-payload'
$IssFile     = Join-Path $HmDir 'Setup-HotelMedia.iss'

function Hd ($m){ Write-Host "`n== $m" -ForegroundColor Cyan }
function Ok ($m){ Write-Host "   [OK] $m" -ForegroundColor Green }
function In ($m){ Write-Host "   [..] $m" -ForegroundColor Gray }

Write-Host ""
Write-Host "  ====================================================" -ForegroundColor Cyan
Write-Host "    HotelMedia installer builder  v$Version" -ForegroundColor Cyan
Write-Host "  ====================================================" -ForegroundColor Cyan

foreach ($d in @($DistDir,$DownloadDir,$StageDir)) { if (-not (Test-Path $d)) { New-Item -ItemType Directory $d -Force | Out-Null } }

# ── locate ISCC ───────────────────────────────────────────────────────────────
if (-not $Iscc) {
    foreach ($c in @("${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe","$env:ProgramFiles\Inno Setup 6\ISCC.exe")) {
        if (Test-Path $c) { $Iscc = $c; break }
    }
}
if (-not $Iscc -or -not (Test-Path $Iscc)) { throw "Inno Setup (ISCC.exe) not found. Install from https://jrsoftware.org/isdl.php" }
Ok "ISCC: $Iscc"

# ── helpers ──────────────────────────────────────────────────────────────────
function Get-Cached { param([string[]]$Urls,[string]$Name)
    $dest = Join-Path $DownloadDir $Name
    if (Test-Path $dest) { In "cached: $Name"; return $dest }
    foreach ($u in $Urls) {
        try { In "downloading $Name"; Invoke-WebRequest $u -OutFile $dest -UseBasicParsing
              Ok "got $Name ($([math]::Round((Get-Item $dest).Length/1MB,1)) MB)"; return $dest }
        catch { Write-Host "      (not available: $u)" -ForegroundColor DarkYellow; if (Test-Path $dest){Remove-Item $dest -Force} }
    }
    throw "Could not download $Name"
}
function Get-PhpUrls { param([string]$Override)
    if ($Override) { return @($Override) }
    $b='https://windows.php.net/downloads/releases'; $p=18,17,16,15,14,13,12,11,10
    (($p|ForEach-Object{"$b/php-8.3.$_-nts-Win32-vs16-x64.zip"})+($p|ForEach-Object{"$b/archives/php-8.3.$_-nts-Win32-vs16-x64.zip"}))
}
function Expand-Fresh { param([string]$Zip,[string]$Dest)
    if (Test-Path $Dest){Remove-Item $Dest -Recurse -Force}; New-Item -ItemType Directory $Dest -Force | Out-Null
    Expand-Archive $Zip -DestinationPath $Dest -Force
}

# ══ 1. downloads ══════════════════════════════════════════════════════════════
Hd "Downloading runtimes"
$phpZip   = Get-Cached (Get-PhpUrls $PhpZipUrl) 'php.zip'
$mariaZip = Get-Cached @($MariaDbZipUrl) 'mariadb.zip'
$nssmZip  = if ($NssmExePath -and (Test-Path $NssmExePath)) { $null } else { Get-Cached @($NssmZipUrl) 'nssm.zip' }

# ══ 2. app payload ════════════════════════════════════════════════════════════
Hd "Assembling application files"
if (Test-Path $Payload){Remove-Item $Payload -Recurse -Force}; New-Item -ItemType Directory $Payload -Force | Out-Null
$ExDirs = @('\.git\\','\\node_modules\\','\\vendor\\','\\\.claude\\','\\dist\\','\\storage\\cache\\','\\storage\\sessions\\','\\storage\\logs\\','\\storage\\temp\\','\\public\\uploads\\media\\','\\public\\uploads\\thumbnails\\','\\public\\apk\\','\\android\\','\\ios\\','\\fastlane\\','\\windows-player\\','\\rpi\\','\\\{app\\}','\\tests\\','\\docker\\','\\nginx\\')
$ExFiles = @('\.env$','\.log$','\.zip$','composer\.lock$','\.DS_Store$','Thumbs\.db$','docker-compose\.yml$','\.dockerignore$','docker-start\.sh$','promo-video\.html$','\bnul$')
$n=0
foreach ($f in (Get-ChildItem $ProjectDir -Recurse -File -ErrorAction SilentlyContinue)) {
    $full=$f.FullName; $rel=$full.Substring($ProjectDir.Length+1); $skip=$false
    foreach ($p in $ExDirs){if($full -match $p){$skip=$true;break}}
    if(-not $skip){foreach($p in $ExFiles){if($rel -match $p){$skip=$true;break}}}
    if(-not $skip -and $f.Length -gt 52428800){$skip=$true}
    if($skip){continue}
    $dest=Join-Path $Payload $rel; $dd=Split-Path $dest -Parent
    if(-not(Test-Path $dd)){New-Item -ItemType Directory $dd -Force|Out-Null}
    Copy-Item $full $dest; $n++
}
foreach ($d in @('storage\logs','storage\cache','storage\sessions','storage\temp','public\uploads\media','public\uploads\thumbnails','public\uploads\apk','data')) {
    New-Item -ItemType Directory (Join-Path $Payload $d) -Force | Out-Null
}
Ok "$n application files"

# ══ 3. runtimes into payload\runtime ══════════════════════════════════════════
Hd "Bundling PHP + MariaDB + NSSM"
$RtPhp=Join-Path $Payload 'runtime\php'; $RtMaria=Join-Path $Payload 'runtime\mariadb'
$RtNssm=Join-Path $Payload 'runtime\nssm'; $RtScr=Join-Path $Payload 'runtime\scripts'; $RtTray=Join-Path $Payload 'runtime\tray'

Expand-Fresh $phpZip $RtPhp; Ok "PHP"
$tmpM=Join-Path $StageDir 'mariadb'; Expand-Fresh $mariaZip $tmpM
$inner=Get-ChildItem $tmpM -Directory | Select-Object -First 1
if(-not $inner){throw "bad MariaDB zip"}
if(Test-Path $RtMaria){Remove-Item $RtMaria -Recurse -Force}; Move-Item $inner.FullName $RtMaria
foreach($j in @('share\doc','share\man','mysql-test','sql-bench')){$jp=Join-Path $RtMaria $j; if(Test-Path $jp){Remove-Item $jp -Recurse -Force}}
Ok "MariaDB"
New-Item -ItemType Directory $RtNssm -Force | Out-Null
if ($NssmExePath -and (Test-Path $NssmExePath)) { Copy-Item $NssmExePath (Join-Path $RtNssm 'nssm.exe') -Force; Ok "NSSM (provided)" }
else {
    $tmpN=Join-Path $StageDir 'nssm'; Expand-Fresh $nssmZip $tmpN
    $n64=Get-ChildItem $tmpN -Filter nssm.exe -Recurse | Where-Object {$_.DirectoryName -match 'win64'} | Select-Object -First 1
    if(-not $n64){$n64=Get-ChildItem $tmpN -Filter nssm.exe -Recurse | Select-Object -First 1}
    Copy-Item $n64.FullName (Join-Path $RtNssm 'nssm.exe') -Force; Ok "NSSM"
}

# runtime scripts
New-Item -ItemType Directory $RtScr -Force | Out-Null
Copy-Item (Join-Path $HmDir 'runtime\*') $RtScr -Recurse -Force
Ok "runtime scripts"

# ══ 4. php.ini ════════════════════════════════════════════════════════════════
@'
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
'@ | Set-Content (Join-Path $RtPhp 'php.ini') -Encoding ASCII
Ok "php.ini"

# ══ 5. icon + tray app ════════════════════════════════════════════════════════
Hd "Building tray app + icon"
New-Item -ItemType Directory $RtTray -Force | Out-Null
$icoPath = Join-Path $RtTray 'hotelmedia.ico'

Add-Type -AssemblyName System.Drawing
$bmp = New-Object System.Drawing.Bitmap 256,256
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = 'AntiAlias'
$g.Clear([System.Drawing.Color]::Transparent)
$rect = New-Object System.Drawing.Rectangle 16,16,224,224
$brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush $rect, ([System.Drawing.Color]::FromArgb(30,136,229)), ([System.Drawing.Color]::FromArgb(13,71,161)), 45
$g.FillRectangle($brush, $rect)
$font = New-Object System.Drawing.Font 'Segoe UI', 150, ([System.Drawing.FontStyle]::Bold)
$sf = New-Object System.Drawing.StringFormat
$sf.Alignment = 'Center'; $sf.LineAlignment = 'Center'
$rectF = New-Object System.Drawing.RectangleF 16, 16, 224, 224
$g.DrawString('H', $font, [System.Drawing.Brushes]::White, $rectF, $sf)
$g.Dispose()
$ico = [System.Drawing.Icon]::FromHandle($bmp.GetHicon())
$fs = [System.IO.File]::Create($icoPath)
$ico.Save($fs); $fs.Close()
$bmp.Dispose()
Ok "icon generated"

# Ensure the C# source has a UTF-8 BOM so csc reads the Persian literals right.
$csSrc = Join-Path $HmDir 'tray\HotelMediaTray.cs'
$csTmp = Join-Path $StageDir 'HotelMediaTray.cs'
$content = Get-Content $csSrc -Raw -Encoding UTF8
[System.IO.File]::WriteAllText($csTmp, $content, (New-Object System.Text.UTF8Encoding $true))

$csc = Get-ChildItem "$env:WINDIR\Microsoft.NET\Framework64" -Filter csc.exe -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1
if (-not $csc) { throw "csc.exe (.NET Framework) not found" }
$trayExe = Join-Path $RtTray 'HotelMediaTray.exe'
& $csc.FullName /nologo /target:winexe /out:"$trayExe" /win32icon:"$icoPath" `
    /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "$csTmp"
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $trayExe)) { throw "Tray app compilation failed" }
Ok "tray app compiled ($($csc.Directory.Name))"

# ══ 6. compile installer ══════════════════════════════════════════════════════
Hd "Compiling installer with Inno Setup"
$payloadAbs = (Resolve-Path $Payload).Path
& $Iscc "/DMyAppVersion=$Version" "/DPayloadDir=$payloadAbs" $IssFile
if ($LASTEXITCODE -ne 0) { throw "ISCC failed ($LASTEXITCODE)" }

$exe = Join-Path $HmDir 'Output\Setup-HotelMedia.exe'
Write-Host ""
if (Test-Path $exe) {
    $mb=[math]::Round((Get-Item $exe).Length/1MB,1)
    Write-Host "  ====================================================" -ForegroundColor Green
    Write-Host "    Setup-HotelMedia.exe built — $mb MB" -ForegroundColor Green
    Write-Host "    $exe" -ForegroundColor White
    Write-Host "  ====================================================" -ForegroundColor Green
} else { throw "Build finished but Setup-HotelMedia.exe not found" }
