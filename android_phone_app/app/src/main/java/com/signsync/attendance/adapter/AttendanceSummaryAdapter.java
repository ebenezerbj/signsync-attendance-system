package com.signsync.attendance.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import com.signsync.attendance.R;
import com.signsync.attendance.model.AttendanceSummary;
import java.util.List;

public class AttendanceSummaryAdapter extends RecyclerView.Adapter<AttendanceSummaryAdapter.ViewHolder> {
    private List<AttendanceSummary> attendanceList;

    public AttendanceSummaryAdapter(List<AttendanceSummary> attendanceList) {
        this.attendanceList = attendanceList;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_attendance_summary, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        AttendanceSummary attendance = attendanceList.get(position);
        holder.dateText.setText(attendance.getDate());
        holder.clockInText.setText(attendance.getClockInTime());
        holder.clockOutText.setText(attendance.getClockOutTime());
        holder.hoursText.setText(attendance.getHoursWorked() + " hrs");
    }

    @Override
    public int getItemCount() {
        return attendanceList != null ? attendanceList.size() : 0;
    }

    public void updateData(List<AttendanceSummary> newData) {
        this.attendanceList = newData;
        notifyDataSetChanged();
    }

    static class ViewHolder extends RecyclerView.ViewHolder {
        TextView dateText, clockInText, clockOutText, hoursText;

        ViewHolder(View itemView) {
            super(itemView);
            dateText = itemView.findViewById(R.id.text_date);
            clockInText = itemView.findViewById(R.id.text_clock_in);
            clockOutText = itemView.findViewById(R.id.text_clock_out);
            hoursText = itemView.findViewById(R.id.text_hours);
        }
    }
}
