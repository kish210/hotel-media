package com.signagecms.player.kiosk;

import android.app.admin.DeviceAdminReceiver;
import android.content.Context;
import android.content.Intent;
import android.util.Log;

/**
 * گیرنده‌ی مدیر دستگاه.
 *
 * وجود این کلاس شرط لازمِ Device Owner شدن است — نامش در همان دستوری
 * می‌آید که هتل موقع آماده‌سازی دستگاه اجرا می‌کند:
 *
 *   adb shell dpm set-device-owner \
 *     com.signagecms.player/.kiosk.KioskAdminReceiver
 *
 * خودش کار زیادی نمی‌کند؛ فقط رویدادها را لاگ می‌کند تا موقع
 * عیب‌یابی در هتل معلوم باشد کِی قفل برداشته شده.
 */
public class KioskAdminReceiver extends DeviceAdminReceiver {

    private static final String TAG = "HotelMediaKiosk";

    @Override
    public void onEnabled(Context context, Intent intent) {
        Log.i(TAG, "مدیر دستگاه فعال شد");
    }

    @Override
    public void onDisabled(Context context, Intent intent) {
        Log.w(TAG, "مدیر دستگاه غیرفعال شد — تلویزیون دیگر قفل نیست");
    }

    @Override
    public void onLockTaskModeEntering(Context context, Intent intent, String pkg) {
        Log.i(TAG, "وارد حالت قفل شد: " + pkg);
    }

    @Override
    public void onLockTaskModeExiting(Context context, Intent intent) {
        /* اگر این بدون دخالت تکنسین در لاگ دیده شد، یعنی چیزی اپ را
           از جلو بیرون کرده — معمولا یک اپ سیستمی سازنده. */
        Log.w(TAG, "از حالت قفل خارج شد");
    }
}
