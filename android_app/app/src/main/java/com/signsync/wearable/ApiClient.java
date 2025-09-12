package com.signsync.wearable;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import com.google.gson.Gson;
import com.google.gson.JsonObject;

import java.io.IOException;
import java.util.concurrent.TimeUnit;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

public class ApiClient {
    private static final String TAG = "ApiClient";
    private static final MediaType JSON = MediaType.get("application/json; charset=utf-8");
    
    private final OkHttpClient client;
    private final Gson gson;
    private final SharedPreferences sharedPrefs;
    private final Context context;
    
    // API endpoints
    private String baseUrl;
    private String apiEndpoint;

    public interface ApiCallback {
        void onSuccess(String response);
        void onError(String error);
    }

    public ApiClient(Context context) {
        this.context = context;
        this.sharedPrefs = context.getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        this.gson = new Gson();
        
        // Initialize HTTP client with timeouts
        this.client = new OkHttpClient.Builder()
            .connectTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .build();
        
        // Load API configuration
        loadApiConfiguration();
    }

    private void loadApiConfiguration() {
        // Get base URL from shared preferences or use default
        baseUrl = sharedPrefs.getString("api_base_url", BuildConfig.API_BASE_URL);
        apiEndpoint = BuildConfig.API_ENDPOINT;
        
        Log.d(TAG, "API Configuration - Base URL: " + baseUrl + ", Endpoint: " + apiEndpoint);
    }

    public void sendHealthData(HealthData healthData, ApiCallback callback) {
        Log.d(TAG, "Sending health data to server");
        
        try {
            // Create JSON payload
            JsonObject payload = new JsonObject();
            payload.addProperty("action", "submit_health_data");
            payload.addProperty("employee_id", healthData.getEmployeeId());
            payload.addProperty("heart_rate", healthData.getHeartRate());
            payload.addProperty("stress_level", healthData.getStressLevel());
            payload.addProperty("temperature", healthData.getTemperature());
            payload.addProperty("steps", healthData.getSteps());
            payload.addProperty("timestamp", healthData.getTimestamp());
            payload.addProperty("device_type", "android_watch");
            
            String jsonString = gson.toJson(payload);
            Log.d(TAG, "Health data payload: " + jsonString);
            
            // Create request
            RequestBody body = RequestBody.create(jsonString, JSON);
            Request request = new Request.Builder()
                .url(baseUrl + apiEndpoint)
                .post(body)
                .addHeader("Content-Type", "application/json")
                .addHeader("User-Agent", "SignSync-WearOS/1.0.0")
                .build();
            
            // Execute asynchronously
            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    Log.e(TAG, "Health data transmission failed", e);
                    callback.onError("Network error: " + e.getMessage());
                }

                @Override
                public void onResponse(Call call, Response response) throws IOException {
                    String responseBody = response.body() != null ? response.body().string() : "";
                    
                    if (response.isSuccessful()) {
                        Log.d(TAG, "Health data transmitted successfully: " + responseBody);
                        callback.onSuccess(responseBody);
                    } else {
                        Log.e(TAG, "Health data transmission failed: " + response.code() + " - " + responseBody);
                        callback.onError("Server error: " + response.code());
                    }
                }
            });
            
        } catch (Exception e) {
            Log.e(TAG, "Error creating health data request", e);
            callback.onError("Request creation error: " + e.getMessage());
        }
    }

    public void sendStressAlert(String employeeId, HealthData healthData, ApiCallback callback) {
        Log.w(TAG, "Sending stress alert to server");
        
        try {
            // Create JSON payload for stress alert
            JsonObject payload = new JsonObject();
            payload.addProperty("action", "stress_alert");
            payload.addProperty("employee_id", employeeId);
            payload.addProperty("heart_rate", healthData.getHeartRate());
            payload.addProperty("stress_level", healthData.getStressLevel());
            payload.addProperty("temperature", healthData.getTemperature());
            payload.addProperty("timestamp", healthData.getTimestamp());
            payload.addProperty("alert_type", "high_stress");
            payload.addProperty("device_type", "android_watch");
            payload.addProperty("urgent", true);
            
            String jsonString = gson.toJson(payload);
            Log.w(TAG, "Stress alert payload: " + jsonString);
            
            // Create request
            RequestBody body = RequestBody.create(jsonString, JSON);
            Request request = new Request.Builder()
                .url(baseUrl + apiEndpoint)
                .post(body)
                .addHeader("Content-Type", "application/json")
                .addHeader("User-Agent", "SignSync-WearOS/1.0.0")
                .addHeader("X-Alert-Priority", "HIGH")
                .build();
            
            // Execute asynchronously
            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    Log.e(TAG, "Stress alert transmission failed", e);
                    callback.onError("Network error: " + e.getMessage());
                }

                @Override
                public void onResponse(Call call, Response response) throws IOException {
                    String responseBody = response.body() != null ? response.body().string() : "";
                    
                    if (response.isSuccessful()) {
                        Log.d(TAG, "Stress alert transmitted successfully: " + responseBody);
                        callback.onSuccess(responseBody);
                    } else {
                        Log.e(TAG, "Stress alert transmission failed: " + response.code() + " - " + responseBody);
                        callback.onError("Server error: " + response.code());
                    }
                }
            });
            
        } catch (Exception e) {
            Log.e(TAG, "Error creating stress alert request", e);
            callback.onError("Request creation error: " + e.getMessage());
        }
    }

    public void authenticateEmployee(String employeeId, String pin, ApiCallback callback) {
        Log.d(TAG, "Authenticating employee: " + employeeId);
        
        try {
            // Create JSON payload for authentication
            JsonObject payload = new JsonObject();
            payload.addProperty("action", "authenticate_employee");
            payload.addProperty("employee_id", employeeId);
            payload.addProperty("pin", pin);
            payload.addProperty("device_type", "android_watch");
            
            String jsonString = gson.toJson(payload);
            
            // Create request
            RequestBody body = RequestBody.create(jsonString, JSON);
            Request request = new Request.Builder()
                .url(baseUrl + apiEndpoint)
                .post(body)
                .addHeader("Content-Type", "application/json")
                .addHeader("User-Agent", "SignSync-WearOS/1.0.0")
                .build();
            
            // Execute asynchronously
            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    Log.e(TAG, "Authentication failed", e);
                    callback.onError("Network error: " + e.getMessage());
                }

                @Override
                public void onResponse(Call call, Response response) throws IOException {
                    String responseBody = response.body() != null ? response.body().string() : "";
                    
                    if (response.isSuccessful()) {
                        Log.d(TAG, "Authentication successful: " + responseBody);
                        callback.onSuccess(responseBody);
                    } else {
                        Log.e(TAG, "Authentication failed: " + response.code() + " - " + responseBody);
                        callback.onError("Authentication failed: " + response.code());
                    }
                }
            });
            
        } catch (Exception e) {
            Log.e(TAG, "Error creating authentication request", e);
            callback.onError("Request creation error: " + e.getMessage());
        }
    }

    public void testConnection(ApiCallback callback) {
        Log.d(TAG, "Testing connection to server");
        
        try {
            // Create simple ping request
            JsonObject payload = new JsonObject();
            payload.addProperty("action", "ping");
            payload.addProperty("device_type", "android_watch");
            payload.addProperty("timestamp", System.currentTimeMillis());
            
            String jsonString = gson.toJson(payload);
            
            // Create request
            RequestBody body = RequestBody.create(jsonString, JSON);
            Request request = new Request.Builder()
                .url(baseUrl + apiEndpoint)
                .post(body)
                .addHeader("Content-Type", "application/json")
                .addHeader("User-Agent", "SignSync-WearOS/1.0.0")
                .build();
            
            // Execute asynchronously
            client.newCall(request).enqueue(new Callback() {
                @Override
                public void onFailure(Call call, IOException e) {
                    Log.e(TAG, "Connection test failed", e);
                    callback.onError("Connection failed: " + e.getMessage());
                }

                @Override
                public void onResponse(Call call, Response response) throws IOException {
                    String responseBody = response.body() != null ? response.body().string() : "";
                    
                    if (response.isSuccessful()) {
                        Log.d(TAG, "Connection test successful: " + responseBody);
                        callback.onSuccess(responseBody);
                    } else {
                        Log.e(TAG, "Connection test failed: " + response.code() + " - " + responseBody);
                        callback.onError("Server error: " + response.code());
                    }
                }
            });
            
        } catch (Exception e) {
            Log.e(TAG, "Error creating connection test request", e);
            callback.onError("Request creation error: " + e.getMessage());
        }
    }

    public void updateApiConfiguration(String newBaseUrl) {
        this.baseUrl = newBaseUrl;
        
        // Save to shared preferences
        SharedPreferences.Editor editor = sharedPrefs.edit();
        editor.putString("api_base_url", newBaseUrl);
        editor.apply();
        
        Log.d(TAG, "API configuration updated - Base URL: " + baseUrl);
    }

    public String getCurrentBaseUrl() {
        return baseUrl;
    }

    public void cleanup() {
        // Cancel all pending requests
        client.dispatcher().cancelAll();
        Log.d(TAG, "API client cleanup completed");
    }
}
