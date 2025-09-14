package com.signsync.attendance.model;

import android.os.Parcel;
import android.os.Parcelable;
import com.google.gson.annotations.SerializedName;

public class AttendanceSummary implements Parcelable {
    
    @SerializedName("id")
    private int id;
    
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("attendance_date")
    private String attendanceDate;
    
    @SerializedName("clock_in")
    private String clockIn;
    
    @SerializedName("clock_out")
    private String clockOut;
    
    @SerializedName("clock_in_status")
    private String clockInStatus;
    
    @SerializedName("clock_out_status")
    private String clockOutStatus;
    
    @SerializedName("total_hours")
    private double totalHours;
    
    @SerializedName("break_time")
    private double breakTime;
    
    @SerializedName("overtime_hours")
    private double overtimeHours;
    
    @SerializedName("location_in")
    private String locationIn;
    
    @SerializedName("location_out")
    private String locationOut;
    
    @SerializedName("notes")
    private String notes;
    
    @SerializedName("is_holiday")
    private boolean isHoliday;
    
    @SerializedName("is_weekend")
    private boolean isWeekend;
    
    public AttendanceSummary() {}
    
    public AttendanceSummary(String employeeId, String attendanceDate, String clockIn, String clockOut, String status) {
        this.employeeId = employeeId;
        this.attendanceDate = attendanceDate;
        this.clockIn = clockIn;
        this.clockOut = clockOut;
        this.clockInStatus = status;
    }
    
    protected AttendanceSummary(Parcel in) {
        id = in.readInt();
        employeeId = in.readString();
        attendanceDate = in.readString();
        clockIn = in.readString();
        clockOut = in.readString();
        clockInStatus = in.readString();
        clockOutStatus = in.readString();
        totalHours = in.readDouble();
        breakTime = in.readDouble();
        overtimeHours = in.readDouble();
        locationIn = in.readString();
        locationOut = in.readString();
        notes = in.readString();
        isHoliday = in.readByte() != 0;
        isWeekend = in.readByte() != 0;
    }
    
    public static final Creator<AttendanceSummary> CREATOR = new Creator<AttendanceSummary>() {
        @Override
        public AttendanceSummary createFromParcel(Parcel in) {
            return new AttendanceSummary(in);
        }
        
        @Override
        public AttendanceSummary[] newArray(int size) {
            return new AttendanceSummary[size];
        }
    };
    
    @Override
    public int describeContents() {
        return 0;
    }
    
    @Override
    public void writeToParcel(Parcel dest, int flags) {
        dest.writeInt(id);
        dest.writeString(employeeId);
        dest.writeString(attendanceDate);
        dest.writeString(clockIn);
        dest.writeString(clockOut);
        dest.writeString(clockInStatus);
        dest.writeString(clockOutStatus);
        dest.writeDouble(totalHours);
        dest.writeDouble(breakTime);
        dest.writeDouble(overtimeHours);
        dest.writeString(locationIn);
        dest.writeString(locationOut);
        dest.writeString(notes);
        dest.writeByte((byte) (isHoliday ? 1 : 0));
        dest.writeByte((byte) (isWeekend ? 1 : 0));
    }
    
    // Getters and Setters
    public int getId() {
        return id;
    }
    
    public void setId(int id) {
        this.id = id;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
    
    public String getAttendanceDate() {
        return attendanceDate;
    }
    
    public void setAttendanceDate(String attendanceDate) {
        this.attendanceDate = attendanceDate;
    }
    
    public String getClockIn() {
        return clockIn;
    }
    
    public void setClockIn(String clockIn) {
        this.clockIn = clockIn;
    }
    
    public String getClockOut() {
        return clockOut;
    }
    
    public void setClockOut(String clockOut) {
        this.clockOut = clockOut;
    }
    
    public String getClockInStatus() {
        return clockInStatus;
    }
    
    public void setClockInStatus(String clockInStatus) {
        this.clockInStatus = clockInStatus;
    }
    
    public String getClockOutStatus() {
        return clockOutStatus;
    }
    
    public void setClockOutStatus(String clockOutStatus) {
        this.clockOutStatus = clockOutStatus;
    }
    
    public double getTotalHours() {
        return totalHours;
    }
    
    public void setTotalHours(double totalHours) {
        this.totalHours = totalHours;
    }
    
    public double getBreakTime() {
        return breakTime;
    }
    
    public void setBreakTime(double breakTime) {
        this.breakTime = breakTime;
    }
    
    public double getOvertimeHours() {
        return overtimeHours;
    }
    
    public void setOvertimeHours(double overtimeHours) {
        this.overtimeHours = overtimeHours;
    }
    
    public String getLocationIn() {
        return locationIn;
    }
    
    public void setLocationIn(String locationIn) {
        this.locationIn = locationIn;
    }
    
    public String getLocationOut() {
        return locationOut;
    }
    
    public void setLocationOut(String locationOut) {
        this.locationOut = locationOut;
    }
    
    public String getNotes() {
        return notes;
    }
    
    public void setNotes(String notes) {
        this.notes = notes;
    }
    
    public boolean isHoliday() {
        return isHoliday;
    }
    
    public void setHoliday(boolean holiday) {
        isHoliday = holiday;
    }
    
    public boolean isWeekend() {
        return isWeekend;
    }
    
    public void setWeekend(boolean weekend) {
        isWeekend = weekend;
    }
    
    // Utility methods
    public boolean hasClockIn() {
        return clockIn != null && !clockIn.isEmpty();
    }
    
    public boolean hasClockOut() {
        return clockOut != null && !clockOut.isEmpty();
    }
    
    public boolean isComplete() {
        return hasClockIn() && hasClockOut();
    }
    
    public boolean isLate() {
        return "Late".equalsIgnoreCase(clockInStatus);
    }
    
    public boolean isOnTime() {
        return "On Time".equalsIgnoreCase(clockInStatus);
    }
    
    public boolean isEarlyDeparture() {
        return "Early".equalsIgnoreCase(clockOutStatus);
    }
    
    public String getFormattedDate() {
        if (attendanceDate != null) {
            try {
                java.text.SimpleDateFormat inputFormat = new java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault());
                java.text.SimpleDateFormat outputFormat = new java.text.SimpleDateFormat("MMM dd, yyyy", java.util.Locale.getDefault());
                java.util.Date date = inputFormat.parse(attendanceDate);
                return outputFormat.format(date);
            } catch (java.text.ParseException e) {
                return attendanceDate;
            }
        }
        return "";
    }
    
    public String getFormattedClockIn() {
        if (clockIn != null) {
            try {
                java.text.SimpleDateFormat inputFormat = new java.text.SimpleDateFormat("HH:mm:ss", java.util.Locale.getDefault());
                java.text.SimpleDateFormat outputFormat = new java.text.SimpleDateFormat("hh:mm a", java.util.Locale.getDefault());
                java.util.Date time = inputFormat.parse(clockIn);
                return outputFormat.format(time);
            } catch (java.text.ParseException e) {
                return clockIn;
            }
        }
        return "N/A";
    }
    
    public String getFormattedClockOut() {
        if (clockOut != null) {
            try {
                java.text.SimpleDateFormat inputFormat = new java.text.SimpleDateFormat("HH:mm:ss", java.util.Locale.getDefault());
                java.text.SimpleDateFormat outputFormat = new java.text.SimpleDateFormat("hh:mm a", java.util.Locale.getDefault());
                java.util.Date time = inputFormat.parse(clockOut);
                return outputFormat.format(time);
            } catch (java.text.ParseException e) {
                return clockOut;
            }
        }
        return "N/A";
    }
    
    public String getFormattedTotalHours() {
        if (totalHours > 0) {
            int hours = (int) totalHours;
            int minutes = (int) ((totalHours - hours) * 60);
            return String.format(java.util.Locale.getDefault(), "%dh %dm", hours, minutes);
        }
        return "0h 0m";
    }
    
    public String getStatusColor() {
        if (isLate()) {
            return "#F87171"; // Red
        } else if (isOnTime()) {
            return "#10B981"; // Green
        } else {
            return "#6B7280"; // Gray
        }
    }
    
    @Override
    public String toString() {
        return "AttendanceSummary{" +
                "id=" + id +
                ", employeeId='" + employeeId + '\'' +
                ", attendanceDate='" + attendanceDate + '\'' +
                ", clockIn='" + clockIn + '\'' +
                ", clockOut='" + clockOut + '\'' +
                ", clockInStatus='" + clockInStatus + '\'' +
                ", totalHours=" + totalHours +
                '}';
    }
}
