package com.signsync.wearable;

import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.os.IBinder;
import android.util.Log;

import androidx.annotation.Nullable;

public class WatchRemovalService extends Service implements SensorEventListener {

    private static final String TAG = "WatchRemovalService";
    private SensorManager sensorManager;
    private Sensor offBodySensor;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "WatchRemovalService CREATED.");
        sensorManager = (SensorManager) getSystemService(Context.SENSOR_SERVICE);
        offBodySensor = sensorManager.getDefaultSensor(Sensor.TYPE_LOW_LATENCY_OFFBODY_DETECT);

        if (offBodySensor == null) {
            Log.e(TAG, "Off-body sensor not available on this device.");
            stopSelf(); // Stop the service if the sensor is not available
        }
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "WatchRemovalService STARTED.");
        if (offBodySensor != null) {
            sensorManager.registerListener(this, offBodySensor, SensorManager.SENSOR_DELAY_NORMAL);
            Log.d(TAG, "Off-body sensor listener registered.");
        }
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        if (sensorManager != null && offBodySensor != null) {
            sensorManager.unregisterListener(this);
            Log.d(TAG, "Off-body sensor listener unregistered.");
        }
        Log.d(TAG, "WatchRemovalService DESTROYED.");
    }

    @Override
    public void onSensorChanged(SensorEvent event) {
        if (event.sensor.getType() == Sensor.TYPE_LOW_LATENCY_OFFBODY_DETECT) {
            // The value is 0.0f if the device is on-body, and 1.0f if it's off-body.
            boolean isOffBody = event.values[0] == 1.0f;
            if (isOffBody) {
                Log.w(TAG, "DEVICE IS OFF-BODY (Watch Removed)");
                // TODO: Add network call to send 'watch_removed' to the backend API.
            } else {
                Log.i(TAG, "DEVICE IS ON-BODY (Watch Re-applied)");
                // TODO: Add network call to send 'watch_reapplied' to the backend API.
            }
        }
    }

    @Override
    public void onAccuracyChanged(Sensor sensor, int accuracy) {
        // Can be ignored for the off-body sensor.
        Log.d(TAG, "Sensor accuracy changed: " + accuracy);
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
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
