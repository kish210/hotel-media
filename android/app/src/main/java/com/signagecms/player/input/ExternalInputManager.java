package com.signagecms.player.input;

import android.content.Context;
import android.content.Intent;
import android.media.tv.TvContract;
import android.media.tv.TvInputInfo;
import android.media.tv.TvInputManager;
import android.net.Uri;
import android.os.Build;
import android.util.Log;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.List;

/**
 * ورودی‌های خارجی تلویزیون — تا مهمان بتواند موبایل یا کنسول بازی
 * وصل کند بدون اینکه به منوی خود تلویزیون دسترسی داشته باشد.
 *
 * روی اندروید، ورودی‌های سخت‌افزاری (HDMI، AV، کامپوننت) در
 * TV Input Framework با پرچم passthrough شناخته می‌شوند. برای رفتن
 * به یکی از آن‌ها:
 *
 *   TvContract.buildChannelUriForPassthroughInput(inputId)
 *
 * و بعد یا TvView.tune(inputId, uri) داخل خود اپ، یا یک Intent.VIEW
 * که اپ تلویزیون سیستم بازش کند.
 *
 * ── نکته‌ی سخت‌افزاری که زیاد فراموش می‌شود ──────────────────────
 * این فقط روی دستگاهی کار می‌کند که خودش پورت HDMI ورودی دارد،
 * یعنی یک تلویزیون واقعی. روی Mi TV Stick و Mi Box و مشابهشان هیچ
 * ورودی HDMI وجود ندارد — آن‌ها فقط خروجی دارند و به پورت تلویزیون
 * وصل می‌شوند. آنجا فهرست خالی برمی‌گردد و رابط کاربر باید صادقانه
 * بگوید که تعویض ورودی از این دستگاه ممکن نیست، نه اینکه دکمه‌ی
 * بی‌اثر نشان دهد.
 */
public final class ExternalInputManager {

    private static final String TAG = "HotelMediaInput";

    private final Context ctx;
    private final TvInputManager tim;

    public ExternalInputManager(Context ctx) {
        this.ctx = ctx;
        TvInputManager m = null;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            try {
                m = (TvInputManager) ctx.getSystemService(Context.TV_INPUT_SERVICE);
            } catch (Exception e) {
                Log.w(TAG, "TvInputManager در دسترس نیست: " + e.getMessage());
            }
        }
        this.tim = m;
    }

    /**
     * فهرست ورودی‌های سخت‌افزاری، به صورت JSON برای لایه‌ی وب.
     *
     * هر آیتم: {"id","label","type","connected"}
     * فهرست خالی یعنی این دستگاه ورودی HDMI ندارد.
     */
    public String listJson() {
        JSONArray out = new JSONArray();
        if (tim == null) return out.toString();

        List<TvInputInfo> inputs;
        try {
            inputs = tim.getTvInputList();
        } catch (Exception e) {
            Log.w(TAG, "getTvInputList نشد: " + e.getMessage());
            return out.toString();
        }

        for (TvInputInfo in : inputs) {
            try {
                // فقط ورودی سخت‌افزاری. تیونرهای داخلی و اپ‌های IPTV
                // اینجا جایی ندارند — مهمان دنبال «HDMI ۱» است.
                if (!in.isPassthroughInput()) continue;
                if (in.isHidden(ctx)) continue;

                JSONObject o = new JSONObject();
                o.put("id", in.getId());
                o.put("label", labelOf(in));
                o.put("type", typeName(in.getType()));
                o.put("connected", isConnected(in.getId()));
                out.put(o);
            } catch (Exception e) {
                Log.w(TAG, "ورودی نادیده گرفته شد: " + e.getMessage());
            }
        }
        return out.toString();
    }

    /**
     * رفتن به یک ورودی.
     *
     * از Intent استفاده می‌کنیم نه TvView، چون TvView نیاز دارد اپ
     * سطح سیستمی باشد تا صدا و تصویرِ passthrough را بگیرد؛ با Intent
     * اپ تلویزیونِ خود دستگاه کار را انجام می‌دهد و روی سازنده‌های
     * بیشتری جواب می‌دهد.
     *
     * @return آیا درخواست فرستاده شد
     */
    public boolean switchTo(String inputId) {
        if (inputId == null || inputId.length() == 0) return false;
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) return false;

        try {
            Uri uri = TvContract.buildChannelUriForPassthroughInput(inputId);
            Intent i = new Intent(Intent.ACTION_VIEW, uri);
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            ctx.startActivity(i);
            return true;
        } catch (Exception e) {
            Log.w(TAG, "تعویض ورودی نشد: " + e.getMessage());
            return false;
        }
    }

    /** آیا کابلی به این ورودی وصل است؟ */
    private boolean isConnected(String inputId) {
        if (tim == null) return false;
        try {
            int s = tim.getInputState(inputId);
            return s == TvInputManager.INPUT_STATE_CONNECTED
                || s == TvInputManager.INPUT_STATE_CONNECTED_STANDBY;
        } catch (Exception e) {
            /* بعضی سازنده‌ها برای ورودی‌های ناشناخته استثنا می‌دهند.
               «نمی‌دانم» را false می‌گیریم تا رابط کاربر ادعای
               اتصالِ نادرست نکند. */
            return false;
        }
    }

    private String labelOf(TvInputInfo in) {
        try {
            CharSequence c = in.loadCustomLabel(ctx);   // نامی که کاربر گذاشته
            if (c != null && c.length() > 0) return c.toString();
            c = in.loadLabel(ctx);
            if (c != null && c.length() > 0) return c.toString();
        } catch (Exception ignored) { }
        return typeName(in.getType());
    }

    private String typeName(int type) {
        switch (type) {
            case TvInputInfo.TYPE_HDMI:      return "HDMI";
            case TvInputInfo.TYPE_COMPONENT: return "Component";
            case TvInputInfo.TYPE_COMPOSITE: return "AV";
            case TvInputInfo.TYPE_SCART:     return "SCART";
            case TvInputInfo.TYPE_VGA:       return "VGA";
            case TvInputInfo.TYPE_DVI:       return "DVI";
            case TvInputInfo.TYPE_DISPLAY_PORT: return "DisplayPort";
            case TvInputInfo.TYPE_TUNER:     return "Tuner";
            default:                         return "ورودی";
        }
    }
}
