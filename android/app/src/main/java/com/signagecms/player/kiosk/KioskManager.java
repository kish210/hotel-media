package com.signagecms.player.kiosk;

import android.app.Activity;
import android.app.admin.DevicePolicyManager;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.Build;
import android.os.UserManager;
import android.util.Log;

/**
 * حالت قفل‌گاه — مهمان نباید به منوهای خود تلویزیون برسد.
 *
 * سه لایه دارد و هر لایه به یکی بیشتر نیاز دارد:
 *
 *  ۱) اجرای خودکار بعد از روشن‌شدن  → BootReceiver (بدون هیچ نیازی)
 *  ۲) دکمه‌ی HOME به اپ ما برگردد    → اپ باید launcher پیش‌فرض باشد
 *  ۳) خروج از اپ ممکن نباشد         → Lock Task Mode
 *
 * لایه‌ی سوم بدون Device Owner هم کار می‌کند ولی اندروید یک پیام تایید
 * نشان می‌دهد («برای خروج ... را نگه دارید») که مهمان می‌تواند ردش کند.
 * با Device Owner بی‌صدا و بدون راه فرار است.
 *
 * ── Device Owner چطور فعال می‌شود ──────────────────────────────────
 * فقط روی دستگاهی که هیچ حساب گوگلی روی آن اضافه نشده (بعد از factory
 * reset، پیش از ورود به حساب):
 *
 *   adb shell dpm set-device-owner \
 *     com.signagecms.player/.kiosk.KioskAdminReceiver
 *
 * این محدودیتِ خود اندروید است، نه چیزی که بشود دورش زد. برای هتل
 * یعنی این کار یک‌بار موقع آماده‌سازی دستگاه‌ها انجام می‌شود.
 *
 * اگر Device Owner نشد، بقیه‌ی لایه‌ها همچنان کار می‌کنند و سیستم
 * بی‌صدا به حالت ضعیف‌تر برمی‌گردد — هیچ‌جا کرش نمی‌کند.
 */
public final class KioskManager {

    private static final String TAG = "HotelMediaKiosk";

    private final Activity activity;
    private final DevicePolicyManager dpm;
    private final ComponentName admin;

    public KioskManager(Activity activity) {
        this.activity = activity;
        this.dpm   = (DevicePolicyManager) activity.getSystemService(Context.DEVICE_POLICY_SERVICE);
        this.admin = new ComponentName(activity, KioskAdminReceiver.class);
    }

    /** آیا این اپ مالک دستگاه است؟ */
    public boolean isDeviceOwner() {
        return dpm != null && dpm.isDeviceOwnerApp(activity.getPackageName());
    }

    /**
     * همه‌ی چیزهایی که فقط Device Owner می‌تواند تنظیم کند.
     * یک‌بار در اولین اجرا صدا می‌شود؛ تکرارش هم بی‌ضرر است.
     */
    public void applyOwnerPolicies() {
        if (!isDeviceOwner()) {
            Log.i(TAG, "Device Owner نیست — سیاست‌های قوی اعمال نمی‌شوند");
            return;
        }

        try {
            // فقط خود ما اجازه‌ی lock task داریم
            dpm.setLockTaskPackages(admin, new String[]{ activity.getPackageName() });
        } catch (Exception e) {
            Log.w(TAG, "setLockTaskPackages نشد: " + e.getMessage());
        }

        // اپ ما launcher پیش‌فرض شود تا HOME به خودمان برگردد.
        // بدون این، مهمان با یک بار HOME روی منوی خود تلویزیون است.
        try {
            IntentFilter home = new IntentFilter(Intent.ACTION_MAIN);
            home.addCategory(Intent.CATEGORY_HOME);
            home.addCategory(Intent.CATEGORY_DEFAULT);
            dpm.addPersistentPreferredActivity(admin, home,
                new ComponentName(activity, activity.getClass()));
        } catch (Exception e) {
            Log.w(TAG, "تنظیم launcher پیش‌فرض نشد: " + e.getMessage());
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            try {
                /* نوار وضعیت و اعلان‌ها پنهان شوند. اعلان‌های سیستم روی
                   تلویزیون اتاق («به‌روزرسانی در دسترس است») برای مهمان
                   بی‌معنی‌اند و راهی به تنظیمات باز می‌کنند. */
                dpm.setStatusBarDisabled(admin, true);
            } catch (Exception e) {
                Log.w(TAG, "setStatusBarDisabled نشد: " + e.getMessage());
            }
        }

        /* محدودیت‌هایی که راه‌های فرار را می‌بندند. هر کدام جدا در try
           است چون بعضی سازنده‌ها بعضی‌شان را پشتیبانی نمی‌کنند و یک
           استثنا نباید جلوی بقیه را بگیرد. */
        String[] restrictions = {
            UserManager.DISALLOW_FACTORY_RESET,
            UserManager.DISALLOW_SAFE_BOOT,
            UserManager.DISALLOW_ADD_USER,
            UserManager.DISALLOW_INSTALL_APPS,
            UserManager.DISALLOW_UNINSTALL_APPS,
            UserManager.DISALLOW_CONFIG_WIFI,
            UserManager.DISALLOW_MODIFY_ACCOUNTS,
        };
        for (String r : restrictions) {
            try { dpm.addUserRestriction(admin, r); }
            catch (Exception e) { Log.w(TAG, "محدودیت " + r + " اعمال نشد"); }
        }

        Log.i(TAG, "سیاست‌های Device Owner اعمال شد");
    }

    /**
     * ورود به حالت قفل. اگر Device Owner باشیم بی‌صدا، وگرنه اندروید
     * یک تایید نشان می‌دهد.
     *
     * @return آیا واقعا وارد شد
     */
    public boolean enterKiosk() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) return false;

        if (isDeviceOwner() && Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            try {
                /* هیچ‌کدام از امکانات سیستمی در دسترس نباشد: نه HOME،
                   نه recents، نه اعلان، نه اطلاعات سیستم. */
                dpm.setLockTaskFeatures(admin, DevicePolicyManager.LOCK_TASK_FEATURE_NONE);
            } catch (Exception e) {
                Log.w(TAG, "setLockTaskFeatures نشد: " + e.getMessage());
            }
        }

        try {
            activity.startLockTask();
            return true;
        } catch (Exception e) {
            // روی بعضی اندروید TVهای سفارشی lock task اصلا وجود ندارد
            Log.w(TAG, "startLockTask نشد: " + e.getMessage());
            return false;
        }
    }

    /**
     * خروج از حالت قفل — برای تکنسین هتل، نه مهمان.
     * پنل باید پیش از این احراز هویت کرده باشد.
     */
    public void exitKiosk() {
        try { activity.stopLockTask(); }
        catch (Exception e) { Log.w(TAG, "stopLockTask نشد: " + e.getMessage()); }
    }

    /** آیا الان در حالت قفلیم؟ */
    public boolean isLocked() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return false;
        android.app.ActivityManager am =
            (android.app.ActivityManager) activity.getSystemService(Context.ACTIVITY_SERVICE);
        if (am == null) return false;
        return am.getLockTaskModeState() != android.app.ActivityManager.LOCK_TASK_MODE_NONE;
    }
}
