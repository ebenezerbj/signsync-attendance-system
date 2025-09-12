package com.signsync.wearos;

import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.IBinder;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import androidx.annotation.Nullable;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import com.signsync.wearable.ApiClient;
import com.signsync.wearable.HealthData;

public class DataSyncService extends Service {
    private static final String TAG = "DataSyncService";
    private static final String PREFS_NAME = "WearOSConfig";
    private static final String OFFLINE_DATA_KEY = "offline_health_data";
    private static final long SYNC_INTERVAL = 5 * 60 * 1000; // 5 minutes
    private static final int MAX_RETRY_ATTEMPTS = 3;
    private static final long RETRY_DELAY = 30 * 1000; // 30 seconds

    private Handler syncHandler;
    private Runnable syncRunnable;
    private ApiClient apiClient;
    private SharedPreferences prefs;
    private ExecutorService executorService;
    private boolean isServiceRunning = false;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "DataSyncService created");
        
        syncHandler = new Handler(Looper.getMainLooper());
        apiClient = new ApiClient(this);
        prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        executorService = Executors.newSingleThreadExecutor();
        
        setupSyncRunnable();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "DataSyncService started");
        
        if (!isServiceRunning) {
            isServiceRunning = true;
            startPeriodicSync();
        }
        
        // If explicit sync requested
        if (intent != null && intent.getBooleanExtra("immediate_sync", false)) {
            performImmediateSync();
        }
        
        return START_STICKY; // Restart if killed
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "DataSyncService destroyed");
        
        isServiceRunning = false;
        if (syncHandler != null && syncRunnable != null) {
            syncHandler.removeCallbacks(syncRunnable);
        }
        
        if (executorService != null && !executorService.isShutdown()) {
            executorService.shutdown();
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null; // Not a bound service
    }

    private void setupSyncRunnable() {
        syncRunnable = new Runnable() {
            @Override
            public void run() {
                if (isServiceRunning) {
                    performSync();
                    scheduleNextSync();
                }
            }
        };
    }

    private void startPeriodicSync() {
        Log.d(TAG, "Starting periodic sync");
        syncHandler.post(syncRunnable);
    }

    private void scheduleNextSync() {
        syncHandler.postDelayed(syncRunnable, SYNC_INTERVAL);
    }

    private void performImmediateSync() {
        executorService.execute(new Runnable() {
            @Override
            public void run() {
                syncOfflineData();
            }
        });
    }

    private void performSync() {
        executorService.execute(new Runnable() {
            @Override
            public void run() {
                syncOfflineData();
            }
        });
    }

    private void syncOfflineData() {
        try {
            // Check if we have network connectivity
            if (!apiClient.isNetworkAvailable()) {
                Log.d(TAG, "No network available, skipping sync");
                return;
            }

            // Get offline data
            List<HealthData> offlineData = getStoredOfflineData();
            if (offlineData.isEmpty()) {
                Log.d(TAG, "No offline data to sync");
                return;
            }

            Log.d(TAG, "Syncing " + offlineData.size() + " offline health records");

            // Prepare sync data
            JSONArray dataArray = new JSONArray();
            for (HealthData data : offlineData) {
                JSONObject dataObj = new JSONObject();
                dataObj.put("timestamp", data.getTimestamp());
                dataObj.put("heart_rate", data.getHeartRate());
                dataObj.put("stress_level", data.getStressLevel());
                dataObj.put("steps", data.getSteps());
                dataObj.put("activity_type", data.getActivityType());
                dataObj.put("location_lat", data.getLocationLat());
                dataObj.put("location_lng", data.getLocationLng());
                dataArray.put(dataObj);
            }

            // Attempt to sync with retry logic
            boolean syncSuccess = attemptSyncWithRetry(dataArray);
            
            if (syncSuccess) {
                // Clear synced data from local storage
                clearSyncedOfflineData();
                Log.i(TAG, "Successfully synced " + offlineData.size() + " health records");
            } else {
                Log.w(TAG, "Failed to sync offline data after all retry attempts");
            }

        } catch (Exception e) {
            Log.e(TAG, "Error during data sync", e);
        }
    }

    private boolean attemptSyncWithRetry(JSONArray dataArray) {
        for (int attempt = 1; attempt <= MAX_RETRY_ATTEMPTS; attempt++) {
            try {
                Log.d(TAG, "Sync attempt " + attempt + "/" + MAX_RETRY_ATTEMPTS);
                
                JSONObject syncRequest = new JSONObject();
                syncRequest.put("action", "sync_offline_data");
                syncRequest.put("employee_id", prefs.getString("employee_id", ""));
                syncRequest.put("health_data", dataArray);

                String response = apiClient.sendRequest(syncRequest.toString());
                
                if (response != null && !response.isEmpty()) {
                    JSONObject responseObj = new JSONObject(response);
                    boolean success = responseObj.optBoolean("success", false);
                    
                    if (success) {
                        Log.i(TAG, "Sync successful on attempt " + attempt);
                        return true;
                    } else {
                        String error = responseObj.optString("error", "Unknown error");
                        Log.w(TAG, "Sync failed on attempt " + attempt + ": " + error);
                    }
                } else {
                    Log.w(TAG, "Empty response on sync attempt " + attempt);
                }

            } catch (JSONException e) {
                Log.e(TAG, "JSON error on sync attempt " + attempt, e);
            } catch (Exception e) {
                Log.e(TAG, "Sync error on attempt " + attempt, e);
            }

            // Wait before retry (except on last attempt)
            if (attempt < MAX_RETRY_ATTEMPTS) {
                try {
                    Thread.sleep(RETRY_DELAY);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    Log.w(TAG, "Sync retry interrupted");
                    break;
                }
            }
        }
        
        return false;
    }

    private List<HealthData> getStoredOfflineData() {
        List<HealthData> healthDataList = new ArrayList<>();
        
        try {
            String storedData = prefs.getString(OFFLINE_DATA_KEY, "[]");
            JSONArray dataArray = new JSONArray(storedData);
            
            for (int i = 0; i < dataArray.length(); i++) {
                JSONObject dataObj = dataArray.getJSONObject(i);
                
                HealthData healthData = new HealthData();
                healthData.setTimestamp(dataObj.optLong("timestamp", System.currentTimeMillis()));
                healthData.setHeartRate(dataObj.optInt("heart_rate", 0));
                healthData.setStressLevel(dataObj.optDouble("stress_level", 0.0));
                healthData.setSteps(dataObj.optInt("steps", 0));
                healthData.setActivityType(dataObj.optString("activity_type", "unknown"));
                healthData.setLocationLat(dataObj.optDouble("location_lat", 0.0));
                healthData.setLocationLng(dataObj.optDouble("location_lng", 0.0));
                
                healthDataList.add(healthData);
            }
            
        } catch (JSONException e) {
            Log.e(TAG, "Error parsing stored offline data", e);
        }
        
        return healthDataList;
    }

    private void clearSyncedOfflineData() {
        SharedPreferences.Editor editor = prefs.edit();
        editor.putString(OFFLINE_DATA_KEY, "[]");
        editor.apply();
        Log.d(TAG, "Cleared synced offline data");
    }

    public static void storeOfflineHealthData(HealthData healthData, SharedPreferences prefs) {
        try {
            String storedData = prefs.getString(OFFLINE_DATA_KEY, "[]");
            JSONArray dataArray = new JSONArray(storedData);
            
            JSONObject dataObj = new JSONObject();
            dataObj.put("timestamp", healthData.getTimestamp());
            dataObj.put("heart_rate", healthData.getHeartRate());
            dataObj.put("stress_level", healthData.getStressLevel());
            dataObj.put("steps", healthData.getSteps());
            dataObj.put("activity_type", healthData.getActivityType());
            dataObj.put("location_lat", healthData.getLocationLat());
            dataObj.put("location_lng", healthData.getLocationLng());
            
            dataArray.put(dataObj);
            
            // Limit stored data to prevent excessive storage usage (keep last 1000 records)
            if (dataArray.length() > 1000) {
                JSONArray trimmedArray = new JSONArray();
                for (int i = dataArray.length() - 1000; i < dataArray.length(); i++) {
                    trimmedArray.put(dataArray.getJSONObject(i));
                }
                dataArray = trimmedArray;
            }
            
            SharedPreferences.Editor editor = prefs.edit();
            editor.putString(OFFLINE_DATA_KEY, dataArray.toString());
            editor.apply();
            
            Log.d("DataSyncService", "Stored offline health data, total records: " + dataArray.length());
            
        } catch (JSONException e) {
            Log.e("DataSyncService", "Error storing offline health data", e);
        }
    }

    public static int getOfflineDataCount(SharedPreferences prefs) {
        try {
            String storedData = prefs.getString(OFFLINE_DATA_KEY, "[]");
            JSONArray dataArray = new JSONArray(storedData);
            return dataArray.length();
        } catch (JSONException e) {
            Log.e("DataSyncService", "Error counting offline data", e);
            return 0;
        }
    }

    public static void requestImmediateSync(android.content.Context context) {
        Intent syncIntent = new Intent(context, DataSyncService.class);
        syncIntent.putExtra("immediate_sync", true);
        context.startService(syncIntent);
    }
}
