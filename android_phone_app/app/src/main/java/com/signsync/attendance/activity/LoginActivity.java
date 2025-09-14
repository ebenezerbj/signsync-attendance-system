package com.signsync.attendance.activity;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.signsync.attendance.R;
import com.signsync.attendance.model.LoginResponse;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class LoginActivity extends AppCompatActivity {
    
    private EditText etEmployeeId;
    private EditText etPin;
    private Button btnLogin;
    private ProgressBar progressBar;
    
    private SharedPreferences sharedPreferences;
    private AttendanceApiService apiService;
    private static final String PREFS_NAME = "AttendancePrefs";
    private static final String KEY_EMPLOYEE_ID = "employee_id";
    private static final String KEY_PIN = "pin";
    private static final String KEY_FIRST_LOGIN = "first_login";
    private static final String KEY_IS_LOGGED_IN = "is_logged_in";
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_login);
        
        sharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        apiService = ApiClient.getApiService();
        
        // Check if already logged in
        if (sharedPreferences.getBoolean(KEY_IS_LOGGED_IN, false)) {
            navigateToMainApp();
            return;
        }
        
        initializeViews();
        setupClickListeners();
    }
    
    private void initializeViews() {
        etEmployeeId = findViewById(R.id.etEmployeeId);
        etPin = findViewById(R.id.etPin);
        btnLogin = findViewById(R.id.btnLogin);
        progressBar = findViewById(R.id.progressBar);
        
        if (progressBar != null) {
            progressBar.setVisibility(View.GONE);
        }
    }
    
    private void setupClickListeners() {
        btnLogin.setOnClickListener(v -> attemptLogin());
    }
    
    private void attemptLogin() {
        String employeeId = etEmployeeId.getText().toString().trim();
        String pin = etPin.getText().toString().trim();
        
        if (TextUtils.isEmpty(employeeId)) {
            etEmployeeId.setError("Employee ID is required");
            return;
        }
        
        if (TextUtils.isEmpty(pin)) {
            etPin.setError("PIN is required");
            return;
        }
        
        if (pin.length() < 4) {
            etPin.setError("PIN must be at least 4 digits");
            return;
        }
        
        // Show progress and disable login button
        if (progressBar != null) {
            progressBar.setVisibility(View.VISIBLE);
        }
        btnLogin.setEnabled(false);
        btnLogin.setText("Logging in...");
        
        // Call API for authentication
        Call<LoginResponse> call = apiService.login(employeeId, pin);
        call.enqueue(new Callback<LoginResponse>() {
            @Override
            public void onResponse(Call<LoginResponse> call, Response<LoginResponse> response) {
                // Hide progress and enable login button
                if (progressBar != null) {
                    progressBar.setVisibility(View.GONE);
                }
                btnLogin.setEnabled(true);
                btnLogin.setText("Login");
                
                if (response.isSuccessful() && response.body() != null) {
                    LoginResponse loginResponse = response.body();
                    
                    if (loginResponse.isSuccess()) {
                        // Save login state
                        SharedPreferences.Editor editor = sharedPreferences.edit();
                        editor.putString(KEY_EMPLOYEE_ID, employeeId);
                        editor.putBoolean(KEY_IS_LOGGED_IN, true);
                        editor.apply();
                        
                        // Check if this is first login with default PIN
                        if (loginResponse.isFirstLogin()) {
                            // Force PIN change
                            Intent intent = new Intent(LoginActivity.this, ChangePinActivity.class);
                            intent.putExtra("employee_id", employeeId);
                            intent.putExtra("is_first_login", true);
                            startActivity(intent);
                            finish();
                        } else {
                            navigateToMainApp();
                        }
                    } else {
                        Toast.makeText(LoginActivity.this, loginResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(LoginActivity.this, "Login failed. Please try again.", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<LoginResponse> call, Throwable t) {
                // Hide progress and enable login button
                if (progressBar != null) {
                    progressBar.setVisibility(View.GONE);
                }
                btnLogin.setEnabled(true);
                btnLogin.setText("Login");
                
                Toast.makeText(LoginActivity.this, "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    private void navigateToMainApp() {
        Intent intent = new Intent(this, EnhancedEmployeePortalActivity.class);
        startActivity(intent);
        finish();
    }
}
