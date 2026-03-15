package com.signsync.attendance.phone.network;

import com.google.gson.Gson;
import com.google.gson.JsonObject;

import java.io.IOException;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import okhttp3.Call;
import okhttp3.Callback;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

public class ApiClient {
    
    private static final String BASE_URL = "https://signsync-attendance.fly.dev";
    private static final MediaType JSON = MediaType.get("application/json; charset=utf-8");
    
    private OkHttpClient client;
    private Gson gson;
    private ExecutorService executor;
    
    public ApiClient() {
        client = new OkHttpClient();
        gson = new Gson();
        executor = Executors.newFixedThreadPool(4);
    }
    
    public interface AuthCallback {
        void onSuccess(AuthResponse response);
        void onError(String error);
    }
    
    public interface AttendanceCallback {
        void onSuccess(AttendanceResponse response);
        void onError(String error);
    }
    
    public interface DataCallback<T> {
        void onSuccess(T data);
        void onError(String error);
    }
    
    public void authenticateUser(String employeeId, String pin, String userType, AuthCallback callback) {
        JsonObject json = new JsonObject();
        json.addProperty("employee_id", employeeId);
        json.addProperty("pin", pin);
        json.addProperty("user_type", userType);
        
        RequestBody body = RequestBody.create(gson.toJson(json), JSON);
        Request request = new Request.Builder()
                .url(BASE_URL + "/signsync_pin_api.php")
                .post(body)
                .build();
        
        client.newCall(request).enqueue(new Callback() {
            @Override
            public void onFailure(Call call, IOException e) {
                callback.onError("Network error: " + e.getMessage());
            }
            
            @Override
            public void onResponse(Call call, Response response) throws IOException {
                try {
                    String responseBody = response.body().string();
                    AuthResponse authResponse = gson.fromJson(responseBody, AuthResponse.class);
                    
                    if (authResponse.isSuccess()) {
                        callback.onSuccess(authResponse);
                    } else {
                        callback.onError(authResponse.getMessage());
                    }
                } catch (Exception e) {
                    callback.onError("Parse error: " + e.getMessage());
                }
            }
        });
    }
    
    public void clockInOut(String employeeId, String action, double latitude, double longitude, AttendanceCallback callback) {
        JsonObject json = new JsonObject();
        json.addProperty("employee_id", employeeId);
        json.addProperty("action", action);
        json.addProperty("timestamp", System.currentTimeMillis() / 1000);
        json.addProperty("latitude", latitude);
        json.addProperty("longitude", longitude);
        
        RequestBody body = RequestBody.create(gson.toJson(json), JSON);
        Request request = new Request.Builder()
                .url(BASE_URL + "/wearos_api.php")
                .post(body)
                .build();
        
        client.newCall(request).enqueue(new Callback() {
            @Override
            public void onFailure(Call call, IOException e) {
                callback.onError("Network error: " + e.getMessage());
            }
            
            @Override
            public void onResponse(Call call, Response response) throws IOException {
                try {
                    String responseBody = response.body().string();
                    AttendanceResponse attendanceResponse = gson.fromJson(responseBody, AttendanceResponse.class);
                    callback.onSuccess(attendanceResponse);
                } catch (Exception e) {
                    callback.onError("Parse error: " + e.getMessage());
                }
            }
        });
    }
    
    public void getEmployeeList(DataCallback<EmployeeListResponse> callback) {
        Request request = new Request.Builder()
                .url(BASE_URL + "/get_employees.php")
                .build();
        
        client.newCall(request).enqueue(new Callback() {
            @Override
            public void onFailure(Call call, IOException e) {
                callback.onError("Network error: " + e.getMessage());
            }
            
            @Override
            public void onResponse(Call call, Response response) throws IOException {
                try {
                    String responseBody = response.body().string();
                    EmployeeListResponse employeeResponse = gson.fromJson(responseBody, EmployeeListResponse.class);
                    callback.onSuccess(employeeResponse);
                } catch (Exception e) {
                    callback.onError("Parse error: " + e.getMessage());
                }
            }
        });
    }
    
    public void getAttendanceReport(String employeeId, String startDate, String endDate, DataCallback<AttendanceReportResponse> callback) {
        String url = BASE_URL + "/report_api.php?employee_id=" + employeeId + 
                     "&start_date=" + startDate + "&end_date=" + endDate;
        
        Request request = new Request.Builder()
                .url(url)
                .build();
        
        client.newCall(request).enqueue(new Callback() {
            @Override
            public void onFailure(Call call, IOException e) {
                callback.onError("Network error: " + e.getMessage());
            }
            
            @Override
            public void onResponse(Call call, Response response) throws IOException {
                try {
                    String responseBody = response.body().string();
                    AttendanceReportResponse reportResponse = gson.fromJson(responseBody, AttendanceReportResponse.class);
                    callback.onSuccess(reportResponse);
                } catch (Exception e) {
                    callback.onError("Parse error: " + e.getMessage());
                }
            }
        });
    }
}
