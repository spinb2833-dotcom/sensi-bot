package com.sensi.inject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/** Server-only HTTP client. No local success fallback. */
public final class NetworkClient {
    private static final int CONNECT_TIMEOUT_MS = 8000;
    private static final int READ_TIMEOUT_MS = 8000;
    private static final String BASE_URL =
            "https://cancelled-massachusetts-approximate-lottery.trycloudflare.com";

    private NetworkClient() {}

    public static String getApi(String path) throws Exception {
        String p = path == null ? "" : path.trim();
        if (!p.startsWith("/")) p = "/" + p;
        return get(BASE_URL + p);
    }

    public static String get(String url) throws Exception {
        HttpURLConnection c = null;
        try {
            c = open(url, "GET");
            return readResponse(c);
        } finally {
            if (c != null) c.disconnect();
        }
    }

    public static String post(String url, String jsonBody) throws Exception {
        HttpURLConnection c = null;
        try {
            c = open(url, "POST");
            c.setDoOutput(true);
            c.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            c.setRequestProperty("Accept", "application/json");
            byte[] body = (jsonBody == null ? "" : jsonBody)
                    .getBytes(StandardCharsets.UTF_8);
            try (OutputStream os = c.getOutputStream()) {
                os.write(body);
            }
            return readResponse(c);
        } finally {
            if (c != null) c.disconnect();
        }
    }

    public static String downloadFile(String url, String destPath) throws Exception {
        throw new UnsupportedOperationException("File downloads are not part of the license client");
    }

    private static HttpURLConnection open(String url, String method) throws Exception {
        if (url == null || !url.startsWith("https://")) {
            throw new SecurityException("HTTPS required");
        }
        HttpURLConnection c = (HttpURLConnection) new URL(url).openConnection();
        c.setRequestMethod(method);
        c.setConnectTimeout(CONNECT_TIMEOUT_MS);
        c.setReadTimeout(READ_TIMEOUT_MS);
        c.setUseCaches(false);
        c.setRequestProperty("Cache-Control", "no-cache");
        return c;
    }

    private static String readResponse(HttpURLConnection c) throws Exception {
        int code = c.getResponseCode();
        InputStream in = code >= 400 ? c.getErrorStream() : c.getInputStream();
        String body = read(in);
        if (body.isEmpty()) throw new Exception("HTTP " + code);
        return body;
    }

    private static String read(InputStream in) throws Exception {
        if (in == null) return "";
        StringBuilder out = new StringBuilder();
        try (BufferedReader br = new BufferedReader(
                new InputStreamReader(in, StandardCharsets.UTF_8))) {
            String line;
            while ((line = br.readLine()) != null) out.append(line);
        }
        return out.toString().trim();
    }
}
