package io.github.pie223.summeet;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;
import android.webkit.CookieManager;
import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import org.json.JSONObject;

/** Runs in the background: asks the site for the numbers behind the navbar badges and notifies when they grow. */
public class NotifyWorker extends Worker {
    private static final String SITE = "https://summeet.altervista.org";
    private static final String CHANNEL = "summeet_messages";

    public NotifyWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
    }

    @NonNull
    @Override
    public Result doWork() {
        Context ctx = getApplicationContext();
        SharedPreferences prefs = ctx.getSharedPreferences("summeet_notify", Context.MODE_PRIVATE);
        try {
            String cookie = CookieManager.getInstance().getCookie(SITE);
            if (cookie == null || cookie.isEmpty()) {
                return Result.success();                       // not logged in
            }
            HttpURLConnection conn = (HttpURLConnection) new URL(SITE + "/counts.php").openConnection();
            conn.setConnectTimeout(10000);
            conn.setReadTimeout(10000);
            conn.setRequestProperty("Cookie", cookie);
            int code = conn.getResponseCode();
            if (code == 401) {
                prefs.edit().clear().apply();                   // logged out: forget the old numbers
                return Result.success();
            }
            if (code != 200) {
                return Result.retry();
            }
            StringBuilder sb = new StringBuilder();
            BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
            String line;
            while ((line = in.readLine()) != null) sb.append(line);
            in.close();

            JSONObject json = new JSONObject(sb.toString());
            int requests = json.optInt("requests", 0);
            int chats = json.optInt("chats", 0);
            int lastRequests = prefs.getInt("requests", 0);
            int lastChats = prefs.getInt("chats", 0);
            prefs.edit().putInt("requests", requests).putInt("chats", chats).apply();

            if (chats > lastChats) {
                notify(ctx, "New message in Summeet");
            } else if (requests > lastRequests) {
                notify(ctx, "New request in Summeet");
            }
            return Result.success();
        } catch (Exception e) {
            return Result.retry();
        }
    }

    private void notify(Context ctx, String text) {
        if (Build.VERSION.SDK_INT >= 33
                && ctx.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            return;                                             // the user has not allowed notifications
        }
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationChannel channel = new NotificationChannel(CHANNEL, "Messages and requests", NotificationManager.IMPORTANCE_DEFAULT);
            ctx.getSystemService(NotificationManager.class).createNotificationChannel(channel);
        }
        Intent open = ctx.getPackageManager().getLaunchIntentForPackage(ctx.getPackageName());
        int flags = PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= 23 ? PendingIntent.FLAG_IMMUTABLE : 0);
        PendingIntent tap = PendingIntent.getActivity(ctx, 0, open, flags);
        NotificationCompat.Builder b = new NotificationCompat.Builder(ctx, CHANNEL)
                .setSmallIcon(ctx.getApplicationInfo().icon)
                .setContentTitle("Summeet")
                .setContentText(text)
                .setContentIntent(tap)
                .setAutoCancel(true);
        NotificationManagerCompat.from(ctx).notify(1, b.build());
    }
}
