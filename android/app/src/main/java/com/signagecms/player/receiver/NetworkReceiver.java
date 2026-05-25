package com.signagecms.player.receiver;
import android.content.*;
import android.net.*;
import android.os.*;
public class NetworkReceiver extends BroadcastReceiver {
    @Override public void onReceive(Context ctx, Intent intent) {
        ConnectivityManager cm = (ConnectivityManager) ctx.getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return;
        NetworkInfo ni = cm.getActiveNetworkInfo();
        if (ni != null && ni.isConnectedOrConnecting()) {
            String server = ctx.getSharedPreferences("signage_prefs", Context.MODE_PRIVATE)
                .getString("server_url","");
            if (!server.isEmpty()) {
                new Handler(Looper.getMainLooper()).postDelayed(() -> {
                    Intent i = new Intent(ctx, com.signagecms.player.MainActivity.class);
                    i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                    ctx.startActivity(i);
                }, 2000);
            }
        }
    }
}
