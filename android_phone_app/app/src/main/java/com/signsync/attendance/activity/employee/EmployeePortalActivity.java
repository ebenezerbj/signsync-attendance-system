package com.signsync.attendance.activity.employee;

import android.content.Intent;
import android.os.Bundle;
import android.view.MenuItem;
import android.view.View;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.fragment.app.Fragment;
import androidx.fragment.app.FragmentActivity;
import androidx.viewpager2.adapter.FragmentStateAdapter;
import androidx.viewpager2.widget.ViewPager2;
import com.google.android.material.tabs.TabLayout;
import com.google.android.material.tabs.TabLayoutMediator;
import com.signsync.attendance.R;
import com.signsync.attendance.fragment.employee.*;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.response.EmployeeResponse;
import com.signsync.attendance.utils.SessionManager;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class EmployeePortalActivity extends AppCompatActivity {
    
    private SessionManager sessionManager;
    private AttendanceApiService apiService;
    
    // UI Components
    private Toolbar toolbar;
    private TextView welcomeText, employeeIdText;
    private TabLayout tabLayout;
    private ViewPager2 viewPager;
    
    // Data
    private Employee currentEmployee;
    private String employeeId;
    
    // Tab titles
    private final String[] TAB_TITLES = {
            "Dashboard", 
            "Attendance", 
            "Leave Requests", 
            "Corrections",
            "Profile"
    };
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_employee_portal);
        
        initializeComponents();
        setupToolbar();
        setupTabs();
        loadEmployeeData();
    }
    
    private void initializeComponents() {
        sessionManager = new SessionManager(this);
        apiService = ApiClient.getApiService();
        
        toolbar = findViewById(R.id.toolbar);
        welcomeText = findViewById(R.id.welcomeText);
        employeeIdText = findViewById(R.id.employeeIdText);
        tabLayout = findViewById(R.id.tabLayout);
        viewPager = findViewById(R.id.viewPager);
        
        employeeId = sessionManager.getEmployeeId();
        if (employeeId == null) {
            Toast.makeText(this, "Employee ID not found. Please login again.", Toast.LENGTH_LONG).show();
            finish();
            return;
        }
    }
    
    private void setupToolbar() {
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("Employee Portal");
        }
    }
    
    private void setupTabs() {
        EmployeePortalPagerAdapter adapter = new EmployeePortalPagerAdapter(this);
        viewPager.setAdapter(adapter);
        
        new TabLayoutMediator(tabLayout, viewPager, (tab, position) -> {
            tab.setText(TAB_TITLES[position]);
            
            // Set icons for tabs
            switch (position) {
                case 0:
                    tab.setIcon(R.drawable.ic_dashboard);
                    break;
                case 1:
                    tab.setIcon(R.drawable.ic_schedule);
                    break;
                case 2:
                    tab.setIcon(R.drawable.ic_calendar);
                    break;
                case 3:
                    tab.setIcon(R.drawable.ic_edit);
                    break;
                case 4:
                    tab.setIcon(R.drawable.ic_person);
                    break;
            }
        }).attach();
    }
    
    private void loadEmployeeData() {
        showLoading(true);
        
        Call<EmployeeResponse> call = apiService.getEmployeeDetails(employeeId);
        call.enqueue(new Callback<EmployeeResponse>() {
            @Override
            public void onResponse(Call<EmployeeResponse> call, Response<EmployeeResponse> response) {
                showLoading(false);
                
                if (response.isSuccessful() && response.body() != null) {
                    EmployeeResponse employeeResponse = response.body();
                    if (employeeResponse.isSuccess()) {
                        currentEmployee = employeeResponse.getEmployee();
                        updateEmployeeInfo();
                    } else {
                        showError("Failed to load employee data: " + employeeResponse.getMessage());
                    }
                } else {
                    showError("Failed to load employee data. Please try again.");
                }
            }
            
            @Override
            public void onFailure(Call<EmployeeResponse> call, Throwable t) {
                showLoading(false);
                showError("Network error: " + t.getMessage());
            }
        });
    }
    
    private void updateEmployeeInfo() {
        if (currentEmployee != null) {
            String welcomeMessage = "Welcome, " + currentEmployee.getName();
            welcomeText.setText(welcomeMessage);
            employeeIdText.setText("Employee ID: " + currentEmployee.getEmployeeId());
            
            // Update session with latest employee data
            sessionManager.updateUserInfo(
                currentEmployee.getName(),
                currentEmployee.getEmail(),
                currentEmployee.getRole()
            );
        }
    }
    
    private void showLoading(boolean show) {
        // You can implement a loading indicator here
        // For now, we'll just disable/enable the ViewPager
        viewPager.setUserInputEnabled(!show);
    }
    
    private void showError(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            onBackPressed();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
    
    // Getter methods for fragments to access employee data
    public Employee getCurrentEmployee() {
        return currentEmployee;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    public AttendanceApiService getApiService() {
        return apiService;
    }
    
    // Method to refresh employee data from fragments
    public void refreshEmployeeData() {
        loadEmployeeData();
    }
    
    /**
     * ViewPager2 Adapter for Employee Portal tabs
     */
    private class EmployeePortalPagerAdapter extends FragmentStateAdapter {
        
        public EmployeePortalPagerAdapter(FragmentActivity fragmentActivity) {
            super(fragmentActivity);
        }
        
        @Override
        public Fragment createFragment(int position) {
            switch (position) {
                case 0:
                    return new EmployeeDashboardFragment();
                case 1:
                    return new EmployeeAttendanceFragment();
                case 2:
                    return new EmployeeLeaveFragment();
                case 3:
                    return new EmployeeCorrectionsFragment();
                case 4:
                    return new EmployeeProfileFragment();
                default:
                    return new EmployeeDashboardFragment();
            }
        }
        
        @Override
        public int getItemCount() {
            return TAB_TITLES.length;
        }
    }
}
