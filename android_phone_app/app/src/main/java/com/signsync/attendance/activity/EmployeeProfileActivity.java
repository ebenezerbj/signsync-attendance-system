package com.signsync.attendance.activity;

import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.provider.MediaStore;
import android.util.Base64;
import android.util.Log;
import android.view.MenuItem;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.ProgressBar;
import android.widget.Switch;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;

import com.signsync.attendance.R;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.ApiResponse;
import com.signsync.attendance.network.AttendanceApiService;

import java.io.ByteArrayOutputStream;
import java.io.IOException;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class EmployeeProfileActivity extends AppCompatActivity {
    
    private static final String TAG = "EmployeeProfile";
    private static final String PREFS_NAME = "AttendancePrefs";
    private static final String KEY_EMPLOYEE_ID = "employee_id";
    private static final int CAMERA_REQUEST_CODE = 100;
    private static final int GALLERY_REQUEST_CODE = 101;
    
    // UI Components
    private Toolbar toolbar;
    private ImageView ivProfilePhoto;
    private TextView tvEmployeeId;
    private EditText etFullName;
    private EditText etEmail;
    private EditText etPhoneNumber;
    private EditText etAddress;
    private TextView tvDepartment;
    private TextView tvBranch;
    private TextView tvJoinDate;
    private Switch switchNotifications;
    private Switch switchLocationTracking;
    private Switch switchBiometricLogin;
    private Button btnUpdateProfile;
    private Button btnChangePhoto;
    private Button btnChangePin;
    private Button btnLogout;
    private ProgressBar progressBar;
    
    // Data
    private SharedPreferences sharedPreferences;
    private AttendanceApiService apiService;
    private String employeeId;
    private Employee currentEmployee;
    private String newProfilePhotoBase64 = "";
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_employee_profile);
        
        // Initialize preferences and API
        sharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        apiService = ApiClient.getRetrofitInstance().create(AttendanceApiService.class);
        
        initializeViews();
        setupToolbar();
        setupClickListeners();
        loadEmployeeProfile();
    }
    
    private void initializeViews() {
        toolbar = findViewById(R.id.toolbar);
        ivProfilePhoto = findViewById(R.id.ivProfilePhoto);
        tvEmployeeId = findViewById(R.id.tvEmployeeId);
        etFullName = findViewById(R.id.etFullName);
        etEmail = findViewById(R.id.etEmail);
        etPhoneNumber = findViewById(R.id.etPhoneNumber);
        etAddress = findViewById(R.id.etAddress);
        tvDepartment = findViewById(R.id.tvDepartment);
        tvBranch = findViewById(R.id.tvBranch);
        tvJoinDate = findViewById(R.id.tvJoinDate);
        switchNotifications = findViewById(R.id.switchNotifications);
        switchLocationTracking = findViewById(R.id.switchLocationTracking);
        switchBiometricLogin = findViewById(R.id.switchBiometricLogin);
        btnUpdateProfile = findViewById(R.id.btnUpdateProfile);
        btnChangePhoto = findViewById(R.id.btnChangePhoto);
        btnChangePin = findViewById(R.id.btnChangePin);
        btnLogout = findViewById(R.id.btnLogout);
        progressBar = findViewById(R.id.progressBar);
    }
    
    private void setupToolbar() {
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setTitle("Employee Profile");
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
        }
    }
    
    private void setupClickListeners() {
        btnUpdateProfile.setOnClickListener(v -> updateProfile());
        btnChangePhoto.setOnClickListener(v -> showPhotoOptions());
        btnChangePin.setOnClickListener(v -> openChangePinActivity());
        btnLogout.setOnClickListener(v -> showLogoutConfirmation());
        
        ivProfilePhoto.setOnClickListener(v -> showPhotoOptions());
    }
    
    private void loadEmployeeProfile() {
        if (employeeId.isEmpty()) {
            Toast.makeText(this, "Employee ID not found", Toast.LENGTH_SHORT).show();
            return;
        }
        
        progressBar.setVisibility(View.VISIBLE);
        
        Call<ApiResponse<Employee>> call = apiService.getEmployeeProfile(employeeId);
        call.enqueue(new Callback<ApiResponse<Employee>>() {
            @Override
            public void onResponse(Call<ApiResponse<Employee>> call, Response<ApiResponse<Employee>> response) {
                progressBar.setVisibility(View.GONE);
                
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<Employee> apiResponse = response.body();
                    if (apiResponse.isSuccess()) {
                        currentEmployee = apiResponse.getData();
                        populateProfileData();
                    } else {
                        Toast.makeText(EmployeeProfileActivity.this, 
                            apiResponse.getMessage(), Toast.LENGTH_LONG).show();
                    }
                } else {
                    Toast.makeText(EmployeeProfileActivity.this, 
                        "Failed to load profile", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<Employee>> call, Throwable t) {
                progressBar.setVisibility(View.GONE);
                Toast.makeText(EmployeeProfileActivity.this, 
                    "Network error: " + t.getMessage(), Toast.LENGTH_LONG).show();
                Log.e(TAG, "Failed to load employee profile", t);
            }
        });
    }
    
    private void populateProfileData() {
        if (currentEmployee == null) return;
        
        tvEmployeeId.setText("ID: " + currentEmployee.getEmployeeId());
        etFullName.setText(currentEmployee.getFullName());
        etEmail.setText(currentEmployee.getEmail());
        etPhoneNumber.setText(currentEmployee.getPhoneNumber());
        etAddress.setText(currentEmployee.getAddress());
        tvDepartment.setText("Department: " + (currentEmployee.getDepartmentName() != null ? 
            currentEmployee.getDepartmentName() : "Not Assigned"));
        tvBranch.setText("Branch: " + (currentEmployee.getBranchName() != null ? 
            currentEmployee.getBranchName() : "Not Assigned"));
        tvJoinDate.setText("Joined: " + (currentEmployee.getDateCreated() != null ? 
            currentEmployee.getDateCreated() : "Unknown"));
        
        // Load preferences
        loadUserPreferences();
        
        // Load profile photo if available
        if (currentEmployee.getProfilePhotoUrl() != null && !currentEmployee.getProfilePhotoUrl().isEmpty()) {
            // TODO: Load image from URL using Glide or similar
            Log.d(TAG, "Profile photo URL: " + currentEmployee.getProfilePhotoUrl());
        }
    }
    
    private void loadUserPreferences() {
        // Load user preferences from SharedPreferences
        switchNotifications.setChecked(sharedPreferences.getBoolean("notifications_enabled", true));
        switchLocationTracking.setChecked(sharedPreferences.getBoolean("location_tracking_enabled", true));
        switchBiometricLogin.setChecked(sharedPreferences.getBoolean("biometric_login_enabled", false));
    }
    
    private void updateProfile() {
        if (!validateInput()) {
            return;
        }
        
        // Create updated employee object
        Employee updatedEmployee = new Employee();
        updatedEmployee.setEmployeeId(employeeId);
        updatedEmployee.setFullName(etFullName.getText().toString().trim());
        updatedEmployee.setEmail(etEmail.getText().toString().trim());
        updatedEmployee.setPhoneNumber(etPhoneNumber.getText().toString().trim());
        updatedEmployee.setAddress(etAddress.getText().toString().trim());
        
        if (!newProfilePhotoBase64.isEmpty()) {
            updatedEmployee.setProfilePhotoBase64(newProfilePhotoBase64);
        }
        
        progressBar.setVisibility(View.VISIBLE);
        btnUpdateProfile.setEnabled(false);
        
        Call<ApiResponse<String>> call = apiService.updateEmployeeProfile(updatedEmployee);
        call.enqueue(new Callback<ApiResponse<String>>() {
            @Override
            public void onResponse(Call<ApiResponse<String>> call, Response<ApiResponse<String>> response) {
                progressBar.setVisibility(View.GONE);
                btnUpdateProfile.setEnabled(true);
                
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<String> apiResponse = response.body();
                    if (apiResponse.isSuccess()) {
                        Toast.makeText(EmployeeProfileActivity.this, 
                            "Profile updated successfully", Toast.LENGTH_SHORT).show();
                        
                        // Save user preferences
                        saveUserPreferences();
                        
                        // Reload profile data
                        loadEmployeeProfile();
                    } else {
                        Toast.makeText(EmployeeProfileActivity.this, 
                            apiResponse.getMessage(), Toast.LENGTH_LONG).show();
                    }
                } else {
                    Toast.makeText(EmployeeProfileActivity.this, 
                        "Failed to update profile", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<String>> call, Throwable t) {
                progressBar.setVisibility(View.GONE);
                btnUpdateProfile.setEnabled(true);
                Toast.makeText(EmployeeProfileActivity.this, 
                    "Network error: " + t.getMessage(), Toast.LENGTH_LONG).show();
                Log.e(TAG, "Failed to update profile", t);
            }
        });
    }
    
    private boolean validateInput() {
        String fullName = etFullName.getText().toString().trim();
        String email = etEmail.getText().toString().trim();
        String phoneNumber = etPhoneNumber.getText().toString().trim();
        
        if (fullName.isEmpty()) {
            etFullName.setError("Full name is required");
            etFullName.requestFocus();
            return false;
        }
        
        if (email.isEmpty()) {
            etEmail.setError("Email is required");
            etEmail.requestFocus();
            return false;
        }
        
        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            etEmail.setError("Please enter a valid email address");
            etEmail.requestFocus();
            return false;
        }
        
        if (phoneNumber.isEmpty()) {
            etPhoneNumber.setError("Phone number is required");
            etPhoneNumber.requestFocus();
            return false;
        }
        
        return true;
    }
    
    private void saveUserPreferences() {
        SharedPreferences.Editor editor = sharedPreferences.edit();
        editor.putBoolean("notifications_enabled", switchNotifications.isChecked());
        editor.putBoolean("location_tracking_enabled", switchLocationTracking.isChecked());
        editor.putBoolean("biometric_login_enabled", switchBiometricLogin.isChecked());
        editor.apply();
    }
    
    private void showPhotoOptions() {
        String[] options = {"Take Photo", "Choose from Gallery", "Cancel"};
        
        new AlertDialog.Builder(this)
            .setTitle("Profile Photo")
            .setItems(options, (dialog, which) -> {
                switch (which) {
                    case 0:
                        openCamera();
                        break;
                    case 1:
                        openGallery();
                        break;
                    case 2:
                        dialog.dismiss();
                        break;
                }
            })
            .show();
    }
    
    private void openCamera() {
        Intent cameraIntent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
        if (cameraIntent.resolveActivity(getPackageManager()) != null) {
            startActivityForResult(cameraIntent, CAMERA_REQUEST_CODE);
        } else {
            Toast.makeText(this, "Camera not available", Toast.LENGTH_SHORT).show();
        }
    }
    
    private void openGallery() {
        Intent galleryIntent = new Intent(Intent.ACTION_PICK, MediaStore.Images.Media.EXTERNAL_CONTENT_URI);
        startActivityForResult(galleryIntent, GALLERY_REQUEST_CODE);
    }
    
    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        
        if (resultCode == RESULT_OK) {
            Bitmap bitmap = null;
            
            if (requestCode == CAMERA_REQUEST_CODE && data != null && data.getExtras() != null) {
                bitmap = (Bitmap) data.getExtras().get("data");
            } else if (requestCode == GALLERY_REQUEST_CODE && data != null) {
                Uri imageUri = data.getData();
                try {
                    bitmap = MediaStore.Images.Media.getBitmap(this.getContentResolver(), imageUri);
                } catch (IOException e) {
                    Log.e(TAG, "Error loading image from gallery", e);
                    Toast.makeText(this, "Error loading image", Toast.LENGTH_SHORT).show();
                    return;
                }
            }
            
            if (bitmap != null) {
                // Resize bitmap if too large
                bitmap = resizeBitmap(bitmap, 500, 500);
                
                // Set image to ImageView
                ivProfilePhoto.setImageBitmap(bitmap);
                
                // Convert to base64
                newProfilePhotoBase64 = bitmapToBase64(bitmap);
                
                Toast.makeText(this, "Photo updated. Save profile to apply changes.", Toast.LENGTH_SHORT).show();
            }
        }
    }
    
    private Bitmap resizeBitmap(Bitmap bitmap, int maxWidth, int maxHeight) {
        int width = bitmap.getWidth();
        int height = bitmap.getHeight();
        
        float scaleWidth = ((float) maxWidth) / width;
        float scaleHeight = ((float) maxHeight) / height;
        float scale = Math.min(scaleWidth, scaleHeight);
        
        if (scale < 1.0f) {
            android.graphics.Matrix matrix = new android.graphics.Matrix();
            matrix.postScale(scale, scale);
            return Bitmap.createBitmap(bitmap, 0, 0, width, height, matrix, false);
        }
        
        return bitmap;
    }
    
    private String bitmapToBase64(Bitmap bitmap) {
        ByteArrayOutputStream byteArrayOutputStream = new ByteArrayOutputStream();
        bitmap.compress(Bitmap.CompressFormat.JPEG, 80, byteArrayOutputStream);
        byte[] byteArray = byteArrayOutputStream.toByteArray();
        return "data:image/jpeg;base64," + Base64.encodeToString(byteArray, Base64.DEFAULT);
    }
    
    private void openChangePinActivity() {
        Intent intent = new Intent(this, ChangePinActivity.class);
        intent.putExtra("employee_id", employeeId);
        intent.putExtra("is_first_login", false);
        startActivity(intent);
    }
    
    private void showLogoutConfirmation() {
        new AlertDialog.Builder(this)
            .setTitle("Logout")
            .setMessage("Are you sure you want to logout?")
            .setPositiveButton("Yes", (dialog, which) -> logout())
            .setNegativeButton("No", null)
            .show();
    }
    
    private void logout() {
        // Clear all preferences
        SharedPreferences.Editor editor = sharedPreferences.edit();
        editor.clear();
        editor.apply();
        
        // Return to login activity
        Intent intent = new Intent(this, LoginActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        finish();
        
        Toast.makeText(this, "Logged out successfully", Toast.LENGTH_SHORT).show();
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            finish();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
}
