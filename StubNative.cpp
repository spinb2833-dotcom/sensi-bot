/* SENSI External native bridge. Kept offset-free and protocol-driven. */
#include <jni.h>
#include <android/log.h>

#define LOG_TAG "SenseiEngine"
#define LOGI(...) __android_log_print(ANDROID_LOG_INFO, LOG_TAG, __VA_ARGS__)

extern "C" {
JNIEXPORT void JNICALL
Java_com_sensi_inject_OverlayService_nativeInit(JNIEnv*, jclass, jint w, jint h) {
    LOGI("nativeInit %dx%d", w, h);
}

JNIEXPORT jboolean JNICALL
Java_com_sensi_inject_OverlayService_nativeConnect(JNIEnv*, jclass) {
    LOGI("nativeConnect: protocol mode");
    return JNI_TRUE;
}

JNIEXPORT void JNICALL
Java_com_sensi_inject_OverlayService_nativeDisconnect(JNIEnv*, jclass) {
    LOGI("nativeDisconnect");
}

JNIEXPORT void JNICALL
Java_com_sensi_inject_RenderSurface_nativeDraw(JNIEnv*, jobject, jobject, jint w, jint h) {
    static bool announced = false;
    if (!announced) {
        LOGI("RenderSurface nativeDraw active %dx%d", w, h);
        announced = true;
    }
}

JNIEXPORT void JNICALL
Java_com_sensi_inject_MotionWidget_nativeSendToggle(JNIEnv*, jclass, jint id, jint value) {
    LOGI("Widget toggle id=%d value=%d", id, value);
}
}
