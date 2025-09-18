package com.signsync.attendance.fragment.employee;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.signsync.attendance.R;
import com.signsync.attendance.adapter.LeaveRequestAdapter;
import com.signsync.attendance.model.LeaveRequest;
import java.util.ArrayList;
import java.util.List;

public class EmployeeLeaveFragment extends Fragment {
    
    private RecyclerView recyclerView;
    private LeaveRequestAdapter adapter;
    private List<LeaveRequest> leaveRequestList;
    private FloatingActionButton fabAddLeave;
    
    public static EmployeeLeaveFragment newInstance() {
        return new EmployeeLeaveFragment();
    }
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_employee_leave, container, false);
        
        recyclerView = view.findViewById(R.id.leaveRequestsRecyclerView);
        recyclerView.setLayoutManager(new LinearLayoutManager(getContext()));
        
        fabAddLeave = view.findViewById(R.id.fabAddLeaveRequest);
        fabAddLeave.setOnClickListener(v -> showAddLeaveDialog());
        
        leaveRequestList = new ArrayList<>();
        adapter = new LeaveRequestAdapter(leaveRequestList);
        recyclerView.setAdapter(adapter);
        
        loadLeaveRequests();
        
        return view;
    }
    
    private void loadLeaveRequests() {
        // Load leave requests from API or local storage
        // This will be implemented with the actual API calls
    }
    
    private void showAddLeaveDialog() {
        // Show dialog to add new leave request
        // This will be implemented with the actual dialog
    }
}
