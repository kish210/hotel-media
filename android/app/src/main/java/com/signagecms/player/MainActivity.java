package com.signagecms.player;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.View;
import android.view.WindowManager;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import com.signagecms.player.input.InputOverlay;
import com.signagecms.player.kiosk.KioskManager;
import com.signagecms.player.service.UpdateService;

/**
 * صفحه‌ی پلیر.
 *
 * سازگاری: از API 16 (اندروید ۴.۱) به بالا. هر API تازه با
 * Build.VERSION.SDK_INT محافظت شده — بدون این، اپ روی اندروید ۴ با
 * NoSuchMethodError می‌افتد، نه با استثنای قابل گرفتن.
 */
public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progress;
    private TextView statusText;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private InputOverlay inputOverlay;
    private PlayerSettings settings;

    /** ورودی با اپ Live TV سیستم باز شد (راه پشتیبان) */
    private boolean externalInput = false;
    private BroadcastReceiver screenReceiver;

    /** آدرسی که همین حالا لود شده — برای تشخیص تغییر تنظیمات */
    private String loadedServer = "";
    private String loadedCode   = "";

    /* راه تکنسین: ۵ بار BACK پشت‌سرهم بخش تنظیمات را باز می‌کند */
    private int backCount = 0;
    private long lastBackAt = 0;

    @SuppressLint({"SetJavaScriptEnabled","ClickableViewAccessibility"})
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        settings = PlayerSettings.of(this);

        // Full screen kiosk
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED |
                             WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD |
                             WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON);
        applyKeepScreenOn();
        hideSystemUi();
        requestWindowFeature(Window.FEATURE_NO_TITLE);

        setContentView(R.layout.activity_main);
        webView    = findViewById(R.id.webView);
        progress   = findViewById(R.id.progressBar);
        statusText = findViewById(R.id.statusText);

        setupWebView();

        /* HDMI مهمان روی همین صفحه، بالای WebView.
           TvView از API 21 است؛ روی اندروید ۴ اصلا ساخته نمی‌شود. */
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            try {
                inputOverlay = new InputOverlay(this, (FrameLayout) webView.getParent(), reason -> {
                    webView.requestFocus();
                    notifyInputClosed(reason);
                });
            } catch (Throwable t) {
                inputOverlay = null;
            }
        }
        registerScreenReceiver();

        String server = SignageApp.get().getServerUrl();
        String code   = SignageApp.get().getScreenCode();

        if (server.isEmpty()) {
            showSetupScreen();
        } else {
            loadPlayer(server, code);
            scheduleUpdateCheck(server);
            if (settings.kioskOnStart()) applyKiosk();
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings ws = webView.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        ws.setDatabaseEnabled(true);                 // localStorage روی WebKit قدیمی
        ws.setMediaPlaybackRequiresUserGesture(false);  // ← مهم برای autoplay
        ws.setLoadWithOverviewMode(true);
        ws.setUseWideViewPort(true);
        ws.setCacheMode(WebSettings.LOAD_DEFAULT);
        ws.setAllowFileAccess(true);
        ws.setAllowContentAccess(true);
        ws.setBuiltInZoomControls(false);
        ws.setDisplayZoomControls(false);

        /* WPAD/محتوا روی HTTP سرو می‌شود؛ روی API 21+ باید صریح اجازه داد */
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            ws.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
            ws.setAllowUniversalAccessFromFileURLs(true);
        }
        try { ws.setPluginState(WebSettings.PluginState.ON); } catch (Throwable ignored) { }

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                progress.setVisibility(View.GONE);
                // inject JS: نوع دستگاه به پلیر بگو
                js("if(window._signageDeviceType===undefined){window._signageDeviceType='android-app';}");
            }

            /* امضای چهارآرگومانی از API 23 است؛ نسخه‌ی قدیمی را هم داریم */
            @Override
            public void onReceivedError(WebView view, WebResourceRequest request,
                                        WebResourceError error) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M
                        && request != null && request.isForMainFrame()) {
                    scheduleReload();
                }
            }

            @Override
            public void onReceivedError(WebView view, int errorCode, String description,
                                        String failingUrl) {
                if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) scheduleReload();
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int p) {
                progress.setProgress(p);
                progress.setVisibility(p >= 100 ? View.GONE : View.VISIBLE);
            }
        });

        // JavaScript Bridge — tv-base.js با نام SignageBridge کار می‌کند،
        // پروفایل‌های قدیمی با AndroidBridge. هر دو ثبت می‌شوند.
        SignageBridge bridge = new SignageBridge(this);
        webView.addJavascriptInterface(bridge, "AndroidBridge");
        webView.addJavascriptInterface(bridge, "SignageBridge");
    }

    /** اجرای JS با پشتیبانی اندروید ۴ (evaluateJavascript از API 19 است) */
    private void js(String script) {
        if (webView == null) return;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT) {
            webView.evaluateJavascript(script, null);
        } else {
            webView.loadUrl("javascript:" + script.replace("\n", " "));
        }
    }

    private void scheduleReload() {
        int delay = settings.retrySeconds() * 1000;
        handler.postDelayed(() -> { if (webView != null) webView.reload(); }, delay);
    }

    public void loadPlayer(String server, String code) {
        loadedServer = server;
        loadedCode   = code;
        String url = server.replaceAll("/$", "") + "/player/" + code;
        progress.setVisibility(View.VISIBLE);
        webView.loadUrl(url);
        webView.setVisibility(View.VISIBLE);
        if (statusText != null) statusText.setVisibility(View.GONE);
    }

    private void showSetupScreen() {
        webView.setVisibility(View.GONE);
        setContentView(R.layout.activity_setup);

        EditText etServer = findViewById(R.id.etServer);
        EditText etCode   = findViewById(R.id.etCode);
        Button   btnSave  = findViewById(R.id.btnSave);
        Button   btnSetup = findViewById(R.id.btnSettings);
        TextView tvStatus = findViewById(R.id.tvStatus);

        etServer.setText(SignageApp.get().getServerUrl());
        etCode.setText(SignageApp.get().getScreenCode());

        btnSave.setOnClickListener(v -> {
            String server = etServer.getText().toString().trim().replaceAll("/+$", "");
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

        if (btnSetup != null) btnSetup.setOnClickListener(v -> openSettings());
    }

    /** بخش تنظیمات — از دکمه‌ی راه‌اندازی یا ۵ بار BACK */
    public void openSettings() {
        try {
            startActivity(new Intent(this, SettingsActivity.class));
        } catch (Throwable t) {
            Toast.makeText(this, "باز کردن تنظیمات ممکن نشد", Toast.LENGTH_SHORT).show();
        }
    }

    // ─── OTA Update Check ─────────────────────────────────────
    private void scheduleUpdateCheck(String server) {
        if (!settings.autoUpdate()) return;
        handler.postDelayed(() -> {
            try {
                Intent i = new Intent(this, UpdateService.class);
                i.putExtra("server", server);
                startService(i);
            } catch (Throwable ignored) { }
        }, 10000);
    }

    // ─── قفل‌گاه ───────────────────────────────────────────────────

    private void applyKiosk() {
        handler.postDelayed(() -> {
            try {
                KioskManager km = new KioskManager(this);
                km.applyOwnerPolicies();
                km.enterKiosk();
            } catch (Throwable ignored) { }
        }, 3000);
    }

    private void applyKeepScreenOn() {
        if (settings != null && settings.keepScreenOn()) {
            getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        } else {
            getWindow().clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        }
    }

    private void hideSystemUi() {
        getWindow().getDecorView().setSystemUiVisibility(
            View.SYSTEM_UI_FLAG_FULLSCREEN |
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION |
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY |
            View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN |
            View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
        );
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
        js("window.TVInputClosed&&window.TVInputClosed('" + r + "')");
    }

    @Override
    public boolean dispatchKeyEvent(KeyEvent event) {
        if (inputOverlay != null && inputOverlay.onKey(event)) return true;
        return super.dispatchKeyEvent(event);
    }

    /**
     * راه پشتیبان (اپ Live TV سیستم) خودش با آماده‌به‌کار بسته نمی‌شود.
     * پس با خاموش شدن صفحه پورتال را جلو می‌آوریم تا روشن شدن بعدی روی
     * پورتال باشد نه HDMI مهمان قبلی.
     */
    private void registerScreenReceiver() {
        screenReceiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context c, Intent i) {
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
        IntentFilter f = new IntentFilter();
        f.addAction(Intent.ACTION_SCREEN_OFF);
        f.addAction(Intent.ACTION_SCREEN_ON);
        registerReceiver(screenReceiver, f);
    }

    @Override
    protected void onStop() {
        /* آماده‌به‌کار، خاموش شدن صفحه یا رفتن به اپ دیگر: HDMI مهمان
           بسته می‌شود تا روشن شدن بعدی روی پورتال باشد. */
        if (inputOverlay != null && inputOverlay.isOpen()) inputOverlay.close("stop");
        super.onStop();
    }

    @Override
    public void onBackPressed() {
        long now = System.currentTimeMillis();
        if (now - lastBackAt > 3000) backCount = 0;
        lastBackAt = now;
        backCount++;

        if (backCount >= 5) {
            backCount = 0;
            openSettings();
            return;
        }
        if (backCount >= 3) {
            Toast.makeText(this, "برای تنظیمات " + (5 - backCount) + " بار دیگر BACK",
                Toast.LENGTH_SHORT).show();
        }
        if (webView != null && webView.canGoBack()) webView.goBack();
    }

    @Override
    protected void onResume() {
        super.onResume();
        /* مهمان از اپ Live TV سیستم برگشت (راه پشتیبان) */
        if (externalInput) { externalInput = false; notifyInputClosed("back"); }

        applyKeepScreenOn();
        hideSystemUi();

        /* از بخش تنظیمات برگشتیم و آدرس/کد عوض شده → دوباره لود کن */
        if (webView != null && !loadedServer.isEmpty() && settings != null) {
            String server = SignageApp.get().getServerUrl();
            String code   = SignageApp.get().getScreenCode();
            if (!server.isEmpty() && (!server.equals(loadedServer) || !code.equals(loadedCode))) {
                loadPlayer(server, code);
            }
        }
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
