package com.signagecms.player;

import android.content.Context;
import android.webkit.JavascriptInterface;

/**
 * JavaScript → Android Bridge
 * JS می‌تونه از این interface برای interact با Android استفاده کنه
 */
public class SignageBridge {
    private final Context ctx;
    private final MainActivity activity;

    public SignageBridge(MainActivity act) {
        this.ctx = act;
        this.activity = act;
    }

    @JavascriptInterface
    public String getDeviceInfo() {
        return "{\"model\":\"" + android.os.Build.MODEL + "\"," +
               "\"brand\":\"" + android.os.Build.BRAND + "\"," +
               "\"os\":\"Android " + android.os.Build.VERSION.RELEASE + "\"," +
               "\"app\":\"SignageCMS Android\"}";
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
}
