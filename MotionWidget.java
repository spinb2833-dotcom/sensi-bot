package com.sensi.inject;

import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.PixelFormat;
import android.graphics.Shader;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.graphics.Typeface;
import java.util.Locale;
import android.widget.FrameLayout;

/** Lightweight static floater. No particle loop, rotation loop, glow loop, or 60 FPS invalidation. */
public class MotionWidget {
    static { System.loadLibrary("sensei"); }
    private static native void nativeSendToggle(int mode, int value);

    public interface OnFloaterToggleListener {
        void onFloaterToggled(int id, boolean on);
    }

    private final Context ctx;
    private final WindowManager wm;
    private final OnFloaterToggleListener listener;
    private WindowManager.LayoutParams params;
    private FrameLayout rootFrame;
    private View speedButton;
    private boolean isActive = false;
    private int speedMultiplier = 1;
    private final Handler timerHandler = new Handler(Looper.getMainLooper());
    private long activeSinceMs = 0L;
    private long elapsedSeconds = 0L;
    private final Runnable timerTick = new Runnable() {
        @Override public void run() {
            if (!isActive) return;
            elapsedSeconds = Math.max(0L, (System.currentTimeMillis() - activeSinceMs) / 1000L);
            if (speedButton != null) speedButton.invalidate();
            timerHandler.postDelayed(this, 1000L);
        }
    };

    private int themeC1 = Color.parseColor("#E51B23");
    private int themeC2 = Color.WHITE;
    private int themeC3 = Color.BLACK;

    public MotionWidget(Context context, WindowManager windowManager, OnFloaterToggleListener listener) {
        this.ctx = context;
        this.wm = windowManager;
        this.listener = listener;
        init();
    }

    public void updateTheme(int c1, int c2, int c3) {
        themeC1 = c1;
        themeC2 = Color.WHITE;
        themeC3 = Color.BLACK;
        if (speedButton != null) speedButton.invalidate();
    }

    public void setSpeedMultiplier(int multiplier) {
        speedMultiplier = Math.max(1, multiplier);
        if (speedButton != null) speedButton.invalidate();
    }

    private void init() {
        int layoutFlag = Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
                ? WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
                : WindowManager.LayoutParams.TYPE_PHONE;

        params = new WindowManager.LayoutParams(
                dp(70), dp(70), layoutFlag,
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE,
                PixelFormat.TRANSLUCENT);
        params.gravity = Gravity.CENTER_VERTICAL | Gravity.END;
        params.x = 0;
        params.y = 0;

        rootFrame = new FrameLayout(ctx);
        speedButton = new View(ctx) {
            private final Paint p = new Paint(Paint.ANTI_ALIAS_FLAG);
            private final Paint gear = new Paint(Paint.ANTI_ALIAS_FLAG);
            private final Path path = new Path();

            @Override protected void onDraw(Canvas c) {
                float w = getWidth(), h = getHeight();
                float cx = w / 2f, cy = h / 2f;
                float r = Math.min(w, h) * 0.34f;

                p.setStyle(Paint.Style.FILL);
                p.setColor(Color.argb(235, 7, 8, 10));
                c.drawCircle(cx, cy, r + dp(8), p);

                p.setStyle(Paint.Style.STROKE);
                p.setStrokeWidth(dp(1.5f));
                p.setColor(isActive ? themeC1 : Color.DKGRAY);
                c.drawCircle(cx, cy, r + dp(8), p);

                LinearGradient grad = new LinearGradient(
                        0, 0, w, h,
                        new int[]{themeC1, Color.WHITE, Color.BLACK},
                        null, Shader.TileMode.CLAMP);
                gear.setShader(grad);
                gear.setStyle(Paint.Style.FILL);

                float outer = r + dp(2);
                float inner = r - dp(6);
                path.reset();
                for (int i = 0; i < 8; i++) {
                    double a = i * Math.PI / 4.0;
                    double b = (i + 0.5) * Math.PI / 4.0;
                    path.moveTo((float)(cx + inner * Math.cos(a)), (float)(cy + inner * Math.sin(a)));
                    path.lineTo((float)(cx + outer * Math.cos(a)), (float)(cy + outer * Math.sin(a)));
                    path.lineTo((float)(cx + outer * Math.cos(b)), (float)(cy + outer * Math.sin(b)));
                    path.lineTo((float)(cx + inner * Math.cos(b)), (float)(cy + inner * Math.sin(b)));
                }
                path.close();
                c.drawPath(path, gear);
                c.drawCircle(cx, cy, inner, gear);

                // Minimal clock face: no bitmap/icon, no particle/glow loop.
                p.setStyle(Paint.Style.STROKE);
                p.setStrokeWidth(dp(2));
                p.setColor(Color.WHITE);
                c.drawCircle(cx, cy, inner, p);
                p.setStrokeWidth(dp(2.2f));
                p.setColor(themeC1);
                float handScale = inner * 0.62f;
                double minuteAngle = ((elapsedSeconds % 60L) / 60.0) * Math.PI * 2.0 - Math.PI / 2.0;
                double hourAngle = (((elapsedSeconds / 60L) % 60L) / 60.0) * Math.PI * 2.0 - Math.PI / 2.0;
                c.drawLine(cx, cy, cx + (float)Math.cos(hourAngle) * handScale * 0.55f,
                        cy + (float)Math.sin(hourAngle) * handScale * 0.55f, p);
                c.drawLine(cx, cy, cx + (float)Math.cos(minuteAngle) * handScale,
                        cy + (float)Math.sin(minuteAngle) * handScale, p);
                p.setStyle(Paint.Style.FILL);
                p.setColor(Color.WHITE);
                c.drawCircle(cx, cy, dp(2), p);

                String timer = String.format(Locale.US, "%02d:%02d", elapsedSeconds / 60L, elapsedSeconds % 60L);
                p.setTextSize(dp(8));
                p.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
                p.setTextAlign(Paint.Align.CENTER);
                p.setColor(Color.WHITE);
                c.drawText(timer, cx, cy + inner + dp(13), p);

                if (isActive) {
                    p.setStyle(Paint.Style.STROKE);
                    p.setStrokeWidth(dp(1.5f));
                    p.setColor(themeC1);
                    c.drawCircle(cx, cy, r + dp(11), p);
                }
            }
        };

        speedButton.setOnTouchListener(new View.OnTouchListener() {
            private int initialX, initialY;
            private float initialTouchX, initialTouchY;

            @Override public boolean onTouch(View v, MotionEvent event) {
                switch (event.getAction()) {
                    case MotionEvent.ACTION_DOWN:
                        initialX = params.x;
                        initialY = params.y;
                        initialTouchX = event.getRawX();
                        initialTouchY = event.getRawY();
                        return true;
                    case MotionEvent.ACTION_UP:
                        float dx = event.getRawX() - initialTouchX;
                        float dy = event.getRawY() - initialTouchY;
                        if (Math.abs(dx) < 10 && Math.abs(dy) < 10) {
                            isActive = !isActive;
                            if (isActive) {
                                activeSinceMs = System.currentTimeMillis();
                                elapsedSeconds = 0L;
                                timerHandler.removeCallbacks(timerTick);
                                timerHandler.post(timerTick);
                            } else {
                                timerHandler.removeCallbacks(timerTick);
                            }
                            try { nativeSendToggle(16, isActive ? speedMultiplier : 0); } catch (Throwable ignored) {}
                            if (listener != null) listener.onFloaterToggled(16, isActive);
                            speedButton.invalidate();
                        }
                        return true;
                    case MotionEvent.ACTION_MOVE:
                        params.x = initialX - (int)(event.getRawX() - initialTouchX);
                        params.y = initialY + (int)(event.getRawY() - initialTouchY);
                        try { wm.updateViewLayout(rootFrame, params); } catch (Exception ignored) {}
                        return true;
                }
                return false;
            }
        });

        rootFrame.addView(speedButton, new FrameLayout.LayoutParams(dp(70), dp(70)));
    }

    public void show() {
        try {
            if (rootFrame.getParent() == null) wm.addView(rootFrame, params);
        } catch (Exception ignored) {}
    }

    public void hide() {
        try {
            if (rootFrame.getParent() != null) wm.removeView(rootFrame);
            timerHandler.removeCallbacks(timerTick);
            if (isActive) {
                isActive = false;
                nativeSendToggle(16, 0);
                if (listener != null) listener.onFloaterToggled(16, false);
            }
        } catch (Exception ignored) {}
    }

    public void setTheme(int c1, int c2, int c3) { updateTheme(c1, c2, c3); }

    private int dp(float v) {
        return (int) TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, v,
                ctx.getResources().getDisplayMetrics());
    }
}
