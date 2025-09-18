package com.signsync.attendance.fragment;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.card.MaterialCardView;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textview.MaterialTextView;
import com.signsync.attendance.R;
import com.signsync.attendance.activity.ChangePasswordActivity;
import com.signsync.attendance.activity.EmergencyContactsActivity;
import com.signsync.attendance.activity.NotificationSettingsActivity;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.network.ApiResponse;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.NetworkClient;
import com.signsync.attendance.utils.SharedPreferencesManager;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class ProfileFragment extends Fragment {
    
    private MaterialCardView cardPersonalInfo;
    private MaterialCardView cardWorkInfo;
    private MaterialCardView cardContactInfo;
    private MaterialCardView cardSettings;
    
    // Personal Info
    private MaterialTextView textViewEmployeeId;
    private MaterialTextView textViewFullName;
    private MaterialTextView textViewEmail;
    private MaterialTextView textViewPhone;
    private MaterialTextView textViewDateOfBirth;
    private MaterialTextView textViewAddress;
    
    // Work Info
    private MaterialTextView textViewDepartment;
    private MaterialTextView textViewPosition;
    private MaterialTextView textViewJoinDate;
    private MaterialTextView textViewEmploymentType;
    private MaterialTextView textViewShift;
    private MaterialTextView textViewBranch;
    private MaterialTextView textViewReportingManager;
    
    // Settings
    private MaterialButton buttonChangePassword;
    private MaterialButton buttonEmergencyContacts;
    private MaterialButton buttonNotificationSettings;
    private MaterialButton buttonEditProfile;
    
    private AttendanceApiService apiService;
    private SharedPreferencesManager prefsManager;
    private Employee currentEmployee;
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_profile, container, false);
        
        initializeViews(view);
        setupClickListeners();
        
        apiService = NetworkClient.getRetrofitInstance().create(AttendanceApiService.class);
        prefsManager = new SharedPreferencesManager(requireContext());
        
        loadEmployeeProfile();
        
        return view;
    }
    
    private void initializeViews(View view) {
        cardPersonalInfo = view.findViewById(R.id.cardPersonalInfo);
        cardWorkInfo = view.findViewById(R.id.cardWorkInfo);
        cardContactInfo = view.findViewById(R.id.cardContactInfo);
        cardSettings = view.findViewById(R.id.cardSettings);
        
        // Personal Info
        textViewEmployeeId = view.findViewById(R.id.textViewEmployeeId);
        textViewFullName = view.findViewById(R.id.textViewFullName);
        textViewEmail = view.findViewById(R.id.textViewEmail);
        textViewPhone = view.findViewById(R.id.textViewPhone);
        textViewDateOfBirth = view.findViewById(R.id.textViewDateOfBirth);
        textViewAddress = view.findViewById(R.id.textViewAddress);
        
        // Work Info
        textViewDepartment = view.findViewById(R.id.textViewDepartment);
        textViewPosition = view.findViewById(R.id.textViewPosition);
        textViewJoinDate = view.findViewById(R.id.textViewJoinDate);
        textViewEmploymentType = view.findViewById(R.id.textViewEmploymentType);
        textViewShift = view.findViewById(R.id.textViewShift);
        textViewBranch = view.findViewById(R.id.textViewBranch);
        textViewReportingManager = view.findViewById(R.id.textViewReportingManager);
        
        // Settings
        buttonChangePassword = view.findViewById(R.id.buttonChangePassword);
        buttonEmergencyContacts = view.findViewById(R.id.buttonEmergencyContacts);
        buttonNotificationSettings = view.findViewById(R.id.buttonNotificationSettings);
        buttonEditProfile = view.findViewById(R.id.buttonEditProfile);
    }
    
    private void setupClickListeners() {
        buttonChangePassword.setOnClickListener(v -> {
            Intent intent = new Intent(getContext(), ChangePasswordActivity.class);
            startActivity(intent);
        });
        
        buttonEmergencyContacts.setOnClickListener(v -> {
            Intent intent = new Intent(getContext(), EmergencyContactsActivity.class);
            startActivity(intent);
        });
        
        buttonNotificationSettings.setOnClickListener(v -> {
            Intent intent = new Intent(getContext(), NotificationSettingsActivity.class);
            startActivity(intent);
        });
        
        buttonEditProfile.setOnClickListener(v -> showEditProfileDialog());
    }
    
    private void loadEmployeeProfile() {
        String employeeId = prefsManager.getEmployeeId();
        if (employeeId == null) {
            Toast.makeText(getContext(), "Employee ID not found", Toast.LENGTH_SHORT).show();
            return;
        }
        
        Call<ApiResponse<Employee>> call = apiService.getEmployeeProfile(employeeId);
        
        call.enqueue(new Callback<ApiResponse<Employee>>() {
            @Override
            public void onResponse(Call<ApiResponse<Employee>> call, Response<ApiResponse<Employee>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<Employee> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess() && apiResponse.getData() != null) {
                        currentEmployee = apiResponse.getData();
                        updateProfileViews();
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to load profile", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<Employee>> call, Throwable t) {
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    private void updateProfileViews() {
        if (currentEmployee == null) return;
        
        // Personal Info
        textViewEmployeeId.setText(currentEmployee.getEmployeeId());
        textViewFullName.setText(currentEmployee.getFullName());
        textViewEmail.setText(currentEmployee.getEmail() != null ? currentEmployee.getEmail() : "Not provided");
        textViewPhone.setText(currentEmployee.getPhone() != null ? currentEmployee.getPhone() : "Not provided");
        textViewDateOfBirth.setText(currentEmployee.getFormattedDateOfBirth());
        textViewAddress.setText(currentEmployee.getAddress() != null ? currentEmployee.getAddress() : "Not provided");
        
        // Work Info
        textViewDepartment.setText(currentEmployee.getDepartment() != null ? currentEmployee.getDepartment() : "Not assigned");
        textViewPosition.setText(currentEmployee.getPosition() != null ? currentEmployee.getPosition() : "Not assigned");
        textViewJoinDate.setText(currentEmployee.getFormattedHireDate());
        textViewEmploymentType.setText(currentEmployee.getEmploymentType() != null ? currentEmployee.getEmploymentType() : "Not specified");
        textViewShift.setText(currentEmployee.getShiftName() != null ? currentEmployee.getShiftName() : "Not assigned");
        textViewBranch.setText(currentEmployee.getBranchName() != null ? currentEmployee.getBranchName() : "Not assigned");
        textViewReportingManager.setText(currentEmployee.getReportingManager() != null ? currentEmployee.getReportingManager() : "Not assigned");
    }
    
    private void showEditProfileDialog() {
        if (getContext() == null || currentEmployee == null) return;
        
        androidx.appcompat.app.AlertDialog.Builder builder = new androidx.appcompat.app.AlertDialog.Builder(getContext());
        
        View dialogView = LayoutInflater.from(getContext()).inflate(R.layout.dialog_edit_profile, null);
        
        TextInputEditText editTextEmail = dialogView.findViewById(R.id.editTextEmail);
        TextInputEditText editTextPhone = dialogView.findViewById(R.id.editTextPhone);
        TextInputEditText editTextAddress = dialogView.findViewById(R.id.editTextAddress);
        TextInputEditText editTextEmergencyContact = dialogView.findViewById(R.id.editTextEmergencyContact);
        
        // Pre-fill current values
        editTextEmail.setText(currentEmployee.getEmail());
        editTextPhone.setText(currentEmployee.getPhone());
        editTextAddress.setText(currentEmployee.getAddress());
        editTextEmergencyContact.setText(currentEmployee.getEmergencyContact());
        
        builder.setView(dialogView)
               .setTitle("Edit Profile")
               .setPositiveButton("Save", (dialog, which) -> {
                   String email = editTextEmail.getText().toString().trim();
                   String phone = editTextPhone.getText().toString().trim();
                   String address = editTextAddress.getText().toString().trim();
                   String emergencyContact = editTextEmergencyContact.getText().toString().trim();
                   
                   updateEmployeeProfile(email, phone, address, emergencyContact);
               })
               .setNegativeButton("Cancel", null)
               .create()
               .show();
    }
    
    private void updateEmployeeProfile(String email, String phone, String address, String emergencyContact) {
        String employeeId = prefsManager.getEmployeeId();
        if (employeeId == null) return;
        
        Employee updatedEmployee = new Employee();
        updatedEmployee.setEmployeeId(employeeId);
        updatedEmployee.setEmail(email);
        updatedEmployee.setPhone(phone);
        updatedEmployee.setAddress(address);
        updatedEmployee.setEmergencyContact(emergencyContact);
        
    Call<ApiResponse<String>> call = apiService.updateEmployeeProfile(updatedEmployee);
        
        call.enqueue(new Callback<ApiResponse<String>>() {
            @Override
            public void onResponse(Call<ApiResponse<String>> call, Response<ApiResponse<String>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<String> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess()) {
                        Toast.makeText(getContext(), "Profile updated successfully", Toast.LENGTH_SHORT).show();
                        loadEmployeeProfile(); // Refresh profile data
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to update profile", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<String>> call, Throwable t) {
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    public void refreshData() {
        loadEmployeeProfile();
    }
    
    @Override
    public void onResume() {
        super.onResume();
        loadEmployeeProfile();
    }
}
