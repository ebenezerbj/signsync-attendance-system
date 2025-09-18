package com.signsync.attendance.activity;

import android.content.Intent;
import android.os.Bundle;
import android.view.MenuItem;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.cardview.widget.CardView;
import com.google.android.material.chip.Chip;
import com.signsync.attendance.R;
import com.signsync.attendance.activity.employee.EmployeeManagementActivity;
import com.signsync.attendance.activity.system.SystemSettingsActivity;
import com.signsync.attendance.activity.reports.ReportsActivity;
import com.signsync.attendance.activity.schedule.ScheduleManagementActivity;
import com.signsync.attendance.activity.security.SecurityAuditActivity;
import com.signsync.attendance.activity.notifications.NotificationCenterActivity;
import com.signsync.attendance.utils.SessionManager;

public class AdminPanelActivity extends AppCompatActivity {
    
    private SessionManager sessionManager;
    private TextView userNameText, userRoleText;
    private Chip adminStatusChip;
    
    // Admin Function Cards
    private CardView employeeManagementCard, systemSettingsCard, reportsCard;
    private CardView scheduleManagementCard, securityAuditCard, notificationCenterCard;
    private CardView dataBackupCard, systemMonitoringCard, integrationManagementCard;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_admin_panel);
        
        initializeViews();
        setupToolbar();
        setupUserInfo();
        setupAdminFunctions();
        checkAdminPermissions();
    }
    
    private void initializeViews() {
        sessionManager = new SessionManager(this);
        
        userNameText = findViewById(R.id.userNameText);
        userRoleText = findViewById(R.id.userRoleText);
        adminStatusChip = findViewById(R.id.adminStatusChip);
        
        // Initialize admin function cards
        employeeManagementCard = findViewById(R.id.employeeManagementCard);
        systemSettingsCard = findViewById(R.id.systemSettingsCard);
        reportsCard = findViewById(R.id.reportsCard);
        scheduleManagementCard = findViewById(R.id.scheduleManagementCard);
        securityAuditCard = findViewById(R.id.securityAuditCard);
        notificationCenterCard = findViewById(R.id.notificationCenterCard);
        dataBackupCard = findViewById(R.id.dataBackupCard);
        systemMonitoringCard = findViewById(R.id.systemMonitoringCard);
        integrationManagementCard = findViewById(R.id.integrationManagementCard);
    }
    
    private void setupToolbar() {
        Toolbar toolbar = findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("Administration Panel");
        }
    }
    
    private void setupUserInfo() {
        String userName = sessionManager.getUserName();
        String userRole = sessionManager.getUserRole();
        
        userNameText.setText(userName != null ? userName : "Administrator");
        userRoleText.setText(userRole != null ? userRole : "System Admin");
        
        // Set admin status chip
        if ("admin".equalsIgnoreCase(userRole) || "super_admin".equalsIgnoreCase(userRole)) {
            adminStatusChip.setText("Admin Access");
            adminStatusChip.setChipBackgroundColorResource(R.color.success_green);
        } else {
            adminStatusChip.setText("Limited Access");
            adminStatusChip.setChipBackgroundColorResource(R.color.warning_orange);
        }
    }
    
    private void setupAdminFunctions() {
        // Employee Management
        employeeManagementCard.setOnClickListener(v -> {
            startActivity(new Intent(this, EmployeeManagementActivity.class));
        });
        
        // System Settings
        systemSettingsCard.setOnClickListener(v -> {
            startActivity(new Intent(this, SystemSettingsActivity.class));
        });
        
        // Reports and Analytics
        reportsCard.setOnClickListener(v -> {
            startActivity(new Intent(this, ReportsActivity.class));
        });
        
        // Schedule Management
        scheduleManagementCard.setOnClickListener(v -> {
            startActivity(new Intent(this, ScheduleManagementActivity.class));
        });
        
        // Security and Audit
        securityAuditCard.setOnClickListener(v -> {
            startActivity(new Intent(this, SecurityAuditActivity.class));
        });
        
        // Notification Center
        notificationCenterCard.setOnClickListener(v -> {
            startActivity(new Intent(this, NotificationCenterActivity.class));
        });
        
        // Data Backup and Recovery
        dataBackupCard.setOnClickListener(v -> {
            // TODO: Implement data backup functionality
            showFeatureComingSoon("Data Backup & Recovery");
        });
        
        // System Monitoring
        systemMonitoringCard.setOnClickListener(v -> {
            // TODO: Implement system monitoring
            showFeatureComingSoon("System Monitoring");
        });
        
        // Integration Management
        integrationManagementCard.setOnClickListener(v -> {
            // TODO: Implement integration management
            showFeatureComingSoon("Integration Management");
        });
    }
    
    private void checkAdminPermissions() {
        String userRole = sessionManager.getUserRole();
        boolean isAdmin = "admin".equalsIgnoreCase(userRole) || "super_admin".equalsIgnoreCase(userRole);
        
        if (!isAdmin) {
            // Disable certain admin-only functions
            systemSettingsCard.setEnabled(false);
            systemSettingsCard.setAlpha(0.5f);
            
            securityAuditCard.setEnabled(false);
            securityAuditCard.setAlpha(0.5f);
            
            dataBackupCard.setEnabled(false);
            dataBackupCard.setAlpha(0.5f);
            
            systemMonitoringCard.setEnabled(false);
            systemMonitoringCard.setAlpha(0.5f);
        }
    }
    
    private void showFeatureComingSoon(String featureName) {
        new androidx.appcompat.app.AlertDialog.Builder(this)
                .setTitle("Feature Coming Soon")
                .setMessage(featureName + " functionality will be available in the next update.")
                .setPositiveButton("OK", null)
                .show();
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            onBackPressed();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
    
    @Override
    protected void onResume() {
        super.onResume();
        // Refresh user info and permissions when returning to admin panel
        setupUserInfo();
        checkAdminPermissions();
    }
}
