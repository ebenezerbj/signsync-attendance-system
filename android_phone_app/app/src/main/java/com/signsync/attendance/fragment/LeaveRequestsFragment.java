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
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.signsync.attendance.R;
import com.signsync.attendance.adapter.LeaveRequestAdapter;
import com.signsync.attendance.model.LeaveRequest;
import com.signsync.attendance.network.ApiResponse;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.NetworkClient;
import com.signsync.attendance.utils.SharedPreferencesManager;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import java.util.ArrayList;
import java.util.List;

public class LeaveRequestsFragment extends Fragment {
    
    private RecyclerView recyclerViewLeaveRequests;
    private SwipeRefreshLayout swipeRefreshLayout;
    private FloatingActionButton fabNewLeaveRequest;
    private MaterialButton buttonAll;
    private MaterialButton buttonPending;
    private MaterialButton buttonApproved;
    private MaterialButton buttonRejected;
    
    private LeaveRequestAdapter leaveRequestAdapter;
    private List<LeaveRequest> leaveRequestList;
    private List<LeaveRequest> filteredList;
    private AttendanceApiService apiService;
    private SharedPreferencesManager prefsManager;
    
    private String currentFilter = "all";
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_leave_requests, container, false);
        
        initializeViews(view);
        setupRecyclerView();
        setupFilterButtons();
        
        apiService = NetworkClient.getRetrofitInstance().create(AttendanceApiService.class);
        prefsManager = new SharedPreferencesManager(requireContext());
        leaveRequestList = new ArrayList<>();
        filteredList = new ArrayList<>();
        
        loadLeaveRequests();
        
        return view;
    }
    
    private void initializeViews(View view) {
        recyclerViewLeaveRequests = view.findViewById(R.id.recyclerViewLeaveRequests);
        swipeRefreshLayout = view.findViewById(R.id.swipeRefreshLayout);
        fabNewLeaveRequest = view.findViewById(R.id.fabNewLeaveRequest);
        buttonAll = view.findViewById(R.id.buttonAll);
        buttonPending = view.findViewById(R.id.buttonPending);
        buttonApproved = view.findViewById(R.id.buttonApproved);
        buttonRejected = view.findViewById(R.id.buttonRejected);
        
        swipeRefreshLayout.setOnRefreshListener(this::loadLeaveRequests);
        
        fabNewLeaveRequest.setOnClickListener(v -> showNewLeaveRequestDialog());
    }
    
    private void setupRecyclerView() {
        leaveRequestAdapter = new LeaveRequestAdapter(filteredList, this::onLeaveRequestClick);
        recyclerViewLeaveRequests.setLayoutManager(new LinearLayoutManager(getContext()));
        recyclerViewLeaveRequests.setAdapter(leaveRequestAdapter);
    }
    
    private void setupFilterButtons() {
        buttonAll.setOnClickListener(v -> applyFilter("all"));
        buttonPending.setOnClickListener(v -> applyFilter("pending"));
        buttonApproved.setOnClickListener(v -> applyFilter("approved"));
        buttonRejected.setOnClickListener(v -> applyFilter("rejected"));
        
        // Set initial filter
        applyFilter("all");
    }
    
    private void applyFilter(String filter) {
        currentFilter = filter;
        
        // Update button states
        resetFilterButtons();
        switch (filter) {
            case "all":
                buttonAll.setBackgroundTintList(getResources().getColorStateList(R.color.primary_color));
                break;
            case "pending":
                buttonPending.setBackgroundTintList(getResources().getColorStateList(R.color.warning_color));
                break;
            case "approved":
                buttonApproved.setBackgroundTintList(getResources().getColorStateList(R.color.success_color));
                break;
            case "rejected":
                buttonRejected.setBackgroundTintList(getResources().getColorStateList(R.color.error_color));
                break;
        }
        
        filterLeaveRequests();
    }
    
    private void resetFilterButtons() {
        buttonAll.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
        buttonPending.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
        buttonApproved.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
        buttonRejected.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
    }
    
    private void filterLeaveRequests() {
        filteredList.clear();
        
        for (LeaveRequest request : leaveRequestList) {
            boolean shouldInclude = false;
            
            switch (currentFilter) {
                case "all":
                    shouldInclude = true;
                    break;
                case "pending":
                    shouldInclude = request.isPending();
                    break;
                case "approved":
                    shouldInclude = request.isApproved();
                    break;
                case "rejected":
                    shouldInclude = request.isRejected();
                    break;
            }
            
            if (shouldInclude) {
                filteredList.add(request);
            }
        }
        
        leaveRequestAdapter.notifyDataSetChanged();
    }
    
    private void loadLeaveRequests() {
        String employeeId = prefsManager.getEmployeeId();
        if (employeeId == null) {
            Toast.makeText(getContext(), "Employee ID not found", Toast.LENGTH_SHORT).show();
            return;
        }
        
        swipeRefreshLayout.setRefreshing(true);
        
        Call<ApiResponse<List<LeaveRequest>>> call = apiService.getEmployeeLeaveRequests(employeeId);
        
        call.enqueue(new Callback<ApiResponse<List<LeaveRequest>>>() {
            @Override
            public void onResponse(Call<ApiResponse<List<LeaveRequest>>> call, Response<ApiResponse<List<LeaveRequest>>> response) {
                swipeRefreshLayout.setRefreshing(false);
                
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<List<LeaveRequest>> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess()) {
                        leaveRequestList.clear();
                        if (apiResponse.getData() != null) {
                            leaveRequestList.addAll(apiResponse.getData());
                        }
                        filterLeaveRequests();
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to load leave requests", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<List<LeaveRequest>>> call, Throwable t) {
                swipeRefreshLayout.setRefreshing(false);
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    private void onLeaveRequestClick(LeaveRequest leaveRequest) {
        showLeaveRequestDetails(leaveRequest);
    }
    
    private void showLeaveRequestDetails(LeaveRequest leaveRequest) {
        if (getContext() == null) return;
        
        androidx.appcompat.app.AlertDialog.Builder builder = new androidx.appcompat.app.AlertDialog.Builder(getContext());
        
        View dialogView = LayoutInflater.from(getContext()).inflate(R.layout.dialog_leave_request_details, null);
        
        // TODO: Initialize dialog views and populate with leave request data
        // This would include leave type, dates, reason, status, etc.
        
        builder.setView(dialogView)
               .setTitle("Leave Request Details")
               .setPositiveButton("Close", null);
        
        // Add cancel button if request is pending
        if (leaveRequest.isPending()) {
            builder.setNegativeButton("Cancel Request", (dialog, which) -> {
                cancelLeaveRequest(leaveRequest);
            });
        }
        
        builder.create().show();
    }
    
    private void showNewLeaveRequestDialog() {
        if (getContext() == null) return;
        
        // TODO: Create new leave request dialog
        // This would include form fields for leave type, dates, reason, etc.
        Toast.makeText(getContext(), "New leave request dialog would open here", Toast.LENGTH_SHORT).show();
    }
    
    private void cancelLeaveRequest(LeaveRequest leaveRequest) {
        Call<ApiResponse<String>> call = apiService.cancelLeaveRequest(leaveRequest.getId());
        
        call.enqueue(new Callback<ApiResponse<String>>() {
            @Override
            public void onResponse(Call<ApiResponse<String>> call, Response<ApiResponse<String>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<String> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess()) {
                        Toast.makeText(getContext(), "Leave request cancelled", Toast.LENGTH_SHORT).show();
                        loadLeaveRequests(); // Refresh the list
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to cancel leave request", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<String>> call, Throwable t) {
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    public void refreshData() {
        loadLeaveRequests();
    }
    
    @Override
    public void onResume() {
        super.onResume();
        loadLeaveRequests();
    }
}
