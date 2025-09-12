package com.signsync.wearable;

import android.Manifest;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.ServiceConnection;
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
    
    // Required permissions for health monitoring
    private static final String[] REQUIRED_PERMISSIONS = {
        Manifest.permission.BODY_SENSORS,
        Manifest.permission.ACTIVITY_RECOGNITION,
        Manifest.permission.INTERNET,
        Manifest.permission.ACCESS_NETWORK_STATE,
        Manifest.permission.WAKE_LOCK,
        Manifest.permission.FOREGROUND_SERVICE
    };

    // UI Components
    private TextView statusText;
    private TextView heartRateText;
    private TextView stressLevelText;
    private TextView connectionStatusText;
    private Button startMonitoringButton;
    private Button stopMonitoringButton;
    private Button configButton;

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
        startMonitoringButton = findViewById(R.id.start_monitoring_button);
        stopMonitoringButton = findViewById(R.id.stop_monitoring_button);
        configButton = findViewById(R.id.config_button);

        // Set button click listeners
        startMonitoringButton.setOnClickListener(v -> startHealthMonitoring());
        stopMonitoringButton.setOnClickListener(v -> stopHealthMonitoring());
        configButton.setOnClickListener(v -> openConfiguration());

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
}
