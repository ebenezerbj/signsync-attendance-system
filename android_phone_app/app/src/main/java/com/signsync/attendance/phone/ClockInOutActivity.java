package com.signsync.attendance.phone;

import android.Manifest;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.location.Location;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationServices;

import com.signsync.attendance.phone.network.ApiClient;
import com.signsync.attendance.phone.network.AttendanceResponse;
import com.signsync.attendance.R;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class ClockInOutActivity extends AppCompatActivity {
    
    private static final int LOCATION_PERMISSION_REQUEST = 100;
    
    private TextView tvCurrentTime;
    private TextView tvLocation;
    private TextView tvLastAction;
    private Button btnClockIn;
    private Button btnClockOut;
    private ProgressBar progressBar;
    
    private FusedLocationProviderClient fusedLocationClient;
    private ApiClient apiClient;
    private String employeeId;
    private double currentLatitude = 0;
    private double currentLongitude = 0;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_clock_in_out);
        
        initViews();
        loadUserData();
        setupLocationClient();
        setupClickListeners();
        updateCurrentTime();
        getCurrentLocation();
    }
    
    private void initViews() {
        tvCurrentTime = findViewById(R.id.tv_current_time);
        tvLocation = findViewById(R.id.tv_location);
        tvLastAction = findViewById(R.id.tv_last_action);
        btnClockIn = findViewById(R.id.btn_clock_in);
        btnClockOut = findViewById(R.id.btn_clock_out);
        // Optional: progress bar may not exist in this simple layout
        int progressBarId = getResources().getIdentifier("progress_bar", "id", getPackageName());
        if (progressBarId != 0) {
            progressBar = findViewById(progressBarId);
        }
        
        apiClient = new ApiClient();
    }
    
    private void loadUserData() {
        SharedPreferences prefs = getSharedPreferences("SignSyncPrefs", MODE_PRIVATE);
        employeeId = prefs.getString("employeeId", "");
    }
    
    private void setupLocationClient() {
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);
    }
    
    private void setupClickListeners() {
        btnClockIn.setOnClickListener(v -> performClockAction("clock_in"));
        btnClockOut.setOnClickListener(v -> performClockAction("clock_out"));
    }
    
    private void updateCurrentTime() {
        SimpleDateFormat dateFormat = new SimpleDateFormat("EEEE, MMMM dd, yyyy HH:mm:ss", Locale.getDefault());
        String currentTime = dateFormat.format(new Date());
        tvCurrentTime.setText(currentTime);
        
        // Update every second
        tvCurrentTime.postDelayed(this::updateCurrentTime, 1000);
    }
    
    private void getCurrentLocation() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.ACCESS_FINE_LOCATION},
                    LOCATION_PERMISSION_REQUEST);
            return;
        }
        
        fusedLocationClient.getLastLocation()
                .addOnSuccessListener(this, location -> {
                    if (location != null) {
                        currentLatitude = location.getLatitude();
                        currentLongitude = location.getLongitude();
                        tvLocation.setText(String.format(Locale.getDefault(),
                                "Location: %.6f, %.6f", currentLatitude, currentLongitude));
                    } else {
                        tvLocation.setText("Location: Unable to determine");
                    }
                });
    }
    
    private void performClockAction(String action) {
        if (currentLatitude == 0 && currentLongitude == 0) {
            Toast.makeText(this, "Please wait for location to be determined", Toast.LENGTH_SHORT).show();
            getCurrentLocation();
            return;
        }
        
        showLoading(true);
        
        apiClient.clockInOut(employeeId, action, currentLatitude, currentLongitude, new ApiClient.AttendanceCallback() {
            @Override
            public void onSuccess(AttendanceResponse response) {
                runOnUiThread(() -> {
                    showLoading(false);
                    handleClockResponse(action, response);
                });
            }
            
            @Override
            public void onError(String error) {
                runOnUiThread(() -> {
                    showLoading(false);
                    Toast.makeText(ClockInOutActivity.this, "Error: " + error, Toast.LENGTH_SHORT).show();
                });
            }
        });
    }
    
    private void handleClockResponse(String action, AttendanceResponse response) {
        if (response.isSuccess()) {
            String actionText = action.equals("clock_in") ? "Clocked In" : "Clocked Out";
            String timestamp = new SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(new Date());
            
            tvLastAction.setText(String.format("%s at %s", actionText, timestamp));
            
            // Update button states
            if (action.equals("clock_in")) {
                btnClockIn.setEnabled(false);
                btnClockOut.setEnabled(true);
            } else {
                btnClockIn.setEnabled(true);
                btnClockOut.setEnabled(false);
            }
            
            Toast.makeText(this, actionText + " successfully!", Toast.LENGTH_SHORT).show();
        } else {
            Toast.makeText(this, "Failed: " + response.getMessage(), Toast.LENGTH_SHORT).show();
        }
    }
    
    private void showLoading(boolean show) {
        if (progressBar != null) {
            progressBar.setVisibility(show ? View.VISIBLE : View.GONE);
        }
        btnClockIn.setEnabled(!show);
        btnClockOut.setEnabled(!show);
    }
    
    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        
        if (requestCode == LOCATION_PERMISSION_REQUEST) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                getCurrentLocation();
            } else {
                tvLocation.setText("Location: Permission denied");
                Toast.makeText(this, "Location permission is required for attendance tracking", Toast.LENGTH_LONG).show();
            }
        }
    }
    
    @Override
    protected void onResume() {
        super.onResume();
        getCurrentLocation();
        updateCurrentTime();
    }
}
