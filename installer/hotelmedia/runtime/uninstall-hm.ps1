#Requires -Version 5.1
<#
.SYNOPSIS
    HotelMedia — service/firewall teardown (called by the uninstaller).
.DESCRIPTION
    Stops + removes the three services, the firewall rules, and the tray app.
    File deletion (including data\mysql) is handled by the Inno uninstaller,
    which asks the user whether to keep the hotel data.
#>
param([string]$InstallDir)

$ErrorActionPreference = 'SilentlyContinue'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch {}

$services = 'HotelMedia-Web','HotelMedia-WS','HotelMedia-MySQL'
$App  = if ($InstallDir) { $InstallDir.TrimEnd('\') } else { $PSScriptRoot }
$Nssm = Join-Path $App 'runtime\nssm\nssm.exe'

# Stop the tray app if running.
Get-Process -Name 'HotelMediaTray' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue

foreach ($svc in $services) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s) {
        Stop-Service $svc -Force -ErrorAction SilentlyContinue
        if (Test-Path $Nssm) { & $Nssm stop $svc 2>$null | Out-Null; & $Nssm remove $svc confirm 2>$null | Out-Null }
        & sc.exe delete $svc 2>$null | Out-Null
    }
}

Get-NetFirewallRule -DisplayName 'HotelMedia TCP *' -ErrorAction SilentlyContinue | Remove-NetFirewallRule -ErrorAction SilentlyContinue

exit 0
