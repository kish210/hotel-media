package com.signagecms.player;

import android.content.Context;
import android.webkit.JavascriptInterface;

import com.signagecms.player.input.ExternalInputManager;
import com.signagecms.player.kiosk.KioskManager;

/**
 * JavaScript → Android Bridge
 * JS می‌تونه از این interface برای interact با Android استفاده کنه
 *
 * هر متد اینجا از نخ WebView صدا می‌شود، نه نخ اصلی. هر چیزی که به
 * UI دست بزند باید در runOnUiThread بپیچد — وگرنه روی بعضی اندروید
 * TVها بی‌صدا هیچ کاری نمی‌کند.
 */
public class SignageBridge {
    private final Context ctx;
    private final MainActivity activity;
    private final KioskManager kiosk;
    private ExternalInputManager inputs;

    public SignageBridge(MainActivity act) {
        this.ctx = act;
        this.activity = act;
        this.kiosk = new KioskManager(act);
        /* ورودی‌های HDMI روی TvInputManager سوارند که از API 21 است.
           روی اندروید ۴ این کلاس وجود ندارد، پس اصلا ساخته نمی‌شود —
           ساختنش می‌تواند بارگذاری کلاس را با NoClassDefFoundError
           بشکند. */
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.LOLLIPOP) {
            try { this.inputs = new ExternalInputManager(act); }
            catch (Throwable t) { this.inputs = null; }
        }
    }

    @JavascriptInterface
    public String getDeviceInfo() {
        return "{\"model\":\"" + android.os.Build.MODEL + "\"," +
               "\"brand\":\"" + android.os.Build.BRAND + "\"," +
               "\"os\":\"Android " + android.os.Build.VERSION.RELEASE + "\"," +
               "\"sdk\":" + android.os.Build.VERSION.SDK_INT + "," +
               "\"app\":\"SignageCMS Android\",\"appVersion\":\"1.7.0\"}";
    }

    @JavascriptInterface
    public void setServerUrl(String url) {
        SignageApp.get().saveServer(url);
    }

    @JavascriptInterface
    public void setScreenCode(String code) {
        SignageApp.get().saveCode(code);
    }

    @JavascriptInterface
    public String getScreenCode() {
        return SignageApp.get().getScreenCode();
    }

    @JavascriptInterface
    public void reloadApp() {
        activity.runOnUiThread(() -> activity.recreate());
    }

    // ── تنظیمات دستگاه ────────────────────────────────────────────

    /** تنظیمات محلی به صورت JSON (اجرای خودکار، قفل‌گاه، بیدار ماندن…) */
    @JavascriptInterface
    public String getSettings() {
        return PlayerSettings.of(ctx).toJson();
    }

    /** باز کردن بخش تنظیمات — فقط از پنل تکنسین صدا زده شود */
    @JavascriptInterface
    public void openSettings() {
        activity.runOnUiThread(activity::openSettings);
    }

    @JavascriptInterface
    public void keepScreenOn(boolean on) {
        activity.runOnUiThread(() -> {
            if (on) {
                activity.getWindow().addFlags(
                    android.view.WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            } else {
                activity.getWindow().clearFlags(
                    android.view.WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            }
        });
    }

    // ── حالت قفل‌گاه ───────────────────────────────────────────────
    // مهمان نباید به منوهای خود تلویزیون برسد.

    /** وضعیت قفل، برای نمایش در پنل مدیریت */
    @JavascriptInterface
    public String getKioskState() {
        return "{\"deviceOwner\":" + kiosk.isDeviceOwner() +
               ",\"locked\":" + kiosk.isLocked() + "}";
    }

    @JavascriptInterface
    public boolean enterKiosk() {
        /* startLockTask باید روی نخ اصلی صدا شود. چون نتیجه لازم است،
           منتظر می‌مانیم — با مهلت، تا اگر نخ اصلی گیر کرد صفحه‌ی
           مهمان قفل نشود. */
        final boolean[] ok = { false };
        final java.util.concurrent.CountDownLatch done =
            new java.util.concurrent.CountDownLatch(1);
        activity.runOnUiThread(() -> {
            try { ok[0] = kiosk.enterKiosk(); } finally { done.countDown(); }
        });
        try { done.await(3, java.util.concurrent.TimeUnit.SECONDS); }
        catch (InterruptedException e) { Thread.currentThread().interrupt(); }
        return ok[0];
    }

    /** خروج از قفل — فقط برای تکنسین؛ پنل باید قبلش احراز هویت کند */
    @JavascriptInterface
    public void exitKiosk() {
        activity.runOnUiThread(kiosk::exitKiosk);
    }

    // ── ورودی‌های خارجی (موبایل، کنسول بازی) ──────────────────────

    /**
     * فهرست ورودی‌های سخت‌افزاری به صورت JSON.
     * آرایه‌ی خالی یعنی این دستگاه پورت HDMI ورودی ندارد — مثل
     * Mi TV Stick و Mi Box که فقط خروجی دارند.
     */
    @JavascriptInterface
    public String listInputs() {
        return inputs != null ? inputs.listJson() : "[]";
    }

    /**
     * نمایش یک ورودی. اول داخل خود اپ (InputOverlay با TvView) — تا BACK
     * و خاموشی آن را ببندند و تلویزیون همیشه روی پورتال روشن شود. اگر
     * این دستگاه اجازه نداد و قفل‌گاه روشن نیست، اپ Live TV سیستم.
     */
    @JavascriptInterface
    public boolean switchInput(String inputId) {
        if (inputs == null || !inputs.isKnownInput(inputId)) return false;
        final boolean[] ok = { false };
        final java.util.concurrent.CountDownLatch done =
            new java.util.concurrent.CountDownLatch(1);
        activity.runOnUiThread(() -> {
            try { ok[0] = activity.openInput(inputId); } finally { done.countDown(); }
        });
        try { done.await(3, java.util.concurrent.TimeUnit.SECONDS); }
        catch (InterruptedException e) { Thread.currentThread().interrupt(); }
        if (ok[0]) return true;

        /* در Lock Task اندروید اپ دیگری را باز نمی‌کند؛ تلاش بی‌فایده است */
        if (kiosk.isLocked()) return false;
        boolean sent = inputs.switchViaSystemApp(inputId);
        if (sent) activity.markExternalInput();
        return sent;
    }

    /** پورتال می‌خواهد برگردد (یا در بوت، ورودی جامانده را ببندد) */
    @JavascriptInterface
    public void closeInput() {
        activity.runOnUiThread(() -> activity.closeInput("js"));
    }
}
