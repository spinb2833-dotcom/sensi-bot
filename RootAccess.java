package com.sensi.inject;

import com.topjohnwu.superuser.Shell;

/** One-shot root state cache. Prevents repeated su/root-manager requests. */
public final class RootAccess {
    public interface Callback { void onResult(boolean granted); }
    private static volatile Boolean cached;
    private static volatile boolean checking;

    private RootAccess() {}

    public static void request(Callback callback) {
        Boolean known = cached;
        if (known != null) {
            if (callback != null) callback.onResult(known);
            return;
        }

        synchronized (RootAccess.class) {
            if (checking) return;
            checking = true;
        }

        new Thread(() -> {
            boolean granted = false;
            try {
                Shell.Result result = Shell.cmd("id -u").exec();
                if (result.isSuccess() && !result.getOut().isEmpty()) {
                    granted = "0".equals(result.getOut().get(0).trim());
                }
            } catch (Throwable ignored) {
            }

            cached = granted;
            synchronized (RootAccess.class) { checking = false; }
            final boolean result = granted;
            if (callback != null) callback.onResult(result);
        }, "sensi-root-check").start();
    }

    public static boolean isCachedRoot() {
        return Boolean.TRUE.equals(cached);
    }

    public static void clearCache() {
        cached = null;
    }
}
