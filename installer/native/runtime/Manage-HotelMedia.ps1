#Requires -Version 5.1
<#
.SYNOPSIS
    Hotel Media — start / stop / restart / status helper for the native install.
.EXAMPLE
    .\Manage-HotelMedia.ps1 status
    .\Manage-HotelMedia.ps1 restart
#>
param(
    [ValidateSet('start','stop','restart','status')]
    [string]$Action = 'status'
)

$ErrorActionPreference = 'SilentlyContinue'
$services = 'Hotel Media-MySQL','HotelMedia-Web','HotelMedia-WS'

switch ($Action) {
    'start'   { foreach ($s in $services)               { Start-Service   $s } }
    'stop'    { foreach ($s in ($services[2..0]))        { Stop-Service    $s -Force } }
    'restart' {
        foreach ($s in ($services[2..0])) { Stop-Service  $s -Force }
        Start-Sleep -Seconds 2
        foreach ($s in $services)         { Start-Service $s }
    }
}

Write-Host ""
Write-Host "  Hotel Media services" -ForegroundColor Cyan
Write-Host "  --------------------------------" -ForegroundColor DarkGray
foreach ($s in $services) {
    $svc = Get-Service -Name $s -ErrorAction SilentlyContinue
    if (-not $svc) { Write-Host ("  {0,-22} (not installed)" -f $s) -ForegroundColor DarkGray; continue }
    $color = if ($svc.Status -eq 'Running') { 'Green' } else { 'Red' }
    Write-Host ("  {0,-22} {1}" -f $svc.Name, $svc.Status) -ForegroundColor $color
}
Write-Host ""
