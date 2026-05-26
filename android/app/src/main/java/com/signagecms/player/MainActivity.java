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
import com.signagecms.player.service.UpdateService;

public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progress;
    private TextView statusText;
    private Handler handler = new Handler(Looper.getMainLooper());

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

        // JavaScript Bridge
        webView.addJavascriptInterface(new SignageBridge(this), "AndroidBridge");
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

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) webView.goBack();
        // در حالت kiosk back رو ignore کن
    }

    @Override
    protected void onResume() {
        super.onResume();
        // hide system UI دوباره
        getWindow().getDecorView().setSystemUiVisibility(
            View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION |
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY);
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (webView != null) { webView.destroy(); }
    }
}
