package com.sensi.inject;

import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.util.Log;

import java.io.File;
import java.io.FileOutputStream;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** Low-frequency diagnostic exporter. Never writes once per render frame. */
public final class DiagnosticLogger {
    private static final String TAG = "SenseiDiagnostics";
    private static final String DIR = "SENSI_EXTERNAL";
    private static final String LOG_NAME = "sensi_external.log";
    private static final String SCRIPT_NAME = "sensi_external_diagnostic.sh";

    private static final ExecutorService IO = Executors.newSingleThreadExecutor(r -> {
        Thread t = new Thread(r, "SENSI-DiagnosticIO");
        t.setDaemon(true);
        return t;
    });

    private static volatile Context context;
    private static volatile Uri logUri;
    private static volatile boolean initialized;

    private DiagnosticLogger() {}

    public static void init(Context ctx) {
        if (ctx == null) return;
        context = ctx.getApplicationContext();
        if (initialized) return;
        initialized = true;
        IO.execute(() -> {
            try {
                ensureLogTarget();
                exportScriptInternal();
                log("===== SENSI EXTERNAL START =====");
                log("Android=" + Build.VERSION.RELEASE + " SDK=" + Build.VERSION.SDK_INT);
                log("Model=" + Build.MODEL);
            } catch (Throwable t) {
                Log.e(TAG, "Diagnostic initialization failed", t);
            }
        });
    }

    public static void log(String message) {
        Context ctx = context;
        if (ctx == null || message == null) return;
        final String line = timestamp() + " " + message + "\n";
        IO.execute(() -> append(line));
    }

    public static void exportScript(Context ctx) {
        if (ctx != null) init(ctx);
        IO.execute(DiagnosticLogger::exportScriptInternal);
    }

    private static String timestamp() {
        return new SimpleDateFormat("yyyy-MM-dd HH:mm:ss.SSS", Locale.US)
                .format(new Date());
    }

    private static synchronized void ensureLogTarget() {
        if (logUri != null) return;
        Context ctx = context;
        if (ctx == null) return;

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ContentResolver cr = ctx.getContentResolver();
                String relative = Environment.DIRECTORY_DOWNLOADS + "/" + DIR;
                Uri collection = MediaStore.Downloads.EXTERNAL_CONTENT_URI;
                String selection = MediaStore.MediaColumns.DISPLAY_NAME + "=? AND "
                        + MediaStore.MediaColumns.RELATIVE_PATH + "=?";
                String[] args = {LOG_NAME, relative + "/"};

                try (Cursor c = cr.query(collection,
                        new String[]{MediaStore.MediaColumns._ID}, selection, args, null)) {
                    if (c != null && c.moveToFirst()) {
                        long id = c.getLong(0);
                        logUri = Uri.withAppendedPath(collection, String.valueOf(id));
                        return;
                    }
                }

                ContentValues values = new ContentValues();
                values.put(MediaStore.MediaColumns.DISPLAY_NAME, LOG_NAME);
                values.put(MediaStore.MediaColumns.MIME_TYPE, "text/plain");
                values.put(MediaStore.MediaColumns.RELATIVE_PATH, relative);
                values.put(MediaStore.MediaColumns.IS_PENDING, 0);
                logUri = cr.insert(collection, values);
            } else {
                File dir = new File(Environment.getExternalStoragePublicDirectory(
                        Environment.DIRECTORY_DOWNLOADS), DIR);
                if (!dir.exists()) dir.mkdirs();
                // Legacy path is opened on demand in append().
            }
        } catch (Throwable t) {
            Log.e(TAG, "Unable to prepare download log", t);
        }
    }

    private static void append(String line) {
        try {
            Context ctx = context;
            if (ctx == null) return;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ensureLogTarget();
                if (logUri == null) return;
                try (OutputStream out = ctx.getContentResolver().openOutputStream(logUri, "wa")) {
                    if (out != null) out.write(line.getBytes(StandardCharsets.UTF_8));
                }
            } else {
                File dir = new File(Environment.getExternalStoragePublicDirectory(
                        Environment.DIRECTORY_DOWNLOADS), DIR);
                if (!dir.exists()) dir.mkdirs();
                try (FileOutputStream out = new FileOutputStream(new File(dir, LOG_NAME), true)) {
                    out.write(line.getBytes(StandardCharsets.UTF_8));
                }
            }
        } catch (Throwable t) {
            Log.e(TAG, "Diagnostic write failed", t);
        }
    }

    private static void exportScriptInternal() {
        Context ctx = context;
        if (ctx == null) return;

        final String script = "#!/system/bin/sh\n"
                + "# SENSI External diagnostic helper\n"
                + "BASE=\"/sdcard/Download/SENSI_EXTERNAL\"\n"
                + "LOG=\"$BASE/sensi_external.log\"\n"
                + "echo '=== SENSI EXTERNAL DIAGNOSTIC ==='\n"
                + "mkdir -p \"$BASE\" 2>/dev/null\n"
                + "echo 'Directory:' $BASE\n"
                + "echo \"Log: $LOG\"\n"
                + "echo\n"
                + "echo '-- Latest connection/state lines --'\n"
                + "grep -E 'Connected|Waiting|PID=|telemetry|TEST MODE|FRAME|ENTITY' \"$LOG\" | tail -80 2>/dev/null\n"
                + "echo\n"
                + "echo '-- Latest coordinates/entities --'\n"
                + "grep 'ENTITY ' \"$LOG\" | tail -20 2>/dev/null\n"
                + "echo\n"
                + "echo '-- Latest camera frames --'\n"
                + "grep 'FRAME #' \"$LOG\" | tail -10 2>/dev/null\n";

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ContentResolver cr = ctx.getContentResolver();
                Uri collection = MediaStore.Downloads.EXTERNAL_CONTENT_URI;
                String relative = Environment.DIRECTORY_DOWNLOADS + "/" + DIR;
                String selection = MediaStore.MediaColumns.DISPLAY_NAME + "=? AND "
                        + MediaStore.MediaColumns.RELATIVE_PATH + "=?";
                String[] args = {SCRIPT_NAME, relative + "/"};
                Uri uri = null;
                try (Cursor c = cr.query(collection,
                        new String[]{MediaStore.MediaColumns._ID}, selection, args, null)) {
                    if (c != null && c.moveToFirst()) {
                        uri = Uri.withAppendedPath(collection, String.valueOf(c.getLong(0)));
                    }
                }
                if (uri == null) {
                    ContentValues values = new ContentValues();
                    values.put(MediaStore.MediaColumns.DISPLAY_NAME, SCRIPT_NAME);
                    values.put(MediaStore.MediaColumns.MIME_TYPE, "text/x-shellscript");
                    values.put(MediaStore.MediaColumns.RELATIVE_PATH, relative);
                    values.put(MediaStore.MediaColumns.IS_PENDING, 0);
                    uri = cr.insert(collection, values);
                }
                if (uri != null) {
                    try (OutputStream out = cr.openOutputStream(uri, "w")) {
                        if (out != null) out.write(script.getBytes(StandardCharsets.UTF_8));
                    }
                }
                // Also attempt a direct Downloads copy. On devices with MANAGE_EXTERNAL_STORAGE
                // this makes the script visible at the exact path used by the helper.
                try {
                    File directDir = new File("/sdcard/Download/" + DIR);
                    if (!directDir.exists()) directDir.mkdirs();
                    try (FileOutputStream out = new FileOutputStream(new File(directDir, SCRIPT_NAME))) {
                        out.write(script.getBytes(StandardCharsets.UTF_8));
                    }
                } catch (Throwable ignored) {
                    // MediaStore copy above remains the supported Android 10+ path.
                }
            } else {
                File dir = new File(Environment.getExternalStoragePublicDirectory(
                        Environment.DIRECTORY_DOWNLOADS), DIR);
                if (!dir.exists()) dir.mkdirs();
                try (FileOutputStream out = new FileOutputStream(new File(dir, SCRIPT_NAME))) {
                    out.write(script.getBytes(StandardCharsets.UTF_8));
                }
            }
        } catch (Throwable t) {
            Log.e(TAG, "Diagnostic script export failed", t);
        }
    }
}
