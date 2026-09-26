package com.sensi.inject;

import android.util.Log;
import org.json.JSONArray;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

/** Lightweight game-owned telemetry cache. No continuous animation or memory scanning. */
public final class GameTelemetry {
    private static final String TAG = "SenseiTelemetry";

    public float camX, camY, camZ;
    public float yaw, pitch, fov = 60f;
    public boolean hasBasis = false;
    public float forwardX = 0f, forwardY = 0f, forwardZ = 1f;
    public float rightX = 1f, rightY = 0f, rightZ = 0f;
    public float upX = 0f, upY = 1f, upZ = 0f;

    public boolean hasViewProjection = false;
    public final float[] viewProjection = new float[16];
    public boolean viewProjectionColumnMajor = true;

    /** true = Android/top-left screen coordinates; false = bottom-left coordinates. */
    public boolean screenYDown = true;
    public float sourceScreenWidth = 0f;
    public float sourceScreenHeight = 0f;

    public int localTeam = 1;
    private final List<TelemetryEntity> entities = new ArrayList<>();
    private long lastFrameMs;
    private long frameCount;
    private long lastDebugLogMs;

    public synchronized void update(JSONObject frame) {
        try {
            JSONObject cam = frame.optJSONObject("camera");
            if (cam != null) {
                camX = (float) cam.optDouble("x", camX);
                camY = (float) cam.optDouble("y", camY);
                camZ = (float) cam.optDouble("z", camZ);
                yaw = (float) cam.optDouble("yaw", yaw);
                pitch = (float) cam.optDouble("pitch", pitch);
                fov = (float) cam.optDouble("fov", fov);

                JSONObject forward = cam.optJSONObject("forward");
                JSONObject right = cam.optJSONObject("right");
                JSONObject up = cam.optJSONObject("up");
                if (forward != null && right != null && up != null) {
                    forwardX = (float) forward.optDouble("x", forwardX);
                    forwardY = (float) forward.optDouble("y", forwardY);
                    forwardZ = (float) forward.optDouble("z", forwardZ);
                    rightX = (float) right.optDouble("x", rightX);
                    rightY = (float) right.optDouble("y", rightY);
                    rightZ = (float) right.optDouble("z", rightZ);
                    upX = (float) up.optDouble("x", upX);
                    upY = (float) up.optDouble("y", upY);
                    upZ = (float) up.optDouble("z", upZ);
                    hasBasis = true;
                }

                JSONArray vp = cam.optJSONArray("viewProjection");
                if (vp == null) vp = cam.optJSONArray("viewProjectionMatrix");
                if (vp != null && vp.length() >= 16) {
                    for (int i = 0; i < 16; i++) {
                        viewProjection[i] = (float) vp.optDouble(i, i % 5 == 0 ? 1.0 : 0.0);
                    }
                    hasViewProjection = true;
                    viewProjectionColumnMajor = !"row-major".equalsIgnoreCase(
                            cam.optString("matrixOrder", "column-major"));
                }
            }

            JSONObject screenSpace = frame.optJSONObject("screenSpace");
            if (screenSpace != null) {
                screenYDown = screenSpace.optBoolean("yDown", screenYDown);
                sourceScreenWidth = (float) screenSpace.optDouble("width", sourceScreenWidth);
                sourceScreenHeight = (float) screenSpace.optDouble("height", sourceScreenHeight);
            }

            localTeam = frame.optInt("localTeam", localTeam);
            lastFrameMs = System.currentTimeMillis();
            frameCount++;

            JSONArray arr = frame.optJSONArray("entities");
            ArrayList<TelemetryEntity> next = new ArrayList<>();
            if (arr != null) {
                for (int i = 0; i < arr.length(); i++) {
                    JSONObject o = arr.optJSONObject(i);
                    if (o == null) continue;

                    TelemetryEntity e = new TelemetryEntity();
                    e.id = o.optInt("id", i);
                    e.name = o.optString("name", "Entity");
                    e.team = o.optInt("team", 0);
                    e.x = (float) o.optDouble("x", 0);
                    e.y = (float) o.optDouble("y", 0);
                    e.z = (float) o.optDouble("z", 0);
                    e.health = (float) o.optDouble("health", 100);
                    e.visible = o.optBoolean("visible", true);

                    JSONObject screen = o.optJSONObject("screen");
                    if (screen != null) {
                        e.hasScreen = true;
                        e.screenX = (float) screen.optDouble("x", 0);
                        e.screenY = (float) screen.optDouble("y", 0);
                        e.screenNormalized = screen.optBoolean("normalized", false);
                        e.screenYDown = screen.optBoolean("yDown", screenYDown);
                    }

                    JSONObject box = o.optJSONObject("box");
                    if (box != null) {
                        e.hasBox = true;
                        e.boxLeft = (float) box.optDouble("left", 0);
                        e.boxTop = (float) box.optDouble("top", 0);
                        e.boxRight = (float) box.optDouble("right", 0);
                        e.boxBottom = (float) box.optDouble("bottom", 0);
                        e.boxNormalized = box.optBoolean("normalized", false);
                        e.boxYDown = box.optBoolean("yDown", screenYDown);
                    }

                    JSONObject h = o.optJSONObject("head");
                    if (h != null) {
                        e.headX = (float) h.optDouble("x", e.x);
                        e.headY = (float) h.optDouble("y", e.y + 1.7f);
                        e.headZ = (float) h.optDouble("z", e.z);
                    } else {
                        e.headX = e.x;
                        e.headY = e.y + 1.7f;
                        e.headZ = e.z;
                    }

                    JSONObject feet = o.optJSONObject("feet");
                    if (feet != null) {
                        e.footX = (float) feet.optDouble("x", e.x);
                        e.footY = (float) feet.optDouble("y", e.y);
                        e.footZ = (float) feet.optDouble("z", e.z);
                        e.hasFeet = true;
                    } else {
                        e.footX = e.x;
                        e.footY = e.y;
                        e.footZ = e.z;
                    }

                    next.add(e);
                }
            }

            entities.clear();
            entities.addAll(next);
            debugLog(next);
        } catch (Throwable t) {
            Log.e(TAG, "Telemetry parse failed", t);
        }
    }

    private synchronized void debugLog(List<TelemetryEntity> current) {
        long now = System.currentTimeMillis();
        if (now - lastDebugLogMs < 1000L) return;
        lastDebugLogMs = now;

        String camera = String.format(java.util.Locale.US,
                "FRAME #%d camera=(%.3f, %.3f, %.3f) yaw=%.2f pitch=%.2f fov=%.2f team=%d entities=%d vp=%s",
                frameCount, camX, camY, camZ, yaw, pitch, fov, localTeam,
                current.size(), hasViewProjection ? "yes" : "no");
        Log.i(TAG, camera);
        DiagnosticLogger.log(camera);

        int limit = Math.min(current.size(), 8);
        for (int i = 0; i < limit; i++) {
            TelemetryEntity e = current.get(i);
            String line = String.format(java.util.Locale.US,
                    "ENTITY id=%d name=%s team=%d pos=(%.3f, %.3f, %.3f) head=(%.3f, %.3f, %.3f) foot=(%.3f, %.3f, %.3f) health=%.1f visible=%s screen=%s box=%s",
                    e.id, e.name, e.team, e.x, e.y, e.z,
                    e.headX, e.headY, e.headZ,
                    e.footX, e.footY, e.footZ,
                    e.health, String.valueOf(e.visible),
                    e.hasScreen ? "yes" : "no", e.hasBox ? "yes" : "no");
            Log.i(TAG, line);
            DiagnosticLogger.log(line);
        }
    }

    public synchronized long getLastFrameAgeMs() {
        if (lastFrameMs == 0L) return Long.MAX_VALUE;
        return Math.max(0L, System.currentTimeMillis() - lastFrameMs);
    }

    public synchronized long getFrameCount() {
        return frameCount;
    }

    /**
     * Live mode deliberately does not manufacture entities.
     * A missing telemetry feed means there is no live ESP data.
     */
    public synchronized void enableDemoIfStale() {
        // Intentionally empty. Use an explicit developer test harness if needed.
    }

    public synchronized List<TelemetryEntity> snapshot() {
        return Collections.unmodifiableList(new ArrayList<>(entities));
    }
}
