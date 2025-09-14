package com.signsync.attendance.network;

import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;
import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import java.util.concurrent.TimeUnit;

public class ApiClient {
    // NETWORK FIX: Using real WiFi IP address instead of emulator address
    
    // Option 1: For Android EMULATOR (AVD) - this was causing connection issues
    // private static final String BASE_URL = "http://10.0.2.2:8080/attendance_register/";
    
    // Option 2: For REAL Android device on same WiFi - ACTIVE CONFIGURATION
    private static final String BASE_URL = "http://192.168.0.189:8080/attendance_register/";
    
    private static Retrofit retrofit = null;

    public static Retrofit getRetrofitInstance() {
        if (retrofit == null) {
            HttpLoggingInterceptor interceptor = new HttpLoggingInterceptor();
            interceptor.setLevel(HttpLoggingInterceptor.Level.BODY);

            OkHttpClient client = new OkHttpClient.Builder()
                    .addInterceptor(interceptor)
                    .connectTimeout(30, TimeUnit.SECONDS)
                    .readTimeout(30, TimeUnit.SECONDS)
                    .writeTimeout(30, TimeUnit.SECONDS)
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .client(client)
                    .addConverterFactory(GsonConverterFactory.create())
                    .build();
        }
        return retrofit;
    }

    public static AttendanceApiService getApiService() {
        return getRetrofitInstance().create(AttendanceApiService.class);
    }
}
