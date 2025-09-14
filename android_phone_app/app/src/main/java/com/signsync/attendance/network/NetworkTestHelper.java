package com.signsync.attendance.network;

import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;
import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import java.util.concurrent.TimeUnit;

public class NetworkTestHelper {
    
    // Array of possible server URLs to try
    public static final String[] POSSIBLE_URLS = {
        "http://192.168.0.189/attendance_register/",     // Your machine's IP
        "http://10.0.2.2/attendance_register/",          // Android emulator localhost
        "http://10.0.2.2:80/attendance_register/",       // Emulator with explicit port 80
        "http://192.168.0.189:80/attendance_register/",  // Machine IP with port 80
        "http://localhost/attendance_register/"          // Direct localhost
    };
    
    public static String getCurrentBaseUrl() {
        // Return the currently configured URL from ApiClient
        return "http://192.168.0.189/attendance_register/";
    }
    
    public static String[] getAlternativeUrls() {
        return POSSIBLE_URLS;
    }
}
