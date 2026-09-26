/* SENSI access screen. The runtime verifier works in the background. */
package com.sensi.inject;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Shader;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.content.pm.PackageManager;
import android.text.InputType;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.View;
import android.view.WindowManager;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import rikka.shizuku.Shizuku;


public class AccessScreen extends Activity {
    private static final int OVERLAY_REQ = 1234;
    private static final int RED = Color.parseColor("#E51B23");
    private static final int RED_DARK = Color.parseColor("#6E0B10");
    private static final int BG = Color.parseColor("#050607");
    private static final int CARD = Color.parseColor("#101214");
    private static final int TEXT = Color.parseColor("#F3F4F5");
    private static final int MUTED = Color.parseColor("#7B8086");
    private static final int AUTH_CYAN = Color.parseColor("#78DDE7");

    private EditText keyInput;
    private SharedPreferences prefs;
    private Handler main;
    private FrameLayout authOverlay;
    private TextView authMessage;
    private android.widget.CheckBox saveLogin;
    private int selectedMode = 0; // 0: Non-Root, 1: Shizuku, 2: Root

    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        main = new Handler(Looper.getMainLooper());
        prefs = getSharedPreferences("sensei", MODE_PRIVATE);
        if (Build.VERSION.SDK_INT >= 23 && !Settings.canDrawOverlays(this)) {
            startActivityForResult(new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                    Uri.parse("package:" + getPackageName())), OVERLAY_REQ);
        }
        ShizukuAccess.register(this);
        DiagnosticLogger.init(this);
        buildLogin();
    }

    private void buildLogin() {
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(BG);
        root.addView(new LoginEffects(this));

        LinearLayout wrap = new LinearLayout(this);
        wrap.setOrientation(LinearLayout.VERTICAL);
        wrap.setGravity(Gravity.CENTER_HORIZONTAL);
        wrap.setPadding(dp(26), dp(18), dp(26), dp(18));
        FrameLayout.LayoutParams wp = new FrameLayout.LayoutParams(-1, -2, Gravity.CENTER);
        root.addView(wrap, wp);

        TextView brand = txt("SENSI MODS", 30, TEXT);
        brand.setTypeface(FontKit.heading(this));
        brand.setGravity(Gravity.CENTER);
        brand.setLetterSpacing(.10f);
        wrap.addView(brand, lp(-1, dp(50), 0, 0, 0, 0));

        TextView sub = txt("SECURITY SYSTEM", 10, MUTED);
        sub.setTypeface(FontKit.mono(this), Typeface.BOLD);
        sub.setGravity(Gravity.CENTER);
        wrap.addView(sub, lp(-1, dp(24), 0, 0, 0, dp(14)));

        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(18), dp(18), dp(18), dp(18));
        GradientDrawable cg = new GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM,
                new int[]{Color.parseColor("#17181A"), Color.parseColor("#0B0C0E")});
        cg.setCornerRadius(dp(12)); cg.setStroke(dp(1), Color.parseColor("#4B1519"));
        card.setBackground(cg);
        wrap.addView(card, lp(-1, -2, 0, 0, 0, 0));

        TextView prompt = txt("Enter your key to continue", 16, TEXT);
        prompt.setGravity(Gravity.CENTER_VERTICAL);
        card.addView(prompt, lp(-1, dp(46), 0, 0, 0, dp(8)));

        TextView keyLabel = txt("Senseidev", 10, RED);
        keyLabel.setTypeface(FontKit.mono(this), Typeface.BOLD);
        card.addView(keyLabel, lp(-1, dp(20), 0, 0, 0, dp(5)));

        keyInput = new EditText(this);
        keyInput.setSingleLine(true);
        keyInput.setText(prefs.getString("saved_key", ""));
        keyInput.setHint("License Key...");
        keyInput.setHintTextColor(MUTED);
        keyInput.setTextColor(TEXT);
        keyInput.setTextSize(14);
        keyInput.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
        keyInput.setPadding(dp(14), 0, dp(14), 0);
        GradientDrawable kg = new GradientDrawable(); kg.setColor(Color.parseColor("#090A0C"));
        kg.setCornerRadius(dp(8)); kg.setStroke(dp(1), RED_DARK); keyInput.setBackground(kg);
        card.addView(keyInput, lp(-1, dp(52), 0, 0, 0, dp(8)));

        saveLogin = new android.widget.CheckBox(this);
        saveLogin.setText("Save login");
        saveLogin.setTextColor(MUTED);
        saveLogin.setTextSize(11);
        saveLogin.setChecked(prefs.getBoolean("save_login", true));
        if (Build.VERSION.SDK_INT >= 21) {
            saveLogin.setButtonTintList(new android.content.res.ColorStateList(
                    new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                    new int[]{RED, Color.parseColor("#667078")}));
        }
        card.addView(saveLogin, lp(-1, dp(38), 0, 0, 0, dp(5)));

        TextView modeLabel = txt("EXECUTION MODE", 9, MUTED);
        modeLabel.setTypeface(FontKit.mono(this), Typeface.BOLD);
        card.addView(modeLabel, lp(-1, dp(18), 0, 0, 0, dp(2)));

        addModeSelection(card);

        TextView musicStatus = txt("MUSIC  •  DISABLED", 9, Color.parseColor("#666B72"));
        musicStatus.setTypeface(FontKit.mono(this), Typeface.BOLD);
        musicStatus.setGravity(Gravity.CENTER_VERTICAL);
        card.addView(musicStatus, lp(-1, dp(24), 0, dp(2), 0, dp(4)));

        TextView login = txt("LOGIN", 15, Color.WHITE);
        login.setGravity(Gravity.CENTER); login.setTypeface(FontKit.heading(this), Typeface.BOLD);
        login.setLetterSpacing(.18f);
        GradientDrawable lg = new GradientDrawable(GradientDrawable.Orientation.TOP_BOTTOM,
                new int[]{RED, Color.parseColor("#A80F15")});
        lg.setCornerRadius(dp(10)); login.setBackground(lg);
        login.setOnClickListener(v -> authenticate());
        card.addView(login, lp(-1, dp(54), 0, 0, 0, 0));

        TextView foot = txt("PROTECTED  •  VERIFIED  •  SENSI", 8, MUTED);
        foot.setGravity(Gravity.CENTER); foot.setTypeface(FontKit.mono(this));
        wrap.addView(foot, lp(-1, dp(30), 0, dp(16), 0, 0));

        authOverlay = buildAuthDialog(root);
        authOverlay.setVisibility(View.GONE);
        root.addView(authOverlay);
        setContentView(root);
    }

    private static final int SHIZUKU_PERMISSION_REQUEST = 1001;
    private static final int STORAGE_PERMISSION_REQUEST = 102;
    private boolean isWaitingForShizuku = false;
    private boolean isWaitingForStorage = false;

    @Override protected void onResume() {
        super.onResume();
        // Resume the Shizuku/storage flow after returning from Shizuku Manager or
        // Android's special "All files access" settings screen.
        if (selectedMode == 1) {
            if (isWaitingForShizuku && checkShizukuSync()) {
                isWaitingForShizuku = false;
                continueShizukuFlow();
            } else if (isWaitingForStorage && hasStorageAccess()) {
                isWaitingForStorage = false;
                beginAuthentication();
            }
        }
    }

    @Override protected void onPause() {
        super.onPause();
    }

    private void addModeSelection(LinearLayout parent) {
        LinearLayout row = new LinearLayout(this);
        row.setPadding(0, dp(5), 0, dp(12));
        String[] labels = {"NON-ROOT", "SHIZUKU", "ROOT"};
        for (int i = 0; i < labels.length; i++) {
            final int index = i;
            TextView tab = new TextView(this);
            tab.setTag("mode_tab_" + i);
            tab.setText(labels[i]); tab.setGravity(Gravity.CENTER); tab.setTextSize(9);
            tab.setTypeface(FontKit.heading(this), Typeface.BOLD);
            updateTabStyle(tab, i == selectedMode);
            tab.setOnClickListener(v -> {
                selectedMode = index;
                isWaitingForShizuku = false; // Reset waiting state
                for (int j = 0; j < labels.length; j++) {
                    TextView t = parent.findViewWithTag("mode_tab_" + j);
                    if (t != null) updateTabStyle(t, j == selectedMode);
                }
            });
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, dp(34), 1f);
            if (i < labels.length - 1) lp.rightMargin = dp(5);
            row.addView(tab, lp);
        }
        parent.addView(row, parent.getChildCount() - 1);
    }

    private void checkShizukuStatus() {
        try {
            if (!Shizuku.pingBinder()) {
                Toast.makeText(this, "Shizuku is installed but its service is not running", Toast.LENGTH_LONG).show();
                return;
            }
            if (Shizuku.checkSelfPermission() == PackageManager.PERMISSION_GRANTED) {
                Toast.makeText(this, "Shizuku: Ready ✓", Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "Shizuku: Permission Required", Toast.LENGTH_SHORT).show();
            }
        } catch (Throwable e) {
            Toast.makeText(this, "Shizuku API could not connect", Toast.LENGTH_LONG).show();
        }
    }

    private void checkRootStatus() {
        RootAccess.request(isRoot -> main.post(() ->
                Toast.makeText(this, isRoot ? "Root: granted ✓" : "Root: Permission Required",
                        Toast.LENGTH_SHORT).show()));
    }

    private void updateTabStyle(TextView tab, boolean selected) {
        tab.setTextColor(selected ? Color.WHITE : Color.parseColor("#A0A5A8"));
        GradientDrawable bg = new GradientDrawable(); bg.setCornerRadius(dp(7));
        bg.setColor(selected ? Color.parseColor("#401416") : Color.parseColor("#111416"));
        bg.setStroke(dp(1), selected ? RED : Color.parseColor("#303539"));
        tab.setBackground(bg);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == STORAGE_PERMISSION_REQUEST) {
            if (hasStorageAccess()) {
                isWaitingForStorage = false;
                beginAuthentication();
            } else {
                Toast.makeText(this, "Storage access is required for Shizuku mode", Toast.LENGTH_LONG).show();
            }
        }
    }

    private void authenticate() {
        String key = keyInput.getText().toString().trim();
        if (key.isEmpty()) {
            Toast.makeText(this, "Enter your key", Toast.LENGTH_SHORT).show();
            return;
        }

        if (selectedMode == 1) {
            continueShizukuFlow();
            return;
        }

        if (selectedMode == 2) {
            // Check on every LOGIN tap. If permission was previously granted,
            // proceed without another prompt; if it was denied, the root
            // manager is asked again on the next tap.
            RootAccess.request(ok -> main.post(() -> {
                if (ok) beginAuthentication();
                else Toast.makeText(this, "Root permission is required. Tap LOGIN again to retry.", Toast.LENGTH_LONG).show();
            }));
            return;
        }

        // Non-root mode needs no privileged-service check.
        beginAuthentication();
    }

    private void continueShizukuFlow() {
        if (!checkShizukuSync()) {
            isWaitingForShizuku = true;
            requestShizukuPermission();
            return;
        }

        if (!hasStorageAccess()) {
            isWaitingForStorage = true;
            requestStorageAccess();
            return;
        }

        isWaitingForShizuku = false;
        isWaitingForStorage = false;
        beginAuthentication();
    }

    private void beginAuthentication() {
        final String key = keyInput.getText().toString().trim();
        if (key.isEmpty()) return;

        keyInput.setEnabled(false);
        showAuth(true);

        new Thread(() -> {
            final boolean secure = RuntimeCheck.check(this);
            main.post(() -> {
                if (!secure) {
                    hideAuth();
                    keyInput.setEnabled(true);
                    Toast.makeText(this, "Security verification failed", Toast.LENGTH_LONG).show();
                    return;
                }
                if (saveLogin != null) {
                    prefs.edit().putBoolean("save_login", saveLogin.isChecked())
                            .putString("saved_key", saveLogin.isChecked() ? key : "").apply();
                }
                authMessage.setText("Authenticating your connection..");
                main.postDelayed(this::launchClient, 900);
            });
        }).start();
    }

    private boolean checkShizukuSync() {
        return ShizukuAccess.isGranted();
    }

    private void requestShizukuPermission() {
        isWaitingForShizuku = true;
        ShizukuAccess.request(this, new ShizukuAccess.Callback() {
            @Override public void onReady() {
                main.post(() -> {
                    isWaitingForShizuku = false;
                    continueShizukuFlow();
                });
            }

            @Override public void onDenied() {
                main.post(() -> {
                    isWaitingForShizuku = false;
                    Toast.makeText(AccessScreen.this, "Shizuku permission denied. Tap LOGIN again to request it.", Toast.LENGTH_LONG).show();
                });
            }

            @Override public void onNotRunning() {
                main.post(() -> {
                    isWaitingForShizuku = true;
                    Toast.makeText(AccessScreen.this, "Start Shizuku, then return and tap LOGIN again.", Toast.LENGTH_LONG).show();
                });
            }
        });
    }

    private boolean hasStorageAccess() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            return Environment.isExternalStorageManager();
        }
        return Build.VERSION.SDK_INT < 23
                || checkSelfPermission(android.Manifest.permission.READ_EXTERNAL_STORAGE) == PackageManager.PERMISSION_GRANTED;
    }

    private void requestStorageAccess() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                Toast.makeText(this, "Allow SENSI MODS to manage storage, then return to the app", Toast.LENGTH_LONG).show();
                Intent i = new Intent(Settings.ACTION_MANAGE_APP_ALL_FILES_ACCESS_PERMISSION,
                        Uri.parse("package:" + getPackageName()));
                try {
                    startActivity(i);
                } catch (Exception e) {
                    startActivity(new Intent(Settings.ACTION_MANAGE_ALL_FILES_ACCESS_PERMISSION));
                }
                return;
            }

            requestPermissions(new String[]{android.Manifest.permission.READ_EXTERNAL_STORAGE,
                    android.Manifest.permission.WRITE_EXTERNAL_STORAGE}, STORAGE_PERMISSION_REQUEST);
        } catch (Throwable e) {
            Toast.makeText(this, "Unable to open storage permissions", Toast.LENGTH_LONG).show();
        }
    }

    private boolean checkRootPermission() {
        // Obsolete - moved to thread in authenticate
        return true;
    }

    private boolean checkShizukuPermission() {
        // Obsolete - moved to thread in authenticate
        return true;
    }

    private void launchClient() {
        DiagnosticLogger.log("Launching OverlayService mode=" + selectedMode);
        try {
            Intent i = new Intent(this, OverlayService.class);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) startForegroundService(i); else startService(i);
        } catch (Exception e) { Toast.makeText(this, "Unable to start client", Toast.LENGTH_LONG).show(); }
        hideAuth();
        moveTaskToBack(true);
    }

    private FrameLayout buildAuthDialog(FrameLayout parent) {
        FrameLayout overlay = new FrameLayout(this);
        overlay.setBackgroundColor(Color.argb(155, 0, 0, 0));
        LinearLayout box = new LinearLayout(this);
        box.setGravity(Gravity.CENTER_VERTICAL); box.setPadding(dp(20), dp(20), dp(20), dp(20));
        GradientDrawable bg = new GradientDrawable(); bg.setColor(Color.parseColor("#494949")); bg.setCornerRadius(dp(4));
        box.setBackground(bg);
        FrameLayout.LayoutParams bp = new FrameLayout.LayoutParams(-1, dp(132), Gravity.CENTER);
        bp.setMargins(dp(18), 0, dp(18), 0); overlay.addView(box, bp);

        ProgressRing ring = new ProgressRing(this); box.addView(ring, new LinearLayout.LayoutParams(dp(72), dp(72)));
        authMessage = txt("Authenticating your connection..", 20, Color.WHITE);
        authMessage.setGravity(Gravity.CENTER_VERTICAL); authMessage.setPadding(dp(18), 0, 0, 0);
        box.addView(authMessage, new LinearLayout.LayoutParams(0, -1, 1f));
        return overlay;
    }

    private void showAuth(boolean show) { authOverlay.setVisibility(show ? View.VISIBLE : View.GONE); }
    private void hideAuth() { showAuth(false); }

    private TextView txt(String s, float size, int color) { TextView t = new TextView(this); t.setText(s); t.setTextSize(size); t.setTextColor(color); return t; }
    private LinearLayout.LayoutParams lp(int w, int h, int l, int t, int r, int b) { LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(w, h); p.setMargins(dp(l), dp(t), dp(r), dp(b)); return p; }
    private int dp(float v) { return (int)TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, v, getResources().getDisplayMetrics()); }

    private class ProgressRing extends View {
        private final Paint p = new Paint(Paint.ANTI_ALIAS_FLAG);
        ProgressRing(Context c) { super(c); p.setStyle(Paint.Style.STROKE); p.setStrokeWidth(dp(7)); }
        @Override protected void onDraw(Canvas c) {
            float r=Math.min(getWidth(),getHeight())*.35f;
            p.setColor(Color.parseColor("#333333"));
            c.drawArc(getWidth()/2f-r,getHeight()/2f-r,getWidth()/2f+r,getHeight()/2f+r,0,360,false,p);
            p.setColor(AUTH_CYAN);
            c.drawArc(getWidth()/2f-r,getHeight()/2f-r,getWidth()/2f+r,getHeight()/2f+r,-55,245,false,p);
        }
    }

    private class LoginEffects extends View {
        private final Paint p = new Paint(Paint.ANTI_ALIAS_FLAG);

        LoginEffects(Context c) {
            super(c);
            setLayerType(View.LAYER_TYPE_HARDWARE, null);
        }

        @Override protected void onDraw(Canvas c) {
            int w = getWidth(), h = getHeight();
            if (w <= 0 || h <= 0) return;
            p.setShader(new android.graphics.LinearGradient(
                    0, 0, w, h,
                    new int[]{Color.parseColor("#08090B"), Color.parseColor("#24070A"), Color.parseColor("#050607")},
                    null, Shader.TileMode.CLAMP));
            c.drawRect(0, 0, w, h, p);
            p.setShader(null);
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
    }

}
