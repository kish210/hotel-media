#Requires -Version 5.1
<#
.SYNOPSIS
    HotelMedia — start / stop / restart / status helper.
.EXAMPLE
    .\Manage-HotelMedia.ps1 restart
#>
param([ValidateSet('start','stop','restart','status')] [string]$Action = 'status')

$ErrorActionPreference = 'SilentlyContinue'
$services = 'HotelMedia-MySQL','HotelMedia-Web','HotelMedia-WS'

switch ($Action) {
    'start'   { foreach ($s in $services)        { Start-Service $s } }
    'stop'    { foreach ($s in ($services[2..0])) { Stop-Service  $s -Force } }
    'restart' {
        foreach ($s in ($services[2..0])) { Stop-Service $s -Force }
        Start-Sleep -Seconds 2
        foreach ($s in $services)         { Start-Service $s }
    }
}

Write-Host ""
Write-Host "  HotelMedia services" -ForegroundColor Cyan
Write-Host "  --------------------------------" -ForegroundColor DarkGray
foreach ($s in $services) {
    $svc = Get-Service -Name $s -ErrorAction SilentlyContinue
    if (-not $svc) { Write-Host ("  {0,-20} (not installed)" -f $s) -ForegroundColor DarkGray; continue }
    $color = if ($svc.Status -eq 'Running') { 'Green' } else { 'Red' }
    Write-Host ("  {0,-20} {1}" -f $svc.Name, $svc.Status) -ForegroundColor $color
}
Write-Host ""
