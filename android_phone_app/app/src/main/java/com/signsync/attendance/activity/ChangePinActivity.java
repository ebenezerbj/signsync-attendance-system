package com.signsync.attendance.activity;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.text.TextUtils;
import android.util.Log;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.google.gson.Gson;
import com.signsync.attendance.R;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;

import java.util.Map;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class ChangePinActivity extends AppCompatActivity {

    private static final String TAG = "ChangePinActivity";

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
    private AttendanceApiService apiService;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_change_pin);

        sharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        apiService = ApiClient.getApiService();

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

        if (!newPin.matches("\\d{4}")) {
            etNewPin.setError("PIN must be exactly 4 digits");
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

        // Disable button to prevent double-submit
        btnChangePin.setEnabled(false);
        btnChangePin.setText("Changing PIN...");

        // Call server API
        Call<Object> call = apiService.changePin(employeeId, currentPin, newPin, isFirstLogin);
        call.enqueue(new Callback<Object>() {
            @Override
            public void onResponse(Call<Object> call, Response<Object> response) {
                btnChangePin.setEnabled(true);
                btnChangePin.setText("Change PIN");

                if (response.isSuccessful() && response.body() != null) {
                    // Parse success response
                    try {
                        String json = new Gson().toJson(response.body());
                        Map<String, Object> result = new Gson().fromJson(json,
                                new com.google.gson.reflect.TypeToken<Map<String, Object>>(){}.getType());
                        boolean success = Boolean.TRUE.equals(result.get("success"));
                        String message = result.containsKey("message") ? String.valueOf(result.get("message")) : "";

                        if (success) {
                            // Update local prefs
                            SharedPreferences.Editor editor = sharedPreferences.edit();
                            editor.putBoolean(KEY_FIRST_LOGIN, false);
                            editor.apply();

                            Toast.makeText(ChangePinActivity.this, "PIN changed successfully!", Toast.LENGTH_SHORT).show();

                            // Navigate to main app
                            Intent intent = new Intent(ChangePinActivity.this, SimpleEmployeePortalActivity.class);
                            startActivity(intent);
                            finish();
                        } else {
                            Toast.makeText(ChangePinActivity.this, message.isEmpty() ? "Failed to change PIN" : message, Toast.LENGTH_LONG).show();
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error parsing response", e);
                        Toast.makeText(ChangePinActivity.this, "Unexpected server response", Toast.LENGTH_LONG).show();
                    }
                } else {
                    // Parse error body for message
                    String errorMsg = "Failed to change PIN";
                    try {
                        if (response.errorBody() != null) {
                            String errorJson = response.errorBody().string();
                            Map<String, Object> errorResult = new Gson().fromJson(errorJson,
                                    new com.google.gson.reflect.TypeToken<Map<String, Object>>(){}.getType());
                            if (errorResult.containsKey("message")) {
                                errorMsg = String.valueOf(errorResult.get("message"));
                            }
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error parsing error body", e);
                    }
                    Toast.makeText(ChangePinActivity.this, errorMsg, Toast.LENGTH_LONG).show();
                }
            }

            @Override
            public void onFailure(Call<Object> call, Throwable t) {
                btnChangePin.setEnabled(true);
                btnChangePin.setText("Change PIN");
                Log.e(TAG, "API call failed", t);
                Toast.makeText(ChangePinActivity.this, "Network error. Please check your connection.", Toast.LENGTH_LONG).show();
            }
        });
    }

    @Override
    public void onBackPressed() {
        if (isFirstLogin) {
            Toast.makeText(this, "You must change your PIN to continue", Toast.LENGTH_SHORT).show();
        } else {
            super.onBackPressed();
        }
    }
}
