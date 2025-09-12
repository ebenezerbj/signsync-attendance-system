package com.signsync.wearable;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.TextUtils;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;
import android.widget.ProgressBar;
import android.widget.Switch;

import androidx.appcompat.app.AppCompatActivity;
import androidx.wear.ambient.AmbientModeSupport;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

public class ConfigActivity extends AppCompatActivity implements 
    AmbientModeSupport.AmbientCallbackProvider {

    private static final String TAG = "ConfigActivity";
    
    // UI Components
    private EditText employeeIdInput;
    private EditText pinInput;
    private EditText serverUrlInput;
    private TextView statusText;
    private Button testConnectionButton;
    private Button saveConfigButton;
    private Button resetConfigButton;
    private ProgressBar loadingProgress;
    private Switch autoStartSwitch;
    private Switch batteryOptimizationSwitch;
    
    // Configuration
    private SharedPreferences sharedPrefs;
    private ApiClient apiClient;
    private AmbientModeSupport.AmbientController ambientController;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_config);

        Log.d(TAG, "ConfigActivity onCreate");
        
        // Initialize ambient mode support
        ambientController = AmbientModeSupport.attach(this);
        
        // Initialize components
        initializeViews();
        initializeConfiguration();
        loadSavedConfiguration();
    }

    private void initializeViews() {
        employeeIdInput = findViewById(R.id.employee_id_input);
        pinInput = findViewById(R.id.pin_input);
        serverUrlInput = findViewById(R.id.server_url_input);
        statusText = findViewById(R.id.config_status_text);
        testConnectionButton = findViewById(R.id.test_connection_button);
        saveConfigButton = findViewById(R.id.save_config_button);
        resetConfigButton = findViewById(R.id.reset_config_button);
        loadingProgress = findViewById(R.id.loading_progress);
        autoStartSwitch = findViewById(R.id.auto_start_switch);
        batteryOptimizationSwitch = findViewById(R.id.battery_optimization_switch);

        // Set button click listeners
        testConnectionButton.setOnClickListener(v -> testConnection());
        saveConfigButton.setOnClickListener(v -> saveConfiguration());
        resetConfigButton.setOnClickListener(v -> resetConfiguration());
        
        // Initially hide progress bar
        loadingProgress.setVisibility(View.GONE);
    }

    private void initializeConfiguration() {
        sharedPrefs = getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        apiClient = new ApiClient(this);
        
        statusText.setText("Configure your SignSync settings");
    }

    private void loadSavedConfiguration() {
        // Load saved employee ID
        String savedEmployeeId = sharedPrefs.getString("employee_id", "");
        if (!savedEmployeeId.isEmpty()) {
            employeeIdInput.setText(savedEmployeeId);
        }
        
        // Load saved PIN (for convenience, though it should be entered each time for security)
        String savedPin = sharedPrefs.getString("employee_pin", "");
        if (!savedPin.isEmpty()) {
            pinInput.setText(savedPin);
        }
        
        // Load saved server URL
        String savedServerUrl = sharedPrefs.getString("api_base_url", BuildConfig.API_BASE_URL);
        serverUrlInput.setText(savedServerUrl);
        
        // Load saved preferences
        boolean autoStart = sharedPrefs.getBoolean("auto_start_monitoring", false);
        autoStartSwitch.setChecked(autoStart);
        
        boolean batteryOptimization = sharedPrefs.getBoolean("battery_optimization", true);
        batteryOptimizationSwitch.setChecked(batteryOptimization);
        
        Log.d(TAG, "Configuration loaded for employee: " + savedEmployeeId);
    }

    private void testConnection() {
        String serverUrl = serverUrlInput.getText().toString().trim();
        
        if (TextUtils.isEmpty(serverUrl)) {
            showError("Please enter server URL");
            return;
        }
        
        // Validate URL format
        if (!serverUrl.startsWith("http://") && !serverUrl.startsWith("https://")) {
            serverUrl = "http://" + serverUrl;
            serverUrlInput.setText(serverUrl);
        }
        
        if (!serverUrl.endsWith("/")) {
            serverUrl += "/";
        }
        
        Log.d(TAG, "Testing connection to: " + serverUrl);
        
        // Show loading state
        setLoadingState(true);
        statusText.setText("Testing connection...");
        
        // Update API client with new URL
        apiClient.updateApiConfiguration(serverUrl);
        
        // Test connection
        apiClient.testConnection(new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(String response) {
                runOnUiThread(() -> {
                    setLoadingState(false);
                    
                    try {
                        JsonObject jsonResponse = JsonParser.parseString(response).getAsJsonObject();
                        if (jsonResponse.has("success") && jsonResponse.get("success").getAsBoolean()) {
                            String apiVersion = "Unknown";
                            String serverTime = "Unknown";
                            
                            if (jsonResponse.has("data")) {
                                JsonObject data = jsonResponse.getAsJsonObject("data");
                                if (data.has("api_version")) {
                                    apiVersion = data.get("api_version").getAsString();
                                }
                                if (data.has("server_time")) {
                                    long timestamp = data.get("server_time").getAsLong();
                                    serverTime = new java.util.Date(timestamp * 1000).toString();
                                }
                            }
                            
                            statusText.setText("✓ Connection successful\nAPI: " + apiVersion + "\nServer: " + serverTime);
                            Toast.makeText(ConfigActivity.this, "Connection successful!", Toast.LENGTH_SHORT).show();
                            
                        } else {
                            statusText.setText("✗ Server responded but API unavailable");
                        }
                    } catch (Exception e) {
                        statusText.setText("✓ Connected but response format unexpected");
                        Log.w(TAG, "Response parsing error: " + e.getMessage());
                    }
                });
            }

            @Override
            public void onError(String error) {
                runOnUiThread(() -> {
                    setLoadingState(false);
                    statusText.setText("✗ Connection failed: " + error);
                    Toast.makeText(ConfigActivity.this, "Connection failed", Toast.LENGTH_SHORT).show();
                });
            }
        });
    }

    private void saveConfiguration() {
        String employeeId = employeeIdInput.getText().toString().trim();
        String pin = pinInput.getText().toString().trim();
        String serverUrl = serverUrlInput.getText().toString().trim();
        
        // Validate required fields
        if (TextUtils.isEmpty(employeeId)) {
            showError("Employee ID is required");
            employeeIdInput.requestFocus();
            return;
        }
        
        if (TextUtils.isEmpty(pin)) {
            showError("PIN is required");
            pinInput.requestFocus();
            return;
        }
        
        if (TextUtils.isEmpty(serverUrl)) {
            showError("Server URL is required");
            serverUrlInput.requestFocus();
            return;
        }
        
        // Validate employee ID format (basic validation)
        if (employeeId.length() < 3) {
            showError("Employee ID seems too short");
            employeeIdInput.requestFocus();
            return;
        }
        
        // Validate PIN format (basic validation)
        if (pin.length() < 4) {
            showError("PIN must be at least 4 characters");
            pinInput.requestFocus();
            return;
        }
        
        // Normalize server URL
        if (!serverUrl.startsWith("http://") && !serverUrl.startsWith("https://")) {
            serverUrl = "http://" + serverUrl;
        }
        
        if (!serverUrl.endsWith("/")) {
            serverUrl += "/";
        }
        
        Log.d(TAG, "Saving configuration for employee: " + employeeId);
        
        // Show loading state
        setLoadingState(true);
        statusText.setText("Authenticating employee...");
        
        // Update API client
        apiClient.updateApiConfiguration(serverUrl);
        
        // Test authentication before saving
        apiClient.authenticateEmployee(employeeId, pin, new ApiClient.ApiCallback() {
            @Override
            public void onSuccess(String response) {
                runOnUiThread(() -> {
                    setLoadingState(false);
                    
                    try {
                        JsonObject jsonResponse = JsonParser.parseString(response).getAsJsonObject();
                        if (jsonResponse.has("success") && jsonResponse.get("success").getAsBoolean()) {
                            
                            // Save configuration
                            SharedPreferences.Editor editor = sharedPrefs.edit();
                            editor.putString("employee_id", employeeId);
                            editor.putString("employee_pin", pin); // Note: In production, consider more secure storage
                            editor.putString("api_base_url", serverUrl);
                            editor.putBoolean("auto_start_monitoring", autoStartSwitch.isChecked());
                            editor.putBoolean("battery_optimization", batteryOptimizationSwitch.isChecked());
                            editor.putLong("config_saved_time", System.currentTimeMillis());
                            editor.apply();
                            
                            // Extract employee info from response
                            String employeeName = "Unknown";
                            String department = "Unknown";
                            
                            if (jsonResponse.has("data")) {
                                JsonObject data = jsonResponse.getAsJsonObject("data");
                                if (data.has("name")) {
                                    employeeName = data.get("name").getAsString();
                                }
                                if (data.has("department")) {
                                    department = data.get("department").getAsString();
                                }
                            }
                            
                            statusText.setText("✓ Configuration saved successfully\n" +
                                             "Employee: " + employeeName + "\n" +
                                             "Department: " + department);
                            
                            Toast.makeText(ConfigActivity.this, "Configuration saved!", Toast.LENGTH_SHORT).show();
                            
                            Log.d(TAG, "Configuration saved successfully for: " + employeeName);
                            
                            // Delay before closing activity
                            new Handler(Looper.getMainLooper()).postDelayed(() -> {
                                finish();
                            }, 2000);
                            
                        } else {
                            String message = "Authentication failed";
                            if (jsonResponse.has("message")) {
                                message = jsonResponse.get("message").getAsString();
                            }
                            statusText.setText("✗ " + message);
                            Toast.makeText(ConfigActivity.this, "Authentication failed", Toast.LENGTH_SHORT).show();
                        }
                    } catch (Exception e) {
                        statusText.setText("✗ Response parsing error");
                        Log.e(TAG, "Response parsing error: " + e.getMessage());
                    }
                });
            }

            @Override
            public void onError(String error) {
                runOnUiThread(() -> {
                    setLoadingState(false);
                    statusText.setText("✗ Authentication error: " + error);
                    Toast.makeText(ConfigActivity.this, "Authentication failed", Toast.LENGTH_SHORT).show();
                });
            }
        });
    }

    private void resetConfiguration() {
        Log.d(TAG, "Resetting configuration");
        
        // Clear all saved preferences
        SharedPreferences.Editor editor = sharedPrefs.edit();
        editor.clear();
        editor.apply();
        
        // Reset UI to defaults
        employeeIdInput.setText("");
        pinInput.setText("");
        serverUrlInput.setText(BuildConfig.API_BASE_URL);
        autoStartSwitch.setChecked(false);
        batteryOptimizationSwitch.setChecked(true);
        
        statusText.setText("Configuration reset to defaults");
        Toast.makeText(this, "Configuration reset", Toast.LENGTH_SHORT).show();
        
        Log.d(TAG, "Configuration reset completed");
    }

    private void setLoadingState(boolean loading) {
        if (loading) {
            loadingProgress.setVisibility(View.VISIBLE);
            testConnectionButton.setEnabled(false);
            saveConfigButton.setEnabled(false);
            resetConfigButton.setEnabled(false);
        } else {
            loadingProgress.setVisibility(View.GONE);
            testConnectionButton.setEnabled(true);
            saveConfigButton.setEnabled(true);
            resetConfigButton.setEnabled(true);
        }
    }

    private void showError(String message) {
        statusText.setText("✗ " + message);
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "ConfigActivity onDestroy");
        
        // Cleanup API client
        if (apiClient != null) {
            apiClient.cleanup();
        }
    }

    // Ambient mode support
    @Override
    public AmbientModeSupport.AmbientCallback getAmbientCallback() {
        return new AmbientModeSupport.AmbientCallback() {
            @Override
            public void onEnterAmbient(Bundle ambientDetails) {
                Log.d(TAG, "Entering ambient mode in config");
                // Minimize UI updates in ambient mode
            }

            @Override
            public void onExitAmbient() {
                Log.d(TAG, "Exiting ambient mode in config");
                // Resume normal UI operations
            }
        };
    }

    @Override
    public void onBackPressed() {
        // Check if configuration is saved
        String savedEmployeeId = sharedPrefs.getString("employee_id", "");
        if (savedEmployeeId.isEmpty()) {
            Toast.makeText(this, "Please configure employee settings first", Toast.LENGTH_SHORT).show();
            return;
        }
        
        super.onBackPressed();
    }
}
