package com.signsync.attendance.phone;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.RadioGroup;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.signsync.attendance.R;
import com.signsync.attendance.phone.network.ApiClient;
import com.signsync.attendance.phone.network.AuthResponse;

public class LoginActivity extends AppCompatActivity {
    
    private EditText etEmployeeId;
    private EditText etPin;
    private RadioGroup rgUserType;
    private Button btnLogin;
    private ProgressBar progressBar;
    
    private static final String PREFS_NAME = "SignSyncPrefs";
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_login);
        
        initViews();
        setupClickListeners();
    }
    
    private void initViews() {
        etEmployeeId = findViewById(R.id.etEmployeeId);
        etPin = findViewById(R.id.etPin);
        // Optional user type controls may not exist in this layout
        int rgId = getResources().getIdentifier("rg_user_type", "id", getPackageName());
        if (rgId != 0) {
            rgUserType = findViewById(rgId);
        }
        btnLogin = findViewById(R.id.btnLogin);
        progressBar = findViewById(R.id.progressBar);
    }
    
    private void setupClickListeners() {
        btnLogin.setOnClickListener(v -> attemptLogin());
    }
    
    private void attemptLogin() {
        String employeeId = etEmployeeId.getText().toString().trim();
        String pin = etPin.getText().toString().trim();
        
        if (employeeId.isEmpty()) {
            etEmployeeId.setError("Employee ID is required");
            return;
        }
        
        if (pin.isEmpty()) {
            etPin.setError("PIN is required");
            return;
        }
        
        showLoading(true);
        
        // Determine user type
        String userType = getUserType();
        
        // Make API call
        ApiClient apiClient = new ApiClient();
        apiClient.authenticateUser(employeeId, pin, userType, new ApiClient.AuthCallback() {
            @Override
            public void onSuccess(AuthResponse response) {
                runOnUiThread(() -> {
                    showLoading(false);
                    saveLoginData(employeeId, userType, response);
                    navigateToDashboard();
                });
            }
            
            @Override
            public void onError(String error) {
                runOnUiThread(() -> {
                    showLoading(false);
                    Toast.makeText(LoginActivity.this, "Login failed: " + error, Toast.LENGTH_SHORT).show();
                });
            }
        });
    }
    
    private String getUserType() {
        if (rgUserType == null) {
            return "employee";
        }
        int selectedId = rgUserType.getCheckedRadioButtonId();
        if (selectedId == getResources().getIdentifier("rb_admin", "id", getPackageName())) {
            return "admin";
        } else if (selectedId == getResources().getIdentifier("rb_supervisor", "id", getPackageName())) {
            return "supervisor";
        } else {
            return "employee";
        }
    }
    
    private void saveLoginData(String employeeId, String userType, AuthResponse response) {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        SharedPreferences.Editor editor = prefs.edit();
        editor.putBoolean("isLoggedIn", true);
        editor.putString("employeeId", employeeId);
        editor.putString("userType", userType);
        editor.putString("employeeName", response.getEmployeeName());
        editor.putString("department", response.getDepartment());
        editor.putString("authToken", response.getToken());
        editor.apply();
    }
    
    private void navigateToDashboard() {
        Intent intent = new Intent(this, DashboardActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        finish();
    }
    
    private void showLoading(boolean show) {
        progressBar.setVisibility(show ? View.VISIBLE : View.GONE);
        btnLogin.setEnabled(!show);
        etEmployeeId.setEnabled(!show);
        etPin.setEnabled(!show);
        rgUserType.setEnabled(!show);
    }
}
