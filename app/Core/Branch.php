<?php
declare(strict_types=1);
namespace App\Core;

/**
 * شعبه‌ی فعال پنل — TODO ۵.۱۴ (هتل زنجیره‌ای)
 *
 * دو حالت:
 *   - کاربر شعبه‌دار (users.location_id): همیشه همان شعبه، بی‌اختیار.
 *   - کاربر همه‌ی شعبه‌ها: پیش‌فرض همه، ولی می‌تواند از بالای پنل
 *     یک شعبه را برای دیدن انتخاب کند (در نشست).
 *
 * رکوردی که location_id ندارد (مشترک، مثل تلویزیون لابی مرکزی) فقط
 * برای کاربر همه‌ی شعبه‌ها دیده می‌شود؛ کارمند یک شعبه آن را نمی‌بیند.
 */
final class Branch
{
    /** شعبه‌ای که کاربر به آن بسته است؛ null یعنی همه */
    public static function locked(): ?int
    {
        $u = Auth::user();
        $l = $u['location_id'] ?? null;
        return $l ? (int)$l : null;
    }

    /** شعبه‌ای که فهرست‌ها باید به آن محدود شوند؛ null یعنی بدون فیلتر */
    public static function current(): ?int
    {
        if (($l = self::locked()) !== null) return $l;
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['branch_view'])) {
            return (int)$_SESSION['branch_view'];
        }
        return null;
    }

    /**
     * شرط SQL برای فهرست‌ها: « AND alias.location_id = ?» یا رشته‌ی خالی.
     * @param list<mixed> $params  پارامتر به آن اضافه می‌شود
     */
    public static function sql(string $column, array &$params): string
    {
        $b = self::current();
        if ($b === null) return '';
        $params[] = $b;
        return " AND $column = ?";
    }

    /**
     * آیا کاربر اجازه‌ی دست زدن به رکوردی با این شعبه را دارد؟
     * فقط محدودیت ثابت کاربر مهم است؛ انتخاب نمایشی مدیر زنجیره
     * اجازه‌ی ویرایش را کم نمی‌کند.
     */
    public static function allows(mixed $locationId): bool
    {
        $l = self::locked();
        return $l === null || (int)$locationId === $l;
    }

    /** شعبه‌ی رکورد تازه: کاربر شعبه‌دار فقط در شعبه‌ی خودش می‌سازد */
    public static function forNew(mixed $requested): ?int
    {
        $l = self::locked();
        if ($l !== null) return $l;
        $r = (int)$requested;
        return $r > 0 ? $r : null;
    }
}
