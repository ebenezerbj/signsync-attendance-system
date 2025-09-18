package com.signsync.attendance.activity;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.location.Address;
import android.location.Geocoder;
import android.location.Location;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.view.MenuItem;
import android.view.View;
import android.widget.Button;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.cardview.widget.CardView;
import androidx.core.app.ActivityCompat;
import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationServices;
import com.google.android.gms.maps.CameraUpdateFactory;
import com.google.android.gms.maps.GoogleMap;
import com.google.android.gms.maps.OnMapReadyCallback;
import com.google.android.gms.maps.SupportMapFragment;
import com.google.android.gms.maps.model.CircleOptions;
import com.google.android.gms.maps.model.LatLng;
import com.google.android.gms.maps.model.MarkerOptions;
import com.google.android.material.chip.Chip;
import com.google.android.material.textfield.TextInputEditText;
import com.signsync.attendance.R;
import com.signsync.attendance.model.Branch;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.response.AttendanceResponse;
import com.signsync.attendance.network.response.BranchResponse;
import com.signsync.attendance.utils.PermissionHelper;
import com.signsync.attendance.utils.SessionManager;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class KioskModeActivity extends AppCompatActivity implements OnMapReadyCallback {
    
    // UI Components
    private Toolbar toolbar;
    private TextInputEditText employeeIdInput;
    private TextView statusText, countdownText, locationText;
    private Button actionButton, resetButton;
    private CardView reasonCard;
    private TextInputEditText reasonInput;
    private ImageView photoPreview;
    private Chip statusChip;
    private GoogleMap googleMap;
    
    // Services and data
    private SessionManager sessionManager;
    private AttendanceApiService apiService;
    private FusedLocationProviderClient fusedLocationClient;
    
    // Location and camera
    private double currentLatitude = 0.0;
    private double currentLongitude = 0.0;
    private String currentAddress = "";
    private boolean isLocationValid = false;
    
    // State
    private boolean isProcessing = false;
    private int countdownSeconds = 0;
    private Handler countdownHandler = new Handler(Looper.getMainLooper());
    
    // Activity result launchers
    private ActivityResultLauncher<Intent> cameraLauncher;
    private ActivityResultLauncher<String[]> permissionLauncher;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_kiosk_mode);
        
        initializeComponents();
        setupToolbar();
        setupActivityLaunchers();
        setupClickListeners();
        setupMap();
        requestPermissions();
    }
    
    private void initializeComponents() {
        sessionManager = new SessionManager(this);
        apiService = ApiClient.getApiService();
        fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);
        
        // Initialize UI components
        toolbar = findViewById(R.id.toolbar);
        employeeIdInput = findViewById(R.id.employeeIdInput);
        statusText = findViewById(R.id.statusText);
        countdownText = findViewById(R.id.countdownText);
        locationText = findViewById(R.id.locationText);
        actionButton = findViewById(R.id.actionButton);
        resetButton = findViewById(R.id.resetButton);
        reasonCard = findViewById(R.id.reasonCard);
        reasonInput = findViewById(R.id.reasonInput);
        photoPreview = findViewById(R.id.photoPreview);
        statusChip = findViewById(R.id.statusChip);
    }
    
    private void setupToolbar() {
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("Kiosk Mode");
        }
    }
    
    private void setupActivityLaunchers() {
        // Camera launcher
        cameraLauncher = registerForActivityResult(
                new ActivityResultContracts.StartActivityForResult(),
                result -> {
                    if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                        // Handle camera result - you would process the photo here
                        showStatus("Photo captured successfully", false);
                    }
                }
        );
        
        // Permission launcher
        permissionLauncher = registerForActivityResult(
                new ActivityResultContracts.RequestMultiplePermissions(),
                result -> {
                    boolean allGranted = true;
                    for (Boolean granted : result.values()) {
                        if (!granted) {
                            allGranted = false;
                            break;
                        }
                    }
                    
                    if (allGranted) {
                        getCurrentLocation();
                    } else {
                        showStatus("Location permissions required for attendance", true);
                    }
                }
        );
    }
    
    private void setupClickListeners() {
        actionButton.setOnClickListener(v -> {
            if (!isProcessing) {
                startAttendanceProcess();
            }
        });
        
        resetButton.setOnClickListener(v -> resetForm());
        
        // Auto-submit when employee ID reaches minimum length
        employeeIdInput.setOnEditorActionListener((v, actionId, event) -> {
            String employeeId = employeeIdInput.getText().toString().trim();
            if (employeeId.length() >= 16) {
                startAttendanceProcess();
                return true;
            }
            return false;
        });
    }
    
    private void setupMap() {
        SupportMapFragment mapFragment = (SupportMapFragment) getSupportFragmentManager()
                .findFragmentById(R.id.mapFragment);
        if (mapFragment != null) {
            mapFragment.getMapAsync(this);
        }
    }
    
    private void requestPermissions() {
        if (!PermissionHelper.hasLocationPermissions(this)) {
            permissionLauncher.launch(PermissionHelper.LOCATION_PERMISSIONS);
        } else {
            getCurrentLocation();
        }
    }
    
    private void getCurrentLocation() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) 
                != PackageManager.PERMISSION_GRANTED) {
            showStatus("Location permission required", true);
            return;
        }
        
        showStatus("Getting your location...", false);
        
        fusedLocationClient.getLastLocation()
                .addOnSuccessListener(this, location -> {
                    if (location != null) {
                        currentLatitude = location.getLatitude();
                        currentLongitude = location.getLongitude();
                        updateLocationDisplay();
                        validateLocation();
                    } else {
                        showStatus("Unable to get location. Please try again.", true);
                    }
                })
                .addOnFailureListener(this, e -> {
                    showStatus("Location error: " + e.getMessage(), true);
                });
    }
    
    private void updateLocationDisplay() {
        try {
            Geocoder geocoder = new Geocoder(this, Locale.getDefault());
            List<Address> addresses = geocoder.getFromLocation(currentLatitude, currentLongitude, 1);
            
            if (addresses != null && !addresses.isEmpty()) {
                Address address = addresses.get(0);
                currentAddress = address.getAddressLine(0);
                locationText.setText(currentAddress);
            } else {
                currentAddress = String.format(Locale.getDefault(), 
                        "%.6f, %.6f", currentLatitude, currentLongitude);
                locationText.setText(currentAddress);
            }
            
            // Update map
            if (googleMap != null) {
                LatLng currentLocation = new LatLng(currentLatitude, currentLongitude);
                googleMap.clear();
                googleMap.addMarker(new MarkerOptions()
                        .position(currentLocation)
                        .title("Your Location"));
                googleMap.animateCamera(CameraUpdateFactory.newLatLngZoom(currentLocation, 16));
            }
            
        } catch (IOException e) {
            currentAddress = String.format(Locale.getDefault(), 
                    "%.6f, %.6f", currentLatitude, currentLongitude);
            locationText.setText(currentAddress);
        }
    }
    
    private void validateLocation() {
        // Check if location is within allowed branch radius
        Call<BranchResponse> call = apiService.validateLocation(currentLatitude, currentLongitude);
        call.enqueue(new Callback<BranchResponse>() {
            @Override
            public void onResponse(Call<BranchResponse> call, Response<BranchResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    BranchResponse branchResponse = response.body();
                    if (branchResponse.isSuccess()) {
                        isLocationValid = true;
                        showStatus("Location verified ✓", false);
                        statusChip.setText("Location Valid");
                        statusChip.setChipBackgroundColorResource(R.color.success_green);
                        
                        // Show branch circle on map
                        if (googleMap != null && branchResponse.getBranch() != null) {
                            Branch branch = branchResponse.getBranch();
                            LatLng branchLocation = new LatLng(branch.getLatitude(), branch.getLongitude());
                            
                            googleMap.addMarker(new MarkerOptions()
                                    .position(branchLocation)
                                    .title(branch.getName()));
                            
                            googleMap.addCircle(new CircleOptions()
                                    .center(branchLocation)
                                    .radius(branch.getAllowedRadius())
                                    .strokeColor(getResources().getColor(R.color.primary_color))
                                    .fillColor(getResources().getColor(R.color.primary_color_alpha)));
                        }
                    } else {
                        isLocationValid = false;
                        showStatus("Location not within allowed area", true);
                        statusChip.setText("Location Invalid");
                        statusChip.setChipBackgroundColorResource(R.color.error_red);
                    }
                } else {
                    isLocationValid = false;
                    showStatus("Unable to validate location", true);
                }
            }
            
            @Override
            public void onFailure(Call<BranchResponse> call, Throwable t) {
                isLocationValid = false;
                showStatus("Location validation failed: " + t.getMessage(), true);
            }
        });
    }
    
    private void startAttendanceProcess() {
        String employeeId = employeeIdInput.getText().toString().trim();
        
        if (employeeId.length() < 16) {
            showStatus("Employee ID must be at least 16 digits", true);
            return;
        }
        
        if (!isLocationValid) {
            showStatus("Invalid location. Please ensure you are within the allowed area.", true);
            return;
        }
        
        isProcessing = true;
        showStatus("Processing attendance...", false);
        startCountdown(5);
        
        // Simulate photo capture
        capturePhoto();
        
        // Process attendance after countdown
        countdownHandler.postDelayed(() -> {
            submitAttendance(employeeId);
        }, 5000);
    }
    
    private void startCountdown(int seconds) {
        countdownSeconds = seconds;
        updateCountdownDisplay();
        
        Runnable countdownRunnable = new Runnable() {
            @Override
            public void run() {
                countdownSeconds--;
                updateCountdownDisplay();
                
                if (countdownSeconds > 0) {
                    countdownHandler.postDelayed(this, 1000);
                }
            }
        };
        
        countdownHandler.postDelayed(countdownRunnable, 1000);
    }
    
    private void updateCountdownDisplay() {
        if (countdownSeconds > 0) {
            countdownText.setText(String.valueOf(countdownSeconds));
            countdownText.setVisibility(View.VISIBLE);
        } else {
            countdownText.setVisibility(View.GONE);
        }
    }
    
    private void capturePhoto() {
        // In a real implementation, you would capture a photo here
        // For now, we'll just show a placeholder
        photoPreview.setImageResource(R.drawable.ic_person_placeholder);
        photoPreview.setVisibility(View.VISIBLE);
    }
    
    private void submitAttendance(String employeeId) {
        String deviceId = sessionManager.getDeviceId();
        if (deviceId == null) {
            deviceId = android.provider.Settings.Secure.getString(
                    getContentResolver(), android.provider.Settings.Secure.ANDROID_ID);
        }
        
    Call<AttendanceResponse> call = apiService.clockIn(
        employeeId,
        "clock_in",
        currentLatitude,
        currentLongitude,
        deviceId, // snapshot placeholder
        reasonInput.getText() != null ? reasonInput.getText().toString() : ""
    );
        
        call.enqueue(new Callback<AttendanceResponse>() {
            @Override
            public void onResponse(Call<AttendanceResponse> call, Response<AttendanceResponse> response) {
                isProcessing = false;
                
                if (response.isSuccessful() && response.body() != null) {
                    AttendanceResponse attendanceResponse = response.body();
                    if (attendanceResponse.isSuccess()) {
                        showSuccessMessage(attendanceResponse);
                    } else {
                        showFailureMessage(attendanceResponse.getMessage());
                    }
                } else {
                    showFailureMessage("Attendance submission failed. Please try again.");
                }
            }
            
            @Override
            public void onFailure(Call<AttendanceResponse> call, Throwable t) {
                isProcessing = false;
                showFailureMessage("Network error: " + t.getMessage());
            }
        });
    }
    
    private void showSuccessMessage(AttendanceResponse response) {
        String timestamp = new SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(new Date());
        showStatus("✅ Clock-in successful at " + timestamp, false);
        statusChip.setText("Success");
        statusChip.setChipBackgroundColorResource(R.color.success_green);
        
        // Show reset button
        resetButton.setVisibility(View.VISIBLE);
        actionButton.setEnabled(false);
        
        // Auto-reset after 10 seconds
        countdownHandler.postDelayed(this::resetForm, 10000);
    }
    
    private void showFailureMessage(String message) {
        showStatus("❌ " + message, true);
        statusChip.setText("Failed");
        statusChip.setChipBackgroundColorResource(R.color.error_red);
        
        // Show reason input for failed attempts
        reasonCard.setVisibility(View.VISIBLE);
        resetButton.setVisibility(View.VISIBLE);
    }
    
    private void showStatus(String message, boolean isError) {
        statusText.setText(message);
        statusText.setTextColor(getResources().getColor(
                isError ? R.color.error_red : R.color.text_primary));
    }
    
    private void resetForm() {
        employeeIdInput.setText("");
        statusText.setText("");
        countdownText.setVisibility(View.GONE);
        reasonCard.setVisibility(View.GONE);
        reasonInput.setText("");
        photoPreview.setVisibility(View.GONE);
        resetButton.setVisibility(View.GONE);
        
        actionButton.setEnabled(true);
        isProcessing = false;
        
        statusChip.setText("Ready");
        statusChip.setChipBackgroundColorResource(R.color.primary_color);
        
        // Refresh location
        getCurrentLocation();
    }
    
    @Override
    public void onMapReady(GoogleMap map) {
        googleMap = map;
        googleMap.getUiSettings().setZoomControlsEnabled(true);
        googleMap.getUiSettings().setMyLocationButtonEnabled(true);
        
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) 
                == PackageManager.PERMISSION_GRANTED) {
            googleMap.setMyLocationEnabled(true);
        }
        
        // Update map with current location if available
        if (currentLatitude != 0.0 && currentLongitude != 0.0) {
            updateLocationDisplay();
        }
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            onBackPressed();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
    
    @Override
    protected void onDestroy() {
        super.onDestroy();
        countdownHandler.removeCallbacksAndMessages(null);
    }
}
