package com.signsync.attendance.phone;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.Menu;
import android.view.MenuItem;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;
import androidx.cardview.widget.CardView;

import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.signsync.attendance.R;

public class DashboardActivity extends AppCompatActivity {
    
    private TextView tvWelcome;
    private TextView tvQuickStats;
    private CardView cardClockInOut;
    private CardView cardEmployees;
    private CardView cardReports;
    private CardView cardAdmin;
    private BottomNavigationView bottomNav;
    
    private String employeeName;
    private String userType;
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        int layoutId = getResources().getIdentifier("activity_dashboard", "layout", getPackageName());
        if (layoutId != 0) {
            setContentView(layoutId);
        } else {
            // Fallback to a simple existing layout to avoid missing resource
            setContentView(com.signsync.attendance.R.layout.activity_main);
        }
        
        loadUserData();
        initViews();
        setupClickListeners();
        updateUIBasedOnUserType();
        loadQuickStats();
    }
    
    private void loadUserData() {
        SharedPreferences prefs = getSharedPreferences("SignSyncPrefs", MODE_PRIVATE);
        employeeName = prefs.getString("employeeName", "User");
        userType = prefs.getString("userType", "employee");
    }
    
    private void initViews() {
    int tvWelcomeId = getResources().getIdentifier("tv_welcome", "id", getPackageName());
    int tvQuickStatsId = getResources().getIdentifier("tv_quick_stats", "id", getPackageName());
    int cardClockInOutId = getResources().getIdentifier("card_clock_in_out", "id", getPackageName());
    int cardEmployeesId = getResources().getIdentifier("card_employees", "id", getPackageName());
    int cardReportsId = getResources().getIdentifier("card_reports", "id", getPackageName());
    int cardAdminId = getResources().getIdentifier("card_admin", "id", getPackageName());
    int bottomNavId = getResources().getIdentifier("bottom_navigation", "id", getPackageName());

    tvWelcome = tvWelcomeId != 0 ? findViewById(tvWelcomeId) : new TextView(this);
    tvQuickStats = tvQuickStatsId != 0 ? findViewById(tvQuickStatsId) : new TextView(this);
    cardClockInOut = cardClockInOutId != 0 ? findViewById(cardClockInOutId) : new CardView(this);
    cardEmployees = cardEmployeesId != 0 ? findViewById(cardEmployeesId) : new CardView(this);
    cardReports = cardReportsId != 0 ? findViewById(cardReportsId) : new CardView(this);
    cardAdmin = cardAdminId != 0 ? findViewById(cardAdminId) : new CardView(this);
    bottomNav = bottomNavId != 0 ? findViewById(bottomNavId) : new com.google.android.material.bottomnavigation.BottomNavigationView(this);
        
        tvWelcome.setText("Welcome, " + employeeName);
    }
    
    private void setupClickListeners() {
        cardClockInOut.setOnClickListener(v -> 
            startActivity(new Intent(this, ClockInOutActivity.class)));
        
        cardEmployees.setOnClickListener(v -> 
            // TODO: Replace with actual EmployeeManagementActivity when available
            startActivity(new Intent(this, ClockInOutActivity.class)));
        
        cardReports.setOnClickListener(v -> 
            // TODO: Replace with actual ReportsActivity when available
            startActivity(new Intent(this, ClockInOutActivity.class)));
        
        cardAdmin.setOnClickListener(v -> 
            // TODO: Replace with actual AdminActivity when available
            startActivity(new Intent(this, ClockInOutActivity.class)));
        
        // Bottom navigation
        bottomNav.setOnItemSelectedListener(item -> {
            int itemId = item.getItemId();
            int navDashboardId = getResources().getIdentifier("nav_dashboard", "id", getPackageName());
            int navAttendanceId = getResources().getIdentifier("nav_attendance", "id", getPackageName());
            int navReportsId = getResources().getIdentifier("nav_reports", "id", getPackageName());
            int navSettingsId = getResources().getIdentifier("nav_settings", "id", getPackageName());

            if (itemId == navDashboardId) {
                // Already on dashboard
                return true;
            } else if (itemId == navAttendanceId) {
                startActivity(new Intent(this, ClockInOutActivity.class));
                return true;
            } else if (itemId == navReportsId) {
                // TODO: Replace with actual ReportsActivity when available
                startActivity(new Intent(this, ClockInOutActivity.class));
                return true;
            } else if (itemId == navSettingsId) {
                // TODO: Replace with actual SettingsActivity when available
                startActivity(new Intent(this, ClockInOutActivity.class));
                return true;
            }
            return false;
        });
    }
    
    private void updateUIBasedOnUserType() {
        switch (userType) {
            case "employee":
                cardEmployees.setVisibility(android.view.View.GONE);
                cardAdmin.setVisibility(android.view.View.GONE);
                break;
            case "supervisor":
                cardAdmin.setVisibility(android.view.View.GONE);
                break;
            case "admin":
                // Admin can see all cards
                break;
        }
    }
    
    private void loadQuickStats() {
        // TODO: Load today's stats from API
        tvQuickStats.setText("Today: Clock In: 8:00 AM | Hours: 8.5 | Status: Working");
    }
    
    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.dashboard_menu, menu);
        return true;
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        int itemId = item.getItemId();
        if (itemId == R.id.action_sync) {
            // Trigger sync
            syncData();
            return true;
        } else if (itemId == R.id.action_logout) {
            logout();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
    
    private void syncData() {
        // TODO: Implement sync with server
    }
    
    private void logout() {
        SharedPreferences prefs = getSharedPreferences("SignSyncPrefs", MODE_PRIVATE);
        prefs.edit().clear().apply();
        
        Intent intent = new Intent(this, LoginActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        finish();
    }
}
