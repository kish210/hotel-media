<?php
/**
 * fids_sync.php — FIDS Live Data Sync Job
 * =========================================
 * از یک ماشین که به fids.airport.ir دسترسی دارد اجرا کنید.
 * داده‌های پرواز را دریافت و در cache می‌نویسد.
 * Docker/سرور بدون دسترسی مستقیم از همین cache می‌خواند.
 *
 * Usage:
 *   php scripts/fids_sync.php                  ← همه فرودگاه‌ها
 *   php scripts/fids_sync.php 2 102 1           ← فقط مهرآباد، مشهد، شیراز
 *   php scripts/fids_sync.php --list            ← نمایش همه فرودگاه‌ها
 *   php scripts/fids_sync.php --loop 60         ← هر ۶۰ ثانیه تکرار
 *
 * Windows Task Scheduler (هر دقیقه):
 *   Program: php
 *   Arguments: D:\duc\signage-cms\scripts\fids_sync.php 2 102 1
 *
 * Output cache: storage/cache/fids/airport_{id}.json
 */

declare(strict_types=1);

// ── Config ─────────────────────────────────────────────────────────────────

define('SCRIPT_DIR',  dirname(__DIR__));
define('CACHE_DIR',   SCRIPT_DIR . '/storage/cache/fids');
define('TIMEOUT',     15);   // HTTP timeout in seconds
define('BASE_URL',    'https://fids.airport.ir');

// فرودگاه‌هایی که پیش‌فرض fetch می‌شن (اگر آرگومان داده نشد)
define('DEFAULT_AIRPORTS', [2, 102, 1, 103, 114, 401]);

// ── Airport catalogue ───────────────────────────────────────────────────────

const AIRPORTS = [
    2    => ['name' => 'مهرآباد (تهران)',        'slug' => 'مهرآباد'],
    102  => ['name' => 'مشهد',                   'slug' => 'مشهد'],
    1    => ['name' => 'شیراز',                  'slug' => 'شیراز'],
    103  => ['name' => 'تبریز',                  'slug' => 'تبریز'],
    114  => ['name' => 'اصفهان',                 'slug' => 'اصفهان'],
    401  => ['name' => 'اهواز',                  'slug' => 'اهواز'],
    104  => ['name' => 'بوشهر',                  'slug' => 'بوشهر'],
    201  => ['name' => 'کرمان',                  'slug' => 'کرمان'],
    117  => ['name' => 'بندرعباس',               'slug' => 'بندرعباس'],
    106  => ['name' => 'ساری',                   'slug' => 'ساري'],
    107  => ['name' => 'یزد',                    'slug' => 'يزد'],
    111  => ['name' => 'کرمانشاه',               'slug' => 'کرمانشاه'],
    110  => ['name' => 'ارومیه',                 'slug' => 'اروميه'],
    203  => ['name' => 'رشت',                    'slug' => 'رشت'],
    109  => ['name' => 'زاهدان',                 'slug' => 'زاهدان'],
    301  => ['name' => 'آبادان',                 'slug' => 'آبادان'],
    202  => ['name' => 'گرگان',                  'slug' => 'گرگان'],
    112  => ['name' => 'همدان',                  'slug' => 'همدان'],
    113  => ['name' => 'اردبیل',                 'slug' => 'اردبيل'],
    105  => ['name' => 'ایلام',                  'slug' => 'ايلام'],
    204  => ['name' => 'بیرجند',                 'slug' => 'بيرجند'],
    402  => ['name' => 'سنندج',                  'slug' => 'سنندج'],
    108  => ['name' => 'شهرکرد',                 'slug' => 'شهرکرد'],
    901  => ['name' => 'بجنورد',                 'slug' => 'بجنورد'],
    601  => ['name' => 'لارستان',                'slug' => 'لارستان'],
    701  => ['name' => 'خرم‌آباد',               'slug' => 'خرم-آباد'],
    702  => ['name' => 'پارس‌آباد مغان',         'slug' => 'پارس-آبادمغان'],
    801  => ['name' => 'سمنان',                  'slug' => 'سمنان'],
    1201 => ['name' => 'نوشهر',                  'slug' => 'نوشهر'],
    802  => ['name' => 'شاهرود',                 'slug' => 'شاهرود'],
    1001 => ['name' => 'یاسوج',                  'slug' => 'ياسوج'],
    501  => ['name' => 'زنجان',                  'slug' => 'زنجان'],
    1401 => ['name' => 'اراک',                   'slug' => 'اراک'],
    1501 => ['name' => 'زابل',                   'slug' => 'زابل'],
];

const SECTIONS = [
    'ورودی داخلی'  => ['arrival',   'domestic'],
    'خروجی داخلی'  => ['departure', 'domestic'],
    'ورودی خارجی'  => ['arrival',   'international'],
    'خروجی خارجی'  => ['departure', 'international'],
];

// ── Entry point ─────────────────────────────────────────────────────────────

$args = array_slice($argv, 1);

// --list
if (in_array('--list', $args, true)) {
    echo "\n✈  لیست فرودگاه‌های پشتیبانی‌شده:\n";
    echo str_repeat('-', 40) . "\n";
    foreach (AIRPORTS as $id => $info) {
        printf("  %-6d  %s\n", $id, $info['name']);
    }
    echo "\n";
    exit(0);
}

// --loop N
$loopInterval = 0;
if (($li = array_search('--loop', $args, true)) !== false) {
    $loopInterval = (int)($args[$li + 1] ?? 60);
    unset($args[$li], $args[$li + 1]);
    $args = array_values($args);
}

// Airport IDs from remaining args; fall back to defaults
$requestedIds = [];
foreach ($args as $a) {
    $id = (int)$a;
    if ($id > 0 && isset(AIRPORTS[$id])) {
        $requestedIds[] = $id;
    } elseif ($id > 0) {
        echo "⚠  فرودگاه با ID=$id وجود ندارد — نادیده گرفته شد\n";
    }
}
if (empty($requestedIds)) {
    $requestedIds = DEFAULT_AIRPORTS;
}

// ── Make sure cache dir exists ─────────────────────────────────────────────

if (!is_dir(CACHE_DIR)) {
    if (!mkdir(CACHE_DIR, 0755, true)) {
        die("❌  نمی‌توان پوشه کش ایجاد کرد: " . CACHE_DIR . "\n");
    }
}

// ── Run (once or loop) ──────────────────────────────────────────────────────

do {
    echo "\n" . str_repeat('═', 50) . "\n";
    echo "🕐  " . date('Y-m-d H:i:s') . "  —  شروع sync\n";
    echo str_repeat('─', 50) . "\n";

    $totalFlights = 0;

    foreach ($requestedIds as $airportId) {
        $name = AIRPORTS[$airportId]['name'];
        echo "\n▶  فرودگاه $name (ID: $airportId) ...\n";

        $flights = syncAirport($airportId);

        if ($flights === null) {
            echo "   ❌  دریافت ناموفق\n";
        } else {
            $count = count($flights);
            $totalFlights += $count;
            echo "   ✅  $count پرواز دریافت و ذخیره شد\n";

            // نمایش خلاصه
            $arr = count(array_filter($flights, fn($f) => $f['type'] === 'arrival'));
            $dep = count(array_filter($flights, fn($f) => $f['type'] === 'departure'));
            echo "      ↓ ورودی: $arr   ↑ خروجی: $dep\n";
        }
    }

    echo "\n" . str_repeat('─', 50) . "\n";
    echo "✔  مجموع: $totalFlights پرواز — " . date('H:i:s') . "\n";

    if ($loopInterval > 0) {
        echo "💤  بعدی در {$loopInterval} ثانیه ...\n";
        sleep($loopInterval);
    }

} while ($loopInterval > 0);

echo "\nانجام شد.\n";
exit(0);

// ── Functions ───────────────────────────────────────────────────────────────

/**
 * Scrape fids.airport.ir for a single airport, write cache, return flights array.
 * Returns null on failure.
 */
function syncAirport(int $airportId): ?array
{
    $info = AIRPORTS[$airportId] ?? null;
    if (!$info) return null;

    $url  = BASE_URL . '/' . $airportId . '/اطلاعات-پرواز-فرودگاه-' . $info['slug'];

    echo "   🌐  GET $url\n";

    $html = httpGet($url);
    if (!$html) {
        echo "   ⚠  پاسخ خالی یا timeout\n";
        return null;
    }

    echo "   📄  " . number_format(strlen($html)) . " bytes دریافت شد\n";

    $flights = parseHtml($html);

    if (empty($flights)) {
        echo "   ⚠  هیچ پروازی از HTML استخراج نشد — صفحه ساختار متفاوتی دارد؟\n";
        // Still write empty array so cache TTL resets
    }

    $cacheFile = CACHE_DIR . "/airport_{$airportId}.json";
    $json      = json_encode($flights, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($cacheFile, $json, LOCK_EX);

    return $flights;
}

// ── HTTP ─────────────────────────────────────────────────────────────────────

function httpGet(string $url): ?string
{
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: fa,fa-IR;q=0.9,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Cache-Control: no-cache',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_VERBOSE        => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) echo "   cURL error: $err\n";
        if ($code !== 200) echo "   HTTP $code\n";

        if ($code === 200 && is_string($body) && strlen($body) > 2000) {
            return $body;
        }
        return null;
    }

    // Fallback: file_get_contents
    $ctx  = stream_context_create(['http' => [
        'timeout'       => TIMEOUT,
        'ignore_errors' => true,
        'header'        => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return (is_string($body) && strlen($body) > 2000) ? $body : null;
}

// ── HTML Parser ───────────────────────────────────────────────────────────────

function parseHtml(string $html): array
{
    if (!mb_check_encoding($html, 'UTF-8')) {
        $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1, Windows-1256, UTF-8');
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();

    $xpath   = new DOMXPath($dom);
    $flights = [];

    foreach (SECTIONS as $keyword => [$direction, $route]) {
        $nodes = $xpath->query(
            "//*[contains(normalize-space(text()),'{$keyword}') or contains(normalize-space(.),'{$keyword}')]"
        );
        if (!$nodes || $nodes->length === 0) continue;

        $heading = null;
        for ($i = 0; $i < $nodes->length; $i++) {
            $n = $nodes->item($i);
            if (!$heading || strlen($n->textContent) < strlen($heading->textContent)) {
                $heading = $n;
            }
        }
        if (!$heading) continue;

        $table = nextTable($heading, $xpath);
        if (!$table) continue;

        $sectionFlights = parseTable($table, $xpath, $direction, $route);

        $symbol = $direction === 'arrival' ? '↓' : '↑';
        $rtName = $route === 'domestic' ? 'داخلی' : 'خارجی';
        $cnt    = count($sectionFlights);
        echo "      $symbol $rtName: $cnt پرواز\n";

        $flights = array_merge($flights, $sectionFlights);
    }

    // Deduplicate
    $seen = [];
    $out  = [];
    foreach ($flights as $f) {
        $key = $f['flight_number'] . '|' . $f['type'] . '|' . $f['scheduled_time'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[]      = $f;
        }
    }
    return $out;
}

function nextTable(DOMNode $node, DOMXPath $xpath, int $depth = 10): ?DOMElement
{
    $cur = $node->nextSibling;
    $w   = 0;
    while ($cur && $w < $depth) {
        if ($cur instanceof DOMElement) {
            $tag = strtolower($cur->nodeName);
            if ($tag === 'table') return $cur;
            $found = $xpath->query('.//table', $cur);
            if ($found && $found->length > 0) return $found->item(0);
            if (in_array($tag, ['h1','h2','h3','h4','section','article'])) break;
        }
        $cur = $cur->nextSibling;
        $w++;
    }
    if ($node->parentNode && $node->parentNode->nodeName !== 'body') {
        return nextTable($node->parentNode, $xpath, $depth - 2);
    }
    return null;
}

function parseTable(DOMElement $table, DOMXPath $xpath, string $direction, string $route): array
{
    $rows      = $xpath->query('.//tr', $table);
    $flights   = [];
    $isArrival = ($direction === 'arrival');

    foreach ($rows as $row) {
        $cells = $xpath->query('.//td', $row);
        if (!$cells || $cells->length < 6) continue;

        $cols = [];
        foreach ($cells as $cell) $cols[] = cleanText($cell->textContent);

        $flightNum   = cleanText($cols[3] ?? '');
        if (!$flightNum || $flightNum === '-' || strlen($flightNum) < 2) continue;

        $scheduledRaw = $cols[1] ?? '';
        $airlineName  = $cols[2] ?? '';
        $place        = $cols[4] ?? '';
        $statusFa     = $cols[5] ?? '';
        $counterBelt  = $cols[6] ?? '';
        $actualRaw    = $cols[7] ?? '';
        $aircraft     = $cols[9] ?? ($cols[8] ?? '');
        $dateRaw      = $cols[10] ?? ($cols[8] ?? '');

        $schedTime = normaliseDateTime($scheduledRaw, $dateRaw);
        $actTime   = $actualRaw ? normaliseDateTime($actualRaw, $dateRaw) : null;

        $flights[] = [
            'flight_number'  => $flightNum,
            'airline_name'   => $airlineName,
            'airline_code'   => airlineCode($flightNum),
            'airline_logo'   => null,
            'type'           => $direction,
            'route'          => $route,
            'origin'         => $isArrival  ? $place : null,
            'destination'    => !$isArrival ? $place : null,
            'scheduled_time' => $schedTime,
            'actual_time'    => $actTime,
            'status'         => statusEn($statusFa),
            'status_fa'      => $statusFa,
            'gate'           => null,
            'terminal'       => null,
            'belt'           => $isArrival  ? $counterBelt : null,
            'counter'        => !$isArrival ? $counterBelt : null,
            'aircraft_type'  => $aircraft,
            'delay_minutes'  => 0,
        ];
    }
    return $flights;
}

// ── String helpers ────────────────────────────────────────────────────────────

function cleanText(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function persianToLatin(string $s): string
{
    return strtr($s, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
        '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
        '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
}

function normaliseDateTime(string $time, string $dateHint = ''): string
{
    $time = persianToLatin(cleanText($time));
    $hhmm = '';
    if (preg_match('/(\d{1,2}):(\d{2})/', $time, $m)) {
        $hhmm = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    return date('Y-m-d') . ' ' . ($hhmm ?: '00:00') . ':00';
}

function airlineCode(string $flightNum): string
{
    if (preg_match('/^([A-Z]{2}|[A-Z]\d|\d[A-Z])/i', $flightNum, $m)) {
        return strtoupper($m[1]);
    }
    return substr(strtoupper(preg_replace('/[^A-Z]/i', '', $flightNum)), 0, 3) ?: '??';
}

function statusEn(string $fa): string
{
    $map = [
        'نشست'        => 'arrived',
        'فرود'        => 'arrived',
        'پرواز کرد'   => 'departed',
        'برخاست'      => 'departed',
        'لغو'         => 'cancelled',
        'کنسل'        => 'cancelled',
        'تأخیر'       => 'delayed',
        'تاخیر'       => 'delayed',
        'سوار شوید'   => 'boarding',
        'سوارشوید'    => 'boarding',
        'سوار'        => 'boarding',
        'تغییر مسیر'  => 'diverted',
        'انحراف'      => 'diverted',
        'به موقع'     => 'scheduled',
        'برنامه‌ریزی' => 'scheduled',
    ];
    foreach ($map as $keyword => $status) {
        if (str_contains($fa, $keyword)) return $status;
    }
    return 'scheduled';
}
