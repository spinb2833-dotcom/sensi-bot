package com.sensi.inject;

import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Handler;
import android.os.Looper;
import android.widget.Toast;

import rikka.shizuku.Shizuku;

/**
 * Owns the Shizuku connection/permission state for the login screen.
 * It deliberately separates "Shizuku installed", "binder alive", and
 * "SENSI authorized" so a running service is never mistaken for a grant.
 */
public final class ShizukuAccess {
    public static final int REQUEST_CODE = 7101;

    public interface Callback {
        void onReady();
        void onDenied();
        void onNotRunning();
    }

    private ShizukuAccess() {}

    public static void register(Activity activity) {
        try {
            Shizuku.addBinderReceivedListenerSticky(() -> {});
            Shizuku.addBinderDeadListener(() -> {});
        } catch (Throwable ignored) {}
    }

    public static boolean isRunning() {
        try {
            return !Shizuku.isPreV11() && Shizuku.pingBinder();
        } catch (Throwable ignored) {
            return false;
        }
    }

    public static boolean isGranted() {
        try {
            return isRunning() &&
                    Shizuku.checkSelfPermission() == PackageManager.PERMISSION_GRANTED;
        } catch (Throwable ignored) {
            return false;
        }
    }

    public static void request(Activity activity, Callback callback) {
        final Handler main = new Handler(Looper.getMainLooper());
        final long deadline = System.currentTimeMillis() + 2500L;

        final Shizuku.OnRequestPermissionResultListener permissionListener =
                new Shizuku.OnRequestPermissionResultListener() {
                    @Override public void onRequestPermissionResult(int requestCode, int result) {
                        if (requestCode != REQUEST_CODE) return;
                        try { Shizuku.removeRequestPermissionResultListener(this); } catch (Throwable ignored) {}
                        if (result == PackageManager.PERMISSION_GRANTED) callback.onReady();
                        else callback.onDenied();
                    }
                };

        final Shizuku.OnBinderReceivedListener binderListener = new Shizuku.OnBinderReceivedListener() {
            @Override public void onBinderReceived() {
                try { Shizuku.removeBinderReceivedListener(this); } catch (Throwable ignored) {}
                evaluate(activity, callback, permissionListener);
            }
        };

        try {
            if (isGranted()) {
                callback.onReady();
                return;
            }

            if (isRunning()) {
                evaluate(activity, callback, permissionListener);
                return;
            }

            // The provider can deliver the binder shortly after Activity startup.
            Shizuku.addBinderReceivedListener(binderListener);
            main.postDelayed(() -> {
                if (System.currentTimeMillis() >= deadline && !isRunning()) {
                    try { Shizuku.removeBinderReceivedListener(binderListener); } catch (Throwable ignored) {}
                    openShizuku(activity);
                    callback.onNotRunning();
                }
            }, 2600L);
        } catch (Throwable ignored) {
            try { Shizuku.removeBinderReceivedListener(binderListener); } catch (Throwable ignored2) {}
            openShizuku(activity);
            callback.onNotRunning();
        }
    }

    private static void evaluate(Activity activity, Callback callback,
                                 Shizuku.OnRequestPermissionResultListener permissionListener) {
        try {
            if (!isRunning()) {
                callback.onNotRunning();
                return;
            }
            if (Shizuku.checkSelfPermission() == PackageManager.PERMISSION_GRANTED) {
                callback.onReady();
                return;
            }

            Shizuku.addRequestPermissionResultListener(permissionListener);
            Shizuku.requestPermission(REQUEST_CODE);
        } catch (Throwable ignored) {
            callback.onDenied();
        }
    }

    public static void openShizuku(Activity activity) {
        try {
            Intent i = activity.getPackageManager().getLaunchIntentForPackage("moe.shizuku.privileged.api");
            if (i == null) i = activity.getPackageManager().getLaunchIntentForPackage("dev.rikka.shizuku");
            if (i != null) activity.startActivity(i);
            else Toast.makeText(activity, "Install Shizuku first, then start its service.", Toast.LENGTH_LONG).show();
        } catch (Throwable ignored) {
            Toast.makeText(activity, "Open Shizuku manually and start its service.", Toast.LENGTH_LONG).show();
        }
    }
}
