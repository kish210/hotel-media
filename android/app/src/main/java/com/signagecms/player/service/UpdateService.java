package com.signagecms.player.service;

import android.app.*;
import android.content.*;
import android.content.pm.PackageInfo;
import android.net.Uri;
import android.os.*;
import androidx.core.content.FileProvider;
import java.io.*;
import java.net.*;

/**
 * OTA Update Service
 * بررسی و دانلود نسخه جدید اپ از سرور
 */
public class UpdateService extends IntentService {

    public UpdateService() { super("UpdateService"); }

    @Override
    protected void onHandleIntent(Intent intent) {
        if (intent == null) return;
        String server = intent.getStringExtra("server");
        if (server == null || server.isEmpty()) return;

        try {
            checkAndUpdate(server);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void checkAndUpdate(String server) throws Exception {
        // دریافت اطلاعات نسخه از سرور
        int currentVersion = getCurrentVersionCode();
        URL url = new URL(server + "/api/v1/app/version");
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setConnectTimeout(8000);
        conn.setReadTimeout(8000);

        if (conn.getResponseCode() != 200) return;

        // خواندن JSON
        BufferedReader reader = new BufferedReader(
            new InputStreamReader(conn.getInputStream()));
        StringBuilder sb = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) sb.append(line);
        conn.disconnect();

        String response = sb.toString();

        // parse ساده JSON
        int latestVersion = parseIntField(response, "version_code");
        String apkUrl     = parseStrField(response, "apk_url");
        String changelog  = parseStrField(response, "changelog");
        boolean forceUpdate = response.contains("\"force\":true");

        if (latestVersion > currentVersion && !apkUrl.isEmpty()) {
            if (forceUpdate) {
                // دانلود و نصب فوری
                downloadAndInstall(apkUrl, changelog);
            } else {
                // notification برای کاربر
                showUpdateNotification(latestVersion, apkUrl, changelog);
            }
        }
    }

    private void downloadAndInstall(String apkUrl, String changelog) throws Exception {
        File apkFile = new File(getCacheDir(), "signage_update.apk");

        // دانلود
        URL url = new URL(apkUrl);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setConnectTimeout(30000);
        conn.setReadTimeout(60000);

        try (InputStream in = conn.getInputStream();
             FileOutputStream out = new FileOutputStream(apkFile)) {
            byte[] buf = new byte[8192];
            int n;
            while ((n = in.read(buf)) != -1) out.write(buf, 0, n);
        }
        conn.disconnect();

        // نصب APK
        installApk(apkFile);
    }

    private void installApk(File apk) {
        Intent install = new Intent(Intent.ACTION_VIEW);
        Uri apkUri;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            apkUri = FileProvider.getUriForFile(this,
                getPackageName() + ".fileprovider", apk);
            install.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
        } else {
            apkUri = Uri.fromFile(apk);
        }

        install.setDataAndType(apkUri, "application/vnd.android.package-archive");
        install.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        startActivity(install);
    }

    private void showUpdateNotification(int version, String apkUrl, String changelog) {
        // ذخیره info برای نمایش بعدی
        getSharedPreferences("signage_prefs", MODE_PRIVATE).edit()
            .putInt("update_version", version)
            .putString("update_url", apkUrl)
            .putString("update_changelog", changelog)
            .apply();

        // Notification
        NotificationChannel ch = null;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            ch = new NotificationChannel("update", "بروزرسانی", NotificationManager.IMPORTANCE_DEFAULT);
            ((NotificationManager) getSystemService(NOTIFICATION_SERVICE)).createNotificationChannel(ch);
        }

        Intent install = new Intent(this, MainActivity.class);
        install.putExtra("action", "update");
        install.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
    }

    private int getCurrentVersionCode() {
        try {
            PackageInfo pi = getPackageManager().getPackageInfo(getPackageName(), 0);
            return pi.versionCode;
        } catch (Exception e) { return 0; }
    }

    private int parseIntField(String json, String field) {
        try {
            int idx = json.indexOf("\"" + field + "\"");
            if (idx < 0) return 0;
            String sub = json.substring(idx + field.length() + 3);
            return Integer.parseInt(sub.replaceAll("[^0-9].*","").trim());
        } catch (Exception e) { return 0; }
    }

    private String parseStrField(String json, String field) {
        try {
            int idx = json.indexOf("\"" + field + "\"");
            if (idx < 0) return "";
            int start = json.indexOf("\"", idx + field.length() + 3) + 1;
            int end   = json.indexOf("\"", start);
            return json.substring(start, end);
        } catch (Exception e) { return ""; }
    }
}
