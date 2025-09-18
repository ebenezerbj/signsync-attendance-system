package com.signsync.attendance.fragment;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.card.MaterialCardView;
import com.google.android.material.textview.MaterialTextView;
import com.signsync.attendance.R;
import com.signsync.attendance.adapter.AttendanceAdapter;
import com.signsync.attendance.model.AttendanceSummary;
import com.signsync.attendance.network.ApiResponse;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.NetworkClient;
import com.signsync.attendance.utils.SharedPreferencesManager;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;

public class AttendanceFragment extends Fragment {
    
    private RecyclerView recyclerViewAttendance;
    private SwipeRefreshLayout swipeRefreshLayout;
    private MaterialCardView cardViewSummary;
    private MaterialTextView textViewMonthYear;
    private MaterialTextView textViewTotalDays;
    private MaterialTextView textViewPresentDays;
    private MaterialTextView textViewAbsentDays;
    private MaterialTextView textViewLateDays;
    private MaterialTextView textViewTotalHours;
    private MaterialButton buttonPreviousMonth;
    private MaterialButton buttonNextMonth;
    private MaterialButton buttonCurrentMonth;
    
    private AttendanceAdapter attendanceAdapter;
    private List<AttendanceSummary> attendanceList;
    private AttendanceApiService apiService;
    private SharedPreferencesManager prefsManager;
    
    private int currentMonth;
    private int currentYear;
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_attendance, container, false);
        
        initializeViews(view);
        setupRecyclerView();
        setupNavigation();
        
        apiService = NetworkClient.getInstance(getContext()).getApiService();
        prefsManager = new SharedPreferencesManager(requireContext());
        attendanceList = new ArrayList<>();
        
        // Set current month and year
        Calendar calendar = Calendar.getInstance();
        currentMonth = calendar.get(Calendar.MONTH) + 1; // Calendar.MONTH is 0-based
        currentYear = calendar.get(Calendar.YEAR);
        
        updateMonthYearDisplay();
        loadAttendanceData();
        
        return view;
    }
    
    private void initializeViews(View view) {
        recyclerViewAttendance = view.findViewById(R.id.recyclerViewAttendance);
        swipeRefreshLayout = view.findViewById(R.id.swipeRefreshLayout);
        cardViewSummary = view.findViewById(R.id.cardViewSummary);
        textViewMonthYear = view.findViewById(R.id.textViewMonthYear);
        textViewTotalDays = view.findViewById(R.id.textViewTotalDays);
        textViewPresentDays = view.findViewById(R.id.textViewPresentDays);
        textViewAbsentDays = view.findViewById(R.id.textViewAbsentDays);
        textViewLateDays = view.findViewById(R.id.textViewLateDays);
        textViewTotalHours = view.findViewById(R.id.textViewTotalHours);
        buttonPreviousMonth = view.findViewById(R.id.buttonPreviousMonth);
        buttonNextMonth = view.findViewById(R.id.buttonNextMonth);
        buttonCurrentMonth = view.findViewById(R.id.buttonCurrentMonth);
        
        swipeRefreshLayout.setOnRefreshListener(this::loadAttendanceData);
    }
    
    private void setupRecyclerView() {
        attendanceAdapter = new AttendanceAdapter(attendanceList, this::onAttendanceItemClick);
        recyclerViewAttendance.setLayoutManager(new LinearLayoutManager(getContext()));
        recyclerViewAttendance.setAdapter(attendanceAdapter);
    }
    
    private void setupNavigation() {
        buttonPreviousMonth.setOnClickListener(v -> {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            updateMonthYearDisplay();
            loadAttendanceData();
        });
        
        buttonNextMonth.setOnClickListener(v -> {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            updateMonthYearDisplay();
            loadAttendanceData();
        });
        
        buttonCurrentMonth.setOnClickListener(v -> {
            Calendar calendar = Calendar.getInstance();
            currentMonth = calendar.get(Calendar.MONTH) + 1;
            currentYear = calendar.get(Calendar.YEAR);
            updateMonthYearDisplay();
            loadAttendanceData();
        });
    }
    
    private void updateMonthYearDisplay() {
        String[] monthNames = {
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        };
        
        String monthYear = monthNames[currentMonth - 1] + " " + currentYear;
        textViewMonthYear.setText(monthYear);
    }
    
    private void loadAttendanceData() {
        String employeeId = prefsManager.getEmployeeId();
        if (employeeId == null) {
            Toast.makeText(getContext(), "Employee ID not found", Toast.LENGTH_SHORT).show();
            return;
        }
        
        swipeRefreshLayout.setRefreshing(true);
        
        Call<ApiResponse<List<AttendanceSummary>>> call = apiService.getEmployeeAttendance(
            employeeId, 
            currentYear, 
            currentMonth
        );
        
        call.enqueue(new Callback<ApiResponse<List<AttendanceSummary>>>() {
            @Override
            public void onResponse(Call<ApiResponse<List<AttendanceSummary>>> call, Response<ApiResponse<List<AttendanceSummary>>> response) {
                swipeRefreshLayout.setRefreshing(false);
                
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<List<AttendanceSummary>> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess()) {
                        attendanceList.clear();
                        if (apiResponse.getData() != null) {
                            attendanceList.addAll(apiResponse.getData());
                        }
                        attendanceAdapter.notifyDataSetChanged();
                        updateSummaryCards();
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to load attendance data", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<List<AttendanceSummary>>> call, Throwable t) {
                swipeRefreshLayout.setRefreshing(false);
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    private void updateSummaryCards() {
        int totalDays = attendanceList.size();
        int presentDays = 0;
        int lateDays = 0;
        double totalHours = 0.0;
        
        for (AttendanceSummary attendance : attendanceList) {
            if (attendance.hasClockIn()) {
                presentDays++;
                if (attendance.isLate()) {
                    lateDays++;
                }
                totalHours += attendance.getTotalHours();
            }
        }
        
        int absentDays = totalDays - presentDays;
        
        textViewTotalDays.setText(String.valueOf(totalDays));
        textViewPresentDays.setText(String.valueOf(presentDays));
        textViewAbsentDays.setText(String.valueOf(absentDays));
        textViewLateDays.setText(String.valueOf(lateDays));
        textViewTotalHours.setText(formatHours(totalHours));
    }
    
    private String formatHours(double hours) {
        int wholeHours = (int) hours;
        int minutes = (int) ((hours - wholeHours) * 60);
        return String.format("%dh %dm", wholeHours, minutes);
    }
    
    private void onAttendanceItemClick(AttendanceSummary attendance) {
        // TODO: Show attendance details in a dialog or navigate to detail view
        showAttendanceDetails(attendance);
    }
    
    private void showAttendanceDetails(AttendanceSummary attendance) {
        if (getContext() == null) return;
        
        androidx.appcompat.app.AlertDialog.Builder builder = new androidx.appcompat.app.AlertDialog.Builder(getContext());
        
        View dialogView = LayoutInflater.from(getContext()).inflate(R.layout.dialog_attendance_details, null);
        
        MaterialTextView textDate = dialogView.findViewById(R.id.textViewDate);
        MaterialTextView textClockIn = dialogView.findViewById(R.id.textViewClockIn);
        MaterialTextView textClockOut = dialogView.findViewById(R.id.textViewClockOut);
        MaterialTextView textStatus = dialogView.findViewById(R.id.textViewStatus);
        MaterialTextView textTotalHours = dialogView.findViewById(R.id.textViewTotalHours);
        MaterialTextView textLocation = dialogView.findViewById(R.id.textViewLocation);
        MaterialTextView textNotes = dialogView.findViewById(R.id.textViewNotes);
        
        textDate.setText(attendance.getFormattedDate());
        textClockIn.setText(attendance.getFormattedClockIn());
        textClockOut.setText(attendance.getFormattedClockOut());
        textStatus.setText(attendance.getClockInStatus());
        textTotalHours.setText(attendance.getFormattedTotalHours());
        textLocation.setText(attendance.getLocationIn() != null ? attendance.getLocationIn() : "N/A");
        textNotes.setText(attendance.getNotes() != null ? attendance.getNotes() : "No notes");
        
        builder.setView(dialogView)
               .setTitle("Attendance Details")
               .setPositiveButton("Close", null)
               .create()
               .show();
    }
    
    public void refreshData() {
        loadAttendanceData();
    }
    
    @Override
    public void onResume() {
        super.onResume();
        loadAttendanceData();
    }
}
