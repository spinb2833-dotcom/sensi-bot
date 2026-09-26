package com.sensi.inject;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.DisplayMetrics;
import android.view.Gravity;
import android.view.View;
import android.view.WindowManager;
import android.util.Log;

import com.topjohnwu.superuser.Shell;

import java.util.List;

public class OverlayService extends Service implements ControlPanel.OnMenuUpdateListener, MotionWidget.OnFloaterToggleListener {

    static { System.loadLibrary("sensei"); }

    public static native void nativeInit(int w, int h);
    public static native boolean nativeConnect();
    public static native void nativeDisconnect();

    public static volatile boolean connected = true;

    private WindowManager windowManager;
    private RenderSurface espView;
    private GameTelemetry telemetry;
    private GameLink gameLink;
    private ControlPanel floatingMenu;
    private MotionWidget speedFloater;
    private boolean masterActive = false;
    private boolean floaterToggleActive = false;
    private static int screenWidth, screenHeight;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private final Runnable runtimeMonitor = new Runnable() {
        @Override public void run() {
            try {
                if (!RuntimeCheck.check(OverlayService.this)) {
                    stopSelf();
                    return;
                }
            } catch (Throwable ignored) {}
            handler.postDelayed(this, 10000);
        }
    };
    private final Runnable connectionChecker = new Runnable() {
        @Override public void run() {
            String state = gameLink != null ? gameLink.getStatus() : "Waiting for My game...";
            if (floatingMenu != null) floatingMenu.updateConnectionStatus(state);
            handler.postDelayed(this, 250);
        }
    };

    private String version = "";
    private String seller = "";

    @Override
    public void onCreate() {
        super.onCreate();
        try {
            RuntimeCheck.enforce(this);
        } catch (Throwable securityFailure) {
            stopSelf();
            android.os.Process.killProcess(android.os.Process.myPid());
            return;
        }
        connected = true;
        DiagnosticLogger.init(this);
        DiagnosticLogger.log("OverlayService created");
        createNotification();

        try {
            windowManager = (WindowManager) getSystemService(WINDOW_SERVICE);
            
            DisplayMetrics dm = getResources().getDisplayMetrics();
            screenWidth = Math.max(dm.widthPixels, dm.heightPixels);
            screenHeight = Math.min(dm.widthPixels, dm.heightPixels);

            telemetry = new GameTelemetry();
            gameLink = new GameLink(telemetry);
            gameLink.start();
            Log.i("SenseiEngine", "Overlay service started. Waiting for My game...");
            DiagnosticLogger.log("Overlay service started");
            createESPOverlay();
            handler.post(connectionChecker);

            // Menu shows slightly after service starts to ensure it's on top of the game
            handler.postDelayed(() -> {
                try {
                    if (floatingMenu == null) {
                        floatingMenu = new ControlPanel(this, windowManager, version, seller, this);
                    }
                    if (speedFloater == null) {
                        speedFloater = new MotionWidget(this, windowManager, this);
                    }
                    if (floatingMenu != null && gameLink != null) {
                        floatingMenu.updateConnectionStatus(gameLink.getStatus());
                    }
                } catch (Exception ignored) {}
            }, 800);

        } catch (Exception ignored) {}
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null) {
            version = intent.getStringExtra("version");
            seller = intent.getStringExtra("seller");
            if (version == null) version = "";
            if (seller == null) seller = "";
        }
        return START_NOT_STICKY;
    }

    private void createESPOverlay() {
        try {
            int layoutFlag;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                layoutFlag = WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY;
            } else {
                layoutFlag = WindowManager.LayoutParams.TYPE_PHONE;
            }

            WindowManager.LayoutParams params = new WindowManager.LayoutParams(
                    screenWidth,
                    screenHeight,
                    layoutFlag,
                    WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                            | WindowManager.LayoutParams.FLAG_NOT_TOUCHABLE
                            | WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN
                            | WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
                    PixelFormat.TRANSLUCENT);
            params.gravity = Gravity.TOP | Gravity.START;
            params.x = 0;
            params.y = 0;

            espView = new RenderSurface(this, telemetry, gameLink);
            windowManager.addView(espView, params);
        } catch (Exception ignored) {}
    }

    private void createNotification() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                String channelId = "sensei_mod_channel";
                NotificationChannel channel = new NotificationChannel(
                        channelId, "@Senseidev", NotificationManager.IMPORTANCE_LOW);
                NotificationManager nm = getSystemService(NotificationManager.class);
                if (nm != null) {
                    nm.createNotificationChannel(channel);
                }

                Notification notification = new Notification.Builder(this, channelId)
                        .setContentTitle("SENSI MODS")
                        .setContentText("ACTIVE ✓")
                        .setSmallIcon(android.R.drawable.ic_menu_info_details)
                        .setOngoing(true)
                        .build();

                if (Build.VERSION.SDK_INT >= 34) {
                    startForeground(1, notification, android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC);
                } else {
                    startForeground(1, notification);
                }
            } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                // For Android 5.0 and above but below Oreo
                Notification notification = new Notification.Builder(this)
                        .setContentTitle("SENSI MODS")
                        .setContentText("ACTIVE ✓")
                        .setSmallIcon(android.R.drawable.ic_menu_info_details)
                        .setOngoing(true)
                        .build();
                startForeground(1, notification);
            }
        } catch (Exception ignored) {}
    }

    @Override
    public void onTaskRemoved(Intent rootIntent) {
        if (floatingMenu != null) {
            floatingMenu.destroy();
        }
        if (speedFloater != null) {
            speedFloater.hide();
        }
        stopForeground(true);
        stopSelf();
        DiagnosticLogger.log("Task removed; stopping overlay cleanly");
        super.onTaskRemoved(rootIntent);
    }

    @Override
    public void onDestroy() {
        DiagnosticLogger.log("OverlayService destroying");
        try {
            handler.removeCallbacks(runtimeMonitor);
            handler.removeCallbacks(connectionChecker);
            if (gameLink != null) { gameLink.stop(); gameLink = null; }
            if (espView != null) espView.shutdown();
            nativeDisconnect();
            if (espView != null) {
                windowManager.removeView(espView);
                espView = null;
            }
            if (floatingMenu != null) {
                floatingMenu.destroy();
                floatingMenu = null;
            }
            if (speedFloater != null) {
                speedFloater.hide();
                speedFloater = null;
            }
        } catch (Exception ignored) {}
        super.onDestroy();
    }

    @Override
    public void onFloaterToggled(int id, boolean on) {
        if (floatingMenu != null) {
            floatingMenu.setToggleState(id, on);
        }
    }

    @Override
    public void onToggleChanged(int id, boolean on) {
        if (espView != null) espView.setFeature(id, on);
        if (gameLink != null) {
            if (id == 101) gameLink.sendFeature("aimbot", on);
            else if (id == 200) gameLink.sendFeature("esp", on);
            else if (id == 201) gameLink.sendFeature("boxes", on);
            else if (id == 202) gameLink.sendFeature("health", on);
            else if (id == 203) gameLink.sendFeature("names", on);
            else if (id == 204) gameLink.sendFeature("distance", on);
            else if (id == 205) gameLink.sendFeature("lines", on);
            else if (id == 206) gameLink.sendFeature("skeleton", on);
        }
        if (id == 100) { // Master Toggle
            masterActive = on;
            if (espView != null) {
                espView.setVisibility(on ? View.VISIBLE : View.GONE);
            }
            updateFloaterVisibility();
        } else if (id == 28) { // Speed Floater Toggle
            floaterToggleActive = on;
            updateFloaterVisibility();
        }
    }

    @Override
    public void onSliderChanged(int id, int value) {
        if (espView != null) {
            if (id == 14) espView.setAimConfig(value, 500);
            if (id == 15) espView.setAimConfig(120, value);
            if (id == 32) espView.setAimConfig(120, value);
        }
        if (id == 27) { // Speed Multiplier
            if (speedFloater != null) {
                speedFloater.setSpeedMultiplier(value);
            }
        }
    }

    @Override
    public void onThemeChanged(int c1, int c2, int c3) {
        if (speedFloater != null) {
            speedFloater.updateTheme(c1, c2, c3);
        }
    }

    private void updateFloaterVisibility() {
        if (speedFloater == null) return;
        if (masterActive && floaterToggleActive) {
            speedFloater.show();
        } else {
            speedFloater.hide();
        }
    }

    @Override
    public IBinder onBind(Intent intent) { return null; }

    public static int getScreenWidth() { return screenWidth; }
    public static int getScreenHeight() { return screenHeight; }
}