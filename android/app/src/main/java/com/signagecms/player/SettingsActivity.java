package com.signagecms.player;

import android.app.Activity;
import android.content.Intent;
import android.os.Build;
import android.os.Bundle;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Switch;
import android.widget.TextView;

import com.signagecms.player.kiosk.KioskManager;

/**
 * بخش تنظیمات دستگاه — همان چیزی که تکنسین هتل موقع نصب لازم دارد:
 * آدرس سرور، کد صفحه، اجرای خودکار، قفل‌گاه، بیدار ماندن صفحه و
 * بروزرسانی خودکار.
 *
 * از دو جا باز می‌شود:
 *   • دکمه‌ی «تنظیمات» روی صفحه‌ی اولیه‌ی راه‌اندازی
 *   • پنج بار پشت‌سرهم BACK روی پلیر (راه تکنسین؛ مهمان تصادفی پیدایش نمی‌کند)
 */
public class SettingsActivity extends Activity {

    private PlayerSettings settings;
    private EditText etServer, etCode, etBootDelay, etRetry;
    private Switch swAutoStart, swKeepScreen, swKiosk, swAutoUpdate;
    private TextView tvStatus;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        settings = PlayerSettings.of(this);

        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        setContentView(R.layout.activity_settings);

        etServer    = findViewById(R.id.etServer);
        etCode      = findViewById(R.id.etCode);
        etBootDelay = findViewById(R.id.etBootDelay);
        etRetry     = findViewById(R.id.etRetry);
        swAutoStart = findViewById(R.id.swAutoStart);
        swKeepScreen= findViewById(R.id.swKeepScreen);
        swKiosk     = findViewById(R.id.swKiosk);
        swAutoUpdate= findViewById(R.id.swAutoUpdate);
        tvStatus    = findViewById(R.id.tvStatus);
        TextView tvDevice = findViewById(R.id.tvDevice);

        etServer.setText(SignageApp.get().getServerUrl());
        etCode.setText(SignageApp.get().getScreenCode());
        etBootDelay.setText(String.valueOf(settings.bootDelay()));
        etRetry.setText(String.valueOf(settings.retrySeconds()));
        swAutoStart.setChecked(settings.autoStart());
        swKeepScreen.setChecked(settings.keepScreenOn());
        swKiosk.setChecked(settings.kioskOnStart());
        swAutoUpdate.setChecked(settings.autoUpdate());

        tvDevice.setText("Android " + Build.VERSION.RELEASE
            + " (API " + Build.VERSION.SDK_INT + ") · " + Build.MODEL);

        ((Button) findViewById(R.id.btnSave)).setOnClickListener(v -> save(false));
        ((Button) findViewById(R.id.btnPlay)).setOnClickListener(v -> save(true));
        ((Button) findViewById(R.id.btnClose)).setOnClickListener(v -> finish());
    }

    private void save(boolean play) {
        String server = etServer.getText().toString().trim().replaceAll("/+$", "");
        String code   = etCode.getText().toString().trim().toUpperCase().replaceAll("\\s+", "");

        if (server.isEmpty() || code.isEmpty()) {
            tvStatus.setText("آدرس سرور و کد صفحه الزامی است");
            return;
        }
        if (!server.startsWith("http")) server = "http://" + server;

        SignageApp.get().saveServer(server);
        SignageApp.get().saveCode(code);

        settings.setAutoStart(swAutoStart.isChecked());
        settings.setKeepScreenOn(swKeepScreen.isChecked());
        settings.setKioskOnStart(swKiosk.isChecked());
        settings.setAutoUpdate(swAutoUpdate.isChecked());
        settings.setBootDelay(parseInt(etBootDelay, 3));
        settings.setRetrySeconds(parseInt(etRetry, 10));

        /* اگر همین حالا قفل‌گاه خواسته شد و دستگاه Device Owner است،
           سیاست‌ها همین‌جا اعمال می‌شوند تا با اولین بوت کامل باشد. */
        if (swKiosk.isChecked()) {
            try { new KioskManager(this).applyOwnerPolicies(); } catch (Throwable ignored) { }
        }

        if (play) {
            startActivity(new Intent(this, MainActivity.class)
                .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP));
        }
        finish();
    }

    private int parseInt(EditText et, int def) {
        try { return Integer.parseInt(et.getText().toString().trim()); }
        catch (Exception e) { return def; }
    }
}
