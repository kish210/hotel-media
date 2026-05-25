<?php
declare(strict_types=1);
namespace App\Services;

/**
 * AirportIrFetcher
 * Fetches live flight data from https://fids.airport.ir
 *
 * Configuration via .env:
 *   FIDS_TIMEOUT=10          HTTP timeout in seconds (default 10)
 *   FIDS_HTTP_PROXY=         HTTP proxy, e.g. http://127.0.0.1:8080  (optional)
 *   FIDS_CACHE_TTL=60        Cache lifetime in seconds (default 60)
 *   FIDS_BASE_URL=           Override base URL (default https://fids.airport.ir)
 */
class AirportIrFetcher
{
    private const DEFAULT_BASE    = 'https://fids.airport.ir';
    private const DEFAULT_TTL     = 60;
    private const DEFAULT_TIMEOUT = 10;

    // ── Airport catalogue ───────────────────────────────────────────────────
    public const AIRPORTS = [
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

    // Section heading keywords → [direction, route]
    private const SECTIONS = [
        'ورودی داخلی'     => ['arrival',   'domestic'],
        'خروجی داخلی'     => ['departure', 'domestic'],
        'ورودی خارجی'     => ['arrival',   'international'],
        'خروجی خارجی'     => ['departure', 'international'],
        'پروازهای ورودی'  => ['arrival',   'domestic'],
        'پروازهای خروجی'  => ['departure', 'domestic'],
        'Arrivals'        => ['arrival',   'domestic'],
        'Departures'      => ['departure', 'domestic'],
    ];

    // ── Public interface ────────────────────────────────────────────────────

    /**
     * Fetch flights for a given airport, filtered by direction and route type.
     *
     * @throws \RuntimeException  when the remote site cannot be reached
     */
    public static function fetch(
        int    $airportId  = 2,
        string $direction  = 'all',
        string $route      = 'all',
        int    $limit      = 20
    ): array {
        $cacheDir  = STORAGE_PATH . '/cache/fids/';
        $cacheFile = $cacheDir . "airport_{$airportId}.json";
        $ttl       = (int)(env('FIDS_CACHE_TTL', self::DEFAULT_TTL));

        // ── Cache read ──────────────────────────────────────────────────────
        $all = [];
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                $all = $cached;
            }
        }

        // ── Scrape if cache is stale / empty ────────────────────────────────
        if (empty($all)) {
            $all = self::scrape($airportId);   // throws on connectivity failure
            if (!empty($all)) {
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
                file_put_contents($cacheFile, json_encode($all, JSON_UNESCAPED_UNICODE));
            }
        }

        if (empty($all)) {
            return [];
        }

        // ── Filter + limit ──────────────────────────────────────────────────
        $filtered = array_values(array_filter($all, static function (array $f) use ($direction, $route): bool {
            $okDir   = $direction === 'all' || $f['type']  === $direction;
            $okRoute = $route     === 'all' || ($f['route'] ?? 'domestic') === $route;
            return $okDir && $okRoute;
        }));

        return array_slice($filtered, 0, $limit);
    }

    /** Invalidates cached data for one airport (or all if $airportId = 0) */
    public static function bust(int $airportId = 0): void
    {
        $cacheDir = STORAGE_PATH . '/cache/fids/';
        if ($airportId) {
            @unlink($cacheDir . "airport_{$airportId}.json");
        } else {
            foreach (glob($cacheDir . 'airport_*.json') ?: [] as $f) @unlink($f);
        }
    }

    /**
     * Test connectivity to fids.airport.ir.
     * Returns an info array: ['reachable'=>bool, 'http_code'=>int, 'latency_ms'=>int, 'error'=>string, 'proxy'=>string]
     */
    public static function testConnection(int $airportId = 2): array
    {
        $url   = self::buildUrl($airportId);
        $proxy = env('FIDS_HTTP_PROXY', '');
        $start = microtime(true);

        $result = ['reachable' => false, 'http_code' => 0, 'latency_ms' => 0,
                   'error' => '', 'url' => $url, 'proxy' => $proxy ?: '(none)'];

        if (!function_exists('curl_init')) {
            $result['error'] = 'cURL extension not available';
            return $result;
        }

        $ch = curl_init($url);
        self::applyCurlOptions($ch, $proxy, 8);
        curl_setopt($ch, CURLOPT_NOBODY, true);     // HEAD only — faster

        curl_exec($ch);
        $result['http_code']   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['latency_ms']  = (int)((microtime(true) - $start) * 1000);
        $curlError             = curl_error($ch);
        $curlErrno             = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno) {
            $result['error'] = "cURL #{$curlErrno}: {$curlError}";
        } elseif ($result['http_code'] >= 200 && $result['http_code'] < 500) {
            $result['reachable'] = true;
        } else {
            $result['error'] = "HTTP {$result['http_code']}";
        }

        return $result;
    }

    // ── Internal ────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException  on network/parse failure
     */
    private static function scrape(int $airportId): array
    {
        $info = self::AIRPORTS[$airportId] ?? null;
        if (!$info) throw new \RuntimeException("فرودگاه با ID {$airportId} در لیست وجود ندارد");

        $proxy   = env('FIDS_HTTP_PROXY', '');
        $timeout = (int)(env('FIDS_TIMEOUT', self::DEFAULT_TIMEOUT));

        // ── Strategy 1: known HTML page ─────────────────────────────────────
        $url  = self::buildUrl($airportId);
        $html = self::httpGet($url, $proxy, $timeout);

        if ($html !== null) {
            $flights = self::parseHtml($html);
            if (!empty($flights)) return $flights;

            // Got HTML but parsing returned nothing — log and continue to strategy 2
            error_log("[FIDS] Parsed 0 flights from HTML for airport {$airportId} (HTML length: " . strlen($html) . ")");
        }

        // ── Strategy 2: alternate slug URL ──────────────────────────────────
        $base    = rtrim(env('FIDS_BASE_URL', self::DEFAULT_BASE), '/');
        $altUrls = [
            "{$base}/{$airportId}/",
            "{$base}/airport/{$airportId}/flights",
            "{$base}/api/airport/{$airportId}/flights",
            "{$base}/Home/GetFlights/{$airportId}",
        ];

        foreach ($altUrls as $altUrl) {
            $body = self::httpGet($altUrl, $proxy, $timeout);
            if ($body === null) continue;

            // Try JSON first
            $json = json_decode($body, true);
            if (is_array($json)) {
                $parsed = self::parseJsonResponse($json, $airportId);
                if (!empty($parsed)) return $parsed;
            }

            // Try HTML
            $parsed = self::parseHtml($body);
            if (!empty($parsed)) return $parsed;
        }

        // ── Nothing worked — throw with clear diagnosis ──────────────────────
        $conn = self::testConnection($airportId);
        if (!$conn['reachable']) {
            throw new \RuntimeException(
                "سایت fids.airport.ir قابل دسترس نیست — " .
                ($conn['error'] ?: "connection timeout") .
                " | IP: 217.218.117.82 | " .
                ($proxy ? "Proxy: {$proxy}" : "بدون proxy. برای تنظیم proxy، FIDS_HTTP_PROXY را در .env مقداردهی کنید")
            );
        }

        throw new \RuntimeException(
            "سایت fids.airport.ir در دسترس است (HTTP {$conn['http_code']}) اما اطلاعات پرواز دریافت نشد — ساختار HTML سایت ممکن است تغییر کرده باشد"
        );
    }

    private static function buildUrl(int $airportId): string
    {
        $base = rtrim(env('FIDS_BASE_URL', self::DEFAULT_BASE), '/');
        $info = self::AIRPORTS[$airportId] ?? ['slug' => (string)$airportId];
        // Note: no U+202B (RLE) prefix — that was causing URL encoding issues.
        // The slug is Persian; curl handles non-ASCII in paths automatically.
        $slug = 'اطلاعات-پرواز-فرودگاه-' . $info['slug'];
        return "{$base}/{$airportId}/{$slug}";
    }

    private static function applyCurlOptions($ch, string $proxy, int $timeout): void
    {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 8),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: fa,fa-IR;q=0.9,en;q=0.7',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Cache-Control: no-cache',
            ],
        ]);

        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
    }

    private static function httpGet(string $url, string $proxy = '', int $timeout = 10): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            self::applyCurlOptions($ch, $proxy, $timeout);
            $body      = curl_exec($ch);
            $code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("[FIDS] cURL error for {$url}: {$curlError}");
                return null;
            }
            if ($code >= 200 && $code < 400 && is_string($body) && strlen($body) > 500) {
                return $body;
            }
            error_log("[FIDS] HTTP {$code} for {$url}");
            return null;
        }

        // Fallback: file_get_contents
        $opts = ['http' => [
            'timeout'       => $timeout,
            'ignore_errors' => true,
            'header'        => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\nAccept-Encoding: gzip, deflate\r\n",
        ]];
        if ($proxy) {
            $opts['http']['proxy']           = $proxy;
            $opts['http']['request_fulluri'] = true;
        }
        $body = @file_get_contents($url, false, stream_context_create($opts));
        return (is_string($body) && strlen($body) > 500) ? $body : null;
    }

    // ── HTML Parser ─────────────────────────────────────────────────────────

    private static function parseHtml(string $html): array
    {
        if (mb_strlen($html) < 500) return [];

        // Ensure UTF-8
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1, Windows-1256, UTF-8');
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath   = new \DOMXPath($dom);
        $flights = [];

        foreach (self::SECTIONS as $keyword => [$direction, $route]) {
            $nodes = $xpath->query(
                "//*[contains(normalize-space(text()),'{$keyword}') or contains(normalize-space(.),'{$keyword}')]"
            );
            if (!$nodes || $nodes->length === 0) continue;

            // Most specific (shortest text) node
            $heading = null;
            for ($i = 0; $i < $nodes->length; $i++) {
                $n = $nodes->item($i);
                if (!$heading || strlen($n->textContent) < strlen($heading->textContent)) {
                    $heading = $n;
                }
            }
            if (!$heading) continue;

            $table = self::nextTable($heading, $xpath);
            if (!$table) continue;

            $flights = array_merge($flights, self::parseTable($table, $xpath, $direction, $route));
        }

        // Fallback: if no section found, try every table on the page
        if (empty($flights)) {
            $tables = $xpath->query('//table');
            if ($tables) {
                foreach ($tables as $table) {
                    $parsed = self::parseTable($table, $xpath, 'departure', 'domestic');
                    if (count($parsed) >= 2) {
                        $flights = array_merge($flights, $parsed);
                        break;
                    }
                }
            }
        }

        // Deduplicate
        $seen   = [];
        $unique = [];
        foreach ($flights as $f) {
            $key = ($f['flight_number'] ?? '') . '|' . ($f['type'] ?? '') . '|' . ($f['scheduled_time'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $f;
            }
        }
        return $unique;
    }

    private static function nextTable(\DOMNode $node, \DOMXPath $xpath, int $depthLimit = 15): ?\DOMElement
    {
        $current = $node->nextSibling;
        $walked  = 0;

        while ($current && $walked < $depthLimit) {
            if ($current instanceof \DOMElement) {
                $tag = strtolower($current->nodeName);
                if ($tag === 'table') return $current;

                $found = $xpath->query('.//table', $current);
                if ($found && $found->length > 0) return $found->item(0);

                if (in_array($tag, ['h1','h2','h3','h4','section','article'])) break;
            }
            $current = $current->nextSibling;
            $walked++;
        }

        if ($node->parentNode && !in_array($node->parentNode->nodeName, ['body','html','#document'])) {
            return self::nextTable($node->parentNode, $xpath, $depthLimit - 3);
        }
        return null;
    }

    private static function parseTable(
        \DOMElement $table,
        \DOMXPath   $xpath,
        string      $direction,
        string      $route
    ): array {
        $rows      = $xpath->query('.//tr', $table);
        $flights   = [];
        $isArrival = ($direction === 'arrival');

        if (!$rows) return [];

        // Auto-detect column order from header row
        $headerMap = self::detectColumns($table, $xpath);

        foreach ($rows as $row) {
            /** @var \DOMElement $row */
            $cells = $xpath->query('.//td', $row);
            if (!$cells || $cells->length === 0) continue;
            if ($cells->length < 4) continue;

            $cols = [];
            foreach ($cells as $cell) {
                $cols[] = self::cleanText($cell->textContent);
            }

            // Use detected columns or fall back to positional defaults
            $flightNum    = $cols[$headerMap['flight']    ?? 3] ?? '';
            $airlineName  = $cols[$headerMap['airline']   ?? 2] ?? '';
            $scheduledRaw = $cols[$headerMap['sched']     ?? 1] ?? '';
            $place        = $cols[$headerMap['place']     ?? 4] ?? '';
            $statusFa     = $cols[$headerMap['status']    ?? 5] ?? '';
            $counterBelt  = $cols[$headerMap['counter']   ?? 6] ?? '';
            $actualRaw    = $cols[$headerMap['actual']    ?? 7] ?? '';
            $aircraft     = $cols[$headerMap['aircraft']  ?? 9] ?? ($cols[8] ?? '');
            $dateRaw      = $cols[$headerMap['date']      ?? 10] ?? ($cols[8] ?? '');

            $flightNum = self::cleanText($flightNum);
            if (!$flightNum || $flightNum === '-' || strlen($flightNum) < 2) continue;

            // Must look like a flight number: letters + digits
            if (!preg_match('/[A-Z0-9]{2,}/i', $flightNum)) continue;

            $schedTime = self::normaliseDateTime($scheduledRaw, $dateRaw);
            $actTime   = ($actualRaw && $actualRaw !== '-' && $actualRaw !== $scheduledRaw)
                ? self::normaliseDateTime($actualRaw, $dateRaw)
                : null;

            $flights[] = [
                'flight_number'  => strtoupper(preg_replace('/\s+/', '', $flightNum)),
                'airline_name'   => $airlineName,
                'airline_code'   => self::airlineCode($flightNum),
                'airline_logo'   => null,
                'type'           => $direction,
                'route'          => $route,
                'origin'         => $isArrival  ? $place : null,
                'destination'    => !$isArrival ? $place : null,
                'scheduled_time' => $schedTime,
                'actual_time'    => $actTime,
                'status'         => self::statusEn($statusFa),
                'status_fa'      => $statusFa,
                'gate'           => null,
                'terminal'       => null,
                'belt'           => $isArrival  ? $counterBelt : null,
                'counter'        => !$isArrival ? $counterBelt : null,
                'aircraft_type'  => $aircraft ?: null,
                'delay_minutes'  => 0,
            ];
        }
        return $flights;
    }

    /**
     * Detect column positions from the <thead> or first <tr> with <th>.
     * Returns array like ['flight'=>3, 'airline'=>2, 'sched'=>1, ...]
     */
    private static function detectColumns(\DOMElement $table, \DOMXPath $xpath): array
    {
        $map    = [];
        $header = $xpath->query('.//tr[th]', $table);
        if (!$header || $header->length === 0) return $map;

        $ths = $xpath->query('.//th', $header->item(0));
        if (!$ths) return $map;

        $keywords = [
            'flight'   => ['پرواز','flight','فلایت','شماره پرواز'],
            'airline'  => ['ایرلاین','airline','هواپیمایی','شرکت'],
            'sched'    => ['برنامه','زمان','ساعت','sched','planned','time'],
            'actual'   => ['واقعی','actual','real','انجام'],
            'place'    => ['مقصد','مبدا','destination','origin','محل','شهر'],
            'status'   => ['وضعیت','status','حالت'],
            'counter'  => ['کانتر','باند','gate','counter','belt'],
            'aircraft' => ['هواپیما','aircraft','نوع'],
            'date'     => ['تاریخ','date','روز'],
        ];

        for ($i = 0; $i < $ths->length; $i++) {
            $text = mb_strtolower(self::cleanText($ths->item($i)->textContent));
            foreach ($keywords as $key => $words) {
                if (!isset($map[$key])) {
                    foreach ($words as $w) {
                        if (str_contains($text, $w)) {
                            $map[$key] = $i;
                            break;
                        }
                    }
                }
            }
        }
        return $map;
    }

    /** Try to parse a JSON response from the site's XHR API */
    private static function parseJsonResponse(array $json, int $airportId): array
    {
        $flights = [];

        // Handle common response wrappers
        $items = $json['data']   ?? $json['flights'] ?? $json['result']
              ?? $json['items']  ?? $json['rows']    ?? $json;

        if (!is_array($items) || empty($items)) return [];

        // Check if it looks like a flat array of flights
        $first = reset($items);
        if (!is_array($first)) return [];

        $today     = date('Y-m-d');
        $flightKey = null;
        foreach (['flight_number','flightNumber','flight','FlightNo','flightNo'] as $k) {
            if (isset($first[$k])) { $flightKey = $k; break; }
        }
        if (!$flightKey) return [];

        foreach ($items as $item) {
            $fn = self::cleanText((string)($item[$flightKey] ?? ''));
            if (!$fn || strlen($fn) < 2) continue;

            $type = 'departure';
            foreach (['type','flightType','direction','Type'] as $k) {
                if (isset($item[$k])) {
                    $v = strtolower((string)$item[$k]);
                    if (in_array($v, ['arrival','2','a','arr','ورودی'])) $type = 'arrival';
                    break;
                }
            }

            $scheduledRaw = (string)($item['scheduled_time'] ?? $item['scheduledTime'] ??
                            $item['time']          ?? $item['Time']         ?? '');
            $schedTime = strlen($scheduledRaw) > 10
                ? $scheduledRaw
                : ($today . ' ' . self::persianToLatin($scheduledRaw) . ':00');

            $statusFa = (string)($item['status_fa'] ?? $item['statusFa'] ?? $item['status'] ?? '');
            $place    = (string)($item['destination'] ?? $item['origin'] ?? $item['city'] ?? $item['place'] ?? '');

            $flights[] = [
                'flight_number'  => strtoupper($fn),
                'airline_name'   => (string)($item['airline_name'] ?? $item['airlineName'] ?? $item['airline'] ?? ''),
                'airline_code'   => self::airlineCode($fn),
                'airline_logo'   => null,
                'type'           => $type,
                'route'          => 'domestic',
                'origin'         => $type === 'arrival'   ? $place : null,
                'destination'    => $type === 'departure' ? $place : null,
                'scheduled_time' => $schedTime,
                'actual_time'    => null,
                'status'         => self::statusEn($statusFa),
                'status_fa'      => $statusFa,
                'gate'           => null,
                'terminal'       => null,
                'belt'           => null,
                'counter'        => null,
                'aircraft_type'  => null,
                'delay_minutes'  => (int)($item['delay_minutes'] ?? $item['delayMinutes'] ?? 0),
            ];
        }
        return $flights;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private static function cleanText(string $s): string
    {
        return trim(preg_replace('/[\s\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]+/u', ' ', $s));
    }

    private static function persianToLatin(string $s): string
    {
        return strtr($s, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
            '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    private static function normaliseDateTime(string $time, string $dateHint = ''): string
    {
        $time = self::persianToLatin(self::cleanText($time));

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/', $time, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:00";
        }

        $hhmm = '';
        if (preg_match('/(\d{1,2}):(\d{2})/', $time, $m)) {
            $hhmm = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }

        return date('Y-m-d') . ' ' . ($hhmm ?: '00:00') . ':00';
    }

    private static function airlineCode(string $flightNum): string
    {
        $clean = preg_replace('/\s+/', '', $flightNum);
        if (preg_match('/^([A-Z]{2}|[A-Z]\d|\d[A-Z])/i', $clean, $m)) {
            return strtoupper($m[1]);
        }
        return substr(strtoupper(preg_replace('/[^A-Z]/i', '', $clean)), 0, 3) ?: '??';
    }

    private static function statusEn(string $fa): string
    {
        $map = [
            'نشست'          => 'arrived',
            'فرود'          => 'arrived',
            'landed'        => 'arrived',
            'پرواز کرد'     => 'departed',
            'برخاست'        => 'departed',
            'departed'      => 'departed',
            'لغو'           => 'cancelled',
            'کنسل'          => 'cancelled',
            'cancelled'     => 'cancelled',
            'تأخیر'         => 'delayed',
            'تاخیر'         => 'delayed',
            'delay'         => 'delayed',
            'سوار شوید'     => 'boarding',
            'سوارشوید'      => 'boarding',
            'سوار'          => 'boarding',
            'boarding'      => 'boarding',
            'تغییر مسیر'    => 'diverted',
            'انحراف'        => 'diverted',
            'به موقع'       => 'scheduled',
            'on time'       => 'scheduled',
            'برنامه‌ریزی'   => 'scheduled',
            'زمان‌بندی'     => 'scheduled',
        ];
        $faLower = mb_strtolower($fa);
        foreach ($map as $keyword => $status) {
            if (str_contains($faLower, mb_strtolower($keyword))) return $status;
        }
        return 'scheduled';
    }
}
