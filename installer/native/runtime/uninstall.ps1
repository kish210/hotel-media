#Requires -Version 5.1
<#
.SYNOPSIS
    Hotel Media — Native uninstall / teardown.
.DESCRIPTION
    Stops and removes the three Windows services and the firewall rules created
    by provision.ps1. Called by the Inno Setup uninstaller. The install folder
    (including the database in data\mysql) is removed by Inno Setup itself,
    unless the user chose to keep it.
#>
param(
    [string]$InstallDir,
    [switch]$KeepData
)

$ErrorActionPreference = 'SilentlyContinue'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

$SVC_DB  = 'HotelMedia-MySQL'
$SVC_WEB = 'HotelMedia-Web'
$SVC_WS  = 'HotelMedia-WS'

$App    = if ($InstallDir) { $InstallDir.TrimEnd('\') } else { $PSScriptRoot }
$Nssm   = Join-Path $App 'runtime\nssm\nssm.exe'

function Info($m) { Write-Host "  [..] $m" -ForegroundColor Cyan }
function Done($m) { Write-Host "  [OK] $m" -ForegroundColor Green }

Write-Host ""
Write-Host "  Removing Hotel Media services..." -ForegroundColor Yellow

# نام‌های قبل از تغییر نام پروژه هم پاک می‌شوند، وگرنه ارتقاء یک سرویس
# یتیم روی پورت باقی می‌گذارد و سرویس جدید بالا نمی‌آید.
$LEGACY = @('SignageCMS-Web', 'SignageCMS-WS', 'SignageCMS-MySQL')

foreach ($svc in @($SVC_WEB, $SVC_WS, $SVC_DB) + $LEGACY) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s) {
        Info "Stopping $svc"
        Stop-Service -Name $svc -Force -ErrorAction SilentlyContinue
        if (Test-Path $Nssm) {
            & $Nssm stop   $svc 2>$null | Out-Null
            & $Nssm remove $svc confirm 2>$null | Out-Null
        }
        & sc.exe delete $svc 2>$null | Out-Null
        Done "$svc removed"
    }
}

# Firewall rules.
foreach ($rule in (Get-NetFirewallRule -DisplayName 'Hotel Media TCP *' -ErrorAction SilentlyContinue)) {
    $rule | Remove-NetFirewallRule -ErrorAction SilentlyContinue
}
Done "Firewall rules removed"

if ($KeepData) {
    Write-Host "  Keeping database in $App\data" -ForegroundColor Gray
}

Write-Host ""
Write-Host "  Hotel Media services removed." -ForegroundColor Green
Write-Host ""
exit 0
