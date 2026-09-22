<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * زیرنویس و باند صوتی VOD.
 *
 * ── چرا همه‌چیز به WebVTT تبدیل می‌شود ─────────────────────────────
 *
 * تگ <track> تنها راهی است که روی هر سه پلتفرم کار می‌کند و از
 * Chromium 23 وجود دارد — یعنی حتی Tizen 2.3 و webOS 3 هم دارندش.
 * ولی <track> فقط WebVTT می‌خورد، نه SRT.
 *
 * ‏AVPlay سامسونگ هم گزینه نیست: فقط SAMI و SMPTE-TT می‌پذیرد و فایل
 * بیرونی را باید اول در حافظه‌ی محلی دانلود کند، که روی URL Launcher
 * اصلا ممکن نیست.
 *
 * پس اپراتور هر چه آپلود کند، سرور یک‌بار به WebVTT تبدیل می‌کند و
 * همه‌جا همان سرو می‌شود.
 */
final class SubtitleService
{
    /** قالب‌هایی که می‌پذیریم */
    public const FORMATS = ['srt', 'vtt', 'ass', 'ssa', 'sub'];

    /** سقف حجم فایل زیرنویس — بیش از این یعنی فایل اشتباهی است */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  تبدیل به WebVTT
    // ══════════════════════════════════════════════════════════════

    /**
     * هر قالب ورودی → WebVTT.
     *
     * @return array{ok:bool,message:string,vtt:string,cues:int}
     */
    public function toVtt(string $content, string $format): array
    {
        $format = strtolower($format);

        $content = $this->normalize($content);
        if ($content === '') {
            return ['ok' => false, 'message' => 'فایل زیرنویس خالی است', 'vtt' => '', 'cues' => 0];
        }

        return match ($format) {
            'vtt'          => $this->fromVtt($content),
            'srt', 'sub'   => $this->fromSrt($content),
            'ass', 'ssa'   => $this->fromAss($content),
            default        => ['ok' => false, 'message' => 'قالب پشتیبانی نمی‌شود: ' . $format,
                               'vtt' => '', 'cues' => 0],
        };
    }

    /**
     * یکسان‌سازی متن خام پیش از هر کاری.
     *
     * سه تله که هر کدام به‌تنهایی کل فایل را خراب می‌کند:
     *  ۱) BOM در ابتدای فایل — «WEBVTT» را بی‌اعتبار می‌کند و مرورگر
     *     کل زیرنویس را دور می‌اندازد بدون هیچ خطایی
     *  ۲) پایان خط ویندوزی — الگوی خط خالی بین بلوک‌ها را می‌شکند
     *  ۳) کدگذاری غیر UTF-8 — زیرنویس فارسی که با Windows-1256 ذخیره
     *     شده روی تلویزیون به شکل حروف درهم دیده می‌شود
     */
    private function normalize(string $s): string
    {
        // BOM
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;

        if (!mb_check_encoding($s, 'UTF-8')) {
            /* زیرنویس فارسی که از ویندوز آمده معمولا Windows-1256 است.
               اگر حدس غلط باشد هم نتیجه بدتر از متن درهم فعلی نیست. */
            foreach (['Windows-1256', 'ISO-8859-1'] as $enc) {
                $try = @mb_convert_encoding($s, 'UTF-8', $enc);
                if ($try !== false && mb_check_encoding($try, 'UTF-8')) { $s = $try; break; }
            }
        }

        // CRLF و CR تنها → LF
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        return trim($s);
    }

    /**
     * ‏SRT → WebVTT.
     *
     * تفاوت اصلی: SRT وقت را با کاما می‌نویسد (00:00:01,500) و VTT با
     * نقطه (00:00:01.500). اگر این تبدیل نشود، مرورگر بی‌صدا هیچ
     * زیرنویسی نشان نمی‌دهد — نه خطایی، نه پیامی.
     *
     * @return array{ok:bool,message:string,vtt:string,cues:int}
     */
    private function fromSrt(string $s): array
    {
        $out  = "WEBVTT\n\n";
        $cues = 0;

        foreach (preg_split("/\n{2,}/", $s) ?: [] as $block) {
            $lines = array_values(array_filter(
                explode("\n", trim($block)),
                static fn($l) => trim($l) !== ''
            ));
            if (count($lines) < 2) continue;

            /* شماره‌ی بلوک اختیاری است؛ بعضی فایل‌ها ندارند */
            if (preg_match('/^\d+$/', trim($lines[0]))) array_shift($lines);
            if (count($lines) < 2) continue;

            $time = $this->srtTime(array_shift($lines));
            if ($time === null) continue;

            $text = trim(implode("\n", $lines));
            if ($text === '') continue;

            $out .= $time . "\n" . $text . "\n\n";
            $cues++;
        }

        if ($cues === 0) {
            return ['ok' => false, 'message' =>
                'هیچ زیرنویس معتبری در فایل پیدا نشد. مطمئن شوید فایل SRT سالم است.',
                'vtt' => '', 'cues' => 0];
        }

        return ['ok' => true, 'message' => '', 'vtt' => $out, 'cues' => $cues];
    }

    /** «00:00:01,500 --> 00:00:04,000» → «00:00:01.500 --> 00:00:04.000» */
    private function srtTime(string $line): ?string
    {
        $re = '/(\d{1,2}):(\d{2}):(\d{2})[,.](\d{1,3})\s*-->\s*'
            . '(\d{1,2}):(\d{2}):(\d{2})[,.](\d{1,3})/';
        if (!preg_match($re, $line, $m)) return null;

        $fmt = static fn(string $h, string $i, string $s, string $ms): string
            => sprintf('%02d:%02d:%02d.%03d', (int)$h, (int)$i, (int)$s, (int)str_pad($ms, 3, '0'));

        return $fmt($m[1], $m[2], $m[3], $m[4]) . ' --> ' . $fmt($m[5], $m[6], $m[7], $m[8]);
    }

    /**
     * فایلی که از قبل VTT است — فقط بررسی و مرتب‌سازی.
     *
     * @return array{ok:bool,message:string,vtt:string,cues:int}
     */
    private function fromVtt(string $s): array
    {
        /* بدون سرآیند WEBVTT مرورگر فایل را رد می‌کند. بعضی ابزارها
           آن را جا می‌اندازند، پس خودمان می‌گذاریم. */
        if (!str_starts_with($s, 'WEBVTT')) $s = "WEBVTT\n\n" . $s;

        $cues = preg_match_all('/-->/', $s);
        if ($cues === 0) {
            return ['ok' => false, 'message' => 'فایل VTT هیچ زیرنویسی ندارد',
                    'vtt' => '', 'cues' => 0];
        }

        /* کاما در فایلی که ادعا می‌کند VTT است هم دیده می‌شود */
        $s = preg_replace_callback(
            '/(\d{2}:\d{2}:\d{2}),(\d{3})/',
            static fn(array $m): string => $m[1] . '.' . $m[2],
            $s
        ) ?? $s;

        return ['ok' => true, 'message' => '', 'vtt' => $s, 'cues' => (int)$cues];
    }

    /**
     * ‏ASS/SSA → WebVTT.
     *
     * فقط متن و زمان برداشته می‌شود؛ رنگ و مکان و فونت که ASS دارد
     * در WebVTT معادل ندارند و تلویزیون هم نشانشان نمی‌دهد.
     *
     * @return array{ok:bool,message:string,vtt:string,cues:int}
     */
    private function fromAss(string $s): array
    {
        $out  = "WEBVTT\n\n";
        $cues = 0;

        foreach (explode("\n", $s) as $line) {
            if (!str_starts_with(trim($line), 'Dialogue:')) continue;

            /* قالب: Dialogue: Layer,Start,End,Style,Name,ML,MR,MV,Effect,Text
               متن آخرین فیلد است و خودش می‌تواند کاما داشته باشد، پس
               تقسیم محدود به ۹ تا. */
            $parts = explode(',', substr(trim($line), 9), 10);
            if (count($parts) < 10) continue;

            $start = $this->assTime(trim($parts[1]));
            $end   = $this->assTime(trim($parts[2]));
            if ($start === null || $end === null) continue;

            /* کدهای سبک {\i1} و شکست خط \N */
            $text = preg_replace('/\{[^}]*\}/', '', $parts[9]) ?? $parts[9];
            $text = str_replace(['\\N', '\\n', '\\h'], ["\n", "\n", ' '], $text);
            $text = trim($text);
            if ($text === '') continue;

            $out .= $start . ' --> ' . $end . "\n" . $text . "\n\n";
            $cues++;
        }

        if ($cues === 0) {
            return ['ok' => false, 'message' => 'هیچ دیالوگی در فایل ASS پیدا نشد',
                    'vtt' => '', 'cues' => 0];
        }
        return ['ok' => true, 'message' => '', 'vtt' => $out, 'cues' => $cues];
    }

    /** «0:00:01.50» (صدم ثانیه) → «00:00:01.500» */
    private function assTime(string $t): ?string
    {
        if (!preg_match('/^(\d+):(\d{2}):(\d{2})\.(\d{2})$/', $t, $m)) return null;
        return sprintf('%02d:%02d:%02d.%03d',
            (int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4] * 10);
    }

    // ══════════════════════════════════════════════════════════════
    //  ذخیره
    // ══════════════════════════════════════════════════════════════

    /**
     * ذخیره‌ی زیرنویس آپلودشده.
     *
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array{ok:bool,message:string,id:int}
     */
    public function store(int $tenantId, int $vodId, array $file, array $meta): array
    {
        $fail = static fn(string $m): array => ['ok' => false, 'message' => $m, 'id' => 0];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $fail('آپلود فایل ناموفق بود');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return $fail('فایل زیرنویس بیش از حد بزرگ است (سقف ۵ مگابایت)');
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::FORMATS, true)) {
            return $fail('قالب پشتیبانی نمی‌شود. مجاز: ' . implode('، ', self::FORMATS));
        }

        $raw = @file_get_contents($file['tmp_name']);
        if ($raw === false) return $fail('فایل خوانده نشد');

        $conv = $this->toVtt($raw, $ext);
        if (!$conv['ok']) return $fail($conv['message']);

        $lang = preg_replace('/[^a-z-]/', '', strtolower(trim((string)($meta['lang'] ?? '')))) ?? '';
        if ($lang === '') return $fail('کد زبان لازم است');

        $dir = PUBLIC_PATH . '/uploads/subtitles/' . $vodId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return $fail('پوشه‌ی زیرنویس ساخته نشد');
        }

        $isSdh = !empty($meta['is_sdh']) ? 1 : 0;
        $name  = $lang . ($isSdh ? '-sdh' : '') . '.vtt';
        $path  = $dir . '/' . $name;

        if (@file_put_contents($path, $conv['vtt']) === false) {
            return $fail('فایل زیرنویس ذخیره نشد');
        }

        $rel = '/uploads/subtitles/' . $vodId . '/' . $name;

        /* اگر همین زبان از قبل هست، جایگزین شود نه اینکه خطا بدهد —
           اپراتور معمولا فایل بهتری پیدا کرده و دوباره آپلود می‌کند. */
        $existing = $this->db->row(
            'SELECT id FROM vod_subtitles WHERE vod_id = ? AND lang = ? AND is_sdh = ?',
            [$vodId, $lang, $isSdh]
        );

        $row = [
            'tenant_id'     => $tenantId,
            'vod_id'        => $vodId,
            'lang'          => $lang,
            'label'         => trim((string)($meta['label'] ?? '')) ?: $this->langLabel($lang),
            'source_format' => in_array($ext, ['srt','vtt','ass','sub'], true) ? $ext : 'srt',
            'file_path'     => $rel,
            'file_size'     => strlen($conv['vtt']),
            'is_sdh'        => $isSdh,
            'is_default'    => !empty($meta['is_default']) ? 1 : 0,
        ];

        if ($existing) {
            $this->db->update('vod_subtitles', $row, ['id' => (int)$existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $id = (int)$this->db->insert('vod_subtitles', $row);
        }

        /* فقط یکی پیش‌فرض بماند، وگرنه مرورگر دلبخواهی یکی را می‌گیرد */
        if ($row['is_default']) {
            $this->db->query(
                'UPDATE vod_subtitles SET is_default = 0 WHERE vod_id = ? AND id <> ?',
                [$vodId, $id]
            );
        }

        return ['ok' => true, 'message' => $conv['cues'] . ' زیرنویس وارد شد', 'id' => $id];
    }

    public function delete(int $id): bool
    {
        $row = $this->db->row('SELECT file_path FROM vod_subtitles WHERE id = ?', [$id]);
        if (!$row) return false;

        $p = PUBLIC_PATH . '/' . ltrim((string)$row['file_path'], '/');
        if (is_file($p)) @unlink($p);

        $this->db->delete('vod_subtitles', ['id' => $id]);
        return true;
    }

    // ══════════════════════════════════════════════════════════════
    //  خواندن برای پلیر
    // ══════════════════════════════════════════════════════════════

    /**
     * زیرنویس‌ها و باندهای صوتی یک ویدیو، آماده برای پلیر.
     *
     * @return array{subtitles:list<array<string,mixed>>,audio:list<array<string,mixed>>}
     */
    public function forPlayer(int $vodId, ?string $preferredLang = null): array
    {
        $subs = $this->db->rows(
            'SELECT id, lang, label, file_path, is_sdh, is_default
               FROM vod_subtitles WHERE vod_id = ? ORDER BY sort_order, label',
            [$vodId]
        );
        $audio = $this->db->rows(
            'SELECT id, lang, label, kind, file_path, track_index, is_default
               FROM vod_audio_tracks WHERE vod_id = ? ORDER BY sort_order, label',
            [$vodId]
        );

        /* زبان مهمان بر پیش‌فرض اپراتور مقدم است: مهمانی که فارسی
           نمی‌داند نباید در منوی ناآشنا دنبال تنظیمات بگردد. */
        if ($preferredLang) {
            $this->preferLang($subs, $preferredLang);
            $this->preferLang($audio, $preferredLang);
        }

        return ['subtitles' => $subs, 'audio' => $audio];
    }

    /** @param list<array<string,mixed>> $rows */
    private function preferLang(array &$rows, string $lang): void
    {
        $found = false;
        foreach ($rows as &$r) {
            $match = ($r['lang'] === $lang);
            $r['is_default'] = $match ? 1 : 0;
            if ($match) $found = true;
        }
        unset($r);

        /* اگر زبان مهمان موجود نبود، پیش‌فرض اپراتور دست‌نخورده بماند
           — خالی‌کردن همه یعنی مهمان هیچ زیرنویسی نمی‌بیند. */
        if (!$found) {
            foreach ($rows as $i => $r) {
                $rows[$i]['is_default'] = $i === 0 ? 1 : 0;
            }
        }
    }

    /** نام خوانا برای کد زبان */
    public function langLabel(string $code): string
    {
        return [
            'fa' => 'فارسی',   'en' => 'English',  'ar' => 'العربية',
            'tr' => 'Türkçe',  'ru' => 'Русский',  'fr' => 'Français',
            'de' => 'Deutsch', 'zh' => '中文',      'hi' => 'हिन्दी',
            'ur' => 'اردو',    'es' => 'Español',
        ][$code] ?? strtoupper($code);
    }
}
