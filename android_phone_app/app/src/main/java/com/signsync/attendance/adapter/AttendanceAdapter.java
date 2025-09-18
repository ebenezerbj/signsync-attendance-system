package com.signsync.attendance.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import com.google.android.material.card.MaterialCardView;
import com.google.android.material.textview.MaterialTextView;
import com.signsync.attendance.R;
import com.signsync.attendance.model.AttendanceSummary;
import java.util.List;

public class AttendanceAdapter extends RecyclerView.Adapter<AttendanceAdapter.AttendanceViewHolder> {
    
    private List<AttendanceSummary> attendanceList;
    private OnAttendanceClickListener clickListener;
    
    public interface OnAttendanceClickListener {
        void onAttendanceClick(AttendanceSummary attendance);
    }
    
    public AttendanceAdapter(List<AttendanceSummary> attendanceList, OnAttendanceClickListener clickListener) {
        this.attendanceList = attendanceList;
        this.clickListener = clickListener;
    }
    
    @NonNull
    @Override
    public AttendanceViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_attendance, parent, false);
        return new AttendanceViewHolder(view);
    }
    
    @Override
    public void onBindViewHolder(@NonNull AttendanceViewHolder holder, int position) {
        AttendanceSummary attendance = attendanceList.get(position);
        holder.bind(attendance);
    }
    
    @Override
    public int getItemCount() {
        return attendanceList.size();
    }
    
    class AttendanceViewHolder extends RecyclerView.ViewHolder {
        
        private MaterialCardView cardView;
        private MaterialTextView textViewDate;
        private MaterialTextView textViewClockIn;
        private MaterialTextView textViewClockOut;
        private MaterialTextView textViewStatus;
        private MaterialTextView textViewTotalHours;
        private MaterialTextView textViewDayType;
        private View statusIndicator;
        
        public AttendanceViewHolder(@NonNull View itemView) {
            super(itemView);
            
            cardView = itemView.findViewById(R.id.cardView);
            textViewDate = itemView.findViewById(R.id.textViewDate);
            textViewClockIn = itemView.findViewById(R.id.textViewClockIn);
            textViewClockOut = itemView.findViewById(R.id.textViewClockOut);
            textViewStatus = itemView.findViewById(R.id.textViewStatus);
            textViewTotalHours = itemView.findViewById(R.id.textViewTotalHours);
            textViewDayType = itemView.findViewById(R.id.textViewDayType);
            statusIndicator = itemView.findViewById(R.id.statusIndicator);
            
            cardView.setOnClickListener(v -> {
                if (clickListener != null) {
                    int position = getAdapterPosition();
                    if (position != RecyclerView.NO_POSITION) {
                        clickListener.onAttendanceClick(attendanceList.get(position));
                    }
                }
            });
        }
        
        public void bind(AttendanceSummary attendance) {
            textViewDate.setText(attendance.getFormattedDate());
            textViewClockIn.setText(attendance.getFormattedClockIn());
            textViewClockOut.setText(attendance.getFormattedClockOut());
            textViewTotalHours.setText(attendance.getFormattedTotalHours());
            
            // Set status
            if (attendance.hasClockIn()) {
                textViewStatus.setText(attendance.getClockInStatus());
                
                // Set status indicator color
                int statusColor = android.graphics.Color.parseColor(attendance.getStatusColor());
                statusIndicator.setBackgroundColor(statusColor);
                
                // Set status text color
                textViewStatus.setTextColor(statusColor);
            } else {
                textViewStatus.setText("Absent");
                textViewStatus.setTextColor(android.graphics.Color.parseColor("#F87171"));
                statusIndicator.setBackgroundColor(android.graphics.Color.parseColor("#F87171"));
            }
            
            // Set day type
            if (attendance.isHoliday()) {
                textViewDayType.setText("Holiday");
                textViewDayType.setVisibility(View.VISIBLE);
                textViewDayType.setBackgroundColor(android.graphics.Color.parseColor("#8B5CF6"));
            } else if (attendance.isWeekend()) {
                textViewDayType.setText("Weekend");
                textViewDayType.setVisibility(View.VISIBLE);
                textViewDayType.setBackgroundColor(android.graphics.Color.parseColor("#6B7280"));
            } else {
                textViewDayType.setVisibility(View.GONE);
            }
            
            // Set card elevation based on completion
            if (attendance.isComplete()) {
                cardView.setCardElevation(4f);
            } else {
                cardView.setCardElevation(2f);
            }
        }
    }
}
