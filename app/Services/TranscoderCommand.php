<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ساخت خط فرمان ffmpeg برای یک کار ترنسکد — تابع خالص.
 *
 * هیچ چیزی اجرا نمی‌کند و به دیتابیس و فایل‌سیستم دست نمی‌زند، تا
 * هر ترکیب ورودی/کدک/خروجی بدون سرور واقعی تست شود. اجرا و نظارت در
 * TranscoderService است.
 *
 * ── معادل قابلیت‌های «رامند» کاماسیستم ─────────────────────────────
 *   ورودی:  UDP، RTP، RTSP، RTMP، HTTP، HLS، MPEG-DASH، SRT، فایل،
 *           کارت کپچر (v4l2 برای HDMI/کامپوزیت، DeckLink برای SDI)
 *   تصویر:  H.264، H.265، MPEG-2 یا copy؛ اندازه، بیت‌ریت، نرخ فریم، preset
 *   صدا:    AAC، MP3، MP2 یا copy؛ بیت‌ریت، مونو/استریو، فیلتر زبان
 *   زیرنویس: عبور زیرنویس با فیلتر زبان روی خروجی MPEG-TS
 *   خروجی:  HLS چندکیفیتی (ABR)، UDP multicast، RTMP، SRT — هم‌زمان
 *   HLS VOD برای فایل (حالت آفلاین)
 * و چیزهایی که برای هتل لازم است: لوگو روی تصویر، شتاب سخت‌افزاری
 * NVIDIA/Intel، و جدایی خروجی‌ها از هم (یک مقصد RTMP مرده کانال
 * multicast را نمی‌اندازد).
 *
 * «HDS» ورودی نیست: پروتکل منسوخ Adobe است و ffmpeg اصلا demuxer اش
 * را ندارد. «بدون تداخل بین استریم‌ها» با این برآورده می‌شود که هر کار
 * فرایند ffmpeg جدای خودش را دارد.
 */
final class TranscoderCommand
{
    public const VIDEO_CODECS = ['h264', 'hevc', 'mpeg2', 'copy'];
    public const AUDIO_CODECS = ['aac', 'mp3', 'mp2', 'copy', 'none'];
    public const HW           = ['cpu', 'nvenc', 'qsv', 'vaapi'];
    public const PRESETS      = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium'];
    public const OUTPUTS      = ['hls', 'udp', 'rtmp', 'srt'];
    public const POSITIONS    = ['tr', 'tl', 'br', 'bl'];

    /** نگاشت preset نرم‌افزاری به preset انکودر NVIDIA (p1 سریع‌ترین) */
    private const NVENC_PRESET = [
        'ultrafast' => 'p1', 'superfast' => 'p2', 'veryfast' => 'p3',
        'faster' => 'p4', 'fast' => 'p5', 'medium' => 'p6',
    ];

    /**
     * تنظیمات را پاک و کامل می‌کند. هر چیزی که ffmpeg را خراب کند یا
     * مسیر دلخواه روی دیسک بسازد اینجا رد می‌شود.
     *
     * @return array{ok:bool,message:string,settings:array}
     */
    public static function normalize(array $in, string $mode, string $inputKind): array
    {
        $bad = static fn(string $m) => ['ok' => false, 'message' => $m, 'settings' => []];

        $v = (array)($in['video'] ?? []);
        $video = [
            'codec'       => in_array($v['codec'] ?? '', self::VIDEO_CODECS, true) ? $v['codec'] : 'h264',
            'hw'          => in_array($v['hw'] ?? '', self::HW, true) ? $v['hw'] : 'cpu',
            'preset'      => in_array($v['preset'] ?? '', self::PRESETS, true) ? $v['preset'] : 'veryfast',
            'fps'         => in_array((int)($v['fps'] ?? 0), [0, 24, 25, 30, 50, 60], true) ? (int)($v['fps'] ?? 0) : 0,
            'gop_seconds' => max(1, min(10, (int)($v['gop_seconds'] ?? 2))),
            'deinterlace' => !empty($v['deinterlace']),
            'low_latency' => !empty($v['low_latency']),
        ];
        if ($video['codec'] === 'mpeg2' && $video['hw'] !== 'cpu') {
            return $bad('MPEG-2 انکودر سخت‌افزاری ندارد — پردازنده را انتخاب کنید');
        }
        if ($video['codec'] === 'copy') $video['hw'] = 'cpu';

        // ── کیفیت‌ها (ABR) ─────────────────────────────────────────
        $rend = [];
        foreach (array_slice((array)($in['renditions'] ?? []), 0, 5) as $r) {
            $h  = (int)($r['height'] ?? 0);
            $br = (int)($r['bitrate'] ?? 0);
            if (!in_array($h, [240, 360, 480, 576, 720, 1080, 1440, 2160], true)) {
                return $bad('ارتفاع تصویر نامعتبر است: ' . $h);
            }
            if ($br < 200 || $br > 50000) return $bad('بیت‌ریت هر کیفیت باید بین ۲۰۰ تا ۵۰۰۰۰ کیلوبیت باشد');
            $rend[] = ['height' => $h, 'bitrate' => $br];
        }
        if ($video['codec'] !== 'copy') {
            if (!$rend) $rend = [['height' => 720, 'bitrate' => 2500]];
            /* بالاترین کیفیت اول: خروجی‌های MPEG-TS همان را می‌گیرند */
            usort($rend, static fn($a, $b) => $b['height'] <=> $a['height'] ?: $b['bitrate'] <=> $a['bitrate']);
        } else {
            $rend = [];
        }

        // ── صدا ────────────────────────────────────────────────────
        $a = (array)($in['audio'] ?? []);
        $langs = [];
        foreach ((array)($a['languages'] ?? []) as $l) {
            $l = strtolower(trim((string)$l));
            if (preg_match('/^[a-z]{3}$/', $l)) $langs[] = $l;   // ISO 639-2، همان که MPEG-TS دارد
        }
        $audio = [
            'codec'       => in_array($a['codec'] ?? '', self::AUDIO_CODECS, true) ? $a['codec'] : 'aac',
            'bitrate'     => in_array((int)($a['bitrate'] ?? 128), [64, 96, 128, 160, 192, 256, 320], true) ? (int)($a['bitrate'] ?? 128) : 128,
            'channels'    => (int)($a['channels'] ?? 2) === 1 ? 1 : 2,
            'sample_rate' => (int)($a['sample_rate'] ?? 48000) === 44100 ? 44100 : 48000,
            'languages'   => array_values(array_unique(array_slice($langs, 0, 4))),
        ];

        // ── زیرنویس ────────────────────────────────────────────────
        $s = (array)($in['subtitles'] ?? []);
        $slangs = [];
        foreach ((array)($s['languages'] ?? []) as $l) {
            $l = strtolower(trim((string)$l));
            if (preg_match('/^[a-z]{3}$/', $l)) $slangs[] = $l;
        }
        $subs = [
            'mode'      => ($s['mode'] ?? 'none') === 'copy' ? 'copy' : 'none',
            'languages' => array_values(array_unique(array_slice($slangs, 0, 6))),
        ];

        // ── لوگو ───────────────────────────────────────────────────
        $o = (array)($in['overlay'] ?? []);
        $img = trim((string)($o['image'] ?? ''));
        /* فقط فایل‌های آپلودشده‌ی خود سامانه — نه مسیر دلخواه روی دیسک */
        if ($img !== '' && !preg_match('#^/uploads/[A-Za-z0-9_./\-]+\.(png|jpg|jpeg)$#', $img)) {
            return $bad('لوگو باید یک فایل PNG یا JPG آپلودشده باشد');
        }
        if ($img !== '' && str_contains($img, '..')) return $bad('مسیر لوگو نامعتبر است');
        if ($img !== '' && $video['codec'] === 'copy') return $bad('لوگو روی تصویر نیاز به انکود دارد — کدک copy با لوگو ممکن نیست');
        $overlay = [
            'image'     => $img,
            'position'  => in_array($o['position'] ?? '', self::POSITIONS, true) ? $o['position'] : 'tr',
            /* درصد ارتفاع تصویر — ۸٪ یعنی ۵۸ پیکسل روی ۷۲۰ */
            'scale_pct' => max(3, min(25, (int)($o['scale_pct'] ?? 8))),
            'margin'    => max(0, min(200, (int)($o['margin'] ?? 24))),
        ];

        // ── ورودی ──────────────────────────────────────────────────
        $i = (array)($in['input'] ?? []);
        $input = [
            'rtsp_transport' => ($i['rtsp_transport'] ?? 'tcp') === 'udp' ? 'udp' : 'tcp',
            'loop'           => !empty($i['loop']) && $inputKind === 'file' && $mode === 'live',
            'alsa'           => preg_match('/^hw:\d+(,\d+)?$/', (string)($i['alsa'] ?? '')) ? (string)$i['alsa'] : '',
        ];

        // ── خروجی‌ها ───────────────────────────────────────────────
        $outs = [];
        foreach (array_slice((array)($in['outputs'] ?? []), 0, 6) as $out) {
            $t = (string)($out['type'] ?? '');
            if (!in_array($t, self::OUTPUTS, true)) return $bad('نوع خروجی نامعتبر است');
            if ($t === 'hls') {
                $outs[] = [
                    'type'    => 'hls',
                    'segment' => max(1, min(10, (int)($out['segment'] ?? 4))),
                    'window'  => max(3, min(30, (int)($out['window'] ?? 6))),
                ];
                continue;
            }
            $url = trim((string)($out['url'] ?? ''));
            $scheme = ['udp' => 'udp', 'rtmp' => 'rtmps?', 'srt' => 'srt'][$t];
            if (!preg_match('#^' . $scheme . '://[^\s\'"|\[\]]+$#i', $url)) {
                return $bad("آدرس خروجی $t نامعتبر است");
            }
            $outs[] = ['type' => $t, 'url' => $url, 'ttl' => max(1, min(32, (int)($out['ttl'] ?? 4)))];
        }
        $types = array_column($outs, 'type');
        if (!$outs) return $bad('حداقل یک خروجی لازم است');
        if (count(array_keys($types, 'hls', true)) > 1) return $bad('فقط یک خروجی HLS مجاز است');
        if ($mode === 'vod' && $types !== ['hls']) {
            return $bad('کار فایل (VOD) فقط خروجی HLS دارد — پخش زنده‌ی فایل را با حالت live و «تکرار» بسازید');
        }
        /* RTMP فقط FLV می‌شناسد و FLV فقط H.264 و AAC */
        if (in_array('rtmp', $types, true)) {
            if (!in_array($video['codec'], ['h264', 'copy'], true) || !in_array($audio['codec'], ['aac', 'copy', 'none'], true)) {
                return $bad('خروجی RTMP فقط H.264 و AAC را می‌پذیرد');
            }
        }
        if ($video['codec'] === 'mpeg2' && in_array('hls', $types, true)) {
            return $bad('MPEG-2 روی HLS پخش نمی‌شود — برای HLS از H.264 استفاده کنید');
        }

        return ['ok' => true, 'message' => '', 'settings' => [
            'video' => $video, 'renditions' => $rend, 'audio' => $audio, 'subtitles' => $subs,
            'overlay' => $overlay, 'input' => $input, 'outputs' => $outs,
        ]];
    }

    /**
     * @param array $job   ردیف transcoder_jobs با settings نرمال‌شده (آرایه)
     * @param array $env   ffmpeg, out_dir, logo_path (مسیر واقعی روی دیسک)، rtsp_timeout_opt، vaapi_device
     * @return list<string> آرگومان‌ها؛ اولی خود باینری
     */
    public static function build(array $job, array $env): array
    {
        $s      = $job['settings'];
        $mode   = (string)$job['mode'];
        $kind   = (string)$job['input_kind'];
        $url    = (string)$job['input_url'];
        $v      = $s['video'];
        $a      = $s['audio'];
        $out    = rtrim((string)$env['out_dir'], '/');
        $copy   = $v['codec'] === 'copy';
        $live   = $mode === 'live';

        /* پیشرفت و لاگ در پوشه‌ی کاری خصوصی؛ خروجی VOD در مسیر عمومی
           است و نباید لاگ ffmpeg (که آدرس ورودی را دارد) کنارش باشد. */
        $work = rtrim((string)($env['work_dir'] ?? $out), '/');
        $cmd = [(string)$env['ffmpeg'], '-hide_banner', '-nostdin', '-loglevel', 'warning',
                '-progress', $work . '/progress.txt', '-y'];

        // ── سخت‌افزار ──────────────────────────────────────────────
        if (!$copy && $v['hw'] === 'vaapi') {
            array_push($cmd, '-vaapi_device', (string)($env['vaapi_device'] ?? '/dev/dri/renderD128'));
        }
        if (!$copy && $v['hw'] === 'qsv') {
            array_push($cmd, '-init_hw_device', 'qsv=hw', '-filter_hw_device', 'hw');
        }

        // ── ورودی ──────────────────────────────────────────────────
        $cmd = array_merge($cmd, self::inputArgs($kind, $url, $s['input'], $live, $env));
        $hasAlsa = $kind === 'v4l2' && $s['input']['alsa'] !== '';
        if ($hasAlsa) array_push($cmd, '-f', 'alsa', '-i', $s['input']['alsa']);
        $audioIn = $hasAlsa ? 1 : 0;

        $logoIn = null;
        if (!$copy && $s['overlay']['image'] !== '' && !empty($env['logo_path'])) {
            $logoIn = $hasAlsa ? 2 : 1;
            array_push($cmd, '-i', (string)$env['logo_path']);
        }

        // ── فیلتر تصویر ────────────────────────────────────────────
        $hls   = null; $ts = [];
        foreach ($s['outputs'] as $o) { if ($o['type'] === 'hls') $hls = $o; else $ts[] = $o; }

        $rend  = $s['renditions'];
        /* خروجی MPEG-TS بالاترین کیفیت را می‌گیرد؛ اگر HLS هم هست، یک
           شاخه‌ی اضافه از همان تصویر لازم است چون هر برچسب فیلتر فقط یک
           بار مصرف می‌شود. */
        $vLabels = [];
        if (!$copy) {
            $n     = ($hls ? count($rend) : 0) + ($ts ? 1 : 0);
            $chain = '[0:v:0]';
            $pre   = [];
            if ($v['deinterlace']) $pre[] = 'yadif=deint=interlaced';
            if ($v['fps'] > 0)     $pre[] = 'fps=' . $v['fps'];
            $pre[] = "split=$n";
            $graph = $chain . implode(',', $pre);
            for ($k = 0; $k < $n; $k++) $graph .= "[s$k]";

            /* لوگو بعد از تغییر اندازه روی هر کیفیت جدا می‌نشیند و اندازه‌اش
               درصدی از ارتفاع همان کیفیت است. ارتفاع‌ها موقع ساخت فرمان
               معلوم‌اند، پس فقط scale و overlay لازم است — scale2ref که
               اندازه را از خود تصویر می‌خواند در ffmpeg ۷ منسوخ شده. */
            if ($logoIn !== null) {
                $graph .= ";[$logoIn:v]format=rgba,split=$n";
                for ($k = 0; $k < $n; $k++) $graph .= "[lg$k]";
            }
            $ov = $s['overlay'];
            $m  = (int)$ov['margin'];
            $pos = ['tr' => "W-w-$m:$m", 'tl' => "$m:$m", 'br' => "W-w-$m:H-h-$m", 'bl' => "$m:H-h-$m"][$ov['position']];

            for ($k = 0; $k < $n; $k++) {
                $r = ($hls && $k < count($rend)) ? $rend[$k] : $rend[0];
                /* بزرگ‌نمایی نمی‌کنیم: ورودی ۵۷۶ خط، خروجی ۱۰۸۰ نمی‌سازد */
                $graph .= ";[s$k]scale=-2:'min({$r['height']},ih)'";
                if ($logoIn !== null) {
                    $lh = max(16, (int)round($r['height'] * $ov['scale_pct'] / 100 / 2) * 2);
                    $graph .= "[sc$k];[lg$k]scale=-2:$lh" . "[lgs$k];[sc$k][lgs$k]overlay=$pos";
                }
                if ($v['hw'] === 'vaapi') $graph .= ',format=nv12,hwupload';
                elseif ($v['hw'] === 'qsv') $graph .= ',format=nv12,hwupload=extra_hw_frames=64';
                else $graph .= ',format=yuv420p';
                $graph .= "[vo$k]";
                $vLabels[] = "[vo$k]";
            }
            array_push($cmd, '-filter_complex', $graph);
        }

        $meta = ['-metadata', 'service_name=' . self::safeMeta((string)($job['name'] ?? 'Hotel Media')),
                 '-metadata', 'service_provider=Hotel Media'];

        // ── خروجی HLS ──────────────────────────────────────────────
        if ($hls) {
            $vcount = $copy ? 1 : count($rend);
            for ($k = 0; $k < $vcount; $k++) {
                array_push($cmd, '-map', $copy ? '0:v:0' : $vLabels[$k]);
            }
            $aTracks = self::mapAudio($cmd, $a, $audioIn);

            for ($k = 0; $k < $vcount; $k++) {
                $cmd = array_merge($cmd, self::videoEnc($v, $copy ? null : $rend[$k], $k, $live));
            }
            $cmd = array_merge($cmd, self::audioEnc($a));

            $segs = $hls['segment'];
            $vsm = [];
            if ($aTracks > 0) {
                for ($t = 0; $t < $aTracks; $t++) {
                    $lang = $a['languages'][$t] ?? '';
                    $vsm[] = "a:$t,agroup:aud" . ($lang !== '' ? ",language:$lang" : '') . ($t === 0 ? ',default:yes' : '');
                }
                for ($k = 0; $k < $vcount; $k++) $vsm[] = "v:$k,agroup:aud";
            } else {
                for ($k = 0; $k < $vcount; $k++) $vsm[] = "v:$k";
            }

            array_push($cmd, '-f', 'hls', '-hls_time', (string)$segs);
            if ($live) {
                array_push($cmd,
                    '-hls_list_size', (string)$hls['window'],
                    /* delete_segments: دیسک پر نمی‌شود. temp_file: تلویزیون
                       پلی‌لیستِ نیمه‌نوشته نمی‌خواند. */
                    '-hls_flags', 'delete_segments+independent_segments+temp_file',
                    '-hls_delete_threshold', '2');
            } else {
                array_push($cmd, '-hls_playlist_type', 'vod', '-hls_list_size', '0',
                    '-hls_flags', 'independent_segments');
            }
            $cmd = array_merge($cmd, $meta);
            array_push($cmd,
                '-master_pl_name', 'index.m3u8',
                '-hls_segment_filename', $out . '/v%v_%05d.ts',
                '-var_stream_map', implode(' ', $vsm),
                $out . '/v%v.m3u8');
        }

        // ── خروجی‌های MPEG-TS/FLV (UDP، SRT، RTMP) با tee ─────────────
        if ($ts) {
            array_push($cmd, '-map', $copy ? '0:v:0' : $vLabels[count($vLabels) - 1]);
            self::mapAudio($cmd, $a, $audioIn);
            if ($s['subtitles']['mode'] === 'copy') {
                if ($s['subtitles']['languages']) {
                    foreach ($s['subtitles']['languages'] as $l) array_push($cmd, '-map', "0:s:m:language:$l?");
                } else {
                    array_push($cmd, '-map', '0:s?');
                }
                array_push($cmd, '-c:s', 'copy');
            }
            $cmd = array_merge($cmd, self::videoEnc($v, $copy ? null : $rend[0], 0, $live));
            $cmd = array_merge($cmd, self::audioEnc($a));
            $cmd = array_merge($cmd, $meta);

            $slaves = [];
            foreach ($ts as $o) {
                /* onfail=ignore: یک مقصد مرده بقیه را نمی‌اندازد */
                if ($o['type'] === 'udp') {
                    $u = $o['url'];
                    $u .= (str_contains($u, '?') ? '&' : '?') . 'pkt_size=1316&ttl=' . $o['ttl'];
                    $slaves[] = '[f=mpegts:onfail=ignore]' . $u;
                } elseif ($o['type'] === 'srt') {
                    $slaves[] = '[f=mpegts:onfail=ignore]' . $o['url'];
                } else {
                    $slaves[] = '[f=flv:onfail=ignore]' . $o['url'];
                }
            }
            array_push($cmd, '-f', 'tee', implode('|', $slaves));
        }

        return $cmd;
    }

    // ══════════════════════════════════════════════════════════════

    private static function inputArgs(string $kind, string $url, array $in, bool $live, array $env): array
    {
        if ($kind === 'v4l2')     return ['-f', 'v4l2', '-thread_queue_size', '512', '-i', $url];
        if ($kind === 'decklink') return ['-f', 'decklink', '-i', $url];

        if ($kind === 'file') {
            $a = [];
            if ($in['loop']) array_push($a, '-stream_loop', '-1');
            /* پخش زنده‌ی فایل باید با سرعت واقعی باشد؛ کار VOD با حداکثر سرعت */
            if ($live) $a[] = '-re';
            array_push($a, '-i', $url);
            return $a;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $a = ['-fflags', '+genpts+discardcorrupt'];

        if ($scheme === 'rtsp' || $scheme === 'rtsps') {
            array_push($a, '-rtsp_transport', $in['rtsp_transport']);
            /* نام گزینه بین نسخه‌های ffmpeg عوض شده؛ سرویس آن را می‌پرسد */
            if (!empty($env['rtsp_timeout_opt'])) array_push($a, (string)$env['rtsp_timeout_opt'], '10000000');
        } elseif ($scheme === 'udp' || $scheme === 'rtp') {
            /* بافر بزرگ‌تر: multicast ماهواره‌ای در لحظه‌ی بار بالای سرور
               بسته گم می‌کند و تصویر شطرنجی می‌شود. */
            if ($scheme === 'udp' && !str_contains($url, 'fifo_size')) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'fifo_size=1000000&overrun_nonfatal=1';
            }
        } elseif ($scheme === 'http' || $scheme === 'https') {
            /* منبع HTTP/HLS/DASH گاهی قطع می‌شود؛ بدون reconnect کار می‌میرد */
            array_push($a, '-reconnect', '1', '-reconnect_streamed', '1', '-reconnect_delay_max', '5',
                           '-rw_timeout', '15000000');
        } elseif ($scheme === 'rtmp' || $scheme === 'rtmps' || $scheme === 'srt') {
            array_push($a, '-rw_timeout', '15000000');
        }

        array_push($a, '-i', $url);
        return $a;
    }

    /** نگاشت صدا؛ تعداد باندهای نگاشت‌شده را برمی‌گرداند */
    private static function mapAudio(array &$cmd, array $a, int $input): int
    {
        if ($a['codec'] === 'none') return 0;
        if ($a['languages']) {
            /* «?» یعنی اگر آن زبان در ورودی نبود خطا نده */
            foreach ($a['languages'] as $l) array_push($cmd, '-map', "$input:a:m:language:$l?");
            return count($a['languages']);
        }
        array_push($cmd, '-map', "$input:a:0?");
        return 1;
    }

    private static function videoEnc(array $v, ?array $r, int $k, bool $live): array
    {
        if ($r === null) return ["-c:v:$k", 'copy'];

        $enc = [
            'h264'  => ['cpu' => 'libx264', 'nvenc' => 'h264_nvenc', 'qsv' => 'h264_qsv', 'vaapi' => 'h264_vaapi'],
            'hevc'  => ['cpu' => 'libx265', 'nvenc' => 'hevc_nvenc', 'qsv' => 'hevc_qsv', 'vaapi' => 'hevc_vaapi'],
            'mpeg2' => ['cpu' => 'mpeg2video'],
        ][$v['codec']][$v['hw']];

        $br = (int)$r['bitrate'];
        $a  = ["-c:v:$k", $enc,
               "-b:v:$k", $br . 'k',
               "-maxrate:v:$k", (int)round($br * 1.1) . 'k',
               "-bufsize:v:$k", ($br * 2) . 'k'];

        /* فریم کلیدی بر حسب زمان نه شماره‌ی فریم: با نرخ فریم نامعلوم
           ورودی هم مرز قطعه‌های HLS بین کیفیت‌ها یکی می‌ماند و تلویزیون
           بدون پرش بین کیفیت‌ها جابه‌جا می‌شود. */
        array_push($a, "-force_key_frames:v:$k", 'expr:gte(t,n_forced*' . (int)$v['gop_seconds'] . ')');

        if ($enc === 'libx264' || $enc === 'libx265') {
            array_push($a, "-preset:v:$k", $v['preset']);
            if ($enc === 'libx264') {
                /* بدون این، x264 روی تغییر صحنه فریم کلیدی اضافه می‌گذارد و
                   مرز قطعه‌ها بین کیفیت‌ها ناهمسان می‌شود */
                array_push($a, "-sc_threshold:v:$k", '0');
                /* high/4.1: همه‌ی تلویزیون‌های هتلی از ۲۰۱۳ به بعد پخشش می‌کنند */
                array_push($a, "-profile:v:$k", 'high', "-level:v:$k", $r['height'] > 1080 ? '5.1' : '4.1');
            }
            if ($v['low_latency'] && $live) array_push($a, "-tune:v:$k", 'zerolatency');
            if ($enc === 'libx265') array_push($a, "-tag:v:$k", 'hvc1');
        } elseif (str_ends_with($enc, '_nvenc')) {
            array_push($a, "-preset:v:$k", self::NVENC_PRESET[$v['preset']], "-rc:v:$k", 'cbr');
            if ($enc === 'h264_nvenc') array_push($a, "-profile:v:$k", 'high');
        } elseif ($enc === 'mpeg2video') {
            /* سیگنال پخش تلویزیونی MPEG-2 باید B-frame محدود داشته باشد */
            array_push($a, "-bf:v:$k", '2');
        }
        return $a;
    }

    private static function audioEnc(array $a): array
    {
        if ($a['codec'] === 'none') return ['-an'];
        if ($a['codec'] === 'copy') return ['-c:a', 'copy'];
        $enc = ['aac' => 'aac', 'mp3' => 'libmp3lame', 'mp2' => 'mp2'][$a['codec']];
        return ['-c:a', $enc, '-b:a', $a['bitrate'] . 'k', '-ac', (string)$a['channels'], '-ar', (string)$a['sample_rate']];
    }

    /** نام کانال در متادیتای MPEG-TS — کاراکتر نقل‌قول و جداکننده‌ی tee خط فرمان را می‌شکند */
    private static function safeMeta(string $s): string
    {
        return mb_substr(preg_replace('/[\'"|\[\]:=\\\\]/u', '', $s) ?? '', 0, 60);
    }
}
