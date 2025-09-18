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
import com.signsync.attendance.R;
import com.signsync.attendance.adapter.AttendanceAdapter;
import com.signsync.attendance.model.AttendanceSummary;
import java.util.ArrayList;
import java.util.List;

public class EmployeeAttendanceFragment extends Fragment {
    
    private RecyclerView recyclerView;
    private AttendanceAdapter adapter;
    private List<AttendanceSummary> attendanceList;
    
    public static EmployeeAttendanceFragment newInstance() {
        return new EmployeeAttendanceFragment();
    }
    
    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_employee_attendance, container, false);
        
        recyclerView = view.findViewById(R.id.attendanceRecyclerView);
        recyclerView.setLayoutManager(new LinearLayoutManager(getContext()));
        
        attendanceList = new ArrayList<>();
        adapter = new AttendanceAdapter(attendanceList, this::onAttendanceClick);
        recyclerView.setAdapter(adapter);
        
        loadAttendanceData();
        
        return view;
    }
    
    private void onAttendanceClick(AttendanceSummary attendance) {
        // Handle attendance item click
    }
    
    private void loadAttendanceData() {
        // Load attendance data from API or local storage
        // This will be implemented with the actual API calls
    }
}
