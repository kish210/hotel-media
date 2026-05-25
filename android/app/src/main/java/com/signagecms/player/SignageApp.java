package com.signagecms.player;
import android.app.Application;
import android.content.SharedPreferences;
public class SignageApp extends Application {
    private static SignageApp instance;
    public static final String PREF_NAME="signage_prefs", KEY_SERVER="server_url", KEY_CODE="screen_code";
    @Override public void onCreate() { super.onCreate(); instance = this; }
    public static SignageApp get() { return instance; }
    public SharedPreferences prefs() { return getSharedPreferences(PREF_NAME,MODE_PRIVATE); }
    public String getServerUrl() { return prefs().getString(KEY_SERVER,""); }
    public String getScreenCode() { return prefs().getString(KEY_CODE,""); }
    public void saveServer(String url) { prefs().edit().putString(KEY_SERVER,url).apply(); }
    public void saveCode(String code) { prefs().edit().putString(KEY_CODE,code).apply(); }
}
