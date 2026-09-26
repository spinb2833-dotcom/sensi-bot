package com.sensi.inject;

import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.view.View;

import java.util.List;
import java.util.Locale;

/** Lightweight renderer driven only by the game's telemetry feed. */
public class RenderSurface extends View {
    private static final long FRAME_INTERVAL_MS = 40L; // 25 FPS: enough for ESP, lighter on the game
    private static final int MAX_DRAW_ENTITIES = 64;

    private final Paint p = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint text = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final GameTelemetry telemetry;
    private volatile GameLink link;
    private volatile boolean running = true;

    private volatile boolean esp = false, boxes = false, health = false, names = false,
            distance = false, lines = false, skeleton = false;
    private volatile boolean aimbot = false;
    private volatile float aimFovPx = 120f, aimRange = 500f;

    public RenderSurface(Context context, GameTelemetry telemetry, GameLink link) {
        super(context);
        this.telemetry = telemetry;
        this.link = link;
        setBackgroundColor(Color.TRANSPARENT);
        setWillNotDraw(false);
        text.setTextSize(22f);
    }

    public void setGameLink(GameLink l) { link = l; }

    public void setFeature(int id, boolean on) {
        switch (id) {
            case 101: aimbot = on; break;
            case 200: esp = on; break;
            case 201: boxes = on; break;
            case 202: health = on; break;
            case 203: names = on; break;
            case 204: distance = on; break;
            case 205: lines = on; break;
            case 206: skeleton = on; break;
            default: break;
        }
        invalidate();
    }

    public void setAimConfig(int fov, int range) {
        aimFovPx = Math.max(10, fov);
        aimRange = Math.max(1, range);
    }

    @Override
    protected void onDraw(Canvas c) {
        super.onDraw(c);
        if (!running || (!esp && !aimbot)) return;

        // Live ESP only: never manufacture entities. If the game is not
        // publishing telemetry, there is nothing to draw.
        List<TelemetryEntity> list = telemetry.snapshot();
        if (list.isEmpty()) {
            if (running) postInvalidateDelayed(FRAME_INTERVAL_MS);
            return;
        }
        final float cx = getWidth() * 0.5f;
        final float cy = getHeight() * 0.5f;
        TelemetryEntity best = null;
        float bestScore = Float.MAX_VALUE;

        int drawn = 0;
        for (TelemetryEntity e : list) {
            if (drawn >= MAX_DRAW_ENTITIES) break;
            if (e.team == telemetry.localTeam) continue;

            Point head;
            Point feet;
            if (e.hasScreen) {
                head = screenPoint(e.screenX, e.screenY, e.screenNormalized, e.screenYDown);
                feet = e.hasFeet ? project(e.footX, e.footY, e.footZ) : null;
            } else {
                head = project(e.headX, e.headY, e.headZ);
                feet = e.hasFeet
                        ? project(e.footX, e.footY, e.footZ)
                        : project(e.x, e.y, e.z);
            }

            if (head == null) continue;

            float screenDist = (float) Math.hypot(head.x - cx, head.y - cy);
            float worldDist = dist(telemetry.camX, telemetry.camY, telemetry.camZ,
                    e.x, e.y, e.z);

            if (aimbot && e.visible && worldDist <= aimRange
                    && screenDist <= aimFovPx && screenDist < bestScore) {
                best = e;
                bestScore = screenDist;
            }

            if (!esp) continue;

            float left, top, right, bottom;
            if (e.hasBox) {
                left = scaleX(e.boxLeft, e.boxNormalized);
                top = scaleY(e.boxTop, e.boxNormalized, e.boxYDown);
                right = scaleX(e.boxRight, e.boxNormalized);
                bottom = scaleY(e.boxBottom, e.boxNormalized, e.boxYDown);
                if (bottom < top) {
                    float t = top; top = bottom; bottom = t;
                }
            } else if (feet != null) {
                top = Math.min(head.y, feet.y);
                bottom = Math.max(head.y, feet.y);
                float h = Math.max(18f, bottom - top);
                float w = Math.max(10f, h * 0.42f);
                left = head.x - w * 0.5f;
                right = head.x + w * 0.5f;
            } else {
                // Head-only telemetry: use a compact body box centered below the head.
                float h = Math.max(34f, getHeight() * 0.06f);
                float w = h * 0.42f;
                left = head.x - w * 0.5f;
                right = head.x + w * 0.5f;
                top = head.y;
                bottom = head.y + h;
            }

            if (right < 0 || left > getWidth() || bottom < 0 || top > getHeight()) continue;
            drawn++;

            p.setStyle(Paint.Style.STROKE);
            p.setStrokeWidth(2f);
            p.setColor(Color.CYAN);

            if (boxes) c.drawRect(left, top, right, bottom, p);

            // Snap to the body/feet, not the head, so the line visibly terminates
            // on the entity rather than floating above it.
            if (lines) c.drawLine(cx, getHeight(), (left + right) * 0.5f, bottom, p);

            if (names) {
                text.setColor(Color.WHITE);
                text.setTextSize(20f);
                c.drawText(e.name == null ? "Entity" : e.name,
                        left, Math.max(20f, top - 6f), text);
            }

            if (distance) {
                text.setColor(Color.LTGRAY);
                text.setTextSize(17f);
                c.drawText(String.format(Locale.US, "%.1fm", worldDist),
                        left, Math.min(getHeight() - 4f, bottom + 18f), text);
            }

            if (health) {
                float ratio = Math.max(0f, Math.min(1f, e.health / 100f));
                p.setStyle(Paint.Style.FILL);
                p.setColor(Color.argb(210, 25, 25, 25));
                c.drawRect(left - 7f, top, left - 3f, bottom, p);
                p.setColor(Color.rgb(50, 220, 100));
                c.drawRect(left - 7f, bottom - (bottom - top) * ratio, left - 3f, bottom, p);
            }

            if (skeleton) drawSkeleton(c, e);
        }

        if (aimbot && best != null && link != null) {
            link.requestAim(best.id, best.headX, best.headY, best.headZ);
            p.setStyle(Paint.Style.STROKE);
            p.setStrokeWidth(2f);
            p.setColor(Color.RED);
            c.drawCircle(cx, cy, aimFovPx, p);
        }

        if (running) postInvalidateDelayed(FRAME_INTERVAL_MS);
    }

    private Point screenPoint(float x, float y, boolean normalized, boolean yDown) {
        float sx = scaleX(x, normalized);
        float sy = scaleY(y, normalized, yDown);
        return new Point(sx, sy);
    }

    private float scaleX(float x, boolean normalized) {
        if (normalized) return x * getWidth();
        if (telemetry.sourceScreenWidth > 1f) return x * getWidth() / telemetry.sourceScreenWidth;
        return x;
    }

    private float scaleY(float y, boolean normalized, boolean yDown) {
        if (normalized) return yDown ? y * getHeight() : (1f - y) * getHeight();
        if (telemetry.sourceScreenHeight > 1f) {
            float scaled = y * getHeight() / telemetry.sourceScreenHeight;
            return yDown ? scaled : getHeight() - scaled;
        }
        return yDown ? y : getHeight() - y;
    }

    private void drawSkeleton(Canvas c, TelemetryEntity e) {
        Point h = e.hasScreen
                ? screenPoint(e.screenX, e.screenY, e.screenNormalized, e.screenYDown)
                : project(e.headX, e.headY, e.headZ);
        Point f = e.hasFeet
                ? project(e.footX, e.footY, e.footZ)
                : project(e.x, e.y, e.z);
        if (h == null || f == null) return;
        p.setStyle(Paint.Style.STROKE);
        p.setStrokeWidth(1.5f);
        p.setColor(Color.YELLOW);
        c.drawLine(h.x, h.y, f.x, f.y, p);
    }

    private Point project(float x, float y, float z) {
        if (telemetry.hasViewProjection) {
            float[] m = telemetry.viewProjection;
            float clipX, clipY, clipW;
            if (telemetry.viewProjectionColumnMajor) {
                clipX = m[0] * x + m[4] * y + m[8] * z + m[12];
                clipY = m[1] * x + m[5] * y + m[9] * z + m[13];
                clipW = m[3] * x + m[7] * y + m[11] * z + m[15];
            } else {
                clipX = m[0] * x + m[1] * y + m[2] * z + m[3];
                clipY = m[4] * x + m[5] * y + m[6] * z + m[7];
                clipW = m[12] * x + m[13] * y + m[14] * z + m[15];
            }
            if (Math.abs(clipW) < 0.001f || clipW <= 0.001f) return null;
            float ndcX = clipX / clipW;
            float ndcY = clipY / clipW;
            if (ndcX < -1.2f || ndcX > 1.2f || ndcY < -1.2f || ndcY > 1.2f) return null;
            float sx = (ndcX * 0.5f + 0.5f) * getWidth();
            float sy = (1f - (ndcY * 0.5f + 0.5f)) * getHeight();
            return new Point(sx, sy);
        }

        final double dx = x - telemetry.camX;
        final double dy = y - telemetry.camY;
        final double dz = z - telemetry.camZ;

        double fx, fy, fz, rx, ry, rz, ux, uy, uz;
        if (telemetry.hasBasis) {
            fx = telemetry.forwardX; fy = telemetry.forwardY; fz = telemetry.forwardZ;
            rx = telemetry.rightX; ry = telemetry.rightY; rz = telemetry.rightZ;
            ux = telemetry.upX; uy = telemetry.upY; uz = telemetry.upZ;
        } else {
            double yaw = Math.toRadians(telemetry.yaw);
            double pitch = Math.toRadians(telemetry.pitch);
            double cp = Math.cos(pitch), sp = Math.sin(pitch);
            double sy = Math.sin(yaw), cy = Math.cos(yaw);
            fx = sy * cp; fy = sp; fz = cy * cp;
            rx = cy; ry = 0.0; rz = -sy;
            ux = fy * rz - fz * ry;
            uy = fz * rx - fx * rz;
            uz = fx * ry - fy * rx;
        }

        double depth = dx * fx + dy * fy + dz * fz;
        if (depth <= 0.05) return null;
        double side = dx * rx + dy * ry + dz * rz;
        double up = dx * ux + dy * uy + dz * uz;

        double fov = Math.max(10.0, Math.min(170.0, telemetry.fov));
        double tan = Math.tan(Math.toRadians(fov) * 0.5);
        double aspect = (double) getWidth() / Math.max(1, getHeight());

        float sx = (float) (getWidth() * 0.5 + side / (tan * aspect * depth) * getWidth() * 0.5);
        float sy = (float) (getHeight() * 0.5 - up / (tan * depth) * getHeight() * 0.5);
        return new Point(sx, sy);
    }

    private static float dist(float a, float b, float c, float x, float y, float z) {
        float dx = x - a, dy = y - b, dz = z - c;
        return (float) Math.sqrt(dx * dx + dy * dy + dz * dz);
    }

    private static final class Point {
        final float x, y;
        Point(float x, float y) { this.x = x; this.y = y; }
    }

    public void shutdown() {
        running = false;
        removeCallbacksAndMessages(null);
    }
}
