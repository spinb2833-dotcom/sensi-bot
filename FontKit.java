package com.sensi.inject;

import android.content.Context;
import android.graphics.Typeface;

/** Bundled SENSI UI fonts. */
public final class FontKit {
    private static Typeface karla, roboto, cousine, droid;
    private FontKit() {}
    public static Typeface body(Context c) {
        if (karla == null) karla = Typeface.createFromAsset(c.getAssets(), "fonts/Karla-Regular.ttf");
        return karla;
    }
    public static Typeface heading(Context c) {
        if (roboto == null) roboto = Typeface.createFromAsset(c.getAssets(), "fonts/Roboto-Medium.ttf");
        return roboto;
    }
    public static Typeface mono(Context c) {
        if (cousine == null) cousine = Typeface.createFromAsset(c.getAssets(), "fonts/Cousine-Regular.ttf");
        return cousine;
    }
    public static Typeface clean(Context c) {
        if (droid == null) droid = Typeface.createFromAsset(c.getAssets(), "fonts/DroidSans.ttf");
        return droid;
    }
}
