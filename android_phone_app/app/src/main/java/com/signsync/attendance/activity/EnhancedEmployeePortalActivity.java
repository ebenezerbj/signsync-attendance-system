package com.signsync.attendance.activity;

import android.Manifest;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.PorterDuff;
import android.graphics.PorterDuffXfermode;
import android.graphics.Rect;
import android.graphics.RectF;
import android.location.Location;
import android.os.Bundle;
import android.provider.MediaStore;
import android.util.Base64;
import android.util.Log;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationServices;
import com.signsync.attendance.R;
import com.signsync.attendance.model.ClockInOutResponse;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;

import java.io.ByteArrayOutputStream;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class EnhancedEmployeePortalActivity extends AppCompatActivity {
    
    private static final String TAG = "EnhancedEmployeePortal";
    private static final int PERMISSION_REQUEST_CODE = 100;
    private static final int CAMERA_REQUEST_CODE = 200;
    private static final String PREFS_NAME = "AttendancePrefs";
    private static final String KEY_EMPLOYEE_ID = "employee_id";
    private static final String KEY_IS_LOGGED_IN = "is_logged_in";
    
    // UI Components
    private TextView welcomeText;
    private TextView employeeIdText;
    private TextView statusText;
    private Button clockInButton;
    private Button clockOutButton;
    private Button viewAttendanceButton;
    private Button profileButton;
    private ImageView photoPreview;
    private EditText reasonField;
    
    private boolean isClockedIn = false;
    private String currentPhotoBase64 = "";
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
        
        setContentView(R.layout.activity_enhanced_employee_portal);
        
        // Initialize location client and API service
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);
        apiService = ApiClient.getRetrofitInstance().create(AttendanceApiService.class);
        
        initializeViews();
        setupClickListeners();
        requestPermissions();
        loadEmployeeData();
        
        // Set the action bar title
        if (getSupportActionBar() != null) {
            getSupportActionBar().setTitle("Enhanced Employee Portal");
        }
    }
    
    private void redirectToLogin() {
        Intent intent = new Intent(this, LoginActivity.class);
        startActivity(intent);
        finish();
    }
    
    private void initializeViews() {
        welcomeText = findViewById(R.id.welcomeText);
        employeeIdText = findViewById(R.id.employeeIdText);
        statusText = findViewById(R.id.statusText);
        clockInButton = findViewById(R.id.clockInButton);
        clockOutButton = findViewById(R.id.clockOutButton);
        viewAttendanceButton = findViewById(R.id.viewAttendanceButton);
        profileButton = findViewById(R.id.profileButton);
        photoPreview = findViewById(R.id.photoPreview);
        reasonField = findViewById(R.id.reasonField);
    }
    
    private void loadEmployeeData() {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        
        if (!employeeId.isEmpty()) {
            welcomeText.setText("Welcome, Employee");
            employeeIdText.setText("Employee ID: " + employeeId);
            statusText.setText("Ready to clock in/out");
        } else {
            welcomeText.setText("Welcome, Employee");
            employeeIdText.setText("Employee ID: Not available");
            statusText.setText("Please log in again");
        }
    }
    
    private void setupClickListeners() {
        clockInButton.setOnClickListener(v -> initiateClockIn());
        clockOutButton.setOnClickListener(v -> initiateClockOut());
        viewAttendanceButton.setOnClickListener(v -> showAttendanceView());
        profileButton.setOnClickListener(v -> showProfile());
        photoPreview.setOnClickListener(v -> capturePhoto());
    }
    
    private void initiateClockIn() {
        if (currentPhotoBase64.isEmpty()) {
            showPhotoRequiredDialog("Please take a selfie before clocking in");
            return;
        }
        
        handleClockAction("clock_in");
    }
    
    private void initiateClockOut() {
        if (currentPhotoBase64.isEmpty()) {
            showPhotoRequiredDialog("Please take a selfie before clocking out");
            return;
        }
        
        handleClockAction("clock_out");
    }
    
    private void showPhotoRequiredDialog(String message) {
        new AlertDialog.Builder(this)
                .setTitle("Photo Required")
                .setMessage(message)
                .setPositiveButton("Take Photo", (dialog, which) -> capturePhoto())
                .setNegativeButton("Cancel", null)
                .show();
    }
    
    private void capturePhoto() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) 
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, 
                    new String[]{Manifest.permission.CAMERA}, CAMERA_REQUEST_CODE);
            return;
        }
        
        Intent cameraIntent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
        if (cameraIntent.resolveActivity(getPackageManager()) != null) {
            startActivityForResult(cameraIntent, CAMERA_REQUEST_CODE);
        } else {
            Toast.makeText(this, "Camera not available", Toast.LENGTH_SHORT).show();
        }
    }
    
    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        
        if (requestCode == CAMERA_REQUEST_CODE && resultCode == RESULT_OK) {
            if (data != null && data.getExtras() != null) {
                Bitmap photo = (Bitmap) data.getExtras().get("data");
                if (photo != null) {
                    // Create circular bitmap for preview
                    Bitmap circularPhoto = getCircularBitmap(photo);
                    photoPreview.setImageBitmap(circularPhoto);
                    photoPreview.setVisibility(View.VISIBLE);
                    
                    // Convert to base64 for API
                    currentPhotoBase64 = bitmapToBase64(photo);
                    
                    Toast.makeText(this, "Photo captured successfully", Toast.LENGTH_SHORT).show();
                }
            }
        }
    }
    
    private Bitmap getCircularBitmap(Bitmap bitmap) {
        int size = Math.min(bitmap.getWidth(), bitmap.getHeight());
        Bitmap output = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888);
        
        Canvas canvas = new Canvas(output);
        Paint paint = new Paint();
        Rect rect = new Rect(0, 0, size, size);
        RectF rectF = new RectF(rect);
        
        paint.setAntiAlias(true);
        canvas.drawARGB(0, 0, 0, 0);
        canvas.drawOval(rectF, paint);
        
        paint.setXfermode(new PorterDuffXfermode(PorterDuff.Mode.SRC_IN));
        canvas.drawBitmap(bitmap, rect, rect, paint);
        
        return output;
    }
    
    private String bitmapToBase64(Bitmap bitmap) {
        ByteArrayOutputStream byteArrayOutputStream = new ByteArrayOutputStream();
        bitmap.compress(Bitmap.CompressFormat.PNG, 100, byteArrayOutputStream);
        byte[] byteArray = byteArrayOutputStream.toByteArray();
        return "data:image/png;base64," + Base64.encodeToString(byteArray, Base64.DEFAULT);
    }
    
    private void handleClockAction(String action) {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) 
                != PackageManager.PERMISSION_GRANTED) {
            Toast.makeText(this, "Location permission required", Toast.LENGTH_SHORT).show();
            return;
        }
        
        // Disable buttons to prevent multiple clicks
        clockInButton.setEnabled(false);
        clockOutButton.setEnabled(false);
        statusText.setText("Processing " + action.replace("_", " ") + "...");
        
        fusedLocationClient.getLastLocation()
                .addOnSuccessListener(this, location -> {
                    if (location != null) {
                        performClockAction(action, location.getLatitude(), location.getLongitude());
                    } else {
                        performClockAction(action, 0.0, 0.0);
                    }
                })
                .addOnFailureListener(this, e -> {
                    Log.e(TAG, "Error getting location", e);
                    performClockAction(action, 0.0, 0.0);
                });
    }
    
    private void performClockAction(String action, double latitude, double longitude) {
        String employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        String reason = reasonField.getText().toString().trim();
        
        Call<ClockInOutResponse> call;
        if ("clock_in".equals(action)) {
            call = apiService.enhancedClockIn(employeeId, action, latitude, longitude, currentPhotoBase64, reason);
        } else {
            call = apiService.enhancedClockOut(employeeId, action, latitude, longitude, currentPhotoBase64, reason);
        }
        
        call.enqueue(new Callback<ClockInOutResponse>() {
            @Override
            public void onResponse(Call<ClockInOutResponse> call, Response<ClockInOutResponse> response) {
                runOnUiThread(() -> {
                    if (response.isSuccessful() && response.body() != null) {
                        ClockInOutResponse clockResponse = response.body();
                        if (clockResponse.isSuccess()) {
                            handleSuccessfulClock(action, clockResponse);
                        } else {
                            handleFailedClock(action, clockResponse.getMessage());
                        }
                    } else {
                        handleFailedClock(action, "Server error");
                    }
                });
            }
            
            @Override
            public void onFailure(Call<ClockInOutResponse> call, Throwable t) {
                runOnUiThread(() -> {
                    handleFailedClock(action, "Network error: " + t.getMessage());
                });
            }
        });
    }
    
    private void handleSuccessfulClock(String action, ClockInOutResponse response) {
        if ("clock_in".equals(action)) {
            isClockedIn = true;
            clockInButton.setEnabled(false);
            clockOutButton.setEnabled(true);
            statusText.setText("Clocked In Successfully");
        } else {
            isClockedIn = false;
            clockInButton.setEnabled(true);
            clockOutButton.setEnabled(false);
            statusText.setText("Clocked Out Successfully");
        }
        
        // Clear photo and reason after successful action
        currentPhotoBase64 = "";
        photoPreview.setVisibility(View.GONE);
        reasonField.setText("");
        
        // Show detailed success message
        ClockInOutResponse.ClockInOutData data = response.getData();
        String message = response.getMessage();
        if (data != null) {
            message += "\nStatus: " + data.getStatus();
            if (data.getClockInTime() != null) {
                message += "\nTime: " + data.getClockInTime();
            }
            if (data.getClockOutTime() != null) {
                message += "\nTime: " + data.getClockOutTime();
            }
        }
        
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
        Log.d(TAG, action + " successful: " + response.getMessage());
    }
    
    private void handleFailedClock(String action, String errorMessage) {
        // Re-enable buttons
        clockInButton.setEnabled(true);
        clockOutButton.setEnabled(true);
        statusText.setText("Action failed - Please try again");
        
        Toast.makeText(this, action.replace("_", " ") + " failed: " + errorMessage, Toast.LENGTH_LONG).show();
        Log.e(TAG, action + " failed: " + errorMessage);
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
        SharedPreferences.Editor editor = sharedPreferences.edit();
        editor.putBoolean(KEY_IS_LOGGED_IN, false);
        editor.apply();
        
        Toast.makeText(this, "Logged out successfully", Toast.LENGTH_SHORT).show();
        redirectToLogin();
    }
    
    private void showAttendanceView() {
        Toast.makeText(this, "Attendance view - Coming soon!", Toast.LENGTH_SHORT).show();
    }
    
    private void showProfile() {
        Toast.makeText(this, "Employee profile - Coming soon!", Toast.LENGTH_SHORT).show();
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
