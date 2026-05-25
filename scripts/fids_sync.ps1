#Requires -Version 5.1
<#
.SYNOPSIS
    FIDS Sync — دریافت داده پرواز از fids.airport.ir و ذخیره در cache

.DESCRIPTION
    این اسکریپت از Windows host اجرا می‌شه (که به fids.airport.ir دسترسی داره)
    و cache JSON می‌نویسه که Docker/PHP از اون می‌خونه.

.PARAMETER Airports
    شناسه فرودگاه‌ها (مثلاً: 2 102 1)  پیش‌فرض: 2 102 1 103 114 401

.PARAMETER Loop
    اگر مقدار داده بشه، هر N ثانیه sync می‌شه (مثلاً: -Loop 60)

.PARAMETER List
    نمایش لیست همه فرودگاه‌ها

.EXAMPLE
    .\fids_sync.ps1
    .\fids_sync.ps1 -Airports 2,102,1
    .\fids_sync.ps1 -Loop 60
    .\fids_sync.ps1 -List
#>

[CmdletBinding()]
param(
    [int[]]$Airports = @(2, 102, 1, 103, 114, 401),
    [int]$Loop = 0,
    [switch]$List
)

$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# ── Config ──────────────────────────────────────────────────────────────────
$ProjectDir = Split-Path $PSScriptRoot -Parent
$CacheDir   = Join-Path $ProjectDir "storage\cache\fids"
$BaseUrl    = "https://fids.airport.ir"
$Timeout    = 20  # seconds

# ── Airport catalogue ────────────────────────────────────────────────────────
$AirportMap = @{
    2    = @{ name = 'مهرآباد (تهران)';   slug = 'مهرآباد' }
    102  = @{ name = 'مشهد';              slug = 'مشهد' }
    1    = @{ name = 'شیراز';             slug = 'شیراز' }
    103  = @{ name = 'تبریز';             slug = 'تبریز' }
    114  = @{ name = 'اصفهان';            slug = 'اصفهان' }
    401  = @{ name = 'اهواز';             slug = 'اهواز' }
    104  = @{ name = 'بوشهر';             slug = 'بوشهر' }
    201  = @{ name = 'کرمان';             slug = 'کرمان' }
    117  = @{ name = 'بندرعباس';          slug = 'بندرعباس' }
    106  = @{ name = 'ساری';              slug = 'ساري' }
    107  = @{ name = 'یزد';               slug = 'يزد' }
    111  = @{ name = 'کرمانشاه';          slug = 'کرمانشاه' }
    110  = @{ name = 'ارومیه';            slug = 'اروميه' }
    203  = @{ name = 'رشت';               slug = 'رشت' }
    109  = @{ name = 'زاهدان';            slug = 'زاهدان' }
    301  = @{ name = 'آبادان';            slug = 'آبادان' }
    202  = @{ name = 'گرگان';             slug = 'گرگان' }
    112  = @{ name = 'همدان';             slug = 'همدان' }
    113  = @{ name = 'اردبیل';            slug = 'اردبيل' }
    105  = @{ name = 'ایلام';             slug = 'ايلام' }
    204  = @{ name = 'بیرجند';            slug = 'بيرجند' }
    402  = @{ name = 'سنندج';             slug = 'سنندج' }
    108  = @{ name = 'شهرکرد';            slug = 'شهرکرد' }
    901  = @{ name = 'بجنورد';            slug = 'بجنورد' }
    601  = @{ name = 'لارستان';           slug = 'لارستان' }
    701  = @{ name = 'خرم‌آباد';          slug = 'خرم-آباد' }
    702  = @{ name = 'پارس‌آباد مغان';    slug = 'پارس-آبادمغان' }
    801  = @{ name = 'سمنان';             slug = 'سمنان' }
    1201 = @{ name = 'نوشهر';             slug = 'نوشهر' }
    802  = @{ name = 'شاهرود';            slug = 'شاهرود' }
    1001 = @{ name = 'یاسوج';             slug = 'ياسوج' }
    501  = @{ name = 'زنجان';             slug = 'زنجان' }
    1401 = @{ name = 'اراک';              slug = 'اراک' }
    1501 = @{ name = 'زابل';              slug = 'زابل' }
}

$Sections = [ordered]@{
    'ورودی داخلی'  = @('arrival',   'domestic')
    'خروجی داخلی'  = @('departure', 'domestic')
    'ورودی خارجی'  = @('arrival',   'international')
    'خروجی خارجی'  = @('departure', 'international')
}

# ── --List ──────────────────────────────────────────────────────────────────
if ($List) {
    Write-Host "`n✈  لیست فرودگاه‌های پشتیبانی‌شده:" -ForegroundColor Cyan
    Write-Host ("-" * 40)
    foreach ($id in ($AirportMap.Keys | Sort-Object)) {
        Write-Host ("  {0,-6}  {1}" -f $id, $AirportMap[$id].name)
    }
    Write-Host ""
    exit 0
}

# ── Create cache dir ─────────────────────────────────────────────────────────
if (-not (Test-Path $CacheDir)) {
    New-Item -ItemType Directory -Force $CacheDir | Out-Null
    Write-Host "📁 پوشه cache ساخته شد: $CacheDir" -ForegroundColor Green
}

# ── Helper: Persian digits to Latin ─────────────────────────────────────────
function ConvertPersianDigits([string]$s) {
    $map = @{'۰'='0';'۱'='1';'۲'='2';'۳'='3';'۴'='4';'۵'='5';'۶'='6';'۷'='7';'۸'='8';'۹'='9';
             '٠'='0';'١'='1';'٢'='2';'٣'='3';'٤'='4';'٥'='5';'٦'='6';'٧'='7';'٨'='8';'٩'='9'}
    foreach ($k in $map.Keys) { $s = $s.Replace($k, $map[$k]) }
    return $s
}

function NormaliseTime([string]$raw) {
    $raw = ConvertPersianDigits($raw.Trim() -replace '\s+', ' ')
    $hhmm = '00:00'
    if ($raw -match '(\d{1,2}):(\d{2})') {
        $hhmm = "{0:D2}:{1:D2}" -f [int]$Matches[1], [int]$Matches[2]
    }
    return (Get-Date -Format "yyyy-MM-dd") + " $hhmm`:00"
}

function CleanText([string]$s) {
    return ($s -replace '\s+', ' ').Trim()
}

function GetStatusEn([string]$fa) {
    if ($fa -match 'نشست|فرود')           { return 'arrived' }
    if ($fa -match 'پرواز کرد|برخاست')    { return 'departed' }
    if ($fa -match 'لغو|کنسل')            { return 'cancelled' }
    if ($fa -match 'تأخیر|تاخیر')         { return 'delayed' }
    if ($fa -match 'سوار')                 { return 'boarding' }
    if ($fa -match 'تغییر مسیر|انحراف')   { return 'diverted' }
    return 'scheduled'
}

function GetAirlineCode([string]$flightNum) {
    if ($flightNum -match '^([A-Za-z]{2}|[A-Za-z]\d|\d[A-Za-z])') {
        return $Matches[1].ToUpper()
    }
    $letters = [regex]::Replace($flightNum, '[^A-Za-z]', '')
    if ($letters.Length -ge 2) { return $letters.Substring(0, [Math]::Min(3, $letters.Length)).ToUpper() }
    return '??'
}

# ── HTML Fetch ───────────────────────────────────────────────────────────────
function FetchHtml([string]$url) {
    try {
        $headers = @{
            'User-Agent'      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'
            'Accept'          = 'text/html,application/xhtml+xml,*/*;q=0.8'
            'Accept-Language' = 'fa,fa-IR;q=0.9,en;q=0.7'
            'Cache-Control'   = 'no-cache'
        }
        $response = Invoke-WebRequest -Uri $url -Headers $headers `
            -TimeoutSec $Timeout -UseBasicParsing `
            -ErrorAction Stop
        if ($response.StatusCode -eq 200 -and $response.Content.Length -gt 2000) {
            return $response.Content
        }
    } catch {
        Write-Host "   ⚠  HTTP error: $_" -ForegroundColor Yellow
    }
    return $null
}

# ── HTML Parser ───────────────────────────────────────────────────────────────
function ParseFlightsFromHtml([string]$html, [int]$airportId) {
    $allFlights = @()

    foreach ($keyword in $Sections.Keys) {
        $dir   = $Sections[$keyword][0]   # arrival | departure
        $route = $Sections[$keyword][1]   # domestic | international
        $isArr = ($dir -eq 'arrival')

        # Find the section heading in HTML (look for keyword near a <table>)
        # Split HTML around the keyword occurrence
        $idx = $html.IndexOf($keyword)
        if ($idx -lt 0) { continue }

        # Extract ~20KB after the keyword to find the table
        $chunk = $html.Substring($idx, [Math]::Min(20000, $html.Length - $idx))

        # Find first <table ...> in chunk
        $tblStart = $chunk.IndexOf('<table', 0, [System.StringComparison]::OrdinalIgnoreCase)
        if ($tblStart -lt 0) { continue }
        $tblEnd = $chunk.IndexOf('</table>', $tblStart, [System.StringComparison]::OrdinalIgnoreCase)
        if ($tblEnd -lt 0) { continue }
        $tableHtml = $chunk.Substring($tblStart, $tblEnd - $tblStart + 8)

        # Parse rows
        $rowMatches = [regex]::Matches($tableHtml, '<tr[^>]*>(.*?)</tr>',
            [System.Text.RegularExpressions.RegexOptions]::Singleline -bor
            [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

        $sectionCount = 0
        foreach ($rowM in $rowMatches) {
            $rowHtml = $rowM.Groups[1].Value

            # Skip header rows (only <th>)
            if ($rowHtml -notmatch '<td') { continue }

            # Extract cell contents
            $cellMatches = [regex]::Matches($rowHtml, '<td[^>]*>(.*?)</td>',
                [System.Text.RegularExpressions.RegexOptions]::Singleline -bor
                [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

            if ($cellMatches.Count -lt 6) { continue }

            # Strip inner HTML tags, decode entities
            $cols = @()
            foreach ($cm in $cellMatches) {
                $txt = $cm.Groups[1].Value -replace '<[^>]+>', '' `
                                           -replace '&nbsp;', ' ' `
                                           -replace '&amp;',  '&' `
                                           -replace '&lt;',   '<' `
                                           -replace '&gt;',   '>'
                $cols += CleanText $txt
            }

            $flightNum = if ($cols.Count -gt 3) { CleanText $cols[3] } else { '' }
            if (-not $flightNum -or $flightNum -eq '-' -or $flightNum.Length -lt 2) { continue }

            $schedRaw  = if ($cols.Count -gt 1) { $cols[1] } else { '' }
            $airline   = if ($cols.Count -gt 2) { $cols[2] } else { '' }
            $place     = if ($cols.Count -gt 4) { $cols[4] } else { '' }
            $statusFa  = if ($cols.Count -gt 5) { $cols[5] } else { '' }
            $counter   = if ($cols.Count -gt 6) { $cols[6] } else { '' }
            $actualRaw = if ($cols.Count -gt 7) { $cols[7] } else { '' }
            $aircraft  = if ($cols.Count -gt 9) { $cols[9] } elseif ($cols.Count -gt 8) { $cols[8] } else { '' }
            $dateRaw   = if ($cols.Count -gt 10) { $cols[10] } else { '' }

            $flight = [ordered]@{
                flight_number  = $flightNum
                airline_name   = $airline
                airline_code   = GetAirlineCode $flightNum
                airline_logo   = $null
                type           = $dir
                route          = $route
                origin         = if ($isArr) { $place } else { $null }
                destination    = if (-not $isArr) { $place } else { $null }
                scheduled_time = NormaliseTime $schedRaw
                actual_time    = if ($actualRaw) { NormaliseTime $actualRaw } else { $null }
                status         = GetStatusEn $statusFa
                status_fa      = $statusFa
                gate           = $null
                terminal       = $null
                belt           = if ($isArr) { $counter } else { $null }
                counter        = if (-not $isArr) { $counter } else { $null }
                aircraft_type  = $aircraft
                delay_minutes  = 0
            }
            $allFlights += $flight
            $sectionCount++
        }

        $sym  = if ($dir -eq 'arrival') { '↓' } else { '↑' }
        $rName = if ($route -eq 'domestic') { 'داخلی' } else { 'خارجی' }
        Write-Host "      $sym $rName`: $sectionCount پرواز" -ForegroundColor Gray
    }

    # Deduplicate
    $seen = @{}
    $unique = @()
    foreach ($f in $allFlights) {
        $key = "$($f.flight_number)|$($f.type)|$($f.scheduled_time)"
        if (-not $seen.ContainsKey($key)) {
            $seen[$key] = $true
            $unique += $f
        }
    }
    return $unique
}

# ── Sync one airport ─────────────────────────────────────────────────────────
function SyncAirport([int]$airportId) {
    $info = $AirportMap[$airportId]
    if (-not $info) {
        Write-Host "   ❌  فرودگاه ID=$airportId وجود ندارد" -ForegroundColor Red
        return $null
    }

    $url = "$BaseUrl/$airportId/اطلاعات-پرواز-فرودگاه-$($info.slug)"
    Write-Host "   🌐  GET $url" -ForegroundColor DarkCyan

    $html = FetchHtml $url
    if (-not $html) {
        Write-Host "   ❌  دریافت ناموفق یا timeout" -ForegroundColor Red
        return $null
    }

    $sizeKb = [Math]::Round($html.Length / 1024, 1)
    Write-Host "   📄  $sizeKb KB دریافت شد" -ForegroundColor DarkGray

    $flights = ParseFlightsFromHtml $html $airportId

    # Write cache
    $cacheFile = Join-Path $CacheDir "airport_$airportId.json"
    $json = $flights | ConvertTo-Json -Depth 5 -Compress:$false
    [System.IO.File]::WriteAllText($cacheFile, $json, [System.Text.Encoding]::UTF8)

    return $flights
}

# ── Main loop ────────────────────────────────────────────────────────────────
do {
    Write-Host ""
    Write-Host ("=" * 50) -ForegroundColor DarkBlue
    Write-Host "🕐  $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  —  شروع sync" -ForegroundColor Cyan
    Write-Host ("-" * 50) -ForegroundColor DarkBlue

    $totalFlights = 0

    foreach ($id in $Airports) {
        if (-not $AirportMap.ContainsKey($id)) {
            Write-Host "⚠  فرودگاه ID=$id پیدا نشد — رد شد" -ForegroundColor Yellow
            continue
        }
        $name = $AirportMap[$id].name
        Write-Host ""
        Write-Host "▶  فرودگاه $name (ID: $id) ..." -ForegroundColor Yellow

        $flights = SyncAirport $id
        if ($null -eq $flights) {
            Write-Host "   ❌  sync ناموفق" -ForegroundColor Red
        } else {
            $count = $flights.Count
            $totalFlights += $count
            $arr = ($flights | Where-Object { $_.type -eq 'arrival' }).Count
            $dep = ($flights | Where-Object { $_.type -eq 'departure' }).Count
            Write-Host "   ✅  $count پرواز ذخیره شد  (↓ورودی: $arr  ↑خروجی: $dep)" -ForegroundColor Green
        }
    }

    Write-Host ""
    Write-Host ("-" * 50) -ForegroundColor DarkBlue
    Write-Host "✔  مجموع: $totalFlights پرواز  —  $(Get-Date -Format 'HH:mm:ss')" -ForegroundColor Green

    if ($Loop -gt 0) {
        Write-Host "💤  بعدی در $Loop ثانیه ..." -ForegroundColor DarkGray
        Start-Sleep -Seconds $Loop
    }

} while ($Loop -gt 0)

Write-Host "`nانجام شد.`n" -ForegroundColor Green
