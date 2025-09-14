package com.signsync.attendance.activity;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.signsync.attendance.R;

public class ChangePinActivity extends AppCompatActivity {
    
    private TextView tvInstructions;
    private EditText etCurrentPin;
    private EditText etNewPin;
    private EditText etConfirmPin;
    private Button btnChangePin;
    
    private SharedPreferences sharedPreferences;
    private static final String PREFS_NAME = "AttendancePrefs";
    private static final String KEY_PIN = "pin";
    private static final String KEY_FIRST_LOGIN = "first_login";
    
    private String employeeId;
    private boolean isFirstLogin;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_change_pin);
        
        sharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        
        // Get data from intent
        employeeId = getIntent().getStringExtra("employee_id");
        isFirstLogin = getIntent().getBooleanExtra("is_first_login", false);
        
        initializeViews();
        setupUI();
        setupClickListeners();
    }
    
    private void initializeViews() {
        tvInstructions = findViewById(R.id.tvInstructions);
        etCurrentPin = findViewById(R.id.etCurrentPin);
        etNewPin = findViewById(R.id.etNewPin);
        etConfirmPin = findViewById(R.id.etConfirmPin);
        btnChangePin = findViewById(R.id.btnChangePin);
    }
    
    private void setupUI() {
        if (isFirstLogin) {
            tvInstructions.setText("Welcome! You must change your default PIN before proceeding.");
            etCurrentPin.setHint("Current PIN (1234)");
        } else {
            tvInstructions.setText("Enter your current PIN and choose a new one.");
            etCurrentPin.setHint("Current PIN");
        }
    }
    
    private void setupClickListeners() {
        btnChangePin.setOnClickListener(v -> attemptPinChange());
    }
    
    private void attemptPinChange() {
        String currentPin = etCurrentPin.getText().toString().trim();
        String newPin = etNewPin.getText().toString().trim();
        String confirmPin = etConfirmPin.getText().toString().trim();
        
        // Validation
        if (TextUtils.isEmpty(currentPin)) {
            etCurrentPin.setError("Current PIN is required");
            return;
        }
        
        if (TextUtils.isEmpty(newPin)) {
            etNewPin.setError("New PIN is required");
            return;
        }
        
        if (newPin.length() < 4) {
            etNewPin.setError("PIN must be at least 4 digits");
            return;
        }
        
        if (TextUtils.isEmpty(confirmPin)) {
            etConfirmPin.setError("Please confirm your new PIN");
            return;
        }
        
        if (!newPin.equals(confirmPin)) {
            etConfirmPin.setError("PINs do not match");
            return;
        }
        
        if (newPin.equals("1234")) {
            etNewPin.setError("Cannot use default PIN. Choose a different PIN.");
            return;
        }
        
        // Validate current PIN
        if (!validateCurrentPin(currentPin)) {
            etCurrentPin.setError("Invalid current PIN");
            return;
        }
        
        // Save new PIN
        SharedPreferences.Editor editor = sharedPreferences.edit();
        editor.putString(KEY_PIN, newPin);
        editor.putBoolean(KEY_FIRST_LOGIN, false);
        editor.apply();
        
        Toast.makeText(this, "PIN changed successfully!", Toast.LENGTH_SHORT).show();
        
        // Navigate to main app
        Intent intent = new Intent(this, SimpleEmployeePortalActivity.class);
        startActivity(intent);
        finish();
    }
    
    private boolean validateCurrentPin(String currentPin) {
        if (isFirstLogin) {
            return currentPin.equals("1234");
        } else {
            String savedPin = sharedPreferences.getString(KEY_PIN, "1234");
            return currentPin.equals(savedPin);
        }
    }
    
    @Override
    public void onBackPressed() {
        if (isFirstLogin) {
            // Don't allow back navigation on first login
            Toast.makeText(this, "You must change your PIN to continue", Toast.LENGTH_SHORT).show();
        } else {
            super.onBackPressed();
        }
    }
}
