package com.sensi.inject;

public final class TelemetryEntity {
    public int id;
    public String name = "Entity";
    public int team = 0;
    public float x, y, z;
    public float health = 100f;
    public boolean visible = true;

    public float headX, headY, headZ;
    public float footX, footY, footZ;
    public boolean hasFeet = false;

    public boolean hasScreen = false;
    public boolean screenNormalized = false;
    public boolean screenYDown = true;
    public float screenX, screenY;

    public boolean hasBox = false;
    public boolean boxNormalized = false;
    public boolean boxYDown = true;
    public float boxLeft, boxTop, boxRight, boxBottom;
}
