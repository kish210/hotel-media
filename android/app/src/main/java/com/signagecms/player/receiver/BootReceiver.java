package com.signagecms.player.receiver;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Handler;
import android.os.Looper;

import com.signagecms.player.MainActivity;
import com.signagecms.player.PlayerSettings;

/**
 * اجرای خودکار بعد از روشن شدن دستگاه.
 *
 * تا نسخه‌ی قبل همیشه اجرا می‌شد و مکث ۳ ثانیه‌ای ثابت داشت. حالا
 * «تنظیمات» می‌تواند خاموشش کند و مکثش را عوض کند — روی تلویزیونی که
 * شبکه‌اش دیر بالا می‌آید، ۳ ثانیه کم است و اپ روی صفحه‌ی خطا گیر می‌کند.
 */
public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context ctx, Intent intent) {
        final PlayerSettings settings = PlayerSettings.of(ctx);
        if (!settings.autoStart()) return;

        final long delay = settings.bootDelay() * 1000L;
        final Context app = ctx.getApplicationContext();

        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            try {
                Intent i = new Intent(app, MainActivity.class);
                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                app.startActivity(i);
            } catch (Exception ignored) {
                /* اندروید ۱۰+ باز کردن Activity از پس‌زمینه را محدود می‌کند؛
                   اپ HOME و Device Owner است، پس در عمل اجازه دارد. */
            }
        }, delay);
    }
}
