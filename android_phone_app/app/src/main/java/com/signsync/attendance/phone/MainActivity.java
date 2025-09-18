package com.signsync.attendance.phone;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import androidx.appcompat.app.AppCompatActivity;

public class MainActivity extends AppCompatActivity {
    
    private static final String PREFS_NAME = "SignSyncPrefs";
    private static final String KEY_IS_LOGGED_IN = "isLoggedIn";
    private static final String KEY_USER_TYPE = "userType";
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Check if user is already logged in
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        boolean isLoggedIn = prefs.getBoolean(KEY_IS_LOGGED_IN, false);
        
        if (isLoggedIn) {
            // User is logged in, go to dashboard
            startActivity(new Intent(this, DashboardActivity.class));
        } else {
            // User needs to log in
            startActivity(new Intent(this, LoginActivity.class));
        }
        
        finish(); // Close this activity
    }
}
