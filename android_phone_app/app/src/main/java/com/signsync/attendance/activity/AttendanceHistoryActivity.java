package com.signsync.attendance.activity;

import android.app.DatePickerDialog;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.util.Log;
import android.view.MenuItem;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.ListView;
import android.widget.ProgressBar;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;

import com.signsync.attendance.R;
import com.signsync.attendance.adapter.AttendanceHistoryAdapter;
import com.signsync.attendance.model.AttendanceRecord;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.ApiResponse;
import com.signsync.attendance.network.AttendanceApiService;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class AttendanceHistoryActivity extends AppCompatActivity {
    
    private static final String TAG = "AttendanceHistory";
    private static final String PREFS_NAME = "AttendancePrefs";
    private static final String KEY_EMPLOYEE_ID = "employee_id";
    
    // UI Components
    private Toolbar toolbar;
    private Spinner monthSpinner;
    private Spinner yearSpinner;
    private Button btnRefresh;
    private Button btnExport;
    private ListView lvAttendanceHistory;
    private ProgressBar progressBar;
    private TextView tvSummary;
    private TextView tvTotalDays;
    private TextView tvPresentDays;
    private TextView tvAbsentDays;
    private TextView tvTotalHours;
    
    // Data
    private List<AttendanceRecord> attendanceList;
    private AttendanceHistoryAdapter adapter;
    private SharedPreferences sharedPreferences;
    private AttendanceApiService apiService;
    private String employeeId;
    
    // Date handling
    private int selectedMonth;
    private int selectedYear;
    private SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault());
    private SimpleDateFormat displayDateFormat = new SimpleDateFormat("MMM dd, yyyy", Locale.getDefault());
    private SimpleDateFormat timeFormat = new SimpleDateFormat("HH:mm", Locale.getDefault());
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_attendance_history);
        
        // Initialize preferences and API
        sharedPreferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        employeeId = sharedPreferences.getString(KEY_EMPLOYEE_ID, "");
        apiService = ApiClient.getRetrofitInstance().create(AttendanceApiService.class);
        
        initializeViews();
        setupToolbar();
        setupSpinners();
        setupClickListeners();
        
        // Set current month/year as default
        Calendar calendar = Calendar.getInstance();
        selectedMonth = calendar.get(Calendar.MONTH) + 1; // Calendar.MONTH is 0-based
        selectedYear = calendar.get(Calendar.YEAR);
        
        updateSpinnerSelections();
        loadAttendanceHistory();
    }
    
    private void initializeViews() {
        toolbar = findViewById(R.id.toolbar);
        monthSpinner = findViewById(R.id.monthSpinner);
        yearSpinner = findViewById(R.id.yearSpinner);
        btnRefresh = findViewById(R.id.btnRefresh);
        btnExport = findViewById(R.id.btnExport);
        lvAttendanceHistory = findViewById(R.id.lvAttendanceHistory);
        progressBar = findViewById(R.id.progressBar);
        tvSummary = findViewById(R.id.tvSummary);
        tvTotalDays = findViewById(R.id.tvTotalDays);
        tvPresentDays = findViewById(R.id.tvPresentDays);
        tvAbsentDays = findViewById(R.id.tvAbsentDays);
        tvTotalHours = findViewById(R.id.tvTotalHours);
        
        attendanceList = new ArrayList<>();
        adapter = new AttendanceHistoryAdapter(this, attendanceList);
        lvAttendanceHistory.setAdapter(adapter);
    }
    
    private void setupToolbar() {
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setTitle("Attendance History");
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
        }
    }
    
    private void setupSpinners() {
        // Month spinner
        String[] months = {
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        };
        ArrayAdapter<String> monthAdapter = new ArrayAdapter<>(this, 
            android.R.layout.simple_spinner_item, months);
        monthAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        monthSpinner.setAdapter(monthAdapter);
        
        // Year spinner
        List<String> years = new ArrayList<>();
        int currentYear = Calendar.getInstance().get(Calendar.YEAR);
        for (int i = currentYear; i >= currentYear - 5; i--) {
            years.add(String.valueOf(i));
        }
        ArrayAdapter<String> yearAdapter = new ArrayAdapter<>(this,
            android.R.layout.simple_spinner_item, years);
        yearAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        yearSpinner.setAdapter(yearAdapter);
        
        // Spinner listeners
        monthSpinner.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedMonth = position + 1;
                loadAttendanceHistory();
            }
            
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
        
        yearSpinner.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedYear = Integer.parseInt(years.get(position));
                loadAttendanceHistory();
            }
            
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }
    
    private void setupClickListeners() {
        btnRefresh.setOnClickListener(v -> loadAttendanceHistory());
        btnExport.setOnClickListener(v -> exportAttendanceData());
        
        lvAttendanceHistory.setOnItemClickListener((parent, view, position, id) -> {
            AttendanceRecord record = attendanceList.get(position);
            showAttendanceDetails(record);
        });
    }
    
    private void updateSpinnerSelections() {
        monthSpinner.setSelection(selectedMonth - 1);
        
        // Find year position
        for (int i = 0; i < yearSpinner.getCount(); i++) {
            if (yearSpinner.getItemAtPosition(i).toString().equals(String.valueOf(selectedYear))) {
                yearSpinner.setSelection(i);
                break;
            }
        }
    }
    
    private void loadAttendanceHistory() {
        if (employeeId.isEmpty()) {
            Toast.makeText(this, "Employee ID not found", Toast.LENGTH_SHORT).show();
            return;
        }
        
        progressBar.setVisibility(View.VISIBLE);
        btnRefresh.setEnabled(false);
        
        Call<ApiResponse<java.util.List<AttendanceRecord>>> call = apiService.getAttendanceHistory(
            employeeId, selectedMonth, selectedYear);
        
        call.enqueue(new Callback<ApiResponse<java.util.List<AttendanceRecord>>>() {
            @Override
            public void onResponse(Call<ApiResponse<java.util.List<AttendanceRecord>>> call, Response<ApiResponse<java.util.List<AttendanceRecord>>> response) {
                progressBar.setVisibility(View.GONE);
                btnRefresh.setEnabled(true);
                
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<java.util.List<AttendanceRecord>> apiResponse = response.body();
                    if (apiResponse.isSuccess()) {
                        attendanceList.clear();
                        attendanceList.addAll(apiResponse.getData());
                        adapter.notifyDataSetChanged();
                        
                        // Simple summary calculation without the AttendanceSummary object
                        updateSummaryData();
                        Log.d(TAG, "Loaded " + attendanceList.size() + " attendance records");
                    } else {
                        Toast.makeText(AttendanceHistoryActivity.this, 
                            apiResponse.getMessage(), Toast.LENGTH_LONG).show();
                    }
                } else {
                    Toast.makeText(AttendanceHistoryActivity.this, 
                        "Failed to load attendance history", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<java.util.List<AttendanceRecord>>> call, Throwable t) {
                progressBar.setVisibility(View.GONE);
                btnRefresh.setEnabled(true);
                Toast.makeText(AttendanceHistoryActivity.this, 
                    "Network error: " + t.getMessage(), Toast.LENGTH_LONG).show();
                Log.e(TAG, "Failed to load attendance history", t);
            }
        });
    }
    
    private void updateSummaryData() {
        int totalDays = attendanceList.size();
        int presentDays = 0;
        
        for (AttendanceRecord record : attendanceList) {
            if (record.getAction() != null && record.getAction().equals("clock_in")) {
                presentDays++;
            }
        }
        
        int absentDays = totalDays - presentDays;
        
        tvSummary.setText(String.format(Locale.getDefault(), 
            "Attendance Summary for %s %d", 
            monthSpinner.getSelectedItem().toString(), selectedYear));
        
        tvTotalDays.setText("Total Working Days: " + totalDays);
        tvPresentDays.setText("Present Days: " + presentDays);
        tvAbsentDays.setText("Absent Days: " + absentDays);
        tvTotalHours.setText("Total Hours: N/A");
    }
    
    private void showAttendanceDetails(AttendanceRecord record) {
        StringBuilder details = new StringBuilder();
        details.append("Date: ").append(formatDate(record.getDate())).append("\n");
        details.append("Status: ").append(record.getStatus()).append("\n");
        
        if (record.getClockInTime() != null && !record.getClockInTime().isEmpty()) {
            details.append("Clock In: ").append(formatTime(record.getClockInTime())).append("\n");
        }
        
        if (record.getClockOutTime() != null && !record.getClockOutTime().isEmpty()) {
            details.append("Clock Out: ").append(formatTime(record.getClockOutTime())).append("\n");
        }
        
        if (record.getHoursWorked() > 0) {
            details.append(String.format(Locale.getDefault(), 
                "Working Hours: %.2f", record.getHoursWorked())).append("\n");
        }
        
        if (record.getBranchName() != null && !record.getBranchName().isEmpty()) {
            details.append("Branch: ").append(record.getBranchName()).append("\n");
        }
        
        if (record.getReason() != null && !record.getReason().isEmpty()) {
            details.append("Reason: ").append(record.getReason());
        }
        
        new androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("Attendance Details")
            .setMessage(details.toString())
            .setPositiveButton("OK", null)
            .show();
    }
    
    private void exportAttendanceData() {
        if (attendanceList.isEmpty()) {
            Toast.makeText(this, "No data to export", Toast.LENGTH_SHORT).show();
            return;
        }
        
        // TODO: Implement CSV export functionality
        Toast.makeText(this, "Export functionality coming soon!", Toast.LENGTH_SHORT).show();
    }
    
    private String formatDate(String dateString) {
        try {
            Date date = dateFormat.parse(dateString);
            return displayDateFormat.format(date);
        } catch (ParseException e) {
            return dateString;
        }
    }
    
    private String formatTime(String timeString) {
        try {
            SimpleDateFormat inputFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
            Date date = inputFormat.parse(timeString);
            return timeFormat.format(date);
        } catch (ParseException e) {
            return timeString;
        }
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
