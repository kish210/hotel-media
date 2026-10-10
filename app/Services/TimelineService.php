<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * پل بین مدل تایم‌لاینِ چندلایه (ویرایش در استودیو) و playlist_items
 * تختِ موجود (آنچه پلیر اجرا می‌کند).
 *
 * چرا این لایه لازم است: استودیو چند تراک هم‌زمان دارد ولی پلیرهای
 * فعلی یک دنباله‌ی تک‌لایه + overlayهای ثابت (لوگو، تیکر) می‌فهمند.
 * این سرویس دو کار می‌کند:
 *
 *   build()   — از playlist_items فعلی یک تایم‌لاین اولیه می‌سازد تا
 *               استودیو هیچ‌وقت خالی باز نشود و کار موجود گم نشود.
 *   compile() — تایم‌لاین را به playlist_items + برند برمی‌گرداند، پس
 *               انتشار نتیجه را برای همان پلیرهای امروز قابل‌اجرا می‌کند.
 *
 * صداقت دامنه: فقط چیزهایی کامپایل می‌شوند که پلیر واقعا اجرا می‌کند —
 * دنباله‌ی ویدیو/تصویر، لوگوی تمام‌مدت، و متن تیکر. overlayهای پنجره‌دار
 * و کامپوزیتور زنده کار پلیر است (فاز بعد) و اینجا جعل نمی‌شوند.
 */
class TimelineService
{
    public const VERSION = 1;

    /** تراک‌های استاندارد و ترتیب لایه‌بندی (پایین‌تر = عقب‌تر) */
    public const TRACK_TYPES = ['video', 'overlay', 'image', 'text', 'logo', 'audio'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * تایم‌لاین اولیه از روی آیتم‌های فعلیِ پلی‌لیست.
     *
     * آیتم‌های رسانه‌ای و ماژول در تراک ویدیو پشت سر هم می‌نشینند
     * (دقیقا همان ترتیب اجرای امروز). لوگو و متنِ برندِ پلی‌لیست به
     * تراک‌های لوگو و متن تبدیل می‌شوند تا در استودیو دیده شوند.
     *
     * @param array<string,mixed> $playlist ردیف playlists
     * @param array<int,array<string,mixed>> $items خروجی getItems()
     * @return array<string,mixed>
     */
    public function build(array $playlist, array $items): array
    {
        $videoClips = [];
        $cursor = 0;

        foreach ($items as $it) {
            $dur = max(1, (int)($it['duration'] ?? 10));
            $videoClips[] = [
                'id'          => 'c' . ($it['id'] ?? uniqid()),
                'itemId'      => (int)($it['id'] ?? 0),
                'mediaId'     => $it['media_id'] !== null ? (int)$it['media_id'] : null,
                'itemType'    => $it['item_type'] ?? 'media',
                'name'        => $it['media_name'] ?? 'محتوا',
                'mediaType'   => $it['media_type'] ?? 'image',
                'thumb'       => $it['thumbnail_path'] ?? null,
                'start'       => $cursor,
                'duration'    => $dur,
                'sourceStart' => 0,
                'fit'         => ($it['fit_mode'] ?? 'contain') === 'cover' ? 'cover' : 'contain',
                'muted'       => (int)($it['muted'] ?? 1) === 1,
                'volume'      => (int)($it['volume'] ?? 100),
            ];
            $cursor += $dur;
        }

        $total = max($cursor, 1);
        $tracks = [];

        $tracks[] = [
            'id' => 'track-video', 'type' => 'video', 'name' => 'ویدیو / تصویر',
            'order' => 0, 'locked' => false, 'hidden' => false, 'muted' => false,
            'clips' => $videoClips,
        ];

        // تراک لوگو از برندِ پلی‌لیست
        $logoClips = [];
        if (!empty($playlist['logo_path'])) {
            $logoClips[] = [
                'id' => 'logo-brand', 'mediaId' => null, 'src' => $playlist['logo_path'],
                'name' => 'لوگوی تابلو', 'start' => 0, 'duration' => $total,
                'position' => 'top-right', 'scale' => 100, 'opacity' => 85,
            ];
        }
        $tracks[] = [
            'id' => 'track-logo', 'type' => 'logo', 'name' => 'لوگو',
            'order' => 1, 'locked' => false, 'hidden' => false, 'muted' => false,
            'clips' => $logoClips,
        ];

        // تراک متن از زیرنویس برند
        $textClips = [];
        if (!empty($playlist['ticker_text'])) {
            $textClips[] = [
                'id' => 'text-ticker', 'text' => (string)$playlist['ticker_text'],
                'name' => 'زیرنویس', 'start' => 0, 'duration' => $total,
                'font' => 'Tahoma', 'size' => 24, 'weight' => 700, 'color' => '#ffffff',
                'bg' => 'rgba(0,0,0,0.6)', 'position' => 'bottom', 'align' => 'center',
                'rtl' => true, 'animation' => 'scroll',
            ];
        }
        $tracks[] = [
            'id' => 'track-text', 'type' => 'text', 'name' => 'متن / زیرنویس',
            'order' => 2, 'locked' => false, 'hidden' => false, 'muted' => false,
            'clips' => $textClips,
        ];

        return [
            'version'    => self::VERSION,
            'fps'        => 25,
            'resolution' => ['w' => 1920, 'h' => 1080],
            'duration'   => $total,
            'tracks'     => $tracks,
        ];
    }

    /**
     * یک ساختار تایم‌لاین را اعتبارسنجی و پاک می‌کند (ورودی از مرورگر
     * است و قابل اعتماد نیست). ساختار ناقص را به شکل سالم برمی‌گرداند
     * نه اینکه خطا بدهد — ویرایشگر نباید به‌خاطر یک کلید گم‌شده کل کار
     * اپراتور را از دست بدهد.
     *
     * @param mixed $raw
     * @return array<string,mixed>
     */
    public function sanitize($raw): array
    {
        if (is_string($raw)) $raw = json_decode($raw, true);
        if (!is_array($raw)) $raw = [];

        $tracks = [];
        foreach (($raw['tracks'] ?? []) as $t) {
            if (!is_array($t)) continue;
            $type = in_array($t['type'] ?? '', self::TRACK_TYPES, true) ? $t['type'] : 'video';

            $clips = [];
            foreach (($t['clips'] ?? []) as $c) {
                if (!is_array($c)) continue;
                $clips[] = $this->sanitizeClip($type, $c);
            }

            $tracks[] = [
                'id'     => (string)($t['id'] ?? ('track-' . uniqid())),
                'type'   => $type,
                'name'   => mb_substr((string)($t['name'] ?? $type), 0, 60),
                'order'  => (int)($t['order'] ?? count($tracks)),
                'locked' => !empty($t['locked']),
                'hidden' => !empty($t['hidden']),
                'muted'  => !empty($t['muted']),
                'clips'  => $clips,
            ];
        }

        $dur = 0;
        foreach ($tracks as $t) {
            foreach ($t['clips'] as $c) {
                $dur = max($dur, (int)$c['start'] + (int)$c['duration']);
            }
        }

        return [
            'version'    => self::VERSION,
            'fps'        => max(1, min(60, (int)($raw['fps'] ?? 25))),
            'resolution' => [
                'w' => max(16, (int)($raw['resolution']['w'] ?? 1920)),
                'h' => max(16, (int)($raw['resolution']['h'] ?? 1080)),
            ],
            'duration'   => max($dur, 1),
            'tracks'     => $tracks,
        ];
    }

    /** @param array<string,mixed> $c @return array<string,mixed> */
    private function sanitizeClip(string $trackType, array $c): array
    {
        $base = [
            'id'       => (string)($c['id'] ?? ('c' . uniqid())),
            'name'     => mb_substr((string)($c['name'] ?? ''), 0, 120),
            'start'    => max(0, (int)($c['start'] ?? 0)),
            'duration' => max(1, (int)($c['duration'] ?? 10)),
        ];

        if ($trackType === 'text') {
            return $base + [
                'text'      => mb_substr((string)($c['text'] ?? ''), 0, 500),
                'font'      => mb_substr((string)($c['font'] ?? 'Tahoma'), 0, 40),
                'size'      => max(8, min(120, (int)($c['size'] ?? 24))),
                'weight'    => (int)($c['weight'] ?? 700),
                'color'     => $this->color($c['color'] ?? '#ffffff'),
                'bg'        => mb_substr((string)($c['bg'] ?? 'rgba(0,0,0,0.6)'), 0, 40),
                'position'  => in_array($c['position'] ?? '', ['top', 'center', 'bottom'], true) ? $c['position'] : 'bottom',
                'align'     => in_array($c['align'] ?? '', ['right', 'center', 'left'], true) ? $c['align'] : 'center',
                'rtl'       => !isset($c['rtl']) || !empty($c['rtl']),
                'animation' => in_array($c['animation'] ?? '', ['none', 'scroll', 'fade'], true) ? $c['animation'] : 'scroll',
            ];
        }

        if ($trackType === 'logo' || $trackType === 'image' || $trackType === 'overlay') {
            return $base + [
                'mediaId'  => isset($c['mediaId']) && $c['mediaId'] !== null ? (int)$c['mediaId'] : null,
                'src'      => mb_substr((string)($c['src'] ?? ''), 0, 500),
                'thumb'    => mb_substr((string)($c['thumb'] ?? ''), 0, 500),
                'position' => in_array($c['position'] ?? '', self::positions(), true) ? $c['position'] : 'top-right',
                'x'        => isset($c['x']) ? max(0, min(100, (float)$c['x'])) : null,
                'y'        => isset($c['y']) ? max(0, min(100, (float)$c['y'])) : null,
                'scale'    => max(5, min(400, (int)($c['scale'] ?? 100))),
                'opacity'  => max(0, min(100, (int)($c['opacity'] ?? 100))),
                'rotation' => max(-180, min(180, (int)($c['rotation'] ?? 0))),
            ];
        }

        // video / audio
        return $base + [
            'mediaId'     => isset($c['mediaId']) && $c['mediaId'] !== null ? (int)$c['mediaId'] : null,
            'itemType'    => mb_substr((string)($c['itemType'] ?? 'media'), 0, 30),
            'mediaType'   => mb_substr((string)($c['mediaType'] ?? 'image'), 0, 20),
            'thumb'       => mb_substr((string)($c['thumb'] ?? ''), 0, 500),
            'sourceStart' => max(0, (int)($c['sourceStart'] ?? 0)),
            'fit'         => ($c['fit'] ?? 'contain') === 'cover' ? 'cover' : 'contain',
            'muted'       => $trackType === 'audio' ? false : !empty($c['muted']),
            'volume'      => max(0, min(100, (int)($c['volume'] ?? 100))),
        ];
    }

    /** @return list<string> */
    public static function positions(): array
    {
        return ['top-left', 'top-center', 'top-right', 'center',
                'bottom-left', 'bottom-center', 'bottom-right', 'free'];
    }

    private function color(mixed $v): string
    {
        $v = (string)$v;
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : '#ffffff';
    }

    /**
     * تایم‌لاین را به playlist_items + برند کامپایل می‌کند — همان چیزی
     * که پلیر اجرا می‌کند. فقط چیزهایی که پلیر واقعا می‌فهمد نوشته
     * می‌شوند؛ بقیه (overlay پنجره‌دار) هشدار برمی‌گرداند نه خطا.
     *
     * تراکنشی است: اگر وسط کار خطا بدهد، آیتم‌های قبلی برنمی‌گردند
     * نیمه‌پاک — یا همه عوض می‌شوند یا هیچ‌کدام.
     *
     * @param array<string,mixed> $timeline
     * @return array{items:int,warnings:list<string>}
     */
    public function compile(int $playlistId, array $timeline): array
    {
        $timeline = $this->sanitize($timeline);
        $warnings = [];

        // تراک پایه: اولین تراک ویدیو
        $videoTrack = null;
        foreach ($timeline['tracks'] as $t) {
            if ($t['type'] === 'video') { $videoTrack = $t; break; }
        }

        $clips = $videoTrack['clips'] ?? [];
        usort($clips, static fn($a, $b) => $a['start'] <=> $b['start']);

        $this->db->beginTransaction();
        try {
            // آیتم‌های قدیمی پاک می‌شوند و از تایم‌لاین بازساخته می‌شوند
            $this->db->query("DELETE FROM playlist_items WHERE playlist_id=?", [$playlistId]);

            $order = 0;
            $written = 0;
            foreach ($clips as $c) {
                if ($c['mediaId'] === null) { $warnings[] = 'یک کلیپ بدون رسانه نادیده گرفته شد'; continue; }
                $this->db->insert('playlist_items', [
                    'playlist_id' => $playlistId,
                    'media_id'    => $c['mediaId'],
                    'duration'    => $c['duration'],
                    'sort_order'  => ++$order,
                    'volume'      => $c['volume'] ?? 100,
                    'fit_mode'    => $c['fit'] ?? 'contain',
                    'muted'       => !empty($c['muted']) ? 1 : 0,
                    'is_active'   => 1,
                ]);
                $written++;
            }

            // تراک لوگو → logo_path (فقط کلیپِ تمام‌مدت؛ پنجره‌دار هنوز نه)
            $logoPath = null;
            foreach ($timeline['tracks'] as $t) {
                if ($t['type'] !== 'logo') continue;
                foreach ($t['clips'] as $c) {
                    $src = $c['src'] ?? '';
                    if ($src === '') continue;
                    /* کلیپ تمام‌مدت → `logo_path` (مسیر قدیمی که همهٔ
                       پروفایل‌ها می‌فهمند). کلیپ پنجره‌دار دیگر به
                       ‏logo_path تبدیل نمی‌شود: پنجره‌ها از
                       ‏`timeline_published` به پلیر می‌روند و
                       ‏tv-overlays.js آن‌ها را سر وقت نشان می‌دهد.

                       پیش از این، پنجره‌دار را به لوگوی همیشگی فشرده
                       می‌کردیم و هشدار می‌دادیم — یعنی اپراتور لوگویی
                       را از ثانیهٔ ۱۰ تا ۲۰ می‌گذاشت و روی تابلو از
                       اول تا آخر می‌دید. */
                    if ((int)$c['start'] === 0 && (int)$c['duration'] >= $timeline['duration']) {
                        $logoPath = $src;
                    }
                }
            }

            // تراک متن → ticker_text (اولین کلیپ)
            $ticker = null;
            foreach ($timeline['tracks'] as $t) {
                if ($t['type'] !== 'text') continue;
                foreach ($t['clips'] as $c) {
                    if (($c['text'] ?? '') !== '') { $ticker = $c['text']; break 2; }
                }
            }

            $this->db->update('playlists', [
                'logo_path'             => $logoPath,
                'ticker_text'           => $ticker,
                'timeline_published'    => json_encode($timeline, JSON_UNESCAPED_UNICODE),
                'timeline_published_at' => date('Y-m-d H:i:s'),
            ], ['id' => $playlistId]);

            $this->db->commit();
            return ['items' => $written, 'warnings' => array_values(array_unique($warnings))];
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * اعتبارسنجی پیش از انتشار (بند ۲۱). خطاها به زبان اپراتور، نه
     * stack trace. آرایه‌ی خالی یعنی آماده‌ی انتشار.
     *
     * @param array<string,mixed> $timeline
     * @return list<string>
     */
    public function validate(array $timeline): array
    {
        $timeline = $this->sanitize($timeline);
        $errors = [];

        $hasAny = false;
        foreach ($timeline['tracks'] as $t) {
            if (!empty($t['clips'])) { $hasAny = true; break; }
        }
        if (!$hasAny) $errors[] = 'تایم‌لاین خالی است — حداقل یک محتوا اضافه کنید.';

        // رسانه‌های ارجاع‌شده باید وجود داشته و آماده باشند
        $ids = [];
        foreach ($timeline['tracks'] as $t) {
            foreach ($t['clips'] as $c) {
                if (!empty($c['mediaId'])) $ids[] = (int)$c['mediaId'];
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rows = $this->db->rows(
                "SELECT id, name, status FROM media WHERE id IN ($ph) AND deleted_at IS NULL",
                $ids
            );
            $found = [];
            foreach ($rows as $r) {
                $found[(int)$r['id']] = $r;
                if (($r['status'] ?? 'ready') === 'processing') {
                    $errors[] = 'رسانه‌ی «' . $r['name'] . '» هنوز در حال پردازش است.';
                }
            }
            foreach ($ids as $id) {
                if (!isset($found[$id])) $errors[] = 'یک رسانه‌ی استفاده‌شده دیگر موجود نیست (شناسه ' . $id . ').';
            }
        }

        return $errors;
    }
}
