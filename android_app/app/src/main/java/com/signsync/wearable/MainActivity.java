package com.signsync.wearable;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends Activity {

    private static final String TAG = "SignSyncWearable";
    private TextView statusText;
    private EditText employeeIdEdit, apiUrlEdit;
    private Button configButton, startButton, stopButton;
    private SharedPreferences sharedPrefs;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        Log.d(TAG, "MainActivity onCreate");

        // Initialize UI components
        statusText = findViewById(R.id.status_text);
        employeeIdEdit = findViewById(R.id.employee_id_edit);
        apiUrlEdit = findViewById(R.id.api_url_edit);
        configButton = findViewById(R.id.config_button);
        startButton = findViewById(R.id.start_button);
        stopButton = findViewById(R.id.stop_button);

        sharedPrefs = getSharedPreferences("SignSyncConfig", MODE_PRIVATE);

        // Load saved configuration
        loadConfiguration();

        // Set button listeners
        configButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                saveConfiguration();
            }
        });

        startButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                startWatchRemovalService();
            }
        });

        stopButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                stopWatchRemovalService();
            }
        });

        // Automatically start the service
        startWatchRemovalService();
    }

    private void loadConfiguration() {
        String savedEmployeeId = sharedPrefs.getString("employee_id", "DEMO_EMP_001");
        String savedApiUrl = sharedPrefs.getString("api_url", "http://192.168.1.100/attendance_register/wearos_api.php");
        
        employeeIdEdit.setText(savedEmployeeId);
        apiUrlEdit.setText(savedApiUrl);
        
        Log.d(TAG, "Configuration loaded: Employee ID = " + savedEmployeeId);
    }

    private void saveConfiguration() {
        String employeeId = employeeIdEdit.getText().toString().trim();
        String apiUrl = apiUrlEdit.getText().toString().trim();

        if (employeeId.isEmpty()) {
            Toast.makeText(this, "Employee ID cannot be empty", Toast.LENGTH_SHORT).show();
            return;
        }

        if (apiUrl.isEmpty()) {
            Toast.makeText(this, "API URL cannot be empty", Toast.LENGTH_SHORT).show();
            return;
        }

        SharedPreferences.Editor editor = sharedPrefs.edit();
        editor.putString("employee_id", employeeId);
        editor.putString("api_url", apiUrl);
        editor.apply();

        Toast.makeText(this, "Configuration saved!", Toast.LENGTH_SHORT).show();
        Log.d(TAG, "Configuration saved: Employee ID = " + employeeId + ", API URL = " + apiUrl);
    }

    private void startWatchRemovalService() {
        try {
            Intent serviceIntent = new Intent(this, WatchRemovalService.class);
            startService(serviceIntent);
            statusText.setText("Watch Removal Service: RUNNING");
            Log.d(TAG, "WatchRemovalService started successfully.");
        } catch (Exception e) {
            statusText.setText("Error starting service.");
            Log.e(TAG, "Failed to start WatchRemovalService", e);
        }
    }

    private void stopWatchRemovalService() {
        try {
            Intent serviceIntent = new Intent(this, WatchRemovalService.class);
            stopService(serviceIntent);
            statusText.setText("Watch Removal Service: STOPPED");
            Log.d(TAG, "WatchRemovalService stopped.");
        } catch (Exception e) {
            statusText.setText("Error stopping service.");
            Log.e(TAG, "Failed to stop WatchRemovalService", e);
        }
    }
}