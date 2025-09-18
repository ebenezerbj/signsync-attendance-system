package com.signsync.attendance.adapter;

import android.content.Context;
import android.graphics.Color;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.TextView;

import com.signsync.attendance.R;
import com.signsync.attendance.model.AttendanceRecord;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.List;
import java.util.Locale;

public class AttendanceHistoryAdapter extends BaseAdapter {
    
    private Context context;
    private List<AttendanceRecord> attendanceList;
    private LayoutInflater inflater;
    private SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault());
    private SimpleDateFormat displayDateFormat = new SimpleDateFormat("MMM dd", Locale.getDefault());
    private SimpleDateFormat timeFormat = new SimpleDateFormat("HH:mm", Locale.getDefault());
    
    public AttendanceHistoryAdapter(Context context, List<AttendanceRecord> attendanceList) {
        this.context = context;
        this.attendanceList = attendanceList;
        this.inflater = LayoutInflater.from(context);
    }
    
    @Override
    public int getCount() {
        return attendanceList.size();
    }
    
    @Override
    public AttendanceRecord getItem(int position) {
        return attendanceList.get(position);
    }
    
    @Override
    public long getItemId(int position) {
        return position;
    }
    
    @Override
    public View getView(int position, View convertView, ViewGroup parent) {
        ViewHolder holder;
        
        if (convertView == null) {
            convertView = inflater.inflate(R.layout.item_attendance_history, parent, false);
            holder = new ViewHolder();
            holder.tvDate = convertView.findViewById(R.id.tvDate);
            holder.tvDay = convertView.findViewById(R.id.tvDay);
            holder.tvStatus = convertView.findViewById(R.id.tvStatus);
            holder.tvClockIn = convertView.findViewById(R.id.tvClockIn);
            holder.tvClockOut = convertView.findViewById(R.id.tvClockOut);
            holder.tvWorkingHours = convertView.findViewById(R.id.tvWorkingHours);
            holder.statusIndicator = convertView.findViewById(R.id.statusIndicator);
            convertView.setTag(holder);
        } else {
            holder = (ViewHolder) convertView.getTag();
        }
        
        AttendanceRecord record = attendanceList.get(position);
        
        // Format and set date
        String formattedDate = formatDate(record.getDate());
        String dayOfWeek = getDayOfWeek(record.getDate());
        holder.tvDate.setText(formattedDate);
        holder.tvDay.setText(dayOfWeek);
        
        // Set status
        holder.tvStatus.setText(record.getStatus());
        
        // Set times
        if (record.getClockInTime() != null && !record.getClockInTime().isEmpty()) {
            holder.tvClockIn.setText("In: " + formatTime(record.getClockInTime()));
            holder.tvClockIn.setVisibility(View.VISIBLE);
        } else {
            holder.tvClockIn.setText("In: --:--");
            holder.tvClockIn.setVisibility(View.VISIBLE);
        }
        
        if (record.getClockOutTime() != null && !record.getClockOutTime().isEmpty()) {
            holder.tvClockOut.setText("Out: " + formatTime(record.getClockOutTime()));
            holder.tvClockOut.setVisibility(View.VISIBLE);
        } else {
            holder.tvClockOut.setText("Out: --:--");
            holder.tvClockOut.setVisibility(View.VISIBLE);
        }
        
        // Set working hours
        if (record.getHoursWorked() > 0) {
            holder.tvWorkingHours.setText(String.format(Locale.getDefault(), 
                "%.2f hrs", record.getHoursWorked()));
        } else {
            holder.tvWorkingHours.setText("0.00 hrs");
        }
        
        // Set status indicator color
        setStatusColor(holder, record.getStatus());
        
        return convertView;
    }
    
    private void setStatusColor(ViewHolder holder, String status) {
        int color;
        int textColor = Color.WHITE;
        
        switch (status.toLowerCase()) {
            case "present":
            case "on time":
                color = Color.parseColor("#4CAF50"); // Green
                break;
            case "late":
                color = Color.parseColor("#FF9800"); // Orange
                break;
            case "absent":
                color = Color.parseColor("#F44336"); // Red
                break;
            case "holiday":
                color = Color.parseColor("#9C27B0"); // Purple
                break;
            case "leave":
                color = Color.parseColor("#2196F3"); // Blue
                break;
            default:
                color = Color.parseColor("#757575"); // Gray
                break;
        }
        
        holder.statusIndicator.setBackgroundColor(color);
        holder.tvStatus.setTextColor(color);
    }
    
    private String formatDate(String dateString) {
        try {
            Date date = dateFormat.parse(dateString);
            return displayDateFormat.format(date);
        } catch (ParseException e) {
            return dateString;
        }
    }
    
    private String getDayOfWeek(String dateString) {
        try {
            Date date = dateFormat.parse(dateString);
            SimpleDateFormat dayFormat = new SimpleDateFormat("EEE", Locale.getDefault());
            return dayFormat.format(date);
        } catch (ParseException e) {
            return "";
        }
    }
    
    private String formatTime(String timeString) {
        try {
            SimpleDateFormat inputFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
            Date date = inputFormat.parse(timeString);
            return timeFormat.format(date);
        } catch (ParseException e) {
            // Try different format
            try {
                SimpleDateFormat altFormat = new SimpleDateFormat("HH:mm:ss", Locale.getDefault());
                Date altDate = altFormat.parse(timeString);
                return timeFormat.format(altDate);
            } catch (ParseException ex) {
                return timeString;
            }
        }
    }
    
    static class ViewHolder {
        TextView tvDate;
        TextView tvDay;
        TextView tvStatus;
        TextView tvClockIn;
        TextView tvClockOut;
        TextView tvWorkingHours;
        View statusIndicator;
    }
}
