#Requires -Version 5.1
<#
.SYNOPSIS
    HotelMedia — native provisioning (no Docker). Persian-facing, branded.
    سماع رایانه کیش | kishwifi.com

.DESCRIPTION
    Runs once (admin) right after the installer copies files. Turns the folder
    into a running server: generates .env (with the hotel name + a free web port),
    initializes MariaDB, registers MariaDB/web/websocket as auto-start Windows
    services, imports the schema, opens the firewall.

    All technical output goes to storage\logs\install.log. On any failure it
    exits non-zero so the installer can roll back; no technical errors are shown.

.NOTES
    Invoked by Setup-HotelMedia.iss. Params:
        -InstallDir <path> -HotelName "<name>" [-WsPort 8080] [-Silent]
#>
param(
    [Parameter(Mandatory = $true)] [string]$InstallDir,
    [string]$HotelName = 'هتل مدیا',
    [int]   $WsPort    = 8080,
    [switch]$Silent
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

# Prefer the hotel name written to a UTF-8 file by the installer (avoids any
# command-line encoding issues with Persian text).
$hnFile = Join-Path ($InstallDir.TrimEnd('\')) 'runtime\hotelname.txt'
if (Test-Path $hnFile) {
    $hn = (Get-Content $hnFile -Raw -Encoding UTF8).Trim()
    if ($hn) { $HotelName = $hn }
}

# ── Service names (shared with Manage-HotelMedia.ps1 / uninstall / tray) ───────
$SVC_DB  = 'HotelMedia-MySQL'
$SVC_WEB = 'HotelMedia-Web'
$SVC_WS  = 'HotelMedia-WS'

$App      = $InstallDir.TrimEnd('\')
$Runtime  = Join-Path $App 'runtime'
$PhpDir   = Join-Path $Runtime 'php'
$PhpExe   = Join-Path $PhpDir 'php.exe'
$PhpIni   = Join-Path $PhpDir 'php.ini'
$NssmExe  = Join-Path $Runtime 'nssm\nssm.exe'
$MariaDir = Join-Path $Runtime 'mariadb'
$DataDir  = Join-Path $App 'data\mysql'
$LogDir   = Join-Path $App 'storage\logs'
$LogFile  = Join-Path $LogDir 'install.log'

if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }
function Log  ($m,$c='Gray',$t='   ') { $l="{0}  {1}{2}" -f (Get-Date -Format 'HH:mm:ss'),$t,$m; Add-Content $LogFile $l -Encoding UTF8; Write-Host ("  {0}{1}" -f $t,$m) -ForegroundColor $c }
function OK   ($m){ Log $m 'Green'  '[OK] ' }
function INFO ($m){ Log $m 'Cyan'   '[..] ' }
function WARN ($m){ Log $m 'Yellow' '[!]  ' }
function FAIL ($m){ Log $m 'Red'    '[X]  '; throw $m }

Log "HotelMedia provisioning started — install dir: $App"
Log "Hotel name: $HotelName"

# ── locate bundled tools ──────────────────────────────────────────────────────
function Find-Tool { param([string]$Root,[string[]]$Names)
    foreach ($n in $Names) { $h = Get-ChildItem $Root -Filter $n -Recurse -File -ErrorAction SilentlyContinue | Select-Object -First 1; if ($h) { return $h.FullName } }
    return $null
}
if (-not (Test-Path $PhpExe))  { FAIL "php.exe missing at $PhpExe" }
if (-not (Test-Path $NssmExe)) { FAIL "nssm.exe missing at $NssmExe" }
$Mysqld     = Find-Tool $MariaDir @('mariadbd.exe','mysqld.exe')
$MysqlCli   = Find-Tool $MariaDir @('mariadb.exe','mysql.exe')
$MysqlAdmin = Find-Tool $MariaDir @('mariadb-admin.exe','mysqladmin.exe')
$InstallDb  = Find-Tool $MariaDir @('mariadb-install-db.exe','mysql_install_db.exe')
if (-not $Mysqld -or -not $MysqlCli) { FAIL "MariaDB binaries missing under $MariaDir" }
$MariaBin  = Split-Path $Mysqld -Parent
$MariaBase = Split-Path $MariaBin -Parent
OK "Bundled PHP + MariaDB located"

# ── helpers ──────────────────────────────────────────────────────────────────
function New-Secret { param([int]$Len=24)
    $c='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
    -join (1..$Len | ForEach-Object { $c[(Get-Random -Maximum $c.Length)] })
}
function Test-PortFree { param([int]$Port)
    try {
        $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Any, $Port)
        $l.Start(); $l.Stop(); return $true
    } catch { return $false }
}
function Find-FreeWebPort {
    foreach ($p in @(80, 8080, 8088, 8090, 8000, 8081, 8082, 8888, 9000)) {
        if ($p -eq $WsPort) { continue }
        if (Test-PortFree $p) { return $p }
    }
    # last resort: a random high port
    foreach ($p in 8100..8200) { if (Test-PortFree $p) { return $p } }
    return 80
}
function Remove-ServiceIfExists { param([string]$Name)
    $s = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if ($s) {
        if ($s.Status -ne 'Stopped') { Stop-Service $Name -Force -ErrorAction SilentlyContinue }
        try { $null = & $NssmExe stop $Name 2>&1 } catch {}
        try { $null = & $NssmExe remove $Name confirm 2>&1 } catch {}
        try { $null = & sc.exe delete $Name 2>&1 } catch {}
        Start-Sleep -Seconds 1
    }
}

# ── 1. choose a free web port ─────────────────────────────────────────────────
$Port = Find-FreeWebPort
if ($Port -ne 80) { WARN "Port 80 was busy — using port $Port instead" } else { OK "Using web port 80" }

# ── 2. .env (idempotent) ──────────────────────────────────────────────────────
$EnvFile = Join-Path $App '.env'
$DbName='hotel_media'; $DbUser='hotel_user'
if (Test-Path $EnvFile) {
    INFO ".env exists — reusing credentials"
    $txt = Get-Content $EnvFile -Raw
    $DbPass = ([regex]::Match($txt,'(?m)^DB_PASSWORD=(.*)$')).Groups[1].Value.Trim().Trim('"')
    if (-not $DbPass) { $DbPass = New-Secret 24 }
} else {
    $tpl = Join-Path $App '.env.example'
    if (-not (Test-Path $tpl)) { FAIL ".env.example missing" }
    $DbPass = New-Secret 24
    $appKey = 'base64:' + [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
    $jwt    = New-Secret 48
    $appUrl = if ($Port -eq 80) { 'http://localhost' } else { "http://localhost:$Port" }
    $env = Get-Content $tpl -Raw
    $repl = [ordered]@{
        'APP_NAME'       = '"' + $HotelName + '"'
        'APP_ENV'        = 'production'
        'APP_DEBUG'      = 'false'
        'APP_URL'        = $appUrl
        'APP_KEY'        = $appKey
        'DB_HOST'        = '127.0.0.1'
        'DB_PORT'        = '3306'
        'DB_DATABASE'    = $DbName
        'DB_USERNAME'    = $DbUser
        'DB_PASSWORD'    = $DbPass
        'JWT_SECRET'     = $jwt
        'WS_PORT'        = "$WsPort"
        'ADMIN_EMAIL'    = 'admin@signagecms.com'
        'ADMIN_PASSWORD' = 'Admin@123456'
    }
    foreach ($k in $repl.Keys) {
        if ($env -match "(?m)^$k=") { $env = [regex]::Replace($env,"(?m)^$k=.*$","$k=$($repl[$k])") }
        else { $env += "`r`n$k=$($repl[$k])" }
    }
    Set-Content $EnvFile $env -Encoding UTF8 -NoNewline
    OK ".env created (APP_NAME, free port, random secrets)"
}

# ── 3. MariaDB data dir + config ──────────────────────────────────────────────
$MyIni = Join-Path $Runtime 'my.ini'
$mtpl  = Join-Path $PSScriptRoot 'my.ini.template'
if (-not (Test-Path $mtpl)) { $mtpl = Join-Path $Runtime 'scripts\my.ini.template' }
(Get-Content $mtpl -Raw).Replace('{{BASEDIR}}',$MariaBase.Replace('\','/')).Replace('{{DATADIR}}',$DataDir.Replace('\','/')).Replace('{{PORT}}','3306') |
    Set-Content $MyIni -Encoding ASCII

$dbInit = Test-Path (Join-Path $DataDir 'mysql')
if (-not $dbInit) {
    INFO "Initializing database storage..."
    if (Test-Path $DataDir) { Remove-Item $DataDir -Recurse -Force }
    New-Item -ItemType Directory -Path $DataDir -Force | Out-Null
    if ($InstallDb) { & $InstallDb "--datadir=$DataDir" 2>&1 | Add-Content $LogFile }
    else { & $Mysqld "--defaults-file=$MyIni" '--initialize-insecure' 2>&1 | Add-Content $LogFile }
    if (-not (Test-Path (Join-Path $DataDir 'mysql'))) { FAIL "DB init failed" }
    OK "Database storage initialized"
}

# ── 4. MariaDB service ────────────────────────────────────────────────────────
Remove-ServiceIfExists $SVC_DB
& $Mysqld "--install" $SVC_DB "--defaults-file=$MyIni" 2>&1 | Add-Content $LogFile
Set-Service $SVC_DB -StartupType Automatic
Start-Service $SVC_DB
INFO "Waiting for database..."
$ready=$false
foreach ($i in 1..30) {
    if ((Test-NetConnection 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue).TcpTestSucceeded){$ready=$true;break}
    Start-Sleep -Seconds 1
}
if (-not $ready) { FAIL "Database did not start" }
OK "Database service online"

# ── 5. create DB + user (fresh installs) ──────────────────────────────────────
if (-not $dbInit) {
    $esc = $DbPass.Replace("'","''")
    $sql = @"
CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$esc';
CREATE USER IF NOT EXISTS '$DbUser'@'127.0.0.1' IDENTIFIED BY '$esc';
GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';
GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
    $sf = Join-Path $env:TEMP "hm_init_$([guid]::NewGuid().ToString('N')).sql"
    Set-Content $sf $sql -Encoding ASCII
    & $MysqlCli '--host=127.0.0.1' '--port=3306' '--user=root' "--execute=source $sf" 2>&1 | Add-Content $LogFile
    $ec=$LASTEXITCODE; Remove-Item $sf -Force -ErrorAction SilentlyContinue
    if ($ec -ne 0) { FAIL "Could not create database/user" }
    OK "Database '$DbName' + user created"
}

# ── 6. import schema + seed admin ─────────────────────────────────────────────
INFO "Importing schema and seeding data..."
$installPhp = Join-Path $App 'public\install.php'
if (-not (Test-Path $installPhp)) { FAIL "public/install.php missing" }
& $PhpExe $installPhp 2>&1 | Add-Content $LogFile
if ($LASTEXITCODE -ne 0) { FAIL "Schema import failed" }
OK "Schema imported, admin account ready"

# ── 7. web + websocket services ───────────────────────────────────────────────
function Register-NssmService { param([string]$Name,[string]$Exe,[string]$Args,[string]$Desc,[string]$Out,[string]$Err)
    Remove-ServiceIfExists $Name
    & $NssmExe install $Name $Exe 2>&1 | Add-Content $LogFile
    & $NssmExe set $Name AppParameters  $Args | Out-Null
    & $NssmExe set $Name AppDirectory   $App  | Out-Null
    & $NssmExe set $Name DisplayName    "HotelMedia - $Name" | Out-Null
    & $NssmExe set $Name Description     $Desc | Out-Null
    & $NssmExe set $Name Start           SERVICE_AUTO_START | Out-Null
    & $NssmExe set $Name AppStdout       $Out | Out-Null
    & $NssmExe set $Name AppStderr       $Err | Out-Null
    & $NssmExe set $Name AppRotateFiles  1 | Out-Null
    & $NssmExe set $Name AppRotateBytes  5242880 | Out-Null
    & $NssmExe set $Name AppExit Default Restart | Out-Null
    Start-Service $Name
}
Register-NssmService -Name $SVC_WEB -Exe $PhpExe `
    -Args "-c `"$PhpIni`" -S 0.0.0.0:$Port -t `"$App\public`" `"$App\public\server-router.php`"" `
    -Desc "HotelMedia web panel / API" `
    -Out (Join-Path $LogDir 'web.log') -Err (Join-Path $LogDir 'web.err.log')
OK "Web service running on port $Port"

Register-NssmService -Name $SVC_WS -Exe $PhpExe `
    -Args "-c `"$PhpIni`" `"$App\websocket\server.php`"" `
    -Desc "HotelMedia realtime server" `
    -Out (Join-Path $LogDir 'ws.log') -Err (Join-Path $LogDir 'ws.err.log')
& $NssmExe set $SVC_WS AppEnvironmentExtra "WS_PORT=$WsPort" | Out-Null
Restart-Service $SVC_WS -ErrorAction SilentlyContinue
OK "Realtime service running"

# ── 8. firewall ───────────────────────────────────────────────────────────────
foreach ($p in @($Port,$WsPort)) {
    $rule="HotelMedia TCP $p"
    Get-NetFirewallRule -DisplayName $rule -ErrorAction SilentlyContinue | Remove-NetFirewallRule -ErrorAction SilentlyContinue
    New-NetFirewallRule -DisplayName $rule -Direction Inbound -Action Allow -Protocol TCP -LocalPort $p -Profile Any -ErrorAction SilentlyContinue | Out-Null
}
OK "Firewall opened ($Port, $WsPort)"

# ── record the chosen port so the installer/tray can read it back ─────────────
Set-Content (Join-Path $Runtime 'port.txt') "$Port" -Encoding ASCII

$base = if ($Port -eq 80) { 'http://localhost' } else { "http://localhost:$Port" }
Log "HotelMedia provisioning finished OK — panel at $base/admin" 'Green' '[OK] '

if (-not $Silent) { Start-Process "$base/welcome.php" -ErrorAction SilentlyContinue }
exit 0
