package com.signagecms.player.input;

import android.app.Activity;
import android.media.tv.TvContract;
import android.media.tv.TvView;
import android.net.Uri;
import android.os.Build;
import android.util.Log;
import android.view.KeyEvent;
import android.view.View;
import android.view.ViewGroup;
import android.widget.FrameLayout;

/**
 * تصویر HDMI مهمان داخل خود اپ — نه با باز کردن اپ تلویزیون سیستم.
 *
 * ── چرا ──────────────────────────────────────────────────────────────
 * راه قبلی Intent.ACTION_VIEW بود که اپ Live TV سازنده را جلو می‌آورد:
 *   - در حالت Lock Task (قفل‌گاه با Device Owner) اندروید باز شدن اپ
 *     دیگر را اصلا اجازه نمی‌دهد، پس تعویض ورودی بی‌صدا شکست می‌خورد.
 *   - بعد از آماده‌به‌کار، تلویزیون روی همان HDMI بیدار می‌شد و مهمان
 *     بعدی تصویر لپ‌تاپ مهمان قبلی را می‌دید.
 *
 * با TvView ورودی لایه‌ای روی همین Activity است:
 *   - BACK آن را می‌بندد و پورتال سر جایش است
 *   - onStop (خاموش شدن صفحه، آماده‌به‌کار) آن را آزاد می‌کند؛ پس
 *     روشن شدن بعدی همیشه روی پورتال است
 *   - ریبوت که جای خود دارد: BootReceiver اپ را باز می‌کند
 *
 * ورودی passthrough (HDMI) طبق مستندات AOSP برای اپ شخص ثالث هم با
 * TvContract.buildChannelUriForPassthroughInput قابل tune است.
 * اگر سازنده‌ای اجازه نداد (onConnectionFailed)، لایه بسته می‌شود و
 * پورتال به مهمان می‌گوید؛ روی چنین دستگاهی Intent قدیمی به‌عنوان
 * پشتیبان امتحان می‌شود، فقط وقتی قفل‌گاه روشن نیست.
 */
public final class InputOverlay {

    private static final String TAG = "HotelMediaInput";

    /** وقتی لایه بسته شد: reason = "back" | "stop" | "failed" | "js" */
    public interface Listener { void onClosed(String reason); }

    private final Activity act;
    private final FrameLayout root;
    private final Listener listener;
    private TvView view;

    public InputOverlay(Activity act, FrameLayout root, Listener listener) {
        this.act = act;
        this.root = root;
        this.listener = listener;
    }

    public boolean isOpen() { return view != null; }

    /** باید روی نخ اصلی صدا شود */
    public boolean open(String inputId) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) return false;
        if (inputId == null || inputId.length() == 0) return false;
        close(null);
        try {
            final TvView tv = new TvView(act);
            tv.setLayoutParams(new FrameLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
            tv.setCallback(new TvView.TvInputCallback() {
                @Override public void onConnectionFailed(String id) {
                    Log.w(TAG, "اتصال به ورودی نشد: " + id);
                    close("failed");
                }
                @Override public void onDisconnected(String id) {
                    Log.w(TAG, "ورودی قطع شد: " + id);
                    close("failed");
                }
            });
            root.addView(tv);                       // روی WebView
            Uri uri = TvContract.buildChannelUriForPassthroughInput(inputId);
            tv.tune(inputId, uri);
            tv.setFocusable(true);
            tv.requestFocus();
            view = tv;
            return true;
        } catch (Exception e) {
            Log.w(TAG, "TvView باز نشد: " + e.getMessage());
            close(null);
            return false;
        }
    }

    /** reason=null یعنی بستن بی‌صدا (بدون خبر به پورتال) */
    public void close(String reason) {
        TvView v = view;
        view = null;
        if (v != null) {
            try { v.reset(); } catch (Exception ignored) { }
            try { root.removeView(v); } catch (Exception ignored) { }
        }
        if (v != null && reason != null && listener != null) listener.onClosed(reason);
    }

    /**
     * کلیدها وقتی ورودی باز است. true یعنی مصرف شد.
     * BACK و ESC برمی‌گردانند؛ صدا و بقیه به سیستم می‌رسند.
     */
    public boolean onKey(KeyEvent ev) {
        if (view == null) return false;
        int k = ev.getKeyCode();
        if (k == KeyEvent.KEYCODE_BACK || k == KeyEvent.KEYCODE_ESCAPE) {
            if (ev.getAction() == KeyEvent.ACTION_UP) close("back");
            return true;
        }
        return false;
    }

    /** برای تست و عیب‌یابی */
    View currentView() { return view; }
}
