package com.signsync.wearable;

import android.content.Context;
import android.content.SharedPreferences;
import android.util.Log;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class ApiClient {

    private static final String TAG = "ApiClient";
    private String serverUrl;

    public ApiClient(Context context) {
        SharedPreferences sharedPrefs = context.getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        // Ensure you have a default value or a configuration screen to set this
        this.serverUrl = sharedPrefs.getString("server_url", "http://192.168.1.100/attendance_register/wearos_api.php");
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