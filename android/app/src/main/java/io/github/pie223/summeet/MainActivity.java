package io.github.pie223.summeet;

import android.Manifest;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import androidx.work.Constraints;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import com.getcapacitor.BridgeActivity;
import java.util.concurrent.TimeUnit;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getBridge().getWebView().addJavascriptInterface(new SummeetBridge(this), "SummeetApp");

        // Ask once (Android 13+) to show notifications.
        if (Build.VERSION.SDK_INT >= 33
                && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, 1);
        }

        // Every 15 minutes (the shortest Android allows), while there is a network connection.
        Constraints online = new Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build();
        PeriodicWorkRequest check = new PeriodicWorkRequest.Builder(NotifyWorker.class, 15, TimeUnit.MINUTES)
                .setConstraints(online)
                .build();
        WorkManager.getInstance(this).enqueueUniquePeriodicWork("summeet_notify", ExistingPeriodicWorkPolicy.KEEP, check);
    }
}