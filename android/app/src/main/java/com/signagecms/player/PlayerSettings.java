package com.signagecms.player;

import android.content.Context;
import android.content.SharedPreferences;

/**
 * تنظیمات محلی دستگاه — بخش «تنظیمات» اپ.
 *
 * چرا در SharedPreferences و نه روی سرور: هر تلویزیون سخت‌افزار خودش را
 * دارد. «اجرای خودکار بعد از روشن شدن» یا «قفل‌گاه» یک تصمیم نصب است،
 * نه تنظیم مرکزی هتل؛ اگر از پنل بیاید و شبکه قطع باشد، دستگاه دیگر
 * بالا نمی‌آید.
 *
 * پیش‌فرض‌ها همان رفتار ۱.۶ است تا دستگاه‌های نصب‌شده بدون تغییر رفتار
 * بروزرسانی شوند.
 */
public final class PlayerSettings {

    /* همان فایل SignageApp تا سرور/کد صفحه دو جا ذخیره نشود */
    private static final String PREF = SignageApp.PREF_NAME;

    public static final String K_AUTO_START   = "auto_start";
    public static final String K_BOOT_DELAY   = "boot_delay";
    public static final String K_KEEP_SCREEN  = "keep_screen_on";
    public static final String K_KIOSK        = "kiosk_on_start";
    public static final String K_AUTO_UPDATE  = "auto_update";
    public static final String K_RETRY        = "retry_seconds";

    private final SharedPreferences p;

    private PlayerSettings(Context ctx) {
        this.p = ctx.getApplicationContext().getSharedPreferences(PREF, Context.MODE_PRIVATE);
    }

    public static PlayerSettings of(Context ctx) {
        return new PlayerSettings(ctx);
    }

    // ── خواندن ───────────────────────────────────────────────────────

    /** اجرای خودکار بعد از روشن شدن دستگاه (BootReceiver) */
    public boolean autoStart()     { return p.getBoolean(K_AUTO_START, true); }

    /** چند ثانیه صبر بعد از بوت تا شبکه بالا بیاید */
    public int bootDelay()         { return clamp(p.getInt(K_BOOT_DELAY, 3), 0, 120); }

    /** صفحه خاموش نشود */
    public boolean keepScreenOn()  { return p.getBoolean(K_KEEP_SCREEN, true); }

    /** بعد از بالا آمدن پلیر، قفل‌گاه روشن شود */
    public boolean kioskOnStart()  { return p.getBoolean(K_KIOSK, false); }

    /** بررسی خودکار نسخه‌ی جدید اپ */
    public boolean autoUpdate()    { return p.getBoolean(K_AUTO_UPDATE, true); }

    /** فاصله‌ی تلاش دوباره بعد از خطای بارگذاری صفحه */
    public int retrySeconds()      { return clamp(p.getInt(K_RETRY, 10), 3, 300); }

    // ── نوشتن ────────────────────────────────────────────────────────

    public void setAutoStart(boolean v)    { p.edit().putBoolean(K_AUTO_START, v).apply(); }
    public void setBootDelay(int v)        { p.edit().putInt(K_BOOT_DELAY, clamp(v, 0, 120)).apply(); }
    public void setKeepScreenOn(boolean v) { p.edit().putBoolean(K_KEEP_SCREEN, v).apply(); }
    public void setKioskOnStart(boolean v) { p.edit().putBoolean(K_KIOSK, v).apply(); }
    public void setAutoUpdate(boolean v)   { p.edit().putBoolean(K_AUTO_UPDATE, v).apply(); }
    public void setRetrySeconds(int v)     { p.edit().putInt(K_RETRY, clamp(v, 3, 300)).apply(); }

    /** برای پنل/پل وب و عیب‌یابی */
    public String toJson() {
        return "{\"auto_start\":" + autoStart() +
               ",\"boot_delay\":" + bootDelay() +
               ",\"keep_screen_on\":" + keepScreenOn() +
               ",\"kiosk_on_start\":" + kioskOnStart() +
               ",\"auto_update\":" + autoUpdate() +
               ",\"retry_seconds\":" + retrySeconds() + "}";
    }

    private static int clamp(int v, int lo, int hi) {
        return v < lo ? lo : (v > hi ? hi : v);
    }
}
