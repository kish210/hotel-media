<?php
declare(strict_types=1);

namespace App\Services;

/**
 * درایورهای ترنسکدر و کارت کپچر — نصب از اینترنت یا بارگذاری دستی.
 *
 * سرور هتل اغلب پشت فیلترینگ یا بی‌اینترنت است، پس هر درایور دو راه
 * دارد: apt (وقتی مخزن در دسترس است) و بارگذاری فایلی که IT از جای
 * دیگری آورده (.deb، آرشیو .tar.gz از .deb ها، یا .run درایور NVIDIA).
 *
 * PHP با کاربر www-data اجرا می‌شود و نصب بسته root می‌خواهد. پل بین
 * این دو /usr/local/sbin/hotel-media-driver است (deploy/hotel-media-driver.sh)
 * که sudoers فقط همان را مجاز کرده؛ همه‌ی بررسی‌های امنیتی آنجاست و
 * این کلاس فقط فهرست، وضعیت و اجرای پس‌زمینه را نگه می‌دارد.
 *
 * بارگذاری دستی یعنی super_admin می‌تواند هر بسته‌ای را با root نصب
 * کند — ذات همین قابلیت است. برای همین صفحه فقط برای super_admin است.
 */
final class DriverService
{
    public const HELPER = '/usr/local/sbin/hotel-media-driver';

    /**
     * id => [نام، کاربرد، راه آنلاین؟، پسوندهای مجاز، راهنمای دانلود دستی]
     */
    public const CATALOG = [
        'ffmpeg' => [
            'name'   => 'FFmpeg',
            'why'    => 'هسته‌ی ترنسکدر، دوربین‌ها و تبدیل ویدیو — بدون آن هیچ‌کدام کار نمی‌کند',
            'online' => true,
            'types'  => ['deb', 'tar.gz'],
            'manual' => 'بسته‌ی ffmpeg و وابستگی‌هایش برای همین نسخه‌ی اوبونتو (apt-get download روی سیستمی با اینترنت)',
        ],
        'intel' => [
            'name'   => 'Intel Media Driver (QSV / VAAPI)',
            'why'    => 'انکود سخت‌افزاری روی پردازنده‌های Intel — چند برابر کانال با همان سرور',
            'online' => true,
            'types'  => ['deb', 'tar.gz'],
            'manual' => 'intel-media-va-driver-non-free و vainfo',
        ],
        'amd' => [
            'name'   => 'AMD / Mesa VAAPI',
            'why'    => 'انکود سخت‌افزاری روی گرافیک AMD',
            'online' => true,
            'types'  => ['deb', 'tar.gz'],
            'manual' => 'mesa-va-drivers و vainfo',
        ],
        'nvidia' => [
            'name'   => 'NVIDIA (NVENC)',
            'why'    => 'انکود سخت‌افزاری روی کارت گرافیک NVIDIA — بیشترین تعداد کانال',
            'online' => true,
            'types'  => ['deb', 'tar.gz', 'run'],
            'manual' => 'فایل NVIDIA-Linux-x86_64-*.run از nvidia.com/drivers (نیاز به راه‌اندازی دوباره)',
        ],
        'decklink' => [
            'name'   => 'Blackmagic DeckLink (Desktop Video)',
            'why'    => 'کارت کپچر SDI و HDMI حرفه‌ای Blackmagic',
            'online' => false,
            'types'  => ['deb', 'tar.gz'],
            'manual' => 'Desktop Video برای لینوکس از blackmagicdesign.com/support — آرشیو .tar.gz یا desktopvideo_*.deb',
        ],
        'v4l' => [
            'name'   => 'Video4Linux (v4l-utils)',
            'why'    => 'ابزار شناسایی کارت‌های کپچر HDMI/کامپوزیت USB و PCIe',
            'online' => true,
            'types'  => ['deb', 'tar.gz'],
            'manual' => 'v4l-utils',
        ],
    ];

    private string $dir;

    public function __construct()
    {
        $this->dir = STORAGE_PATH . '/drivers';
        foreach (['', '/incoming', '/logs'] as $d) {
            if (!is_dir($this->dir . $d)) @mkdir($this->dir . $d, 0775, true);
        }
    }

    public function supported(): bool { return PHP_OS_FAMILY === 'Linux'; }

    /** پل root نصب و sudo برایش تنظیم شده؟ */
    public function helperReady(): bool
    {
        if (!$this->supported()) return false;
        return trim((string)shell_exec('sudo -n ' . self::HELPER . ' check 2>/dev/null')) === 'ok';
    }

    /** وضعیت همه‌ی درایورها — بدون root */
    public function all(): array
    {
        $pci = strtolower((string)shell_exec('command -v lspci >/dev/null 2>&1 && lspci 2>/dev/null'));
        $out = [];
        foreach (self::CATALOG as $id => $d) {
            $out[] = $d + [
                'id'        => $id,
                'version'   => $this->installedVersion($id),
                'hardware'  => $this->hardware($id, $pci),
                'job'       => $this->job($id),
            ];
        }
        return $out;
    }

    private function installedVersion(string $id): string
    {
        $dpkg = static function (string ...$pkgs): string {
            foreach ($pkgs as $p) {
                $v = trim((string)shell_exec("dpkg-query -W -f='\${Status} \${Version}' " . escapeshellarg($p) . ' 2>/dev/null'));
                if (str_starts_with($v, 'install ok installed ')) return substr($v, strlen('install ok installed '));
            }
            return '';
        };
        switch ($id) {
            case 'ffmpeg':
                $l = (string)shell_exec('ffmpeg -version 2>/dev/null | head -1');
                return preg_match('/ffmpeg version (\S+)/', $l, $m) ? $m[1] : '';
            case 'intel':    return $dpkg('intel-media-va-driver-non-free', 'intel-media-va-driver');
            case 'amd':      return $dpkg('mesa-va-drivers');
            case 'v4l':      return $dpkg('v4l-utils');
            case 'decklink': return $dpkg('desktopvideo');
            case 'nvidia':
                $v = trim((string)shell_exec('nvidia-smi --query-gpu=driver_version --format=csv,noheader 2>/dev/null | head -1'));
                return preg_match('/^[\d.]+$/', $v) ? $v : '';
        }
        return '';
    }

    /** true/false اگر معلوم است، null اگر lspci نیست یا بی‌ربط است */
    private function hardware(string $id, string $pci): ?bool
    {
        if ($pci === '') return null;
        return match ($id) {
            'nvidia'   => str_contains($pci, 'nvidia'),
            'intel'    => (bool)preg_match('/vga[^\n]*intel|display[^\n]*intel/', $pci),
            'amd'      => (bool)preg_match('/vga[^\n]*(amd|ati)|display[^\n]*(amd|ati)/', $pci),
            'decklink' => str_contains($pci, 'blackmagic'),
            default    => null,
        };
    }

    // ══════════════════════════════════════════════════════════════
    //  نصب در پس‌زمینه
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,message:string} */
    public function installOnline(string $id): array
    {
        if (!isset(self::CATALOG[$id])) return ['ok' => false, 'message' => 'درایور ناشناخته'];
        if (!self::CATALOG[$id]['online']) {
            return ['ok' => false, 'message' => 'این درایور از اینترنت نصب نمی‌شود — فایلش را بارگذاری کنید'];
        }
        return $this->launch($id, ['online', $id]);
    }

    /**
     * فایل بارگذاری‌شده را به incoming می‌برد و نصب را شروع می‌کند.
     * @param array $file ردیف $_FILES
     */
    public function installFile(string $id, array $file): array
    {
        if (!isset(self::CATALOG[$id])) return ['ok' => false, 'message' => 'درایور ناشناخته'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'فایل کامل بارگذاری نشد (خطای ' . (int)($file['error'] ?? 0) . ')'];
        }

        $name = strtolower((string)($file['name'] ?? ''));
        $ext  = str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz') ? 'tar.gz'
              : pathinfo($name, PATHINFO_EXTENSION);
        if (!in_array($ext, self::CATALOG[$id]['types'], true)) {
            return ['ok' => false, 'message' => 'برای این درایور فقط ' . implode('، ', self::CATALOG[$id]['types']) . ' پذیرفته می‌شود'];
        }
        if (!$this->looksRight($file['tmp_name'], $ext)) {
            return ['ok' => false, 'message' => 'محتوای فایل با پسوندش جور نیست'];
        }

        /* نام فایل از ما است، نه از مرورگر — نام اصلی فقط در لاگ می‌آید */
        $dest = $this->dir . '/incoming/' . $id . '-' . date('Ymd-His') . '.' . $ext;
        if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
            return ['ok' => false, 'message' => 'ذخیره‌ی فایل روی سرور ناموفق بود'];
        }
        @chmod($dest, 0644);

        /* فقط آخرین سه فایل هر درایور نگه داشته می‌شود — نصب‌کننده‌ی
           NVIDIA چند صد مگابایت است */
        $old = glob($this->dir . '/incoming/' . $id . '-*') ?: [];
        rsort($old);
        foreach (array_slice($old, 3) as $f) @unlink($f);

        return $this->launch($id, ['file', $id, $dest], basename((string)($file['name'] ?? '')));
    }

    /** بررسی سرسری امضای فایل تا فایل HTML با پسوند .deb به apt نرسد */
    private function looksRight(string $path, string $ext): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) return false;
        $head = (string)fread($fh, 8);
        fclose($fh);
        return match ($ext) {
            'deb'    => str_starts_with($head, "!<arch>"),
            'tar.gz' => str_starts_with($head, "\x1f\x8b"),
            'run'    => str_starts_with($head, '#!'),
            default  => false,
        };
    }

    private function launch(string $id, array $args, string $label = ''): array
    {
        if (!$this->supported()) return ['ok' => false, 'message' => 'نصب درایور فقط روی سرور لینوکس ممکن است'];
        if (!$this->helperReady()) {
            return ['ok' => false, 'message' => 'ابزار نصب روی سرور تنظیم نشده — راهنمای بالای صفحه را ببینید'];
        }
        if (($this->job($id)['running'] ?? false)) return ['ok' => false, 'message' => 'نصب این درایور در حال اجراست'];

        $log  = $this->dir . '/logs/' . $id . '.log';
        $exit = $this->dir . '/logs/' . $id . '.exit';
        @unlink($exit);
        @file_put_contents($log, '[' . date('Y-m-d H:i:s') . '] ' . ($label !== '' ? "فایل: $label\n" : "نصب از اینترنت\n"));

        $cmd = 'sudo -n ' . self::HELPER . ' ' . implode(' ', array_map('escapeshellarg', $args));
        /* apt چند دقیقه طول می‌کشد؛ درخواست وب منتظر نمی‌ماند */
        $sh  = 'nohup setsid sh -c ' . escapeshellarg($cmd . ' >> ' . escapeshellarg($log) . ' 2>&1; echo $? > ' . escapeshellarg($exit))
             . ' > /dev/null 2>&1 & echo $!';
        $pid = (int)trim((string)shell_exec($sh));
        if ($pid <= 0) return ['ok' => false, 'message' => 'اجرای نصب ناموفق بود'];

        @file_put_contents($this->dir . '/logs/' . $id . '.pid', (string)$pid);
        return ['ok' => true, 'message' => 'نصب شروع شد — لاگ را دنبال کنید'];
    }

    /** وضعیت آخرین نصب این درایور */
    public function job(string $id): array
    {
        $base = $this->dir . '/logs/' . $id;
        if (!is_file($base . '.log')) return ['running' => false, 'exit' => null];

        $pid     = (int)@file_get_contents($base . '.pid');
        $running = !is_file($base . '.exit') && $pid > 0
                && trim((string)shell_exec('ps -o args= -p ' . $pid . ' 2>/dev/null')) !== '';
        $exit    = is_file($base . '.exit') ? (int)trim((string)file_get_contents($base . '.exit')) : null;

        /* درایور تازه یعنی انکودر تازه — فهرست قابلیت‌های ترنسکدر باید دوباره خوانده شود */
        if ($exit === 0) @unlink(STORAGE_PATH . '/cache/transcoder_caps.json');

        return ['running' => $running, 'exit' => $running ? null : $exit,
                'at' => date('Y-m-d H:i', (int)filemtime($base . '.log'))];
    }

    public function log(string $id): string
    {
        if (!isset(self::CATALOG[$id])) return '';
        $f = $this->dir . '/logs/' . $id . '.log';
        if (!is_file($f)) return '';
        $all = (string)file_get_contents($f);
        return strlen($all) > 60000 ? substr($all, -60000) : $all;
    }
}
