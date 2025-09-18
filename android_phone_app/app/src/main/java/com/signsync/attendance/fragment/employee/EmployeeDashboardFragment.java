package com.signsync.attendance.fragment.employee;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.cardview.widget.CardView;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import com.signsync.attendance.R;
import com.signsync.attendance.activity.employee.EmployeePortalActivity;
import com.signsync.attendance.adapter.AttendanceSummaryAdapter;
import com.signsync.attendance.model.AttendanceSummary;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.model.EmployeeDashboardResponse;
import com.signsync.attendance.model.GamificationData;
import com.signsync.attendance.network.ApiResponse;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import java.util.ArrayList;
import java.util.List;

public class EmployeeDashboardFragment extends Fragment {
    
    // UI Components
    private TextView streakDaysText, streakDescText;
    private TextView leaveDaysText, leaveDescText;
    private TextView todayStatusText, todayTimeText;
    private TextView weekHoursText, monthHoursText;
    private CardView pulseSurveyCard;
    private LinearLayout moodButtonsLayout;
    private RecyclerView recentAttendanceRecycler;
    
    // Data
    private EmployeePortalActivity parentActivity;
    private AttendanceSummaryAdapter attendanceAdapter;
    private List<AttendanceSummary> recentAttendance = new ArrayList<>();
    private GamificationData gamificationData;
    
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, 
                           @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_employee_dashboard, container, false);
    }
    
    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);
        
        parentActivity = (EmployeePortalActivity) getActivity();
        initializeViews(view);
        setupRecyclerView();
        setupPulseSurvey();
        loadDashboardData();
    }
    
    private void initializeViews(View view) {
        // Stats cards
        streakDaysText = view.findViewById(R.id.streakDaysText);
        streakDescText = view.findViewById(R.id.streakDescText);
        leaveDaysText = view.findViewById(R.id.leaveDaysText);
        leaveDescText = view.findViewById(R.id.leaveDescText);
        
        // Today's status
        todayStatusText = view.findViewById(R.id.todayStatusText);
        todayTimeText = view.findViewById(R.id.todayTimeText);
        
        // Work hours
        weekHoursText = view.findViewById(R.id.weekHoursText);
        monthHoursText = view.findViewById(R.id.monthHoursText);
        
        // Pulse survey
        pulseSurveyCard = view.findViewById(R.id.pulseSurveyCard);
        moodButtonsLayout = view.findViewById(R.id.moodButtonsLayout);
        
        // Recent attendance
        recentAttendanceRecycler = view.findViewById(R.id.recentAttendanceRecycler);
    }
    
    private void setupRecyclerView() {
        attendanceAdapter = new AttendanceSummaryAdapter(recentAttendance);
        recentAttendanceRecycler.setLayoutManager(new LinearLayoutManager(getContext()));
        recentAttendanceRecycler.setAdapter(attendanceAdapter);
    }
    
    private void setupPulseSurvey() {
        View happyButton = moodButtonsLayout.findViewById(R.id.happyMoodButton);
        View neutralButton = moodButtonsLayout.findViewById(R.id.neutralMoodButton);
        View sadButton = moodButtonsLayout.findViewById(R.id.sadMoodButton);
        
        happyButton.setOnClickListener(v -> submitPulseSurvey("happy"));
        neutralButton.setOnClickListener(v -> submitPulseSurvey("neutral"));
        sadButton.setOnClickListener(v -> submitPulseSurvey("sad"));
    }
    
    private void loadDashboardData() {
        if (parentActivity == null) return;
        
        String employeeId = parentActivity.getEmployeeId();
        
        Call<EmployeeDashboardResponse> call = parentActivity.getApiService().getEmployeeDashboard(employeeId);
        call.enqueue(new Callback<EmployeeDashboardResponse>() {
            @Override
            public void onResponse(Call<EmployeeDashboardResponse> call, Response<EmployeeDashboardResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    EmployeeDashboardResponse dashboardResponse = response.body();
                    if (dashboardResponse.isSuccess()) {
                        updateDashboardUI(dashboardResponse);
                    } else {
                        showError("Failed to load dashboard: " + dashboardResponse.getMessage());
                    }
                } else {
                    showError("Failed to load dashboard data");
                }
            }
            
            @Override
            public void onFailure(Call<EmployeeDashboardResponse> call, Throwable t) {
                showError("Network error: " + t.getMessage());
            }
        });
    }
    
    private void submitPulseSurvey(String mood) {
        if (parentActivity == null) return;
        
        String employeeId = parentActivity.getEmployeeId();
        
        Call<ApiResponse<String>> call = parentActivity.getApiService().submitPulseSurvey(employeeId, mood, "");
        
        call.enqueue(new Callback<ApiResponse<String>>() {
            @Override
            public void onResponse(Call<ApiResponse<String>> call, Response<ApiResponse<String>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<String> body = response.body();
                    if (body.isSuccess()) {
                        Toast.makeText(getContext(), "Thank you for your feedback!", Toast.LENGTH_SHORT).show();
                        pulseSurveyCard.setVisibility(View.GONE);
                    } else {
                        showError("Failed to submit feedback: " + body.getMessage());
                    }
                } else {
                    showError("Failed to submit feedback");
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<String>> call, Throwable t) {
                showError("Network error: " + t.getMessage());
            }
        });
    }
    
    private void updateDashboardUI(EmployeeDashboardResponse response) {
        // Update gamification stats
        GamificationData gamify = response.getGamificationData();
        if (gamify != null) {
            streakDaysText.setText(String.valueOf(gamify.getStreak()));
            streakDescText.setText("Current on-time streak");
        }
        
        // Update leave balance
        int leaveBalance = response.getLeaveBalance();
        leaveDaysText.setText(String.valueOf(leaveBalance));
        leaveDescText.setText("Leave days remaining");
        
        // Update today's status
        String todayStatus = response.getTodayStatus();
        String todayTime = response.getTodayTime();
        todayStatusText.setText(todayStatus != null ? todayStatus : "Not clocked in");
        todayTimeText.setText(todayTime != null ? todayTime : "--:--");
        
        // Update work hours
        weekHoursText.setText(String.format("%.1f hrs", response.getWeekHours()));
        monthHoursText.setText(String.format("%.1f hrs", response.getMonthHours()));
        
        // Update recent attendance
        recentAttendance.clear();
        if (response.getRecentAttendance() != null) {
            recentAttendance.addAll(response.getRecentAttendance());
        }
        attendanceAdapter.notifyDataSetChanged();
    }
    
    private void showError(String message) {
        if (getContext() != null) {
            Toast.makeText(getContext(), message, Toast.LENGTH_LONG).show();
        }
    }
    
    @Override
    public void onResume() {
        super.onResume();
        // Refresh data when fragment becomes visible
        loadDashboardData();
    }
}
