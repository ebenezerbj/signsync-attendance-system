package com.signsync.attendance.activity;

import android.Manifest;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.location.Location;
import android.os.Bundle;
import android.util.Log;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationServices;
import com.signsync.attendance.R;
import com.signsync.attendance.model.AttendanceRecord;
import com.signsync.attendance.model.ClockInOutResponse;
import com.signsync.attendance.network.response.AttendanceResponse;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class SimpleEmployeePortalActivity extends AppCompatActivity {
    
    private static final String TAG = "SimpleEmployeePortal";
    private static final int PERMISSION_REQUEST_CODE = 100;
    private static final String PREFS_NAME = "AttendancePrefs";
    private static final String KEY_EMPLOYEE_ID = "employee_id";
    private static final String KEY_IS_LOGGED_IN = "is_logged_in";
    
    // UI Components
    private TextView welcomeText;
    private TextView employeeIdText;
    private Button clockInButton;
    private Button clockOutButton;
    private Button viewAttendanceButton;
    private Button profileButton;
    
    private boolean isClockedIn = false;
    private SharedPreferences sharedPreferences;
    private FusedLocationProviderClient fusedLocationClient;
    private AttendanceApiService apiService;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        sharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        
        // Check if user is logged in
        if (!sharedPreferences.getBoolean(KEY_IS_LOGGED_IN, false)) {
            redirectToLogin();
            return;
        }
        
        setContentView(R.layout.activity_simple_employee_portal);
        
        // Initialize location client and API service
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);
        apiService = ApiClient.getRetrofitInstance().create(AttendanceApiService.class);
        
        initializeViews();
        setupClickListeners();
        requestPermissions();
        loadEmployeeData();
        checkTodayAttendanceStatus();
    }
    
    private void redirectToLogin() {
        Intent intent = new Intent(this, LoginActivity.class);
        startActivity(intent);
        finish();
    }
    
    private void initializeViews() {
        welcomeText = findViewById(R.id.welcomeText);
        employeeIdText = findViewById(R.id.employeeIdText);
        clockInButton = findViewById(R.id.clockInButton);
        clockOutButton = findViewById(R.id.clockOutButton);
        viewAttendanceButton = findViewById(R.id.viewAttendanceButton);
        profileButton = findViewById(R.id.profileButton);
    }
    
    private void loadEmployeeData() {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        
        if (!employeeId.isEmpty()) {
            welcomeText.setText("Welcome, Employee");
            employeeIdText.setText("Employee ID: " + employeeId);
        } else {
            welcomeText.setText("Welcome, Employee");
            employeeIdText.setText("Employee ID: Not available");
        }
    }
    
    private void setupClickListeners() {
        clockInButton.setOnClickListener(v -> handleClockIn());
        clockOutButton.setOnClickListener(v -> handleClockOut());
        viewAttendanceButton.setOnClickListener(v -> showAttendanceView());
        profileButton.setOnClickListener(v -> showProfile());
    }
    
    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.employee_menu, menu);
        return true;
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        int itemId = item.getItemId();
        if (itemId == R.id.action_change_pin) {
            openChangePinActivity();
            return true;
        } else if (itemId == R.id.action_logout) {
            logout();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
    
    private void openChangePinActivity() {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        Intent intent = new Intent(this, ChangePinActivity.class);
        intent.putExtra("employee_id", employeeId);
        intent.putExtra("is_first_login", false);
        startActivity(intent);
    }
    
    private void logout() {
        // Clear login state
        SharedPreferences.Editor editor = sharedPreferences.edit();
        editor.putBoolean(KEY_IS_LOGGED_IN, false);
        editor.apply();
        
        Toast.makeText(this, "Logged out successfully", Toast.LENGTH_SHORT).show();
        redirectToLogin();
    }
    
    private void handleClockIn() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) 
                != PackageManager.PERMISSION_GRANTED) {
            Toast.makeText(this, "Location permission required for clock in", Toast.LENGTH_SHORT).show();
            return;
        }
        
        // Disable button to prevent multiple clicks
        clockInButton.setEnabled(false);
        
        fusedLocationClient.getLastLocation()
                .addOnSuccessListener(this, location -> {
                    if (location != null) {
                        performClockIn(location.getLatitude(), location.getLongitude());
                    } else {
                        // Use default location if GPS not available
                        performClockIn(0.0, 0.0);
                    }
                })
                .addOnFailureListener(this, e -> {
                    Log.e(TAG, "Error getting location", e);
                    // Use default location on failure
                    performClockIn(0.0, 0.0);
                });
    }
    
    private void performClockIn(double latitude, double longitude) {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        
    Call<AttendanceResponse> call = apiService.clockIn(
                employeeId, 
                "clock_in", 
                latitude, 
                longitude, 
                "", // snapshot - empty for now
                "" // reason - empty for now
        );
        
    call.enqueue(new Callback<AttendanceResponse>() {
            @Override
        public void onResponse(Call<AttendanceResponse> call, Response<AttendanceResponse> response) {
                clockInButton.setEnabled(true);
                
                if (response.isSuccessful() && response.body() != null) {
            AttendanceResponse clockResponse = response.body();
            if (clockResponse.isSuccess()) {
                        isClockedIn = true;
                        clockInButton.setEnabled(false);
                        clockOutButton.setEnabled(true);
                        Toast.makeText(SimpleEmployeePortalActivity.this, 
                clockResponse.getMessage(), Toast.LENGTH_SHORT).show();
            Log.d(TAG, "Clock in successful: " + clockResponse.getMessage());
                    } else {
                        Toast.makeText(SimpleEmployeePortalActivity.this, 
                                "Clock in failed: " + clockResponse.getMessage(), Toast.LENGTH_LONG).show();
                        Log.e(TAG, "Clock in failed: " + clockResponse.getMessage());
                    }
                } else {
                    Toast.makeText(SimpleEmployeePortalActivity.this, 
                            "Clock in failed: Server error", Toast.LENGTH_SHORT).show();
                    Log.e(TAG, "Clock in failed: Response not successful");
                }
            }
            
            @Override
        public void onFailure(Call<AttendanceResponse> call, Throwable t) {
                clockInButton.setEnabled(true);
                Toast.makeText(SimpleEmployeePortalActivity.this, 
                        "Clock in failed: Network error", Toast.LENGTH_SHORT).show();
                Log.e(TAG, "Clock in network error", t);
            }
        });
    }
    
    private void handleClockOut() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) 
                != PackageManager.PERMISSION_GRANTED) {
            Toast.makeText(this, "Location permission required for clock out", Toast.LENGTH_SHORT).show();
            return;
        }
        
        // Disable button to prevent multiple clicks
        clockOutButton.setEnabled(false);
        
        fusedLocationClient.getLastLocation()
                .addOnSuccessListener(this, location -> {
                    if (location != null) {
                        performClockOut(location.getLatitude(), location.getLongitude());
                    } else {
                        // Use default location if GPS not available
                        performClockOut(0.0, 0.0);
                    }
                })
                .addOnFailureListener(this, e -> {
                    Log.e(TAG, "Error getting location", e);
                    // Use default location on failure
                    performClockOut(0.0, 0.0);
                });
    }
    
    private void performClockOut(double latitude, double longitude) {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        
        Call<ClockInOutResponse> call = apiService.clockOut(
                employeeId, 
                "clock_out", 
                latitude, 
                longitude, 
                1 // Default branch ID
        );
        
        call.enqueue(new Callback<ClockInOutResponse>() {
            @Override
            public void onResponse(Call<ClockInOutResponse> call, Response<ClockInOutResponse> response) {
                clockOutButton.setEnabled(true);
                
                if (response.isSuccessful() && response.body() != null) {
                    ClockInOutResponse clockResponse = response.body();
                    if (clockResponse.isSuccess()) {
                        isClockedIn = false;
                        clockInButton.setEnabled(true);
                        clockOutButton.setEnabled(false);
                        Toast.makeText(SimpleEmployeePortalActivity.this, 
                                clockResponse.getMessage(), Toast.LENGTH_SHORT).show();
                        Log.d(TAG, "Clock out successful: " + clockResponse.getMessage());
                    } else {
                        Toast.makeText(SimpleEmployeePortalActivity.this, 
                                "Clock out failed: " + clockResponse.getMessage(), Toast.LENGTH_LONG).show();
                        Log.e(TAG, "Clock out failed: " + clockResponse.getMessage());
                    }
                } else {
                    Toast.makeText(SimpleEmployeePortalActivity.this, 
                            "Clock out failed: Server error", Toast.LENGTH_SHORT).show();
                    Log.e(TAG, "Clock out failed: Response not successful");
                }
            }
            
            @Override
            public void onFailure(Call<ClockInOutResponse> call, Throwable t) {
                clockOutButton.setEnabled(true);
                Toast.makeText(SimpleEmployeePortalActivity.this, 
                        "Clock out failed: Network error", Toast.LENGTH_SHORT).show();
                Log.e(TAG, "Clock out network error", t);
            }
        });
    }
    
    private void checkTodayAttendanceStatus() {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        String today = new java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault()).format(new java.util.Date());
        
        // For now, default to clocked out state until we implement the status check
        isClockedIn = false;
        clockInButton.setEnabled(true);
        clockOutButton.setEnabled(false);
        
        Log.d(TAG, "Attendance status check - defaulting to clocked out state");
    }
    
    private void showAttendanceView() {
        Toast.makeText(this, "Attendance view - Coming soon!", Toast.LENGTH_SHORT).show();
        // In a real app, would open attendance activity/fragment
    }
    
    private void showProfile() {
        Toast.makeText(this, "Employee profile - Coming soon!", Toast.LENGTH_SHORT).show();
        // In a real app, would open profile activity/fragment
    }
    
    private void requestPermissions() {
        String[] permissions = {
            Manifest.permission.CAMERA,
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION
        };
        
        boolean needsPermission = false;
        for (String permission : permissions) {
            if (ContextCompat.checkSelfPermission(this, permission) 
                != PackageManager.PERMISSION_GRANTED) {
                needsPermission = true;
                break;
            }
        }
        
        if (needsPermission) {
            ActivityCompat.requestPermissions(this, permissions, PERMISSION_REQUEST_CODE);
        }
    }
    
    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        
        if (requestCode == PERMISSION_REQUEST_CODE) {
            boolean allGranted = true;
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    allGranted = false;
                    break;
                }
            }
            
            if (allGranted) {
                Toast.makeText(this, "All permissions granted", Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "Some permissions denied. App may not work properly.", 
                    Toast.LENGTH_LONG).show();
            }
        }
    }
}
