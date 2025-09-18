package com.signsync.attendance.fragment.employee;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import com.google.android.material.button.MaterialButton;
import com.signsync.attendance.R;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.utils.SharedPreferencesManager;

public class EmployeeProfileFragment extends Fragment {
    
    private ImageView profileImageView;
    private TextView nameTextView;
    private TextView employeeIdTextView;
    private TextView departmentTextView;
    private TextView emailTextView;
    private TextView phoneTextView;
    private MaterialButton editProfileButton;
    private MaterialButton changePasswordButton;
    private MaterialButton logoutButton;
    
    private SharedPreferencesManager prefsManager;
    
    public static EmployeeProfileFragment newInstance() {
        return new EmployeeProfileFragment();
    }
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_employee_profile, container, false);
        
        initViews(view);
        setupClickListeners();
        loadProfileData();
        
        return view;
    }
    
    private void initViews(View view) {
        profileImageView = view.findViewById(R.id.profileImageView);
        nameTextView = view.findViewById(R.id.nameTextView);
        employeeIdTextView = view.findViewById(R.id.employeeIdTextView);
        departmentTextView = view.findViewById(R.id.departmentTextView);
        emailTextView = view.findViewById(R.id.emailTextView);
        phoneTextView = view.findViewById(R.id.phoneTextView);
        editProfileButton = view.findViewById(R.id.editProfileButton);
        changePasswordButton = view.findViewById(R.id.changePasswordButton);
        logoutButton = view.findViewById(R.id.logoutButton);
        
        prefsManager = new SharedPreferencesManager(getContext());
    }
    
    private void setupClickListeners() {
        editProfileButton.setOnClickListener(v -> editProfile());
        changePasswordButton.setOnClickListener(v -> changePassword());
        logoutButton.setOnClickListener(v -> logout());
    }
    
    private void loadProfileData() {
        // Load profile data from SharedPreferences or API
        Employee employee = prefsManager.getCurrentEmployee();
        if (employee != null) {
            nameTextView.setText(employee.getName());
            employeeIdTextView.setText("ID: " + employee.getEmployeeId());
            departmentTextView.setText(employee.getDepartment());
            emailTextView.setText(employee.getEmail());
            phoneTextView.setText(employee.getPhone());
        }
    }
    
    private void editProfile() {
        // Show edit profile dialog or navigate to edit activity
    }
    
    private void changePassword() {
        // Show change password dialog
    }
    
    private void logout() {
        // Logout user and return to login screen
        prefsManager.logout();
        getActivity().finish();
    }
}
