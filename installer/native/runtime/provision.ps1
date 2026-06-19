#Requires -Version 5.1
<#
.SYNOPSIS
    SignageCMS — Native (no-Docker) provisioning.
    سماع رایانه کیش | kishwifi.com

.DESCRIPTION
    Runs once, with admin rights, right after the Inno Setup installer copies
    the files. Turns a freshly-copied folder into a fully working server:

        1. Generates .env with random secrets (DB password, APP_KEY, JWT).
        2. Initializes a private MariaDB data directory.
        3. Registers + starts MariaDB as a Windows service.
        4. Creates the database, the app DB user, and runs the schema/seeds
           via the app's own installer (public/install.php).
        5. Registers the web server (php -S) and the WebSocket server as
           Windows services (via NSSM) so they auto-start on boot.
        6. Opens the Windows Firewall for the web + WebSocket ports.

    Everything lives under the install folder — nothing is installed system-wide
    except three auto-start services. Designed to be safe to re-run.

.NOTES
    Invoked by SignageCMS-Native.iss. Not meant to be run by hand, but it can be:
        powershell -ExecutionPolicy Bypass -File provision.ps1 -InstallDir C:\SignageCMS
#>
param(
    [Parameter(Mandatory = $true)]
    [string]$InstallDir,
    [int]   $Port   = 80,
    [int]   $WsPort = 8080,
    [switch]$Silent
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

# ── Service names (single source of truth, shared with uninstall.ps1) ─────────
$SVC_DB  = 'SignageCMS-MySQL'
$SVC_WEB = 'SignageCMS-Web'
$SVC_WS  = 'SignageCMS-WS'

# ── Paths ─────────────────────────────────────────────────────────────────────
$App      = $InstallDir.TrimEnd('\')
$Runtime  = Join-Path $App 'runtime'
$PhpDir   = Join-Path $Runtime 'php'
$PhpExe   = Join-Path $PhpDir 'php.exe'
$NssmExe  = Join-Path $Runtime 'nssm\nssm.exe'
$MariaDir = Join-Path $Runtime 'mariadb'
$DataDir  = Join-Path $App 'data\mysql'
$LogDir   = Join-Path $App 'storage\logs'
$LogFile  = Join-Path $LogDir 'install.log'

# ── Logging ─────────────────────────────────────────────────────────────────
if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }
function Log {
    param([string]$Msg, [string]$Color = 'Gray', [string]$Tag = '   ')
    $line = "{0}  {1}{2}" -f (Get-Date -Format 'HH:mm:ss'), $Tag, $Msg
    Add-Content -Path $LogFile -Value $line -Encoding UTF8
    Write-Host ("  {0}{1}" -f $Tag, $Msg) -ForegroundColor $Color
}
function OK   ($m) { Log $m 'Green'  '[OK] ' }
function INFO ($m) { Log $m 'Cyan'   '[..] ' }
function WARN ($m) { Log $m 'Yellow' '[!]  ' }
function FAIL ($m) { Log $m 'Red'    '[X]  '; throw $m }

Write-Host ""
Write-Host "  ====================================================" -ForegroundColor Cyan
Write-Host "    SignageCMS  -  Native setup (no Docker)" -ForegroundColor Cyan
Write-Host "    سماع رایانه کیش | kishwifi.com" -ForegroundColor Cyan
Write-Host "  ====================================================" -ForegroundColor Cyan
Write-Host ""
Log "Install dir : $App"
Log "Web port    : $Port    WebSocket port: $WsPort"

# ── 0. Locate the MariaDB bin folder (zip may be nested) ──────────────────────
function Find-Tool {
    param([string]$Root, [string[]]$Names)
    foreach ($n in $Names) {
        $hit = Get-ChildItem -Path $Root -Filter $n -Recurse -File -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($hit) { return $hit.FullName }
    }
    return $null
}

if (-not (Test-Path $PhpExe))  { FAIL "php.exe not found at $PhpExe (bundle is incomplete)" }
if (-not (Test-Path $NssmExe)) { FAIL "nssm.exe not found at $NssmExe (bundle is incomplete)" }

$Mysqld     = Find-Tool $MariaDir @('mariadbd.exe','mysqld.exe')
$MysqlCli   = Find-Tool $MariaDir @('mariadb.exe','mysql.exe')
$MysqlAdmin = Find-Tool $MariaDir @('mariadb-admin.exe','mysqladmin.exe')
$InstallDb  = Find-Tool $MariaDir @('mariadb-install-db.exe','mysql_install_db.exe')
if (-not $Mysqld)   { FAIL "MariaDB server (mariadbd/mysqld) not found under $MariaDir" }
if (-not $MysqlCli) { FAIL "MariaDB client not found under $MariaDir" }
$MariaBin = Split-Path $Mysqld -Parent
$MariaBase = Split-Path $MariaBin -Parent
OK "Found MariaDB at $MariaBin"

# ── helpers ──────────────────────────────────────────────────────────────────
function New-Secret { param([int]$Len = 24)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
    -join (1..$Len | ForEach-Object { $chars[(Get-Random -Maximum $chars.Length)] })
}
function Remove-ServiceIfExists {
    param([string]$Name)
    $svc = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if ($svc) {
        INFO "Removing existing service: $Name"
        if ($svc.Status -ne 'Stopped') { Stop-Service -Name $Name -Force -ErrorAction SilentlyContinue }
        & $NssmExe stop   $Name 2>$null | Out-Null
        & $NssmExe remove $Name confirm 2>$null | Out-Null
        & sc.exe delete   $Name 2>$null | Out-Null
        Start-Sleep -Seconds 1
    }
}

# ══════════════════════════════════════════════════════════════════════════════
# 1. Generate .env (idempotent — keep an existing one)
# ══════════════════════════════════════════════════════════════════════════════
$EnvFile = Join-Path $App '.env'
$DbName  = 'signage_cms'
$DbUser  = 'signage_user'

if (Test-Path $EnvFile) {
    INFO ".env already present — reusing existing credentials"
    $envText = Get-Content $EnvFile -Raw
    $DbPass  = ([regex]::Match($envText, '(?m)^DB_PASSWORD=(.*)$')).Groups[1].Value.Trim().Trim('"')
    if (-not $DbPass) { $DbPass = New-Secret 24 }
} else {
    $tpl = Join-Path $App '.env.example'
    if (-not (Test-Path $tpl)) { FAIL ".env.example missing — cannot build configuration" }

    $DbPass   = New-Secret 24
    $appKey   = 'base64:' + [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
    $jwt      = New-Secret 48
    $appUrl   = if ($Port -eq 80) { 'http://localhost' } else { "http://localhost:$Port" }

    $env = Get-Content $tpl -Raw
    $repl = [ordered]@{
        'APP_ENV'      = 'production'
        'APP_DEBUG'    = 'false'
        'APP_URL'      = $appUrl
        'APP_KEY'      = $appKey
        'DB_HOST'      = '127.0.0.1'
        'DB_PORT'      = '3306'
        'DB_DATABASE'  = $DbName
        'DB_USERNAME'  = $DbUser
        'DB_PASSWORD'  = $DbPass
        'JWT_SECRET'   = $jwt
        'WS_PORT'      = "$WsPort"
    }
    foreach ($k in $repl.Keys) {
        if ($env -match "(?m)^$k=") { $env = [regex]::Replace($env, "(?m)^$k=.*$", "$k=$($repl[$k])") }
        else                        { $env += "`r`n$k=$($repl[$k])" }
    }
    Set-Content -Path $EnvFile -Value $env -Encoding UTF8 -NoNewline
    OK ".env generated with fresh secrets"
}

# ══════════════════════════════════════════════════════════════════════════════
# 2. Initialize the MariaDB data directory
# ══════════════════════════════════════════════════════════════════════════════
$MyIni = Join-Path $Runtime 'my.ini'
$tpl   = Join-Path $PSScriptRoot 'my.ini.template'
if (-not (Test-Path $tpl)) { $tpl = Join-Path $Runtime 'scripts\my.ini.template' }
(Get-Content $tpl -Raw).
    Replace('{{BASEDIR}}', $MariaBase.Replace('\','/')).
    Replace('{{DATADIR}}', $DataDir.Replace('\','/')).
    Replace('{{PORT}}',    '3306') | Set-Content -Path $MyIni -Encoding ASCII

$dbInitialized = Test-Path (Join-Path $DataDir 'mysql')
if (-not $dbInitialized) {
    INFO "Initializing database storage (one-time)..."
    if (Test-Path $DataDir) { Remove-Item $DataDir -Recurse -Force }
    New-Item -ItemType Directory -Path $DataDir -Force | Out-Null

    if ($InstallDb) {
        & $InstallDb "--datadir=$DataDir" 2>&1 | Add-Content $LogFile
    } else {
        # MariaDB >= 10.4 can self-initialize on first start.
        & $Mysqld "--defaults-file=$MyIni" '--initialize-insecure' 2>&1 | Add-Content $LogFile
    }
    if (-not (Test-Path (Join-Path $DataDir 'mysql'))) {
        FAIL "Database initialization failed — see $LogFile"
    }
    OK "Database storage initialized"
} else {
    INFO "Database storage already initialized — keeping data"
}

# ══════════════════════════════════════════════════════════════════════════════
# 3. Register + start the MariaDB service
# ══════════════════════════════════════════════════════════════════════════════
Remove-ServiceIfExists $SVC_DB
INFO "Registering service: $SVC_DB"
& $Mysqld "--install" $SVC_DB "--defaults-file=$MyIni" 2>&1 | Add-Content $LogFile
Set-Service -Name $SVC_DB -StartupType Automatic
Start-Service -Name $SVC_DB
OK "MariaDB service started"

# Wait until it accepts connections.
INFO "Waiting for the database to come online..."
$ready = $false
foreach ($i in 1..30) {
    if ($MysqlAdmin) {
        & $MysqlAdmin "--host=127.0.0.1" "--port=3306" "--user=root" 'ping' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    } else {
        $t = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
        if ($t.TcpTestSucceeded) { $ready = $true; break }
    }
    Start-Sleep -Seconds 1
}
if (-not $ready) { FAIL "Database did not become ready in time — see $LogFile" }
OK "Database is online"

# ══════════════════════════════════════════════════════════════════════════════
# 4. Create database + app user (only on a fresh data dir)
# ══════════════════════════════════════════════════════════════════════════════
if (-not $dbInitialized) {
    INFO "Creating database and application user..."
    $escPass = $DbPass.Replace("'", "''")
    $sql = @"
CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$escPass';
CREATE USER IF NOT EXISTS '$DbUser'@'127.0.0.1' IDENTIFIED BY '$escPass';
GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';
GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
    $sqlFile = Join-Path $env:TEMP "signage_init_$([guid]::NewGuid().ToString('N')).sql"
    Set-Content -Path $sqlFile -Value $sql -Encoding ASCII
    & $MysqlCli "--host=127.0.0.1" "--port=3306" "--user=root" "--execute=source $sqlFile" 2>&1 | Add-Content $LogFile
    $dbExit = $LASTEXITCODE
    Remove-Item $sqlFile -Force -ErrorAction SilentlyContinue
    if ($dbExit -ne 0) { FAIL "Could not create database/user — see $LogFile" }
    OK "Database '$DbName' and user '$DbUser' created"
}

# ══════════════════════════════════════════════════════════════════════════════
# 5. Run the application installer (schema + seeds + admin user)
# ══════════════════════════════════════════════════════════════════════════════
INFO "Importing schema and seeding data (this can take a minute)..."
$installPhp = Join-Path $App 'public\install.php'
if (-not (Test-Path $installPhp)) { FAIL "public/install.php missing" }
& $PhpExe $installPhp 2>&1 | Tee-Object -FilePath $LogFile -Append | Out-Host
if ($LASTEXITCODE -ne 0) { FAIL "Application installer failed — see $LogFile" }
OK "Schema imported and admin account created"

# ══════════════════════════════════════════════════════════════════════════════
# 6. Register web + websocket services
# ══════════════════════════════════════════════════════════════════════════════
$PhpIni = Join-Path $PhpDir 'php.ini'

function Register-NssmService {
    param([string]$Name, [string]$Exe, [string]$Args, [string]$Desc, [string]$Out, [string]$Err)
    Remove-ServiceIfExists $Name
    INFO "Registering service: $Name"
    & $NssmExe install $Name $Exe 2>&1 | Add-Content $LogFile
    & $NssmExe set $Name AppParameters       $Args            | Out-Null
    & $NssmExe set $Name AppDirectory         $App             | Out-Null
    & $NssmExe set $Name DisplayName          "SignageCMS - $Name" | Out-Null
    & $NssmExe set $Name Description           $Desc            | Out-Null
    & $NssmExe set $Name Start                SERVICE_AUTO_START | Out-Null
    & $NssmExe set $Name AppStdout            $Out             | Out-Null
    & $NssmExe set $Name AppStderr            $Err             | Out-Null
    & $NssmExe set $Name AppRotateFiles       1                | Out-Null
    & $NssmExe set $Name AppRotateBytes       5242880          | Out-Null
    & $NssmExe set $Name AppExit Default      Restart          | Out-Null
    Start-Service -Name $Name
    OK "$Name started"
}

# Web server (PHP built-in server with the front-controller router).
Register-NssmService -Name $SVC_WEB -Exe $PhpExe `
    -Args "-c `"$PhpIni`" -S 0.0.0.0:$Port -t `"$App\public`" `"$App\public\server-router.php`"" `
    -Desc "SignageCMS web dashboard / API (PHP built-in server)" `
    -Out (Join-Path $LogDir 'web.log') -Err (Join-Path $LogDir 'web.err.log')

# WebSocket server (pure-PHP streams).
Register-NssmService -Name $SVC_WS -Exe $PhpExe `
    -Args "-c `"$PhpIni`" `"$App\websocket\server.php`"" `
    -Desc "SignageCMS realtime WebSocket server" `
    -Out (Join-Path $LogDir 'ws.log') -Err (Join-Path $LogDir 'ws.err.log')

# Make the WS service see the right port.
& $NssmExe set $SVC_WS AppEnvironmentExtra "WS_PORT=$WsPort" | Out-Null
Restart-Service -Name $SVC_WS -ErrorAction SilentlyContinue

# ══════════════════════════════════════════════════════════════════════════════
# 7. Firewall
# ══════════════════════════════════════════════════════════════════════════════
INFO "Opening Windows Firewall ports..."
foreach ($p in @($Port, $WsPort)) {
    $rule = "SignageCMS TCP $p"
    Get-NetFirewallRule -DisplayName $rule -ErrorAction SilentlyContinue | Remove-NetFirewallRule -ErrorAction SilentlyContinue
    New-NetFirewallRule -DisplayName $rule -Direction Inbound -Action Allow `
        -Protocol TCP -LocalPort $p -Profile Any -ErrorAction SilentlyContinue | Out-Null
}
OK "Firewall rules added (ports $Port, $WsPort)"

# ══════════════════════════════════════════════════════════════════════════════
# Done
# ══════════════════════════════════════════════════════════════════════════════
$ip = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notlike '169.*' -and $_.IPAddress -ne '127.0.0.1' } |
        Select-Object -First 1).IPAddress
$base = if ($Port -eq 80) { 'http://localhost' } else { "http://localhost:$Port" }

Write-Host ""
Write-Host "  ====================================================" -ForegroundColor Green
Write-Host "    SignageCMS is installed and running!" -ForegroundColor Green
Write-Host "  ====================================================" -ForegroundColor Green
Write-Host ""
Write-Host "    Dashboard : $base/admin" -ForegroundColor White
if ($ip) { Write-Host "    On network: http://$ip$(if($Port -ne 80){":$Port"})/admin" -ForegroundColor White }
Write-Host ""
Write-Host "    Login     : admin@signagecms.com" -ForegroundColor White
Write-Host "    Password  : Admin@123456   (change it after first login)" -ForegroundColor Yellow
Write-Host ""
Write-Host "    Services  : $SVC_DB, $SVC_WEB, $SVC_WS (auto-start on boot)" -ForegroundColor Gray
Write-Host "    Log file  : $LogFile" -ForegroundColor Gray
Write-Host ""

if (-not $Silent) {
    Start-Process "$base/admin" -ErrorAction SilentlyContinue
}
exit 0
