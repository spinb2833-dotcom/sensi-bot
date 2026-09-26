/*
 * ============================================================
 *  Root Executor - Sensi Mods
 *  Made by Senseidev
 * ============================================================
 */
package com.sensi.inject;

import com.topjohnwu.superuser.Shell;
import java.io.File;

public class PrivilegedRunner {

    private static final String BINARY_NAME = "Sensei";
    private static final String DEST_PATH = "/data/local/tmp/" + BINARY_NAME;
    private static final String LOG_PATH = "/data/local/tmp/sensei.log";

    public static String lastError = "";

    public static boolean inject(String sourcePath, String sessionToken) {
        try {
            // Kill existing process
            Shell.cmd("killall " + BINARY_NAME + " 2>/dev/null").exec();
            Thread.sleep(300);

            // Copy binary to temp
            Shell.Result cp = Shell.cmd("cp " + sourcePath + " " + DEST_PATH).exec();
            if (!cp.isSuccess()) {
                lastError = "cp failed: " + String.join(" ", cp.getErr());
                return false;
            }

            // Make executable
            Shell.cmd("chmod 755 " + DEST_PATH).exec();

            // Clear log
            Shell.cmd("echo '' > " + LOG_PATH).exec();

            // Execute with token
            Shell.cmd(DEST_PATH + " --token=" + sessionToken + " > " + LOG_PATH + " 2>&1 &").exec();
            Thread.sleep(500);

            // Check if running
            boolean running = isRunning();
            if (!running) {
                String logs = getLastLogs();
                lastError = logs.isEmpty() ? "process exited immediately" : logs;
                return false;
            }

            // Clean up
            Shell.cmd("rm -f " + DEST_PATH).exec();
            lastError = "";
            return true;

        } catch (Exception e) {
            lastError = e.getClass().getSimpleName() + ": " + e.getMessage();
            return false;
        }
    }

    public static boolean isRunning() {
        Shell.Result result = Shell.cmd("pidof " + BINARY_NAME).exec();
        return result.isSuccess()
                && !result.getOut().isEmpty()
                && !result.getOut().get(0).trim().isEmpty();
    }

    public static String getLastLogs() {
        Shell.Result result = Shell.cmd("cat " + LOG_PATH + " 2>/dev/null").exec();
        StringBuilder sb = new StringBuilder();
        for (String line : result.getOut()) sb.append(line).append("\n");
        return sb.toString().trim();
    }
}