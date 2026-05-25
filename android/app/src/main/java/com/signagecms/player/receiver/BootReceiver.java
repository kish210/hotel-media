package com.signagecms.player.receiver;
import android.content.*;
import android.os.*;
public class BootReceiver extends BroadcastReceiver {
    @Override public void onReceive(Context ctx, Intent intent) {
        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            Intent i = new Intent(ctx, com.signagecms.player.MainActivity.class);
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            ctx.startActivity(i);
        }, 3000);
    }
}
