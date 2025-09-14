package com.signsync.attendance.activity.employee;

import android.os.Bundle;
import android.widget.TextView;
import android.widget.Button;
import androidx.appcompat.app.AppCompatActivity;
import com.signsync.attendance.R;

public class SimpleEmployeePortalActivity extends AppCompatActivity {
    
    private TextView welcomeText;
    private TextView employeeIdText;
    private Button clockInButton;
    private Button clockOutButton;
    private Button viewAttendanceButton;
    private Button profileButton;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_simple_employee_portal);
        
        initViews();
        setupClickListeners();
        loadEmployeeData();
    }
    
    private void initViews() {
        welcomeText = findViewById(R.id.welcomeText);
        employeeIdText = findViewById(R.id.employeeIdText);
        clockInButton = findViewById(R.id.clockInButton);
        clockOutButton = findViewById(R.id.clockOutButton);
        viewAttendanceButton = findViewById(R.id.viewAttendanceButton);
        profileButton = findViewById(R.id.profileButton);
    }
    
    private void setupClickListeners() {
        clockInButton.setOnClickListener(v -> {
            // Clock in functionality
            clockInButton.setText("Clocked In ✓");
            clockInButton.setEnabled(false);
            clockOutButton.setEnabled(true);
        });
        
        clockOutButton.setOnClickListener(v -> {
            // Clock out functionality
            clockOutButton.setText("Clocked Out ✓");
            clockOutButton.setEnabled(false);
            clockInButton.setEnabled(true);
            clockInButton.setText("Clock In");
        });
        
        viewAttendanceButton.setOnClickListener(v -> {
            // View attendance functionality
            // Could open attendance history
        });
        
        profileButton.setOnClickListener(v -> {
            // Profile functionality
            // Could open profile settings
        });
    }
    
    private void loadEmployeeData() {
        // For now, show sample data
        welcomeText.setText("Welcome, John Doe");
        employeeIdText.setText("Employee ID: EMP001");
    }
}
