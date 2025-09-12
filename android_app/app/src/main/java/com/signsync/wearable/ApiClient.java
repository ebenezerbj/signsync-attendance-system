package com.signsync.wearable;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.util.Log;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import org.json.JSONObject;

public class ApiClient {

    private static final String TAG = "ApiClient";
    private String serverUrl;
    private Context context;
    private ExecutorService executorService;

    public interface ApiCallback {
        void onSuccess(String response);
        void onError(String error);
    }

    public ApiClient(Context context) {
        this.context = context;
        this.executorService = Executors.newCachedThreadPool();
        SharedPreferences sharedPrefs = context.getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        // Ensure you have a default value or a configuration screen to set this
        this.serverUrl = sharedPrefs.getString("server_url", "http://192.168.1.100/attendance_register/wearos_api.php");
    }

    public boolean isNetworkAvailable() {
        ConnectivityManager connectivityManager = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
        NetworkInfo activeNetworkInfo = connectivityManager.getActiveNetworkInfo();
        return activeNetworkInfo != null && activeNetworkInfo.isConnected();
    }

    public void updateApiConfiguration(String serverUrl) {
        this.serverUrl = serverUrl;
        SharedPreferences sharedPrefs = context.getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        sharedPrefs.edit().putString("server_url", serverUrl).apply();
    }

    public void testConnection(ApiCallback callback) {
        executorService.execute(() -> {
            try {
                JSONObject testPayload = new JSONObject();
                testPayload.put("action", "test_connection");
                String response = sendRequest(testPayload.toString());
                if (response != null) {
                    callback.onSuccess(response);
                } else {
                    callback.onError("Connection test failed");
                }
            } catch (Exception e) {
                callback.onError("Connection test error: " + e.getMessage());
            }
        });
    }

    public void authenticateEmployee(String employeeId, String pin, ApiCallback callback) {
        executorService.execute(() -> {
            try {
                JSONObject authPayload = new JSONObject();
                authPayload.put("action", "authenticate");
                authPayload.put("employee_id", employeeId);
                authPayload.put("pin", pin);
                String response = sendRequest(authPayload.toString());
                if (response != null) {
                    callback.onSuccess(response);
                } else {
                    callback.onError("Authentication failed");
                }
            } catch (Exception e) {
                callback.onError("Authentication error: " + e.getMessage());
            }
        });
    }

    public void sendHealthData(HealthData healthData, ApiCallback callback) {
        executorService.execute(() -> {
            try {
                JSONObject dataPayload = new JSONObject();
                dataPayload.put("action", "health_data");
                dataPayload.put("employee_id", healthData.getEmployeeId());
                dataPayload.put("heart_rate", healthData.getHeartRate());
                dataPayload.put("stress_level", healthData.getStressLevel());
                dataPayload.put("steps", healthData.getSteps());
                dataPayload.put("temperature", healthData.getTemperature());
                dataPayload.put("timestamp", healthData.getTimestamp());
                dataPayload.put("activity_type", healthData.getActivityType());
                dataPayload.put("location_lat", healthData.getLocationLat());
                dataPayload.put("location_lng", healthData.getLocationLng());
                
                String response = sendRequest(dataPayload.toString());
                if (response != null) {
                    callback.onSuccess(response);
                } else {
                    callback.onError("Failed to send health data");
                }
            } catch (Exception e) {
                callback.onError("Health data error: " + e.getMessage());
            }
        });
    }

    public void sendStressAlert(String employeeId, HealthData healthData, ApiCallback callback) {
        executorService.execute(() -> {
            try {
                JSONObject alertPayload = new JSONObject();
                alertPayload.put("action", "stress_alert");
                alertPayload.put("employee_id", employeeId);
                alertPayload.put("stress_level", healthData.getStressLevel());
                alertPayload.put("heart_rate", healthData.getHeartRate());
                alertPayload.put("timestamp", healthData.getTimestamp());
                
                String response = sendRequest(alertPayload.toString());
                if (response != null) {
                    callback.onSuccess(response);
                } else {
                    callback.onError("Failed to send stress alert");
                }
            } catch (Exception e) {
                callback.onError("Stress alert error: " + e.getMessage());
            }
        });
    }

    public void cleanup() {
        if (executorService != null && !executorService.isShutdown()) {
            executorService.shutdown();
        }
    }

    public String sendRequest(String jsonPayload) {
        HttpURLConnection connection = null;
        try {
            URL url = new URL(serverUrl);
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            connection.setDoOutput(true);
            connection.setConnectTimeout(15000); // 15 seconds
            connection.setReadTimeout(15000); // 15 seconds

            Log.d(TAG, "Sending request to " + serverUrl);
            Log.d(TAG, "Payload: " + jsonPayload);

            OutputStream os = connection.getOutputStream();
            os.write(jsonPayload.getBytes("UTF-8"));
            os.close();

            int responseCode = connection.getResponseCode();
            Log.d(TAG, "Response Code: " + responseCode);

            if (responseCode == HttpURLConnection.HTTP_OK) {
                BufferedReader in = new BufferedReader(new InputStreamReader(connection.getInputStream()));
                String inputLine;
                StringBuilder response = new StringBuilder();
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();
                Log.d(TAG, "Response: " + response.toString());
                return response.toString();
            } else {
                Log.e(TAG, "Request failed with response code: " + responseCode);
                return null;
            }
        } catch (Exception e) {
            Log.e(TAG, "Error sending request", e);
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }
}