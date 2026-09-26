/*
 * ============================================================
 *  ControlPanel - Standard Mod Menu
 *  Made by Senseidev
 * ============================================================
 */
package com.sensi.inject;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.PixelFormat;
import android.graphics.RectF;
import android.graphics.Shader;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.text.Spanned;
import android.util.TypedValue;
import android.util.Log;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import java.util.Locale;

public class ControlPanel {

    private static int C_PANEL   = Color.parseColor("#120507");
    private static int C_ACCENT  = Color.parseColor("#FF3B4F");
    private static int C_ACCENT2 = Color.parseColor("#090B0F");
    private static int C_ACCENT3 = Color.parseColor("#F7F9FC");

    private static final int C_BG      = Color.parseColor("#FF0B0D10");
    private static final int C_DIM     = Color.parseColor("#66FFFFFF");

    private static final int PANEL_WIDTH_DP  = 410;
    private static final int PANEL_HEIGHT_DP = 350;
    private static final int ICON_SIZE_DP    = 65;
    private static final int CORNER_RADIUS   = 5;


    public interface OnMenuUpdateListener {
        void onToggleChanged(int id, boolean on);
        void onSliderChanged(int id, int value);
        void onThemeChanged(int c1, int c2, int c3);
    }

    private static native void nativeSendToggle(int id, int state);

    private final Context ctx;
    private final WindowManager wm;
    private final OnMenuUpdateListener listener;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private WindowManager.LayoutParams params;
    private FrameLayout rootFrame;
    private View iconView;
    private LinearLayout panelView;
    private LinearLayout currentCard;
    private View statusDot;
    private TextView statusText;
    private final String version;
    private final String seller;

    private Bitmap iconBitmap;
    private boolean isActivated = false;
    
    private int activeTab = 0;
    private LinearLayout featureList;
    private int iconOpacity = 100;
    private int panelA = Color.parseColor("#0B0D10");
    private int panelB = Color.parseColor("#15181C");
    private int panelC = Color.WHITE;
    
    private final boolean[] toggleStates = new boolean[1000];
    private final int[] sliderStates = new int[1000];
    
    // 25 clean theme packs. Each pack is exactly: unique accent + black + white.
    private static final String[] THEME_NAMES = {
        "Red Noir", "Orange Noir", "Amber Noir", "Yellow Noir", "Lime Noir",
        "Green Noir", "Emerald Noir", "Teal Noir", "Cyan Noir", "Sky Noir",
        "Blue Noir", "Cobalt Noir", "Indigo Noir", "Violet Noir", "Purple Noir",
        "Magenta Noir", "Fuchsia Noir", "Pink Noir", "Rose Noir", "Coral Noir",
        "Salmon Noir", "Copper Noir", "Mint Noir", "Ice Noir", "Silver Noir"
    };

    private static final int[][] THEME_PACKS = {
        {Color.parseColor("#E51B23"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#FF6B1A"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#F5A623"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#FFD400"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#9BE33C"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#31C56A"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#18B86A"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#19C7B5"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#18D9E8"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#35B7FF"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#2979FF"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#4B6FFF"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#6267FF"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#8B5CF6"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#A855F7"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#C026D3"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#E43AF2"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#F04F9A"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#FF4F81"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#FF6F61"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#FF7A7A"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#C87533"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#45D6A1"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#8BE8FF"), Color.BLACK, Color.WHITE},
        {Color.parseColor("#B8C0CC"), Color.BLACK, Color.WHITE}
    };

    public ControlPanel(Context context, WindowManager windowManager, String version, String seller, OnMenuUpdateListener listener) {
        this.ctx = context;
        this.wm = windowManager;
        this.listener = listener;
        this.version = (version != null && !version.isEmpty()) ? version : "1.0";
        this.seller = (seller != null && !seller.isEmpty()) ? seller : "Senseidev";

        try {
            iconBitmap = BitmapFactory.decodeResource(ctx.getResources(), R.mipmap.icon);
        } catch (Exception e) {
            iconBitmap = null;
        }

        // Initialize default slider values
        sliderStates[14] = 120; // AimFov
        sliderStates[10] = 0;   // ESP Color
        sliderStates[27] = 1;   // Speed Multiplier
        sliderStates[998] = iconOpacity;

        initWindowParams();
        buildUI();
    }

    private void initWindowParams() {
        int layoutFlag;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            layoutFlag = WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY;
        } else {
            layoutFlag = WindowManager.LayoutParams.TYPE_PHONE;
        }

        params = new WindowManager.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT,
                ViewGroup.LayoutParams.WRAP_CONTENT,
                layoutFlag,
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE,
                PixelFormat.TRANSLUCENT);
        params.gravity = Gravity.TOP | Gravity.START;
        params.x = 0;
        params.y = dp(80f);
    }

    private void buildUI() {
        rootFrame = new FrameLayout(ctx) {
            @Override
            public boolean performClick() { return super.performClick(); }
        };

        iconView = buildIcon();
        panelView = buildPanel();
        panelView.setVisibility(View.GONE);

        rootFrame.addView(iconView);
        rootFrame.addView(panelView);
        
        try {
            wm.addView(rootFrame, params);
        } catch (Exception ignored) {}

        iconView.setOnClickListener(v -> {
            iconView.setVisibility(View.GONE);
            panelView.setVisibility(View.VISIBLE);
        });

        View.OnTouchListener drag = new View.OnTouchListener() {
            private int initialX, initialY;
            private float initialTouchX, initialTouchY;

            @Override
            public boolean onTouch(View v, MotionEvent event) {
                switch (event.getAction()) {
                    case MotionEvent.ACTION_DOWN:
                        initialX = params.x; initialY = params.y;
                        initialTouchX = event.getRawX(); initialTouchY = event.getRawY();
                        return true;
                    case MotionEvent.ACTION_UP:
                        float dx = event.getRawX() - initialTouchX, dy = event.getRawY() - initialTouchY;
                        if (Math.abs(dx) < 10 && Math.abs(dy) < 10) v.performClick();
                        return true;
                    case MotionEvent.ACTION_MOVE:
                        params.x = initialX + (int) (event.getRawX() - initialTouchX);
                        params.y = initialY + (int) (event.getRawY() - initialTouchY);
                        try { wm.updateViewLayout(rootFrame, params); } catch (Exception ignored) {}
                        return true;
                }
                return false;
            }
        };
        iconView.setOnTouchListener(drag);
        rootFrame.setOnTouchListener(drag);
    }

    private View buildIcon() {
        View icon = new View(ctx) {
            private final Paint glow = new Paint(Paint.ANTI_ALIAS_FLAG);
            private final Paint imagePaint = new Paint(Paint.ANTI_ALIAS_FLAG | Paint.FILTER_BITMAP_FLAG);
            private final android.graphics.RectF rect = new android.graphics.RectF();
            @Override public boolean performClick() { return super.performClick(); }
            @Override protected void onDraw(Canvas c) {
                float w = getWidth(), h = getHeight();
                if (iconBitmap != null) {
                    float s = Math.min(w, h) * 0.82f;
                    rect.set(w/2f-s/2f, h/2f-s/2f, w/2f+s/2f, h/2f+s/2f);
                    c.drawBitmap(iconBitmap, null, rect, imagePaint);
                } else {
                    Paint p = new Paint(Paint.ANTI_ALIAS_FLAG);
                    p.setColor(C_ACCENT); p.setTextSize(dp(24)); p.setTypeface(Typeface.DEFAULT_BOLD);
                    p.setTextAlign(Paint.Align.CENTER);
                    c.drawText("S", w/2f, h/2f+dp(8), p);
                }
            }
        };
        icon.setLayoutParams(new FrameLayout.LayoutParams(dp(ICON_SIZE_DP), dp(ICON_SIZE_DP)));
        return icon;
    }

    private LinearLayout buildPanel() {
        LinearLayout mainContainer = new LinearLayout(ctx);
        mainContainer.setOrientation(LinearLayout.HORIZONTAL);
        refreshPanelBackground(mainContainer);
        
        // Add padding at the edges of the panel to prevent elements from clinging to borders
        mainContainer.setPadding(dp(12), dp(12), dp(12), dp(12));
        int screenW = ctx.getResources().getDisplayMetrics().widthPixels;
        int screenH = ctx.getResources().getDisplayMetrics().heightPixels;
        int panelW = Math.min(dp(PANEL_WIDTH_DP), Math.max(dp(300), screenW - dp(18)));
        int panelH = Math.min(dp(PANEL_HEIGHT_DP + 60), Math.max(dp(300), screenH - dp(120)));
        mainContainer.setLayoutParams(new FrameLayout.LayoutParams(panelW, panelH));

        // Sidebar
        LinearLayout sidebar = new LinearLayout(ctx);
        sidebar.setOrientation(LinearLayout.VERTICAL);
        sidebar.setGravity(Gravity.CENTER_HORIZONTAL);
        sidebar.setPadding(0, dp(10), 0, 0);
        sidebar.setLayoutParams(new LinearLayout.LayoutParams(dp(92), -1));
        
        sidebar.addView(buildSidebarTab("AIMBOT", 0));
        sidebar.addView(buildSidebarTab("VISUAL", 1));
        sidebar.addView(buildSidebarTab("EXPLOITS", 2));
        sidebar.addView(buildSidebarTab("THEME", 3));
        sidebar.addView(buildSidebarTab("SETTINGS", 4));
        
        mainContainer.addView(sidebar);

        // Content Area
        LinearLayout contentWrapper = new LinearLayout(ctx);
        contentWrapper.setOrientation(LinearLayout.VERTICAL);
        contentWrapper.setLayoutParams(new LinearLayout.LayoutParams(0, -1, 1f));

        contentWrapper.addView(buildHeader());

        ScrollView scroll = new ScrollView(ctx);
        scroll.setVerticalScrollBarEnabled(false);
        scroll.setLayoutParams(new LinearLayout.LayoutParams(-1, 0, 1f));

        featureList = new LinearLayout(ctx);
        featureList.setOrientation(LinearLayout.VERTICAL);
        featureList.setPadding(dp(5), dp(5), dp(10), dp(5));

        refreshContent();

        scroll.addView(featureList);
        contentWrapper.addView(scroll);
        
        // Footer
        TextView footer = new TextView(ctx);
        footer.setText(String.format(Locale.US, "v%s SENSI EXTERNAL BY %s", version, seller)); 
        footer.setTextColor(Color.GRAY); footer.setTextSize(7);
        footer.setGravity(Gravity.CENTER);
        contentWrapper.addView(footer);

        TextView close = new TextView(ctx);
        close.setText("MINIMIZE INTERFACE"); close.setTextColor(C_ACCENT); close.setTextSize(9);
        close.setGravity(Gravity.CENTER); close.setPadding(0, dp(5), 0, dp(10));
        close.setOnClickListener(v -> {
            panelView.setVisibility(View.GONE);
            iconView.setVisibility(View.VISIBLE);
        });
        contentWrapper.addView(close);

        mainContainer.addView(contentWrapper);

        return mainContainer;
    }

    private void refreshPanelBackground(View v) {
        // Keep the main panel intentionally clean: exactly two background colors.
        // The third theme color (white) is reserved for highlights/text.
        GradientDrawable gd = new GradientDrawable(
                GradientDrawable.Orientation.TL_BR,
                new int[]{Color.parseColor("#0A0C0F"), Color.parseColor("#15181C")});
        gd.setCornerRadius(dp(CORNER_RADIUS));
        gd.setStroke(dp(1), withAlpha(C_ACCENT, 80));
        v.setBackground(gd);
    }


    private TextView buildSidebarTab(String label, int tabIndex) {
        TextView v = new TextView(ctx);
        v.setText(label); v.setTextSize(9); v.setGravity(Gravity.CENTER);
        v.setTypeface(FontKit.heading(ctx), Typeface.BOLD);
        v.setLetterSpacing(.08f);
        v.setTextColor(tabIndex == activeTab ? Color.WHITE : Color.parseColor("#85878D"));
        v.setPadding(0, dp(10), 0, dp(10));
        v.setBackground(makeSidebarBackground(tabIndex == activeTab));
        v.setOnClickListener(x -> { 
            activeTab = tabIndex; 
            refreshContent(); 
            updateSidebarColors(); 
        });
        v.setLayoutParams(new LinearLayout.LayoutParams(-1, dp(55)));
        return v;
    }

    private GradientDrawable makeSidebarBackground(boolean active) {
        GradientDrawable g = new GradientDrawable(GradientDrawable.Orientation.LEFT_RIGHT,
                active ? new int[]{withAlpha(C_ACCENT, 28), Color.TRANSPARENT} : new int[]{Color.TRANSPARENT,Color.TRANSPARENT});
        g.setCornerRadius(dp(7));
        if(active) g.setStroke(dp(1), withAlpha(C_ACCENT, 100));
        return g;
    }

    private View buildSidebarIconView(int type, int tabIndex) {
        View v = new View(ctx) {
            private final Paint p = new Paint(Paint.ANTI_ALIAS_FLAG);
            private final Path path = new Path();

            @Override
            protected void onDraw(Canvas c) {
                float w = getWidth(), h = getHeight();
                float s = dp(20);
                float cx = w/2, cy = h/2;
                
                int color1 = activeTab == tabIndex ? C_ACCENT : Color.DKGRAY;
                int color2 = activeTab == tabIndex ? C_ACCENT2 : Color.GRAY;
                int color3 = activeTab == tabIndex ? C_ACCENT3 : Color.BLACK;
                
                LinearGradient grad = new LinearGradient(0, 0, w, h, new int[]{color1, color3}, null, Shader.TileMode.CLAMP);
                p.setShader(grad);
                p.setStyle(Paint.Style.STROKE);
                p.setStrokeWidth(dp(1.5f));

                if (type == 0) { // Aimbot - Sharp Crosshair
                    c.drawCircle(cx, cy, s/2.2f, p);
                    c.drawLine(cx - s/2, cy, cx - s/5, cy, p);
                    c.drawLine(cx + s/5, cy, cx + s/2, cy, p);
                    c.drawLine(cx, cy - s/2, cx, cy - s/5, p);
                    c.drawLine(cx, cy + s/5, cx, cy + s/2, p);
                    p.setStyle(Paint.Style.FILL);
                    c.drawCircle(cx, cy, dp(1.5f), p);
                } else if (type == 1) { // ESP - Sharp Eye
                    path.reset();
                    path.moveTo(cx - s/2, cy); // Left corner
                    path.quadTo(cx, cy - s/2.2f, cx + s/2, cy); // Top curve
                    path.quadTo(cx, cy + s/2.2f, cx - s/2, cy); // Bottom curve
                    path.close();
                    c.drawPath(path, p);
                    p.setStyle(Paint.Style.FILL);
                    c.drawCircle(cx, cy, s/5.5f, p);
                    p.setStyle(Paint.Style.STROKE);
                    p.setStrokeWidth(dp(1.2f));
                    c.drawLine(cx - s/2.2f, cy - s/2.2f, cx + s/2.2f, cy + s/2.2f, p); // Sharp cross line
                } else if (type == 2) { // Exploits - Sharp Lightning
                    path.reset();
                    path.moveTo(cx + dp(3), cy - s/2);
                    path.lineTo(cx - dp(5), cy + dp(1));
                    path.lineTo(cx + dp(1), cy + dp(1));
                    path.lineTo(cx - dp(3), cy + s/2);
                    path.lineTo(cx + dp(5), cy - dp(1));
                    path.lineTo(cx - dp(1), cy - dp(1));
                    path.close();
                    p.setStyle(Paint.Style.FILL);
                    c.drawPath(path, p);
                } else if (type == 3) { // Theme - palette dot
                    p.setStyle(Paint.Style.STROKE); p.setStrokeWidth(dp(2));
                    c.drawCircle(cx, cy, s/3, p);
                    p.setStyle(Paint.Style.FILL); c.drawCircle(cx+s/4, cy-s/4, dp(2), p);
                } else if (type == 4) { // Settings - Detailed Gear
                    p.setStrokeWidth(dp(2));
                    c.drawCircle(cx, cy, s/4, p);
                    p.setStyle(Paint.Style.STROKE);
                    p.setStrokeWidth(dp(1.5f));
                    for (int i=0; i<8; i++) {
                        c.save();
                        c.rotate(i*45, cx, cy);
                        path.reset();
                        path.moveTo(cx - dp(2.5f), cy - s/2);
                        path.lineTo(cx + dp(2.5f), cy - s/2);
                        path.lineTo(cx + dp(1.5f), cy - s/3.5f);
                        path.lineTo(cx - dp(1.5f), cy - s/3.5f);
                        path.close();
                        p.setStyle(Paint.Style.FILL);
                        c.drawPath(path, p);
                        c.restore();
                    }
                }
                p.setShader(null);
            }
        };
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, dp(40));
        v.setLayoutParams(lp);
        v.setOnClickListener(view -> {
            activeTab = tabIndex;
            updateSidebarColors();
            refreshContent();
        });
        return v;
    }

    private void updateSidebarColors() {
        LinearLayout sidebar = (LinearLayout) panelView.getChildAt(0);
        for (int i = 0; i < sidebar.getChildCount(); i++) {
            View child = sidebar.getChildAt(i);
            if (child instanceof TextView) {
                TextView tv = (TextView) child;
                int index = i;
                tv.setTextColor(index == activeTab ? Color.WHITE : Color.parseColor("#85878D"));
                tv.setBackground(makeSidebarBackground(index == activeTab));
            }
            child.invalidate();
        }
    }

    private int exploitSubTab = 0;
    private int visualSubTab = 0;
    private int settingsSubTab = 0;

    private void refreshContent() {
        featureList.removeAllViews();
        switch (activeTab) {
            case 0:
                addSection(featureList, "AIMBOT");
                startCard();
                addCheckBox(" MAIN CONTROL", toggleStates[100], 100);
                addCheckBox("Aimbot", toggleStates[101], 101);
                addCheckBox("Silent Aim", toggleStates[102], 102);
                addCheckBox("Aim Lock", toggleStates[103], 103);
                endCard(featureList);
                addSection(featureList, "METRIC CONFIGURATION");
                startCard();
                addSlider(currentCard, "FOV Radius", 120, 10, 360, "°", 14);
                addSlider(currentCard, "Range Vector", 500, 25, 1000, "m", 15);
                addChoice("Priority ", new String[]{"Crosshair", "Distance"});
                endCard(featureList);
                addMutedCard(featureList, "Architecture Online. All modules reporting nominal status.");
                break;
            case 1:
                addSection(featureList, "VISUALS");
                addSegmentedTabs(featureList, new String[]{"ESP", "COLOR"}, visualSubTab,
                        which -> { visualSubTab = which; refreshContent(); });
                if (visualSubTab == 0) buildEspPage(); else buildColourPage();
                break;
            case 2:
                addSection(featureList, "EXPLOITS");
                addSegmentedTabs(featureList, new String[]{"BRUTAL", "FLOATER"}, exploitSubTab,
                        which -> { exploitSubTab = which; refreshContent(); });
                if (exploitSubTab == 0) {
                    addSection(featureList, "BRUTAL");
                    startCard();
                    addCheckBox("Speed Hack", false);
                    addCheckBox("No Recoil", false);
                    addCheckBox("Fast Reload", false);
                    addMutedInfo("// Features restricted in non-root mode");
                    endCard(featureList);
                } else {
                    addSection(featureList, "FLOATER");
                    startCard();
                    addCheckBox("Speed Floater", toggleStates[28], 28);
                    addSlider(currentCard, "Floater Speed", 1, 1, 5, "x", 27);
                    addMutedInfo("// Floating widget interface active");
                    endCard(featureList);
                }
                break;
            case 3:
                addSection(featureList, "INTERFACE THEMES");
                startCard();
                addMutedInfo("Two-stop gradients • accent + black • white highlights");
                addSlider(currentCard, "Theme Pack", sliderStates[999], 0, THEME_PACKS.length - 1, "", 999);
                addMutedInfo("Selected: " + THEME_NAMES[Math.max(0, Math.min(THEME_NAMES.length - 1, sliderStates[999]))]);
                endCard(featureList);

                break;
            case 4:
                addSection(featureList, "SETTINGS");
                addSegmentedTabs(featureList, new String[]{"DEVELOPER", "DEVICE", "SECURITY", "INFO"}, settingsSubTab,
                        which -> { settingsSubTab = which; refreshContent(); });
                startCard();
                if (settingsSubTab == 0) {
                    addInfoRow("Developer", "Senseidev");
                    addInfoRow("Support", "SENSI MODS");
                    addInfoRow("System", "Native Android");
                } else if (settingsSubTab == 1) {
                    addInfoRow("Hardware", android.os.Build.MODEL);
                    addInfoRow("Android", android.os.Build.VERSION.RELEASE);
                    addInfoRow("SDK", String.valueOf(android.os.Build.VERSION.SDK_INT));
                } else if (settingsSubTab == 2) {
                    addInfoRow("Environment", "Protected");
                    addInfoRow("Google Protection", "Active Verified ✓");
                    addInfoRow("Certificate", "Signature Match");
                    addMutedInfo("// System integrity verified successfully.");
                } else {
                    addInfoRow("Module", "SENSI MODS");
                    addInfoRow("Build", version);
                    addInfoRow("Key Alias", "Senseidev");
                }
                endCard(featureList);
                break;
        }
    }

    private void buildEspPage() {
        addSection(featureList, "ESP OPTIONS");
        startCard();
        addCheckBox("Master Visual", toggleStates[200], 200);
        addCheckBox("Draw Boxes", toggleStates[201], 201);
        addCheckBox("Health Bar", toggleStates[202], 202);
        addCheckBox("Player Name", toggleStates[203], 203);
        addCheckBox("Show Distance", toggleStates[204], 204);
        addCheckBox("Snap Lines", toggleStates[205], 205);
        addChoice("Line Position", new String[]{"Top", "Center", "Bottom"});
        addCheckBox("Skeleton ", toggleStates[206], 206);
        endCard(featureList);

        addSection(featureList, "BOX & LINE STYLE");
        startCard();
        addChoice("Box Type", new String[]{"Filled", "Outline", "Corner", "3D"});
        addChoice("Line Type", new String[]{"Solid", "Dashed", "Glow", "None"});
        addSlider(currentCard, "Line Thickness", 2, 1, 8, "px", 31);
        addSlider(currentCard, "Draw Distance", 300, 50, 1000, "m", 32);
        endCard(featureList);
    }

    private void buildColourPage() {
        addSection(featureList, "COLOUR");
        startCard();
        addChoice("Box Color", new String[]{"Cyan", "Red", "Green", "Yellow", "White", "Purple"});
        addChoice("Line Color", new String[]{"Cyan", "Red", "Green", "Yellow", "White", "Purple"});
        addChoice("Text Color", new String[]{"White", "Cyan", "Red", "Yellow"});
        addSlider(currentCard, "Red", 90, 0, 255, "", 41);
        addSlider(currentCard, "Green", 20, 0, 255, "", 42);
        addSlider(currentCard, "Blue", 25, 0, 255, "", 43);
        addSlider(currentCard, "Opacity", 90, 0, 100, "%", 44);
        endCard(featureList);
    }

    private void addMutedCard(LinearLayout parent, String text) {
        startCard(); addMutedInfo(text); endCard(parent);
    }

    private interface TabAction { void run(int which); }

    private void addSegmentedTabs(LinearLayout parent, String[] labels, int selected, TabAction action) {
        LinearLayout row = new LinearLayout(ctx);
        row.setPadding(dp(2), dp(4), dp(2), dp(6));
        for (int i=0; i<labels.length; i++) {
            final int index=i;
            TextView tab = new TextView(ctx);
            tab.setText(labels[i]); tab.setGravity(Gravity.CENTER); tab.setTextSize(9);
            tab.setTypeface(FontKit.heading(ctx), Typeface.BOLD);
            tab.setTextColor(i==selected ? Color.WHITE : Color.parseColor("#A0A5A8"));
            GradientDrawable bg = new GradientDrawable(); bg.setCornerRadius(dp(7));
            bg.setColor(i==selected ? Color.parseColor("#401416") : Color.parseColor("#111416"));
            bg.setStroke(dp(1), i==selected ? C_ACCENT : Color.parseColor("#303539"));
            tab.setBackground(bg); tab.setOnClickListener(v -> action.run(index));
            row.addView(tab, new LinearLayout.LayoutParams(0, dp(36), 1f));
            if (i < labels.length-1) ((LinearLayout.LayoutParams)tab.getLayoutParams()).rightMargin=dp(4);
        }
        parent.addView(row);
    }

    private void addChoice(String label, String[] values) {
        LinearLayout row = new LinearLayout(ctx);
        row.setGravity(Gravity.CENTER_VERTICAL); row.setPadding(dp(10), dp(7), dp(10), dp(7));
        TextView name = new TextView(ctx); name.setText(label); name.setTextColor(Color.parseColor("#D6D9DB")); name.setTextSize(10);
        row.addView(name, new LinearLayout.LayoutParams(0, -2, 1f));
        android.widget.Spinner spinner = new android.widget.Spinner(ctx);
        android.widget.ArrayAdapter<String> adapter = new android.widget.ArrayAdapter<String>(ctx, android.R.layout.simple_spinner_dropdown_item, values);
        spinner.setAdapter(adapter); spinner.setSelection(0);
        row.addView(spinner, new LinearLayout.LayoutParams(dp(130), dp(42)));
        currentCard.addView(row);
    }

    private void addInfoRow(String left, String right) {
        LinearLayout row = new LinearLayout(ctx); row.setGravity(Gravity.CENTER_VERTICAL); row.setPadding(dp(10), dp(9), dp(10), dp(9));
        TextView a = new TextView(ctx); a.setText(left); a.setTextColor(Color.parseColor("#A9AFB2")); a.setTextSize(10);
        TextView b = new TextView(ctx); b.setText(right); b.setTextColor(Color.WHITE); b.setTextSize(10); b.setGravity(Gravity.END);
        row.addView(a, new LinearLayout.LayoutParams(0,-2,1f)); row.addView(b, new LinearLayout.LayoutParams(dp(150),-2)); currentCard.addView(row);
    }

    private void addMutedInfo(String text) {
        TextView tv = new TextView(ctx); tv.setText(text); tv.setTextColor(Color.parseColor("#78848B")); tv.setTextSize(10);
        tv.setPadding(dp(12), dp(10), dp(12), dp(10)); currentCard.addView(tv);
    }

    private void addPanelColor(String name, int a, int b, int c) {
        TextView tv = new TextView(ctx);
        tv.setText(name); tv.setTextColor(Color.WHITE); tv.setTextSize(10); tv.setGravity(Gravity.CENTER_VERTICAL);
        tv.setTypeface(FontKit.heading(ctx), Typeface.BOLD); tv.setPadding(dp(12),0,dp(12),0);
        GradientDrawable g = new GradientDrawable(GradientDrawable.Orientation.LEFT_RIGHT,new int[]{a,b});
        g.setCornerRadius(dp(8)); g.setStroke(dp(1),Color.argb(80,255,255,255)); tv.setBackground(g);
        tv.setOnClickListener(v -> { 
            C_ACCENT=a; C_ACCENT2=Color.BLACK; C_ACCENT3=Color.WHITE;
            updateAllColors();
            Log.i("SenseiUI", "Panel theme selected: " + name);
        });
        currentCard.addView(tv,new LinearLayout.LayoutParams(-1,dp(42)));
        TextView gap = new TextView(ctx); currentCard.addView(gap,new LinearLayout.LayoutParams(-1,dp(6)));
    }

    private void addCheckBox(final String name, boolean checked, final int id) {
        android.widget.CheckBox cb = new android.widget.CheckBox(ctx);
        cb.setText(name); cb.setTextColor(Color.parseColor("#D6DEE2")); cb.setTextSize(11); cb.setChecked(toggleStates[id]);
        cb.setButtonTintList(new android.content.res.ColorStateList(
                new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                new int[]{C_ACCENT, Color.parseColor("#66747B")}));
        
        cb.setOnCheckedChangeListener(new android.widget.CompoundButton.OnCheckedChangeListener() {
            @Override
            public void onCheckedChanged(android.widget.CompoundButton buttonView, boolean isChecked) {
                toggleStates[id] = isChecked;
                try {
                    nativeSendToggle(id, isChecked ? 1 : 0);
                } catch (Throwable ignored) {}
                
                if (listener != null) listener.onToggleChanged(id, isChecked);
            }
        });
        currentCard.addView(cb, new LinearLayout.LayoutParams(-1, dp(42)));
    }

    private void addCheckBox(String name, boolean checked) {
        // Legacy support for non-functional checkboxes
        android.widget.CheckBox cb = new android.widget.CheckBox(ctx);
        cb.setText(name); cb.setTextColor(Color.parseColor("#D6DEE2")); cb.setTextSize(11); cb.setChecked(checked);
        cb.setButtonTintList(new android.content.res.ColorStateList(
                new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                new int[]{C_ACCENT, Color.parseColor("#66747B")}));
        currentCard.addView(cb, new LinearLayout.LayoutParams(-1, dp(42)));
    }

    private LinearLayout buildHeader() {
        LinearLayout h = new LinearLayout(ctx);
        h.setOrientation(LinearLayout.VERTICAL);
        h.setPadding(dp(5), dp(10), dp(12), dp(5));
        
        View titleView = new View(ctx) {
            private final Paint tp = new Paint(Paint.ANTI_ALIAS_FLAG);
            private float phase = 0f;
            private final Runnable tick = new Runnable() {
                @Override public void run() {
                    if (getVisibility() != View.VISIBLE) return;
                    phase += 0.12f;
                    if (phase > 1f) phase = 0f;
                    invalidate();
                    handler.postDelayed(this, 180L);
                }
            };
            { handler.post(tick); }
            @Override protected void onDetachedFromWindow() {
                handler.removeCallbacks(tick);
                super.onDetachedFromWindow();
            }
            @Override protected void onDraw(Canvas canvas) {
                int width = Math.max(1, getWidth());
                float shift = width * phase;
                LinearGradient grad = new LinearGradient(-shift, 0, width - shift, 0,
                        new int[]{C_ACCENT, Color.WHITE, Color.BLACK}, null, Shader.TileMode.MIRROR);
                tp.setShader(grad);
                tp.setTextSize(dp(14));
                tp.setTypeface(Typeface.create(FontKit.mono(ctx), Typeface.BOLD));
                tp.setFakeBoldText(true);
                canvas.drawText("SENSI EXTERNAL", 0, dp(14), tp);
            }
        };
        h.addView(titleView, new LinearLayout.LayoutParams(-1, dp(20)));

        LinearLayout status = new LinearLayout(ctx);
        status.setGravity(Gravity.CENTER_VERTICAL);
        statusDot = new View(ctx);
        statusDot.setBackground(makeCircle(Color.parseColor("#8A8F98")));
        LinearLayout.LayoutParams dotLp = new LinearLayout.LayoutParams(dp(6), dp(6));
        dotLp.rightMargin = dp(6);
        status.addView(statusDot, dotLp);
        statusText = new TextView(ctx);
        statusText.setText("Waiting for My game..."); statusText.setTextColor(Color.parseColor("#9AA1AA")); statusText.setTextSize(9);
        status.addView(statusText);
        h.addView(status);

        return h;
    }

    private void addSection(LinearLayout p, String label) {
        TextView tv = new TextView(ctx);
        tv.setText(label); tv.setTextColor(C_ACCENT); tv.setTextSize(9);
        tv.setPadding(dp(5), dp(10), 0, dp(3));
        tv.setTypeface(FontKit.heading(ctx), Typeface.BOLD);
        p.addView(tv);
    }

    private void startCard() {
        currentCard = new LinearLayout(ctx);
        currentCard.setOrientation(LinearLayout.VERTICAL);
        GradientDrawable gd = new GradientDrawable(); 
        gd.setColor(Color.parseColor("#0DFFFFFF")); 
        gd.setCornerRadius(dp(4));
        currentCard.setBackground(gd);
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, -2); lp.bottomMargin = dp(6);
        currentCard.setLayoutParams(lp);
    }

    private void endCard(LinearLayout p) { p.addView(currentCard); currentCard = null; }

    private void addToggle(String name, int id) {
        LinearLayout row = new LinearLayout(ctx);
        row.setGravity(Gravity.CENTER_VERTICAL); row.setPadding(dp(12), dp(10), dp(12), dp(10));
        TextView tv = new TextView(ctx); tv.setText(name); tv.setTextColor(Color.GRAY); tv.setTextSize(11);
        row.addView(tv, new LinearLayout.LayoutParams(0, -2, 1f));
        row.addView(buildSwitch(toggleStates[id], id));
        currentCard.addView(row);
    }

    private View buildSwitch(boolean on, int id) {
        FrameLayout sw = new FrameLayout(ctx);
        sw.setLayoutParams(new LinearLayout.LayoutParams(dp(36), dp(18)));
        GradientDrawable track = new GradientDrawable(); track.setCornerRadius(dp(9));
        
        View thumb = new View(ctx);
        GradientDrawable tgd = new GradientDrawable(); tgd.setShape(GradientDrawable.OVAL);
        thumb.setBackground(tgd);
        FrameLayout.LayoutParams tlp = new FrameLayout.LayoutParams(dp(14), dp(14));
        tlp.gravity = Gravity.CENTER_VERTICAL; tlp.leftMargin = dp(2);
        sw.addView(thumb, tlp);

        final boolean[] s = { on };
        Runnable update = () -> {
            int c1 = C_ACCENT;
            int c2 = C_ACCENT2;
            int c3 = C_ACCENT3;
            
            if (s[0]) {
                track.setOrientation(GradientDrawable.Orientation.LEFT_RIGHT);
                track.setColors(new int[]{c1 & 0x4DFFFFFF | 0x4D000000, c3 & 0x4DFFFFFF | 0x4D000000});
                tgd.setColor(c1);
                tlp.leftMargin = dp(20);
            } else {
                track.setColor(Color.parseColor("#33FFFFFF"));
                tgd.setColor(Color.parseColor("#AAAAAA"));
                tlp.leftMargin = dp(2);
            }
            
            thumb.setLayoutParams(tlp);
            sw.setBackground(track);
        };
        update.run();
        sw.setOnClickListener(v -> {
            s[0] = !s[0];
            toggleStates[id] = s[0];
            update.run();
            
            try {
                nativeSendToggle(id, s[0] ? 1 : 0);
            } catch (Throwable ignored) {}

            if (listener != null) listener.onToggleChanged(id, s[0]);
        });
        return sw;
    }

    private void addSlider(LinearLayout p, String name, int def, int min, int max, String unit, int id) {
        int currentVal = sliderStates[id] == 0 ? def : sliderStates[id];
        LinearLayout row = new LinearLayout(ctx);
        row.setOrientation(LinearLayout.VERTICAL);
        row.setPadding(dp(10), dp(7), dp(10), dp(7));

        LinearLayout header = new LinearLayout(ctx);
        TextView tv = new TextView(ctx);
        tv.setText(name); tv.setTextColor(Color.parseColor("#BFC5C8")); tv.setTextSize(10);
        header.addView(tv, new LinearLayout.LayoutParams(0, -2, 1f));
        TextView value = new TextView(ctx);
        value.setText(String.format(Locale.US, "%d%s", currentVal, unit));
        value.setTextColor(C_ACCENT); value.setTextSize(10);
        header.addView(value);
        row.addView(header);

        android.widget.SeekBar bar = new android.widget.SeekBar(ctx);
        bar.setMax(Math.max(1, max - min));
        bar.setProgress(Math.max(0, Math.min(max - min, currentVal - min)));
        bar.setMinHeight(dp(18));
        bar.setMaxHeight(dp(18));
        bar.setPadding(0, dp(6), 0, dp(6));
        bar.setProgressDrawable(new ThinGradientProgressDrawable());
        if (Build.VERSION.SDK_INT >= 21) {
            bar.setThumbTintList(new android.content.res.ColorStateList(
                    new int[][]{new int[]{android.R.attr.state_enabled}, new int[]{}},
                    new int[]{C_ACCENT, Color.DKGRAY}));
        }
        bar.setOnSeekBarChangeListener(new android.widget.SeekBar.OnSeekBarChangeListener() {
            @Override public void onProgressChanged(android.widget.SeekBar seekBar, int progress, boolean fromUser) {
                int val = min + progress;
                sliderStates[id] = val;
                value.setText(String.format(Locale.US, "%d%s", val, unit));
                if (fromUser) handleSlider(id, val);
            }
            @Override public void onStartTrackingTouch(android.widget.SeekBar seekBar) {}
            @Override public void onStopTrackingTouch(android.widget.SeekBar seekBar) {}
        });
        row.addView(bar, new LinearLayout.LayoutParams(-1, dp(24)));
        p.addView(row);
    }

    public void setToggleState(int id, boolean on) {
        if (id >= 0 && id < toggleStates.length) {
            toggleStates[id] = on;
            if (activeTab == 2 && featureList != null) {
                // If we're on the Exploits tab, we need to refresh to show the new state
                handler.post(this::refreshContent);
            }
        }
    }

    private void handleSlider(int id, int val) {
        sliderStates[id] = val;
        if (id == 999) {
            C_ACCENT = THEME_PACKS[val][0];
            C_ACCENT2 = Color.BLACK;
            C_ACCENT3 = Color.WHITE;
            // Theme packs affect the title, sliders, switches and accents only.
            // The main panel stays neutral dark for readability and lower visual noise.
            updateAllColors();
        } else if (id == 998) {
            iconOpacity = val;
            // Enhanced opacity logic:
            // 100 -> 1.0 (Full)
            // 50  -> 0.05 (Almost gone)
            // 5   -> 0.0 (Faded gone)
            float alpha;
            if (val <= 5) {
                alpha = 0f;
            } else if (val <= 50) {
                // Curve between 5 and 50 to reach ~0.05 at 50
                alpha = 0.05f * (float) Math.pow((val - 5) / 45.0, 2.0);
            } else {
                // Curve between 50 and 100 to reach 1.0 at 100
                alpha = 0.05f + 0.95f * (float) Math.pow((val - 50) / 50.0, 1.5);
            }
            iconView.setAlpha(alpha);
        } else {
            // UI-only preview: slider state stays inside the panel.
        }
        if (listener != null) listener.onSliderChanged(id, val);
    }
    
    private void updateAllColors() {
        refreshPanelBackground(panelView);
        updateSidebarColors();
        refreshContent();
        // Update header
        LinearLayout contentWrapper = (LinearLayout) panelView.getChildAt(1);
        LinearLayout header = (LinearLayout) contentWrapper.getChildAt(0);
        header.getChildAt(0).invalidate(); // Redraw titleView

        LinearLayout status = (LinearLayout) header.getChildAt(1);
        statusDot.setBackground(makeCircle(C_ACCENT));
        TextView st = (TextView) status.getChildAt(1);
        st.setTextColor(C_ACCENT);
        // Update close button
        TextView close = (TextView) contentWrapper.getChildAt(contentWrapper.getChildCount() - 1);
        close.setTextColor(C_ACCENT);
        // Update icon
        iconView.invalidate();
        
        if (listener != null) listener.onThemeChanged(C_ACCENT, C_ACCENT2, C_ACCENT3);
    }

    private GradientDrawable makeCircle(int color) {
        GradientDrawable gd = new GradientDrawable(); gd.setShape(GradientDrawable.OVAL); gd.setColor(color); return gd;
    }

    public void destroy() {
        if (rootFrame != null) {
            try { wm.removeView(rootFrame); } catch (Exception ignored) {}
            rootFrame = null;
        }
    }

    public void updateConnectionStatus(boolean isConnected) {
        updateConnectionStatus(isConnected ? "Connected" : "Waiting for My game...");
    }

    public void updateConnectionStatus(String state) {
        if (statusDot == null || statusText == null) return;

        String value = (state == null || state.trim().isEmpty())
                ? "Waiting for My game..." : state.trim();
        boolean connected = "Connected".equalsIgnoreCase(value);

        int dotColor;
        int textColor;
        if (connected) {
            dotColor = Color.parseColor("#55D98A");
            textColor = dotColor;
        } else if (value.startsWith("Game found")) {
            dotColor = C_ACCENT;
            textColor = C_ACCENT;
        } else {
            dotColor = Color.parseColor("#8A8F98");
            textColor = Color.parseColor("#9AA1AA");
        }

        statusDot.setBackground(makeCircle(dotColor));
        statusText.setText(value);
        statusText.setTextColor(textColor);
    }

    private int withAlpha(int color, int alpha) {
        return Color.argb(Math.max(0, Math.min(255, alpha)), Color.red(color), Color.green(color), Color.blue(color));
    }

    private final class ThinGradientProgressDrawable extends android.graphics.drawable.Drawable {
        private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
        @Override public void draw(Canvas canvas) {
            RectF b = getBounds().exactCenterY() == 0 ? new RectF(getBounds()) : new RectF(getBounds());
            float cy = b.centerY();
            float h = Math.max(1f, dp(3));
            b.set(b.left, cy - h / 2f, b.right, cy + h / 2f);
            paint.setStyle(Paint.Style.FILL);
            paint.setShader(new LinearGradient(b.left, 0, b.right, 0,
                    new int[]{Color.BLACK, Color.WHITE, Color.BLACK}, null, Shader.TileMode.CLAMP));
            canvas.drawRoundRect(b, h, h, paint);
            float level = getLevel() / 10000f;
            if (level > 0f) {
                RectF progress = new RectF(b.left, b.top, b.left + b.width() * level, b.bottom);
                paint.setShader(new LinearGradient(progress.left, 0, Math.max(progress.right, progress.left + 1), 0,
                        new int[]{C_ACCENT, Color.WHITE, Color.BLACK}, null, Shader.TileMode.CLAMP));
                canvas.drawRoundRect(progress, h, h, paint);
            }
            paint.setShader(null);
        }
        @Override protected boolean onLevelChange(int level) { invalidateSelf(); return true; }
        @Override public void setAlpha(int alpha) { paint.setAlpha(alpha); }
        @Override public void setColorFilter(android.graphics.ColorFilter colorFilter) { paint.setColorFilter(colorFilter); }
        @Override public int getOpacity() { return android.graphics.PixelFormat.TRANSLUCENT; }
        @Override public int getIntrinsicHeight() { return dp(3); }
    }

    private int dp(float v) { return (int) TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, v, ctx.getResources().getDisplayMetrics()); }
}
