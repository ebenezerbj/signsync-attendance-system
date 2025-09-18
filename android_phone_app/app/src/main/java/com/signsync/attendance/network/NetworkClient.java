package com.signsync.attendance.network;

import android.content.Context;
import com.signsync.attendance.utils.SharedPreferencesManager;

public class NetworkClient {
    private static NetworkClient instance;
    private AttendanceApiService apiService;
    private SharedPreferencesManager prefsManager;

    private NetworkClient(Context context) {
        this.apiService = ApiClient.getApiService();
        this.prefsManager = new SharedPreferencesManager(context);
    }

    public static synchronized NetworkClient getInstance(Context context) {
        if (instance == null) {
            instance = new NetworkClient(context.getApplicationContext());
        }
        return instance;
    }

    public AttendanceApiService getApiService() {
        return apiService;
    }

    public String getAuthToken() {
        return prefsManager.getAuthToken();
    }

    public boolean isLoggedIn() {
        return prefsManager.isLoggedIn();
    }

    public static retrofit2.Retrofit getRetrofitInstance() {
        return ApiClient.getRetrofit();
    }
}
