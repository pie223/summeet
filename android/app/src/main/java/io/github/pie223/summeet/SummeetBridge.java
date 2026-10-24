package io.github.pie223.summeet;

import android.content.ContentValues;
import android.content.Context;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.util.Base64;
import android.webkit.JavascriptInterface;
import android.widget.Toast;
import java.io.OutputStream;

public class SummeetBridge {
    private final Context ctx;

    public SummeetBridge(Context ctx) {
        this.ctx = ctx;
    }

    @JavascriptInterface
    public void savePng(String base64, String name) {
        boolean ok = false;
        try {
            ContentValues values = new ContentValues();
            values.put(MediaStore.Downloads.DISPLAY_NAME, name);
            values.put(MediaStore.Downloads.MIME_TYPE, "image/png");
            android.net.Uri uri = ctx.getContentResolver().insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values);
            OutputStream out = ctx.getContentResolver().openOutputStream(uri);
            out.write(Base64.decode(base64, Base64.DEFAULT));
            out.close();
            ok = true;
        } catch (Exception e) {
            ok = false;
        }
        final String msg = ok ? "Saved in Downloads" : "Could not save";
        new Handler(Looper.getMainLooper()).post(new Runnable() {
            public void run() { Toast.makeText(ctx, msg, Toast.LENGTH_SHORT).show(); }
        });
    }
}
