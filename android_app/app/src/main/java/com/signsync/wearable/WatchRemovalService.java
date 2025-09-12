package com.signsync.wearable;

import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.Log;
import android.widget.Toast;

import androidx.annotation.Nullable;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
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
    private SharedPreferences sharedPrefs;
    private Handler handler;
    
    // Default API endpoint - can be configured later
    private static final String DEFAULT_API_URL = "http://192.168.1.100/attendance_register/wearos_api.php";

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "WatchRemovalService CREATED.");
        
        sensorManager = (SensorManager) getSystemService(Context.SENSOR_SERVICE);
        offBodySensor = sensorManager.getDefaultSensor(Sensor.TYPE_LOW_LATENCY_OFFBODY_DETECT);
        executorService = Executors.newSingleThreadExecutor();
        sharedPrefs = getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        handler = new Handler(Looper.getMainLooper());

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
        if (executorService != null && !executorService.isShutdown()) {
            executorService.shutdown();
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
                showToast("Watch Removed");
                sendWatchRemovalAlert("watch_removed");
            } else {
                Log.i(TAG, "DEVICE IS ON-BODY (Watch Re-applied)");
                showToast("Watch Reapplied");
                sendWatchRemovalAlert("watch_reapplied");
            }
        }
    }

    @Override
    public void onAccuracyChanged(Sensor sensor, int accuracy) {
        // Can be ignored for the off-body sensor.
        Log.d(TAG, "Sensor accuracy changed: " + accuracy);
    }

    private void sendWatchRemovalAlert(String action) {
        // Get employee ID from shared preferences
        String employeeId = sharedPrefs.getString("employee_id", "DEMO_EMP_001");
        String apiUrl = sharedPrefs.getString("api_url", DEFAULT_API_URL);
        
        // Run network call in background thread
        executorService.execute(() -> {
            try {
                JSONObject requestData = new JSONObject();
                requestData.put("action", action);
                requestData.put("employee_id", employeeId);
                requestData.put("timestamp", System.currentTimeMillis() / 1000);
                requestData.put("device_info", "WearOS " + android.os.Build.MODEL);
                requestData.put("device_id", android.provider.Settings.Secure.getString(getContentResolver(), 
                        android.provider.Settings.Secure.ANDROID_ID));
                
                Log.d(TAG, "Sending " + action + " alert for employee: " + employeeId);
                
                String response = sendHttpRequest(apiUrl, requestData.toString());
                
                if (response != null) {
                    JSONObject responseJson = new JSONObject(response);
                    boolean success = responseJson.optBoolean("success", false);
                    String message = responseJson.optString("message", "Unknown response");
                    
                    if (success) {
                        Log.i(TAG, action + " alert sent successfully: " + message);
                    } else {
                        Log.e(TAG, action + " alert failed: " + message);
                    }
                } else {
                    Log.e(TAG, "No response received for " + action + " alert");
                }
                
            } catch (Exception e) {
                Log.e(TAG, "Error sending " + action + " alert", e);
            }
        });
    }
    
    private String sendHttpRequest(String apiUrl, String jsonPayload) {
        HttpURLConnection connection = null;
        try {
            URL url = new URL(apiUrl);
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            connection.setDoOutput(true);
            connection.setConnectTimeout(10000); // 10 seconds
            connection.setReadTimeout(10000); // 10 seconds

            // Send request
            try (OutputStream os = connection.getOutputStream()) {
                os.write(jsonPayload.getBytes("UTF-8"));
            }

            int responseCode = connection.getResponseCode();
            Log.d(TAG, "HTTP Response Code: " + responseCode);

            if (responseCode == HttpURLConnection.HTTP_OK) {
                BufferedReader in = new BufferedReader(new InputStreamReader(connection.getInputStream()));
                String inputLine;
                StringBuilder response = new StringBuilder();
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();
                return response.toString();
            } else {
                Log.e(TAG, "HTTP request failed with response code: " + responseCode);
                return null;
            }
        } catch (Exception e) {
            Log.e(TAG, "Error sending HTTP request", e);
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    private void showToast(final String message) {
        handler.post(() -> Toast.makeText(getApplicationContext(), message, Toast.LENGTH_SHORT).show());
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
