package com.signsync.wearable;

import android.Manifest;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.Ser    private void initializeServices() {
        Log.d(TAG, "Initializing services");
        
        // Bind to health monitoring service
        Intent serviceIntent = new Intent(this, HealthMonitoringService.class);
        bindService(serviceIntent, serviceConnection, Context.BIND_AUTO_CREATE);
        
        // Start data sync service
        Intent syncServiceIntent = new Intent(this, DataSyncService.class);
        startForegroundService(syncServiceIntent);

        // Start watch removal service
        Intent watchRemovalIntent = new Intent(this, WatchRemovalService.class);
        startService(watchRemovalIntent);
    }ion;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.os.IBinder;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.wear.ambient.AmbientModeSupport;

import com.google.android.gms.tasks.OnCompleteListener;
import com.google.android.gms.tasks.Task;
import com.google.android.gms.wearable.MessageApi;
import com.google.android.gms.wearable.MessageEvent;
import com.google.android.gms.wearable.Node;
import com.google.android.gms.wearable.Wearable;

import java.util.List;

public class MainActivity extends AppCompatActivity implements 
    AmbientModeSupport.AmbientCallbackProvider,
    MessageApi.MessageListener {

    private static final String TAG = "SignSyncWearable";
    private static final int PERMISSION_REQUEST_CODE = 100;
    
    // Required permissions for health monitoring and location
    private static final String[] REQUIRED_PERMISSIONS = {
        Manifest.permission.BODY_SENSORS,
        Manifest.permission.ACTIVITY_RECOGNITION,
        Manifest.permission.INTERNET,
        Manifest.permission.ACCESS_NETWORK_STATE,
        Manifest.permission.WAKE_LOCK,
        Manifest.permission.FOREGROUND_SERVICE,
        Manifest.permission.ACCESS_FINE_LOCATION,
        Manifest.permission.ACCESS_COARSE_LOCATION,
        Manifest.permission.ACCESS_WIFI_STATE,
        Manifest.permission.BLUETOOTH,
        Manifest.permission.BLUETOOTH_ADMIN,
        Manifest.permission.BLUETOOTH_SCAN,
        Manifest.permission.BLUETOOTH_CONNECT
    };

    // UI Components
    private TextView statusText;
    private TextView heartRateText;
    private TextView stressLevelText;
    private TextView connectionStatusText;
    private TextView attendanceStatusText;
    private TextView workDurationText;
    private Button startMonitoringButton;
    private Button stopMonitoringButton;
    private Button configButton;
    private Button clockInButton;
    private Button clockOutButton;

    // Service binding
    private HealthMonitoringService healthService;
    private boolean isServiceBound = false;
    
    // Ambient mode support
    private AmbientModeSupport.AmbientController ambientController;
    
    // Shared preferences for configuration
    private SharedPreferences sharedPrefs;
    
    // Update handler
    private Handler uiUpdateHandler;
    private Runnable uiUpdateRunnable;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        Log.d(TAG, "MainActivity onCreate");
        
        // Initialize ambient mode support
        ambientController = AmbientModeSupport.attach(this);
        
        // Initialize UI components
        initializeViews();
        
        // Initialize shared preferences
        sharedPrefs = getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        
        // Initialize update handler
        uiUpdateHandler = new Handler(Looper.getMainLooper());
        setupUIUpdateRunnable();
        
        // Check and request permissions
        if (checkPermissions()) {
            initializeServices();
        } else {
            requestPermissions();
        }
    }

    private void initializeViews() {
        statusText = findViewById(R.id.status_text);
        heartRateText = findViewById(R.id.heart_rate_text);
        stressLevelText = findViewById(R.id.stress_level_text);
        connectionStatusText = findViewById(R.id.connection_status_text);
        attendanceStatusText = findViewById(R.id.attendance_status_text);
        workDurationText = findViewById(R.id.work_duration_text);
        startMonitoringButton = findViewById(R.id.start_monitoring_button);
        stopMonitoringButton = findViewById(R.id.stop_monitoring_button);
        configButton = findViewById(R.id.config_button);
        clockInButton = findViewById(R.id.clock_in_button);
        clockOutButton = findViewById(R.id.clock_out_button);

        // Set button click listeners
        startMonitoringButton.setOnClickListener(v -> startHealthMonitoring());
        stopMonitoringButton.setOnClickListener(v -> stopHealthMonitoring());
        configButton.setOnClickListener(v -> openConfiguration());
        clockInButton.setOnClickListener(v -> performClockIn());
        clockOutButton.setOnClickListener(v -> performClockOut());

        // Initial UI state
        updateUIState(false);
    }

    private void setupUIUpdateRunnable() {
        uiUpdateRunnable = new Runnable() {
            @Override
            public void run() {
                if (isServiceBound && healthService != null) {
                    updateHealthData();
                }
                // Schedule next update in 5 seconds
                uiUpdateHandler.postDelayed(this, 5000);
            }
        };
    }

    private boolean checkPermissions() {
        for (String permission : REQUIRED_PERMISSIONS) {
            if (ContextCompat.checkSelfPermission(this, permission) 
                != PackageManager.PERMISSION_GRANTED) {
                return false;
            }
        }
        return true;
    }

    private void requestPermissions() {
        ActivityCompat.requestPermissions(this, REQUIRED_PERMISSIONS, PERMISSION_REQUEST_CODE);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, 
                                         @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        
        if (requestCode == PERMISSION_REQUEST_CODE) {
            boolean allPermissionsGranted = true;
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    allPermissionsGranted = false;
                    break;
                }
            }

            if (allPermissionsGranted) {
                Log.d(TAG, "All permissions granted");
                initializeServices();
            } else {
                Log.e(TAG, "Some permissions denied");
                Toast.makeText(this, "Health monitoring requires all permissions", 
                             Toast.LENGTH_LONG).show();
                statusText.setText("Permissions required for health monitoring");
            }
        }
    }

    private void initializeServices() {
        Log.d(TAG, "Initializing services");
        
        // Bind to health monitoring service
        Intent serviceIntent = new Intent(this, HealthMonitoringService.class);
        bindService(serviceIntent, serviceConnection, Context.BIND_AUTO_CREATE);
        
        // Start data sync service
        Intent syncServiceIntent = new Intent(this, DataSyncService.class);
        startForegroundService(syncServiceIntent);
        
        // Initialize Wearable API
        Wearable.getMessageApi(getGoogleApiClient()).addListener(this);
        
        statusText.setText("Services initializing...");
    }

    private ServiceConnection serviceConnection = new ServiceConnection() {
        @Override
        public void onServiceConnected(ComponentName name, IBinder service) {
            Log.d(TAG, "Health monitoring service connected");
            HealthMonitoringService.LocalBinder binder = 
                (HealthMonitoringService.LocalBinder) service;
            healthService = binder.getService();
            isServiceBound = true;
            
            // Start UI updates
            uiUpdateHandler.post(uiUpdateRunnable);
            
            runOnUiThread(() -> {
                statusText.setText("Services connected");
                updateUIState(healthService.isMonitoring());
            });
        }

        @Override
        public void onServiceDisconnected(ComponentName name) {
            Log.d(TAG, "Health monitoring service disconnected");
            healthService = null;
            isServiceBound = false;
            
            runOnUiThread(() -> {
                statusText.setText("Service disconnected");
                updateUIState(false);
            });
        }
    };

    private void startHealthMonitoring() {
        if (isServiceBound && healthService != null) {
            Log.d(TAG, "Starting health monitoring");
            
            // Get employee ID from shared preferences
            String employeeId = sharedPrefs.getString("employee_id", "");
            if (employeeId.isEmpty()) {
                Toast.makeText(this, "Please configure employee ID first", 
                             Toast.LENGTH_SHORT).show();
                openConfiguration();
                return;
            }
            
            healthService.startMonitoring(employeeId);
            updateUIState(true);
            statusText.setText("Health monitoring started");
            
            Toast.makeText(this, "Health monitoring started", Toast.LENGTH_SHORT).show();
        } else {
            Toast.makeText(this, "Service not available", Toast.LENGTH_SHORT).show();
        }
    }

    private void stopHealthMonitoring() {
        if (isServiceBound && healthService != null) {
            Log.d(TAG, "Stopping health monitoring");
            healthService.stopMonitoring();
            updateUIState(false);
            statusText.setText("Health monitoring stopped");
            
            // Clear health data display
            heartRateText.setText("Heart Rate: --");
            stressLevelText.setText("Stress Level: --");
            
            Toast.makeText(this, "Health monitoring stopped", Toast.LENGTH_SHORT).show();
        }
    }

    private void openConfiguration() {
        Intent configIntent = new Intent(this, ConfigActivity.class);
        startActivity(configIntent);
    }

    private void updateUIState(boolean isMonitoring) {
        if (isMonitoring) {
            startMonitoringButton.setVisibility(View.GONE);
            stopMonitoringButton.setVisibility(View.VISIBLE);
        } else {
            startMonitoringButton.setVisibility(View.VISIBLE);
            stopMonitoringButton.setVisibility(View.GONE);
        }
    }

    private void updateHealthData() {
        if (healthService != null) {
            HealthData latestData = healthService.getLatestHealthData();
            if (latestData != null) {
                runOnUiThread(() -> {
                    heartRateText.setText("Heart Rate: " + latestData.getHeartRate() + " bpm");
                    stressLevelText.setText("Stress Level: " + 
                        String.format("%.1f", latestData.getStressLevel()));
                    
                    // Update connection status
                    boolean isOnline = healthService.isConnectedToServer();
                    connectionStatusText.setText("Server: " + (isOnline ? "Connected" : "Offline"));
                });
            }
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        Log.d(TAG, "MainActivity onResume");
        
        // Restart UI updates if service is bound
        if (isServiceBound) {
            uiUpdateHandler.post(uiUpdateRunnable);
        }
        
        // Update attendance status when app resumes
        updateAttendanceStatus();
        
        // Start location service if not already running
        LocationService.startLocationService(this);
    }

    // Get current location data from LocationService
    private LocationService.LocationData getLocationData() {
        try {
            // This would ideally be done through service binding
            // For now, we'll create a temporary instance to get location data
            Intent serviceIntent = new Intent(this, LocationService.class);
            startService(serviceIntent);
            
            // In a real implementation, you'd bind to the service and get data
            // For now, return a basic location data structure
            LocationService.LocationData locationData = new LocationService.LocationData();
            
            // Try to get last known location if available
            android.location.LocationManager locationManager = 
                (android.location.LocationManager) getSystemService(LOCATION_SERVICE);
            
            if (ActivityCompat.checkSelfPermission(this, 
                    android.Manifest.permission.ACCESS_FINE_LOCATION) == android.content.pm.PackageManager.PERMISSION_GRANTED) {
                
                android.location.Location lastKnownLocation = 
                    locationManager.getLastKnownLocation(android.location.LocationManager.GPS_PROVIDER);
                
                if (lastKnownLocation == null) {
                    lastKnownLocation = locationManager.getLastKnownLocation(
                        android.location.LocationManager.NETWORK_PROVIDER);
                }
                
                if (lastKnownLocation != null) {
                    locationData.latitude = lastKnownLocation.getLatitude();
                    locationData.longitude = lastKnownLocation.getLongitude();
                    locationData.accuracy = lastKnownLocation.getAccuracy();
                    locationData.locationMethod = "gps";
                    
                    // Check if at workplace (basic distance check)
                    float workplaceLat = sharedPrefs.getFloat("workplace_lat", 0.0f);
                    float workplaceLng = sharedPrefs.getFloat("workplace_lng", 0.0f);
                    
                    if (workplaceLat != 0.0f && workplaceLng != 0.0f) {
                        android.location.Location workplaceLocation = new android.location.Location("workplace");
                        workplaceLocation.setLatitude(workplaceLat);
                        workplaceLocation.setLongitude(workplaceLng);
                        
                        float distance = lastKnownLocation.distanceTo(workplaceLocation);
                        locationData.isAtWorkplace = distance <= 100.0f; // 100 meter radius
                    }
                }
            }
            
            return locationData;
            
        } catch (Exception e) {
            Log.e(TAG, "Error getting location data", e);
            return null;
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        Log.d(TAG, "MainActivity onPause");
        
        // Stop UI updates to save battery
        uiUpdateHandler.removeCallbacks(uiUpdateRunnable);
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "MainActivity onDestroy");
        
        // Cleanup
        uiUpdateHandler.removeCallbacks(uiUpdateRunnable);
        
        if (isServiceBound) {
            unbindService(serviceConnection);
            isServiceBound = false;
        }
        
        // Remove message listener
        Wearable.getMessageApi(getGoogleApiClient()).removeListener(this);
    }

    // Ambient mode support
    @Override
    public AmbientModeSupport.AmbientCallback getAmbientCallback() {
        return new AmbientModeSupport.AmbientCallback() {
            @Override
            public void onEnterAmbient(Bundle ambientDetails) {
                Log.d(TAG, "Entering ambient mode");
                // Reduce UI updates frequency in ambient mode
                uiUpdateHandler.removeCallbacks(uiUpdateRunnable);
                setupAmbientUIUpdates();
            }

            @Override
            public void onExitAmbient() {
                Log.d(TAG, "Exiting ambient mode");
                // Resume normal UI updates
                uiUpdateHandler.removeCallbacks(uiUpdateRunnable);
                setupUIUpdateRunnable();
                uiUpdateHandler.post(uiUpdateRunnable);
            }
        };
    }

    private void setupAmbientUIUpdates() {
        // Update UI every 30 seconds in ambient mode to save battery
        Runnable ambientUpdateRunnable = new Runnable() {
            @Override
            public void run() {
                if (isServiceBound && healthService != null) {
                    updateHealthData();
                }
                uiUpdateHandler.postDelayed(this, 30000); // 30 seconds
            }
        };
        uiUpdateHandler.post(ambientUpdateRunnable);
    }

    // Wearable message handling
    @Override
    public void onMessageReceived(MessageEvent messageEvent) {
        Log.d(TAG, "Message received: " + messageEvent.getPath());
        
        if ("/stress_alert".equals(messageEvent.getPath())) {
            // Handle stress alert from phone app or server
            runOnUiThread(() -> {
                Toast.makeText(this, "Stress alert received!", Toast.LENGTH_LONG).show();
                // Could trigger additional actions like vibration
            });
        }
    }

    // Helper method to get Google API client
    private com.google.android.gms.common.api.GoogleApiClient getGoogleApiClient() {
        return new com.google.android.gms.common.api.GoogleApiClient.Builder(this)
            .addApi(Wearable.API)
            .build();
    }

    // Clock In functionality
    private void performClockIn() {
        String employeeId = sharedPrefs.getString("employee_id", "");
        if (employeeId.isEmpty()) {
            Toast.makeText(this, "Please configure employee ID first", Toast.LENGTH_LONG).show();
            openConfiguration();
            return;
        }

        clockInButton.setEnabled(false);
        clockInButton.setText("Clocking In...");

        new Thread(() -> {
            try {
                // Get comprehensive location data
                LocationService.LocationData locationData = getLocationData();
                
                org.json.JSONObject request = new org.json.JSONObject();
                request.put("action", "clock_in");
                request.put("employee_id", employeeId);
                request.put("timestamp", System.currentTimeMillis() / 1000);
                request.put("device_info", "WearOS " + android.os.Build.MODEL);

                // Add comprehensive location information
                if (locationData != null) {
                    request.put("location_lat", locationData.latitude);
                    request.put("location_lng", locationData.longitude);
                    request.put("location_accuracy", locationData.accuracy);
                    request.put("location_method", locationData.locationMethod);
                    request.put("is_at_workplace", locationData.isAtWorkplace);
                    
                    // Add WiFi networks information
                    org.json.JSONArray wifiNetworks = new org.json.JSONArray();
                    for (LocationService.WifiNetworkInfo wifi : locationData.wifiNetworks) {
                        org.json.JSONObject wifiObj = new org.json.JSONObject();
                        wifiObj.put("ssid", wifi.ssid);
                        wifiObj.put("bssid", wifi.bssid);
                        wifiObj.put("rssi", wifi.rssi);
                        wifiObj.put("frequency", wifi.frequency);
                        wifiNetworks.put(wifiObj);
                    }
                    request.put("wifi_networks", wifiNetworks);
                    
                    // Add beacon information
                    org.json.JSONArray beacons = new org.json.JSONArray();
                    for (LocationService.BeaconInfo beacon : locationData.beacons) {
                        org.json.JSONObject beaconObj = new org.json.JSONObject();
                        beaconObj.put("uuid", beacon.uuid);
                        beaconObj.put("major", beacon.major);
                        beaconObj.put("minor", beacon.minor);
                        beaconObj.put("rssi", beacon.rssi);
                        beaconObj.put("distance", beacon.distance);
                        beacons.put(beaconObj);
                    }
                    request.put("beacons", beacons);
                } else {
                    // Fallback for no location data
                    request.put("location_lat", 0.0);
                    request.put("location_lng", 0.0);
                    request.put("location_method", "unavailable");
                }

                String response = apiClient.sendRequest(request.toString());
                
                runOnUiThread(() -> {
                    try {
                        if (response != null && !response.isEmpty()) {
                            org.json.JSONObject responseObj = new org.json.JSONObject(response);
                            boolean success = responseObj.optBoolean("success", false);
                            String message = responseObj.optString("message", "Unknown error");

                            if (success) {
                                Toast.makeText(this, "Clock in successful!", Toast.LENGTH_LONG).show();
                                clockInButton.setEnabled(false);
                                clockOutButton.setEnabled(true);
                                updateAttendanceStatus();
                            } else {
                                Toast.makeText(this, "Clock in failed: " + message, Toast.LENGTH_LONG).show();
                                clockInButton.setEnabled(true);
                            }
                        } else {
                            Toast.makeText(this, "Network error during clock in", Toast.LENGTH_LONG).show();
                            clockInButton.setEnabled(true);
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error parsing clock in response", e);
                        Toast.makeText(this, "Error during clock in", Toast.LENGTH_LONG).show();
                        clockInButton.setEnabled(true);
                    }
                    clockInButton.setText("Clock In");
                });

            } catch (Exception e) {
                Log.e(TAG, "Clock in error", e);
                runOnUiThread(() -> {
                    Toast.makeText(this, "Clock in failed: " + e.getMessage(), Toast.LENGTH_LONG).show();
                    clockInButton.setEnabled(true);
                    clockInButton.setText("Clock In");
                });
            }
        }).start();
    }

    // Clock Out functionality
    private void performClockOut() {
        String employeeId = sharedPrefs.getString("employee_id", "");
        if (employeeId.isEmpty()) {
            Toast.makeText(this, "Please configure employee ID first", Toast.LENGTH_LONG).show();
            openConfiguration();
            return;
        }

        clockOutButton.setEnabled(false);
        clockOutButton.setText("Clocking Out...");

        new Thread(() -> {
            try {
                ApiClient apiClient = new ApiClient(this);
                
                // Get comprehensive location data
                LocationService.LocationData locationData = getLocationData();
                
                org.json.JSONObject request = new org.json.JSONObject();
                request.put("action", "clock_out");
                request.put("employee_id", employeeId);
                request.put("timestamp", System.currentTimeMillis() / 1000);
                request.put("device_info", "WearOS " + android.os.Build.MODEL);

                // Add comprehensive location information
                if (locationData != null) {
                    request.put("location_lat", locationData.latitude);
                    request.put("location_lng", locationData.longitude);
                    request.put("location_accuracy", locationData.accuracy);
                    request.put("location_method", locationData.locationMethod);
                    request.put("is_at_workplace", locationData.isAtWorkplace);
                    
                    // Add WiFi networks information
                    org.json.JSONArray wifiNetworks = new org.json.JSONArray();
                    for (LocationService.WifiNetworkInfo wifi : locationData.wifiNetworks) {
                        org.json.JSONObject wifiObj = new org.json.JSONObject();
                        wifiObj.put("ssid", wifi.ssid);
                        wifiObj.put("bssid", wifi.bssid);
                        wifiObj.put("rssi", wifi.rssi);
                        wifiObj.put("frequency", wifi.frequency);
                        wifiNetworks.put(wifiObj);
                    }
                    request.put("wifi_networks", wifiNetworks);
                    
                    // Add beacon information
                    org.json.JSONArray beacons = new org.json.JSONArray();
                    for (LocationService.BeaconInfo beacon : locationData.beacons) {
                        org.json.JSONObject beaconObj = new org.json.JSONObject();
                        beaconObj.put("uuid", beacon.uuid);
                        beaconObj.put("major", beacon.major);
                        beaconObj.put("minor", beacon.minor);
                        beaconObj.put("rssi", beacon.rssi);
                        beaconObj.put("distance", beacon.distance);
                        beacons.put(beaconObj);
                    }
                    request.put("beacons", beacons);
                } else {
                    // Fallback for no location data
                    request.put("location_lat", 0.0);
                    request.put("location_lng", 0.0);
                    request.put("location_method", "unavailable");
                }

                String response = apiClient.sendRequest(request.toString());
                
                runOnUiThread(() -> {
                    try {
                        if (response != null && !response.isEmpty()) {
                            org.json.JSONObject responseObj = new org.json.JSONObject(response);
                            boolean success = responseObj.optBoolean("success", false);
                            String message = responseObj.optString("message", "Unknown error");

                            if (success) {
                                org.json.JSONObject data = responseObj.optJSONObject("data");
                                double workHours = data != null ? data.optDouble("work_duration_hours", 0) : 0;
                                
                                Toast.makeText(this, "Clock out successful! Worked: " + 
                                    String.format("%.1f hours", workHours), Toast.LENGTH_LONG).show();
                                
                                clockInButton.setEnabled(true);
                                clockOutButton.setEnabled(false);
                                updateAttendanceStatus();
                            } else {
                                Toast.makeText(this, "Clock out failed: " + message, Toast.LENGTH_LONG).show();
                                clockOutButton.setEnabled(true);
                            }
                        } else {
                            Toast.makeText(this, "Network error during clock out", Toast.LENGTH_LONG).show();
                            clockOutButton.setEnabled(true);
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error parsing clock out response", e);
                        Toast.makeText(this, "Error during clock out", Toast.LENGTH_LONG).show();
                        clockOutButton.setEnabled(true);
                    }
                    clockOutButton.setText("Clock Out");
                });

            } catch (Exception e) {
                Log.e(TAG, "Clock out error", e);
                runOnUiThread(() -> {
                    Toast.makeText(this, "Clock out failed: " + e.getMessage(), Toast.LENGTH_LONG).show();
                    clockOutButton.setEnabled(true);
                    clockOutButton.setText("Clock Out");
                });
            }
        }).start();
    }

    // Update attendance status display
    private void updateAttendanceStatus() {
        String employeeId = sharedPrefs.getString("employee_id", "");
        if (employeeId.isEmpty()) {
            attendanceStatusText.setText("Attendance: Not configured");
            workDurationText.setText("Work Time: --");
            return;
        }

        new Thread(() -> {
            try {
                ApiClient apiClient = new ApiClient(this);
                org.json.JSONObject request = new org.json.JSONObject();
                request.put("action", "get_attendance_status");
                request.put("employee_id", employeeId);

                String response = apiClient.sendRequest(request.toString());
                
                runOnUiThread(() -> {
                    try {
                        if (response != null && !response.isEmpty()) {
                            org.json.JSONObject responseObj = new org.json.JSONObject(response);
                            boolean success = responseObj.optBoolean("success", false);

                            if (success) {
                                org.json.JSONObject data = responseObj.optJSONObject("data");
                                if (data != null) {
                                    String status = data.optString("status", "unknown");
                                    String clockInTime = data.optString("clock_in_time", null);
                                    String clockOutTime = data.optString("clock_out_time", null);
                                    double workDuration = data.optDouble("work_duration_hours", 0);

                                    // Update attendance status text
                                    switch (status) {
                                        case "clocked_in":
                                            attendanceStatusText.setText("Attendance: Clocked In");
                                            clockInButton.setEnabled(false);
                                            clockOutButton.setEnabled(true);
                                            break;
                                        case "clocked_out":
                                            attendanceStatusText.setText("Attendance: Clocked Out");
                                            clockInButton.setEnabled(true);
                                            clockOutButton.setEnabled(false);
                                            break;
                                        default:
                                            attendanceStatusText.setText("Attendance: Not clocked in");
                                            clockInButton.setEnabled(true);
                                            clockOutButton.setEnabled(false);
                                            break;
                                    }

                                    // Update work duration
                                    if (status.equals("clocked_in") && clockInTime != null) {
                                        // Calculate current work time for active session
                                        long clockInMillis = java.time.Instant.parse(clockInTime + "Z").toEpochMilli();
                                        long currentMillis = System.currentTimeMillis();
                                        double currentWorkHours = (currentMillis - clockInMillis) / (1000.0 * 60 * 60);
                                        workDurationText.setText("Work Time: " + String.format("%.1f hrs", currentWorkHours));
                                    } else if (workDuration > 0) {
                                        workDurationText.setText("Work Time: " + String.format("%.1f hrs", workDuration));
                                    } else {
                                        workDurationText.setText("Work Time: --");
                                    }
                                }
                            } else {
                                attendanceStatusText.setText("Attendance: Error loading");
                                workDurationText.setText("Work Time: --");
                            }
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error parsing attendance status", e);
                        attendanceStatusText.setText("Attendance: Error");
                        workDurationText.setText("Work Time: --");
                    }
                });

            } catch (Exception e) {
                Log.e(TAG, "Error getting attendance status", e);
                runOnUiThread(() -> {
                    attendanceStatusText.setText("Attendance: Network error");
                    workDurationText.setText("Work Time: --");
                });
            }
        }).start();
    }
}
