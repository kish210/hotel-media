package com.signagecms.player;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.*;
import android.webkit.*;
import android.widget.*;
import com.signagecms.player.input.InputOverlay;
import com.signagecms.player.service.UpdateService;

public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progress;
    private TextView statusText;
    private Handler handler = new Handler(Looper.getMainLooper());
    private InputOverlay inputOverlay;
    /** ورودی با اپ Live TV سیستم باز شد (راه پشتیبان) */
    private boolean externalInput = false;
    private android.content.BroadcastReceiver screenReceiver;

    @SuppressLint({"SetJavaScriptEnabled","ClickableViewAccessibility"})
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Full screen kiosk
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON |
                             WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED |
                             WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD |
                             WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON);
        getWindow().getDecorView().setSystemUiVisibility(
            View.SYSTEM_UI_FLAG_FULLSCREEN |
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION |
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY |
            View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN |
            View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
        );
        requestWindowFeature(Window.FEATURE_NO_TITLE);

        setContentView(R.layout.activity_main);
        webView    = findViewById(R.id.webView);
        progress   = findViewById(R.id.progressBar);
        statusText = findViewById(R.id.statusText);

        setupWebView();

        /* HDMI مهمان روی همین صفحه، بالای WebView */
        inputOverlay = new InputOverlay(this, (FrameLayout) webView.getParent(), reason -> {
            webView.requestFocus();
            notifyInputClosed(reason);
        });
        registerScreenReceiver();

        String server = SignageApp.get().getServerUrl();
        String code   = SignageApp.get().getScreenCode();

        if (server.isEmpty()) {
            showSetupScreen();
        } else {
            loadPlayer(server, code);
            // OTA check بعد از ۱۰ ثانیه
            handler.postDelayed(() -> checkForUpdate(server), 10000);
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setMediaPlaybackRequiresUserGesture(false);  // ← مهم برای autoplay
        ws.setLoadWithOverviewMode(true);
        ws.setUseWideViewPort(true);
        ws.setCacheMode(WebSettings.LOAD_DEFAULT);
        ws.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        ws.setAllowFileAccess(true);
        ws.setAllowContentAccess(true);
        ws.setBuiltInZoomControls(false);
        ws.setDisplayZoomControls(false);
        // Video
        ws.setPluginState(WebSettings.PluginState.ON);
        ws.setAllowUniversalAccessFromFileURLs(true);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                progress.setVisibility(View.GONE);
                // inject JS: نوع دستگاه به پلیر بگو
                webView.evaluateJavascript(
                    "if(window._signageDeviceType===undefined){window._signageDeviceType='android-app';}", null);
            }
            @Override
            public void onReceivedError(WebView view, WebResourceRequest request,
                                        WebResourceError error) {
                if (request.isForMainFrame()) {
                    handler.postDelayed(() -> webView.reload(), 5000);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int p) {
                progress.setProgress(p);
                if (p >= 100) {
                    progress.setVisibility(View.GONE);
                } else {
                    progress.setVisibility(View.VISIBLE);
                }
            }
        });

        // JavaScript Bridge — tv-base.js با نام SignageBridge دنبالش می‌گشت و
        // این‌جا فقط AndroidBridge ثبت می‌شد؛ تعویض ورودی و قفل‌گاه هرگز
        // از صفحه صدا زده نمی‌شدند. هر دو نام ثبت می‌شود.
        SignageBridge bridge = new SignageBridge(this);
        webView.addJavascriptInterface(bridge, "AndroidBridge");
        webView.addJavascriptInterface(bridge, "SignageBridge");
    }

    public void loadPlayer(String server, String code) {
        String url = server.replaceAll("/$","") + "/player/" + code;
        progress.setVisibility(View.VISIBLE);
        webView.loadUrl(url);
        webView.setVisibility(View.VISIBLE);
        if (statusText != null) statusText.setVisibility(View.GONE);
    }

    private void showSetupScreen() {
        // Setup UI
        webView.setVisibility(View.GONE);
        setContentView(R.layout.activity_setup);

        EditText etServer = findViewById(R.id.etServer);
        EditText etCode   = findViewById(R.id.etCode);
        Button   btnSave  = findViewById(R.id.btnSave);
        TextView tvStatus = findViewById(R.id.tvStatus);

        btnSave.setOnClickListener(v -> {
            String server = etServer.getText().toString().trim();
            String code   = etCode.getText().toString().trim().toUpperCase();
            if (server.isEmpty() || code.isEmpty()) {
                tvStatus.setText("آدرس سرور و کد الزامی است");
                return;
            }
            if (!server.startsWith("http")) server = "http://" + server;
            SignageApp.get().saveServer(server);
            SignageApp.get().saveCode(code);
            tvStatus.setText("در حال اتصال...");
            recreate();
        });
    }

    // ─── OTA Update Check ─────────────────────────────────────
    private void checkForUpdate(String server) {
        Intent i = new Intent(this, UpdateService.class);
        i.putExtra("server", server);
        startService(i);
    }

    // ─── ورودی خارجی (HDMI مهمان) ──────────────────────────────────

    /** روی نخ اصلی */
    public boolean openInput(String inputId) {
        return inputOverlay != null && inputOverlay.open(inputId);
    }

    /** روی نخ اصلی */
    public void closeInput(String reason) {
        if (inputOverlay != null && inputOverlay.isOpen()) inputOverlay.close(reason);
        externalInput = false;
    }

    public void markExternalInput() { externalInput = true; }

    /* اپ ما HOME است؛ زدن دکمه‌ی HOME روی ریموت همین را صدا می‌زند.
       مهمان از HOME انتظار دارد به منوی هتل برگردد، نه روی HDMI بماند. */
    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        if (inputOverlay != null && inputOverlay.isOpen()) inputOverlay.close("back");
    }

    private void notifyInputClosed(String reason) {
        if (webView == null) return;
        String r = reason == null ? "" : reason.replaceAll("[^a-z]", "");
        webView.evaluateJavascript("window.TVInputClosed&&window.TVInputClosed('" + r + "')", null);
    }

    @Override
    public boolean dispatchKeyEvent(KeyEvent event) {
        if (inputOverlay != null && inputOverlay.onKey(event)) return true;
        return super.dispatchKeyEvent(event);
    }

    /**
     * راه پشتیبان (اپ Live TV سیستم) خودش با آماده‌به‌کار بسته نمی‌شود.
     * پس با خاموش شدن صفحه پورتال را جلو می‌آوریم تا روشن شدن بعدی روی
     * پورتال باشد نه HDMI مهمان قبلی. اپ HOME و Device Owner است، پس
     * اندروید ۱۰+ هم اجازه‌ی باز کردن Activity از پس‌زمینه را می‌دهد.
     */
    private void registerScreenReceiver() {
        screenReceiver = new android.content.BroadcastReceiver() {
            @Override
            public void onReceive(android.content.Context c, Intent i) {
                if (!externalInput) return;
                externalInput = false;
                try {
                    Intent back = new Intent(MainActivity.this, MainActivity.class);
                    back.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_REORDER_TO_FRONT);
                    startActivity(back);
                } catch (Exception ignored) { }
                notifyInputClosed("stop");
            }
        };
        android.content.IntentFilter f = new android.content.IntentFilter();
        f.addAction(Intent.ACTION_SCREEN_OFF);
        f.addAction(Intent.ACTION_SCREEN_ON);
        registerReceiver(screenReceiver, f);
    }

    @Override
    protected void onStop() {
        /* آماده‌به‌کار، خاموش شدن صفحه یا رفتن به اپ دیگر: HDMI مهمان
           بسته می‌شود تا روشن شدن بعدی روی پورتال باشد. */
        /* فقط لایه‌ی خود اپ؛ پرچم راه پشتیبان همین‌جا نباید پاک شود،
           چون باز شدن اپ Live TV خودش onStop را صدا می‌زند. */
        if (inputOverlay != null && inputOverlay.isOpen()) inputOverlay.close("stop");
        super.onStop();
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) webView.goBack();
        // در حالت kiosk back رو ignore کن
    }

    @Override
    protected void onResume() {
        super.onResume();
        /* مهمان از اپ Live TV سیستم برگشت (راه پشتیبان) */
        if (externalInput) { externalInput = false; notifyInputClosed("back"); }
        // hide system UI دوباره
        getWindow().getDecorView().setSystemUiVisibility(
            View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION |
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY);
    }

    @Override
    protected void onDestroy() {
        if (screenReceiver != null) {
            try { unregisterReceiver(screenReceiver); } catch (Exception ignored) { }
        }
        super.onDestroy();
        if (webView != null) { webView.destroy(); }
    }
}
