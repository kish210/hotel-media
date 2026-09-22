<?php
declare(strict_types=1);

namespace App\Services;

/**
 * کلاینت HTTP با پشتیبانی Digest و Basic.
 *
 * ── چرا لازم شد ─────────────────────────────────────────────────────
 *
 * تی‌وی‌هدند از نسخه‌های جدید با «digest: 1» نصب می‌شود و احراز هویت
 * Basic را اصلا نمی‌پذیرد — همیشه ۴۰۱ برمی‌گرداند. روی نصب واقعی
 * Ubuntu 24.04 تایید شد:
 *
 *     basic  → HTTP 401
 *     digest → HTTP 200
 *
 * کد ما فقط Basic می‌فرستاد، یعنی روی هر نصب تازه‌ی تی‌وی‌هدند اتصال
 * برقرار نمی‌شد و پیام «نام کاربری یا رمز اشتباه است» می‌داد — که
 * گمراه‌کننده بود، چون رمز درست بود.
 *
 * ── چرا دستی و نه curl ──────────────────────────────────────────────
 *
 * ‏curl خودش CURLAUTH_DIGEST دارد، ولی افزونه‌ی curl روی همه‌ی
 * نصب‌های PHP فعال نیست و بقیه‌ی کد پروژه با stream context کار
 * می‌کند. این کلاس هر دو را می‌پوشاند: اگر curl بود از آن استفاده
 * می‌کند، وگرنه digest را خودش می‌سازد.
 */
final class HttpDigestClient
{
    private int $timeout;

    public function __construct(int $timeout = 12)
    {
        $this->timeout = $timeout;
    }

    /**
     * درخواست GET با احراز هویت خودکار.
     *
     * اول بدون احراز هویت می‌فرستد؛ اگر ۴۰۱ گرفت از سرآیند
     * WWW-Authenticate می‌فهمد Digest می‌خواهد یا Basic و دوباره
     * می‌فرستد. این همان کاری است که مرورگر می‌کند.
     *
     * @return array{ok:bool,status:int,body:string,error:string}
     */
    public function get(string $url, string $user = '', string $pass = ''): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'status' => 0, 'body' => '',
                    'error' => 'آدرس باید با http شروع شود'];
        }

        if (function_exists('curl_init')) {
            return $this->viaCurl($url, $user, $pass);
        }
        return $this->viaStream($url, $user, $pass);
    }

    // ══════════════════════════════════════════════════════════════
    //  مسیر curl
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,status:int,body:string,error:string} */
    private function viaCurl(string $url, string $user, string $pass): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'HotelMedia',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        if ($user !== '') {
            /* ‏ANY یعنی «هر چه سرور خواست» — curl اول بدون احراز هویت
               می‌زند، سرآیند را می‌خواند و روش درست را انتخاب می‌کند.
               با این، هم تی‌وی‌هدندِ digest کار می‌کند و هم سرورهای
               قدیمی‌تر که Basic می‌خواهند. */
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        }

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'body' => '',
                    'error' => $this->netError($err)];
        }
        return $this->judge($status, (string)$body);
    }

    // ══════════════════════════════════════════════════════════════
    //  مسیر stream — وقتی curl نیست
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,status:int,body:string,error:string} */
    private function viaStream(string $url, string $user, string $pass): array
    {
        /* تلاش اول: بدون احراز هویت. اگر سرور باز باشد همین کافی است
           و یک رفت‌وبرگشت کمتر می‌خورد. */
        [$status, $body, $headers] = $this->raw($url, '');
        if ($status === 0) {
            return ['ok' => false, 'status' => 0, 'body' => '',
                    'error' => 'اتصال برقرار نشد'];
        }
        if ($status !== 401 || $user === '') {
            return $this->judge($status, $body);
        }

        $challenge = $this->challenge($headers);
        if ($challenge === null) {
            return ['ok' => false, 'status' => 401, 'body' => $body,
                    'error' => 'سرور روش احراز هویت را اعلام نکرد'];
        }

        $auth = str_starts_with(strtolower($challenge), 'digest')
            ? $this->digestHeader($challenge, $url, $user, $pass)
            : 'Authorization: Basic ' . base64_encode($user . ':' . $pass) . "\r\n";

        if ($auth === '') {
            return ['ok' => false, 'status' => 401, 'body' => $body,
                    'error' => 'ساخت سرآیند احراز هویت ناموفق بود'];
        }

        [$status2, $body2] = $this->raw($url, $auth);
        return $this->judge($status2, $body2);
    }

    /**
     * یک درخواست خام.
     *
     * @return array{0:int,1:string,2:list<string>}
     */
    private function raw(string $url, string $extraHeader): array
    {
        $header = "Accept: application/json\r\nUser-Agent: HotelMedia\r\n" . $extraHeader;

        $ctx = stream_context_create(['http' => [
            'method'        => 'GET',
            'timeout'       => $this->timeout,
            'header'        => $header,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return [0, '', []];

        $headers = $http_response_header ?? [];
        $status  = 0;
        if (isset($headers[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $headers[0], $m)) {
            $status = (int)$m[1];
        }
        return [$status, (string)$body, $headers];
    }

    /** @param list<string> $headers */
    private function challenge(array $headers): ?string
    {
        foreach ($headers as $h) {
            if (stripos($h, 'WWW-Authenticate:') === 0) {
                return trim(substr($h, 17));
            }
        }
        return null;
    }

    /**
     * ساخت سرآیند Digest طبق RFC 2617.
     *
     * فقط qop=auth و الگوریتم MD5 پشتیبانی می‌شود — همان چیزی که
     * تی‌وی‌هدند می‌فرستد.
     */
    private function digestHeader(string $challenge, string $url, string $user, string $pass): string
    {
        $p = [];
        if (preg_match_all('/(\w+)=(?:"([^"]*)"|([^,\s]+))/', $challenge, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) $p[strtolower($x[1])] = $x[2] !== '' ? $x[2] : ($x[3] ?? '');
        }

        $realm = $p['realm'] ?? '';
        $nonce = $p['nonce'] ?? '';
        if ($realm === '' || $nonce === '') return '';

        /* مسیر باید همان چیزی باشد که در خط درخواست رفته، با کوئری */
        $parts = parse_url($url);
        $uri   = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $ha1 = md5($user . ':' . $realm . ':' . $pass);
        $ha2 = md5('GET:' . $uri);

        $qop = '';
        foreach (explode(',', $p['qop'] ?? '') as $q) {
            if (trim($q) === 'auth') { $qop = 'auth'; break; }
        }

        $out = 'Authorization: Digest username="' . $user . '", realm="' . $realm
             . '", nonce="' . $nonce . '", uri="' . $uri . '"';

        if ($qop === 'auth') {
            /* cnonce باید غیرقابل‌حدس باشد؛ با nc=00000001 هر درخواست
               یک‌بار مصرف می‌ماند. */
            $cnonce   = bin2hex(random_bytes(8));
            $nc       = '00000001';
            $response = md5($ha1 . ':' . $nonce . ':' . $nc . ':' . $cnonce . ':auth:' . $ha2);
            $out .= ', qop=auth, nc=' . $nc . ', cnonce="' . $cnonce . '"';
        } else {
            $response = md5($ha1 . ':' . $nonce . ':' . $ha2);
        }

        $out .= ', response="' . $response . '"';
        if (!empty($p['opaque'])) $out .= ', opaque="' . $p['opaque'] . '"';

        return $out . "\r\n";
    }

    // ══════════════════════════════════════════════════════════════
    //  تفسیر پاسخ
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,status:int,body:string,error:string} */
    private function judge(int $status, string $body): array
    {
        if ($status === 401 || $status === 403) {
            return ['ok' => false, 'status' => $status, 'body' => $body,
                    'error' => 'نام کاربری یا رمز پذیرفته نشد'];
        }
        if ($status === 404) {
            return ['ok' => false, 'status' => 404, 'body' => $body,
                    'error' => 'این مسیر روی سرور وجود ندارد'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'status' => $status, 'body' => $body,
                    'error' => 'سرور خطای HTTP ' . $status . ' داد'];
        }
        return ['ok' => true, 'status' => $status, 'body' => $body, 'error' => ''];
    }

    /** پیام curl انگلیسی است و برای اپراتور هتل بی‌فایده */
    private function netError(string $err): string
    {
        if (stripos($err, 'certificate') !== false || stripos($err, 'SSL') !== false) {
            return 'گواهی امنیتی بررسی نشد — بسته‌ی ca-certificates را به‌روز کنید';
        }
        if (stripos($err, 'timed out') !== false || stripos($err, 'timeout') !== false) {
            return 'سرور در زمان مقرر پاسخ نداد';
        }
        if (stripos($err, 'refused') !== false) {
            return 'سرور اتصال را رد کرد — سرویس روشن است؟';
        }
        return 'اتصال برقرار نشد' . ($err !== '' ? ' (' . $err . ')' : '');
    }
}
