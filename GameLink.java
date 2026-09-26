package com.sensi.inject;

import android.util.Log;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.io.PrintWriter;
import java.net.InetSocketAddress;
import java.net.Socket;
import org.json.JSONObject;
import com.topjohnwu.superuser.Shell;

/** External link for the developer-controlled game. */
public final class GameLink {
    private static final String TAG = "SenseiEngine";
    private static final String TARGET_PACKAGE = "mobi.byss.gun3";
    public static final int DEFAULT_PORT = 27091;

    private static final long PROCESS_POLL_MS = 700L;
    private static final long TELEMETRY_RETRY_MS = 3000L;

    private final GameTelemetry telemetry;
    private volatile boolean running;
    private volatile boolean connected;
    private volatile boolean telemetryConnected;
    private volatile boolean gameProcessRunning;
    private volatile int gamePid = -1;
    private volatile String status = "Waiting for My game...";

    private long nextTelemetryAttemptMs;
    private int lastLoggedPid = -1;
    private boolean lastLoggedTelemetryState = false;

    private Socket socket;
    private PrintWriter writer;
    private Thread thread;

    public GameLink(GameTelemetry telemetry) { this.telemetry = telemetry; }

    public synchronized void start() {
        if (running) return;
        running = true;
        status = "Waiting for My game...";
        nextTelemetryAttemptMs = 0L;
        lastLoggedPid = -1;
        lastLoggedTelemetryState = false;
        thread = new Thread(this::loop, "SENSI-GameLink");
        thread.setPriority(Thread.NORM_PRIORITY - 1);
        thread.start();
        DiagnosticLogger.log("GameLink started package=" + TARGET_PACKAGE + " port=" + DEFAULT_PORT);
        Log.i(TAG, "GameLink started package=" + TARGET_PACKAGE + " port=" + DEFAULT_PORT);
    }

    private void loop() {
        while (running) {
            final int pid = findGamePid();
            gamePid = pid;
            boolean processNow = pid > 0;
            boolean processChanged = processNow != gameProcessRunning || pid != lastLoggedPid;
            gameProcessRunning = processNow;

            if (!processNow) {
                if (connected || telemetryConnected || lastLoggedPid > 0) {
                    Log.i(TAG, "Target game process disappeared");
                    DiagnosticLogger.log("Game process stopped");
                }
                connected = false;
                telemetryConnected = false;
                status = "Waiting for My game...";
                lastLoggedPid = -1;
                closeSocket();
                sleep(PROCESS_POLL_MS);
                continue;
            }

            // Process detection is the stable UI connection state. The socket is
            // only a telemetry channel and must never make the panel flicker.
            connected = true;
            status = "Connected";

            if (processChanged && pid != lastLoggedPid) {
                lastLoggedPid = pid;
                Log.i(TAG, "Connected PID=" + pid);
                DiagnosticLogger.log("Connected PID=" + pid);
            }

            long now = System.currentTimeMillis();
            if (!telemetryConnected && now >= nextTelemetryAttemptMs) {
                nextTelemetryAttemptMs = now + TELEMETRY_RETRY_MS;
                connectTelemetryOnce(pid);
            }

            sleep(PROCESS_POLL_MS);
        }

        closeSocket();
        connected = false;
        telemetryConnected = false;
        gameProcessRunning = false;
        status = "Waiting for My game...";
    }

    private void connectTelemetryOnce(int pid) {
        Socket s = new Socket();
        try {
            s.connect(new InetSocketAddress("127.0.0.1", DEFAULT_PORT), 350);
            s.setSoTimeout(0);
            socket = s;
            writer = new PrintWriter(new OutputStreamWriter(s.getOutputStream()), true);
            telemetryConnected = true;
            if (!lastLoggedTelemetryState) {
                lastLoggedTelemetryState = true;
                Log.i(TAG, "Telemetry channel connected PID=" + pid);
                DiagnosticLogger.log("Telemetry channel connected PID=" + pid);
            }
            writer.println("{\"type\":\"hello\",\"client\":\"SENSI_EXTERNAL\",\"version\":3}");

            BufferedReader br = new BufferedReader(new InputStreamReader(s.getInputStream()));
            String line;
            while (running && gameProcessRunning && (line = br.readLine()) != null) {
                if (line.trim().isEmpty()) continue;
                try {
                    telemetry.update(new JSONObject(line));
                } catch (Throwable parseError) {
                    Log.e(TAG, "Bad telemetry frame", parseError);
                    DiagnosticLogger.log("Telemetry parse error: " + parseError.getClass().getSimpleName());
                }
            }
        } catch (Throwable e) {
            telemetryConnected = false;
            if (lastLoggedTelemetryState) {
                lastLoggedTelemetryState = false;
                DiagnosticLogger.log("Telemetry channel unavailable; process remains connected PID=" + pid);
            }
        } finally {
            telemetryConnected = false;
            closeSocket();
            nextTelemetryAttemptMs = System.currentTimeMillis() + TELEMETRY_RETRY_MS;
        }
    }

    private int findGamePid() {
        // Do not invoke su/root-manager APIs in the hot polling loop. Package PID
        // detection does not require root and this avoids repeated UID/root prompts.
        return runPidCommand();
    }

    private int runPidCommand() {
        Process process = null;
        try {
            process = new ProcessBuilder("sh", "-c", "pidof " + TARGET_PACKAGE)
                    .redirectErrorStream(true).start();
            BufferedReader br = new BufferedReader(new InputStreamReader(process.getInputStream()));
            int pid = parsePid(br.readLine());
            if (pid > 0) return pid;
        } catch (Throwable ignored) {
            // Fall through to /proc fallback.
        } finally {
            if (process != null) process.destroy();
        }

        return findPidFromProc();
    }

    private int findPidFromProc() {
        java.io.File proc = new java.io.File("/proc");
        java.io.File[] entries = proc.listFiles();
        if (entries == null) return -1;

        for (java.io.File entry : entries) {
            String name = entry.getName();
            if (!name.matches("\\d+")) continue;

            java.io.File cmdline = new java.io.File(entry, "cmdline");
            try (java.io.FileInputStream in = new java.io.FileInputStream(cmdline)) {
                byte[] buf = new byte[256];
                int n = in.read(buf);
                if (n <= 0) continue;
                int end = 0;
                while (end < n && buf[end] != 0) end++;
                String command = new String(buf, 0, end, java.nio.charset.StandardCharsets.UTF_8);
                if (TARGET_PACKAGE.equals(command)) {
                    return Integer.parseInt(name);
                }
            } catch (Throwable ignored) {
                // Process may disappear while we scan /proc.
            }
        }
        return -1;
    }

    private int parsePid(String line) {
        if (line == null) return -1;
        String[] parts = line.trim().split("\\s+");
        for (String part : parts) {
            try {
                int value = Integer.parseInt(part.trim());
                if (value > 0) return value;
            } catch (NumberFormatException ignored) {}
        }
        return -1;
    }

    private void sleep(long ms) {
        try { Thread.sleep(ms); }
        catch (InterruptedException e) { Thread.currentThread().interrupt(); }
    }

    private synchronized void closeSocket() {
        try { if (socket != null) socket.close(); } catch (Throwable ignored) {}
        socket = null;
        writer = null;
    }

    public boolean isConnected() { return connected; }
    public boolean isTelemetryConnected() { return telemetryConnected; }
    public boolean isGameProcessRunning() { return gameProcessRunning; }
    public int getGamePid() { return gamePid; }
    public String getStatus() { return status; }

    public synchronized void requestAim(int entityId, float x, float y, float z) {
        PrintWriter w = writer;
        if (!connected || !telemetryConnected || w == null) return;
        try {
            JSONObject o = new JSONObject();
            o.put("type", "aim");
            o.put("entityId", entityId);
            JSONObject p = new JSONObject();
            p.put("x", x); p.put("y", y); p.put("z", z);
            o.put("point", p);
            w.println(o.toString());
        } catch (Throwable e) {
            Log.d(TAG, "Aim request failed", e);
        }
    }

    public synchronized void sendFeature(String feature, boolean enabled) {
        PrintWriter w = writer;
        if (!connected || !telemetryConnected || w == null) return;
        try {
            JSONObject o = new JSONObject();
            o.put("type", "feature");
            o.put("name", feature);
            o.put("enabled", enabled);
            w.println(o.toString());
            Log.i(TAG, "Feature " + feature + "=" + enabled);
        } catch (Throwable e) {
            Log.d(TAG, "Feature request failed", e);
        }
    }

    public synchronized void stop() {
        running = false;
        connected = false;
        telemetryConnected = false;
        gameProcessRunning = false;
        status = "Waiting for My game...";
        closeSocket();
        if (thread != null) thread.interrupt();
        thread = null;
        DiagnosticLogger.log("GameLink stopped");
        Log.i(TAG, "GameLink stopped");
    }
}
