package com.signsync.wearable;

import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.Log;
import android.widget.Toast;

import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class WatchRemovalService extends Service implements SensorEventListener {

    private static final String TAG = "WatchRemovalService";
    private SensorManager sensorManager;
    private Sensor offBodySensor;
    private ExecutorService executorService;
    private Handler handler = new Handler(Looper.getMainLooper());

    @Override
    public void onCreate() {
        super.onCreate();
        sensorManager = (SensorManager) getSystemService(Context.SENSOR_SERVICE);
        offBodySensor = sensorManager.getDefaultSensor(Sensor.TYPE_LOW_LATENCY_OFFBODY_DETECT);
        executorService = Executors.newSingleThreadExecutor();

        if (offBodySensor == null) {
            Log.w(TAG, "Low latency off-body sensor not available.");
            // Fallback or alternative method could be implemented here
            stopSelf();
        }
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.i(TAG, "Watch Removal Service started.");
        if (offBodySensor != null) {
            sensorManager.registerListener(this, offBodySensor, SensorManager.SENSOR_DELAY_NORMAL);
        }
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        if (sensorManager != null) {
            sensorManager.unregisterListener(this);
        }
        if (executorService != null && !executorService.isShutdown()) {
            executorService.shutdown();
        }
        Log.i(TAG, "Watch Removal Service stopped.");
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onSensorChanged(SensorEvent event) {
        if (event.sensor.getType() == Sensor.TYPE_LOW_LATENCY_OFFBODY_DETECT) {
            float value = event.values[0];
            if (value == 0.0) {
                // Device is off-body
                Log.w(TAG, "Watch removed from body.");
                showToast("Watch Removed");
                sendWatchStatusUpdate("watch_removed");
            } else {
                // Device is on-body
                Log.i(TAG, "Watch reapplied to body.");
                showToast("Watch Reapplied");
                sendWatchStatusUpdate("watch_reapplied");
            }
        }
    }

    @Override
    public void onAccuracyChanged(Sensor sensor, int accuracy) {
        // Can be used to log changes in sensor accuracy if needed
        Log.d(TAG, "Sensor accuracy changed: " + accuracy);
    }

    private void sendWatchStatusUpdate(final String action) {
        executorService.execute(() -> {
            try {
                String employeeId = getSharedPreferences("wearable_prefs", MODE_PRIVATE)
                        .getString("employee_id", null);
                String deviceId = android.provider.Settings.Secure.getString(getContentResolver(), 
                        android.provider.Settings.Secure.ANDROID_ID);

                if (employeeId == null) {
                    Log.e(TAG, "Employee ID not found. Cannot send status update.");
                    return;
                }

                URL url = new URL(ApiClient.API_BASE_URL);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
                conn.setDoOutput(true);
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(15000);

                JSONObject jsonParam = new JSONObject();
                jsonParam.put("action", action);
                jsonParam.put("employee_id", employeeId);
                jsonParam.put("device_id", deviceId);
                jsonParam.put("timestamp", System.currentTimeMillis() / 1000);

                try (OutputStream os = conn.getOutputStream()) {
                    byte[] input = jsonParam.toString().getBytes("utf-8");
                    os.write(input, 0, input.length);
                }

                int responseCode = conn.getResponseCode();
                if (responseCode == HttpURLConnection.HTTP_OK) {
                    Log.i(TAG, "Successfully sent " + action + " status to server.");
                } else {
                    Log.e(TAG, "Server responded with error code: " + responseCode);
                }
                conn.disconnect();

            } catch (Exception e) {
                Log.e(TAG, "Error sending watch status update to server", e);
            }
        });
    }

    private void showToast(final String message) {
        handler.post(() -> Toast.makeText(getApplicationContext(), message, Toast.LENGTH_SHORT).show());
    }
}
