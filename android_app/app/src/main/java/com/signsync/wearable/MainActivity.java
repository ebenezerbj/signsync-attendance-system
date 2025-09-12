package com.signsync.wearable;

import android.app.Activity;
import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import android.widget.TextView;

public class MainActivity extends Activity {

    private static final String TAG = "SignSyncWearable";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        final TextView statusText = findViewById(R.id.status_text);
        statusText.setText("Starting Watch Removal Service...");
        Log.d(TAG, "MainActivity onCreate");

        // Start the watch removal service
        try {
            Intent serviceIntent = new Intent(this, WatchRemovalService.class);
            startService(serviceIntent);
            statusText.setText("Watch Removal Service Started.");
            Log.d(TAG, "WatchRemovalService started successfully.");
        } catch (Exception e) {
            statusText.setText("Error starting service.");
            Log.e(TAG, "Failed to start WatchRemovalService", e);
        }
    }
}