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
import com.signsync.attendance.adapter.AttendanceCorrectionAdapter;
import com.signsync.attendance.model.AttendanceCorrection;
import com.signsync.attendance.network.ApiResponse;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.NetworkClient;
import com.signsync.attendance.utils.SharedPreferencesManager;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import java.util.ArrayList;
import java.util.List;

public class CorrectionsFragment extends Fragment {
    
    private RecyclerView recyclerViewCorrections;
    private SwipeRefreshLayout swipeRefreshLayout;
    private FloatingActionButton fabNewCorrection;
    private MaterialButton buttonAll;
    private MaterialButton buttonPending;
    private MaterialButton buttonApproved;
    private MaterialButton buttonRejected;
    
    private AttendanceCorrectionAdapter correctionAdapter;
    private List<AttendanceCorrection> correctionList;
    private List<AttendanceCorrection> filteredList;
    private AttendanceApiService apiService;
    private SharedPreferencesManager prefsManager;
    
    private String currentFilter = "all";
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_corrections, container, false);
        
        initializeViews(view);
        setupRecyclerView();
        setupFilterButtons();
        
        apiService = NetworkClient.getInstance(getContext()).getApiService();
        prefsManager = new SharedPreferencesManager(requireContext());
        correctionList = new ArrayList<>();
        filteredList = new ArrayList<>();
        
        loadCorrections();
        
        return view;
    }
    
    private void initializeViews(View view) {
        recyclerViewCorrections = view.findViewById(R.id.recyclerViewCorrections);
        swipeRefreshLayout = view.findViewById(R.id.swipeRefreshLayout);
        fabNewCorrection = view.findViewById(R.id.fabNewCorrection);
        buttonAll = view.findViewById(R.id.buttonAll);
        buttonPending = view.findViewById(R.id.buttonPending);
        buttonApproved = view.findViewById(R.id.buttonApproved);
        buttonRejected = view.findViewById(R.id.buttonRejected);
        
        swipeRefreshLayout.setOnRefreshListener(this::loadCorrections);
        
        fabNewCorrection.setOnClickListener(v -> showNewCorrectionDialog());
    }
    
    private void setupRecyclerView() {
        correctionAdapter = new AttendanceCorrectionAdapter(filteredList, this::onCorrectionClick);
        recyclerViewCorrections.setLayoutManager(new LinearLayoutManager(getContext()));
        recyclerViewCorrections.setAdapter(correctionAdapter);
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
        
        filterCorrections();
    }
    
    private void resetFilterButtons() {
        buttonAll.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
        buttonPending.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
        buttonApproved.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
        buttonRejected.setBackgroundTintList(getResources().getColorStateList(R.color.surface_variant));
    }
    
    private void filterCorrections() {
        filteredList.clear();
        
        for (AttendanceCorrection correction : correctionList) {
            boolean shouldInclude = false;
            
            switch (currentFilter) {
                case "all":
                    shouldInclude = true;
                    break;
                case "pending":
                    shouldInclude = correction.isPending();
                    break;
                case "approved":
                    shouldInclude = correction.isApproved();
                    break;
                case "rejected":
                    shouldInclude = correction.isRejected();
                    break;
            }
            
            if (shouldInclude) {
                filteredList.add(correction);
            }
        }
        
        correctionAdapter.notifyDataSetChanged();
    }
    
    private void loadCorrections() {
        String employeeId = prefsManager.getEmployeeId();
        if (employeeId == null) {
            Toast.makeText(getContext(), "Employee ID not found", Toast.LENGTH_SHORT).show();
            return;
        }
        
        swipeRefreshLayout.setRefreshing(true);
        
        Call<ApiResponse<List<AttendanceCorrection>>> call = apiService.getEmployeeCorrections(employeeId);
        
        call.enqueue(new Callback<ApiResponse<List<AttendanceCorrection>>>() {
            @Override
            public void onResponse(Call<ApiResponse<List<AttendanceCorrection>>> call, Response<ApiResponse<List<AttendanceCorrection>>> response) {
                swipeRefreshLayout.setRefreshing(false);
                
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<List<AttendanceCorrection>> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess()) {
                        correctionList.clear();
                        if (apiResponse.getData() != null) {
                            correctionList.addAll(apiResponse.getData());
                        }
                        filterCorrections();
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to load corrections", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<List<AttendanceCorrection>>> call, Throwable t) {
                swipeRefreshLayout.setRefreshing(false);
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    private void onCorrectionClick(AttendanceCorrection correction) {
        showCorrectionDetails(correction);
    }
    
    private void showCorrectionDetails(AttendanceCorrection correction) {
        if (getContext() == null) return;
        
        androidx.appcompat.app.AlertDialog.Builder builder = new androidx.appcompat.app.AlertDialog.Builder(getContext());
        
        View dialogView = LayoutInflater.from(getContext()).inflate(R.layout.dialog_correction_details, null);
        
        // TODO: Initialize dialog views and populate with correction data
        // This would include date, original times, requested times, reason, status, etc.
        
        builder.setView(dialogView)
               .setTitle("Correction Details")
               .setPositiveButton("Close", null);
        
        // Add cancel button if correction is pending
        if (correction.isPending()) {
            builder.setNegativeButton("Cancel Request", (dialog, which) -> {
                cancelCorrection(correction);
            });
        }
        
        builder.create().show();
    }
    
    private void showNewCorrectionDialog() {
        if (getContext() == null) return;
        
        // TODO: Create new correction request dialog
        // This would include form fields for date, time corrections, reason, etc.
        Toast.makeText(getContext(), "New correction request dialog would open here", Toast.LENGTH_SHORT).show();
    }
    
    private void cancelCorrection(AttendanceCorrection correction) {
        Call<ApiResponse<String>> call = apiService.cancelCorrection(correction.getId());
        
        call.enqueue(new Callback<ApiResponse<String>>() {
            @Override
            public void onResponse(Call<ApiResponse<String>> call, Response<ApiResponse<String>> response) {
                if (response.isSuccessful() && response.body() != null) {
                    ApiResponse<String> apiResponse = response.body();
                    
                    if (apiResponse.isSuccess()) {
                        Toast.makeText(getContext(), "Correction request cancelled", Toast.LENGTH_SHORT).show();
                        loadCorrections(); // Refresh the list
                    } else {
                        Toast.makeText(getContext(), apiResponse.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                } else {
                    Toast.makeText(getContext(), "Failed to cancel correction", Toast.LENGTH_SHORT).show();
                }
            }
            
            @Override
            public void onFailure(Call<ApiResponse<String>> call, Throwable t) {
                Toast.makeText(getContext(), "Network error: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
    
    public void refreshData() {
        loadCorrections();
    }
    
    @Override
    public void onResume() {
        super.onResume();
        loadCorrections();
    }
}
