package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class DashboardStats {
    
    @SerializedName("total_attendance_days")
    private int totalAttendanceDays;
    
    @SerializedName("present_days")
    private int presentDays;
    
    @SerializedName("absent_days")
    private int absentDays;
    
    @SerializedName("late_days")
    private int lateDays;
    
    @SerializedName("early_departures")
    private int earlyDepartures;
    
    @SerializedName("total_hours")
    private double totalHours;
    
    @SerializedName("overtime_hours")
    private double overtimeHours;
    
    @SerializedName("leave_balance")
    private int leaveBalance;
    
    @SerializedName("pending_leave_requests")
    private int pendingLeaveRequests;
    
    @SerializedName("pending_corrections")
    private int pendingCorrections;
    
    @SerializedName("this_month_hours")
    private double thisMonthHours;
    
    @SerializedName("attendance_percentage")
    private double attendancePercentage;
    
    @SerializedName("punctuality_score")
    private double punctualityScore;
    
    @SerializedName("last_clock_in")
    private String lastClockIn;
    
    @SerializedName("last_clock_out")
    private String lastClockOut;
    
    @SerializedName("is_clocked_in")
    private boolean isClockedIn;
    
    public DashboardStats() {}
    
    // Getters and Setters
    public int getTotalAttendanceDays() {
        return totalAttendanceDays;
    }
    
    public void setTotalAttendanceDays(int totalAttendanceDays) {
        this.totalAttendanceDays = totalAttendanceDays;
    }
    
    public int getPresentDays() {
        return presentDays;
    }
    
    public void setPresentDays(int presentDays) {
        this.presentDays = presentDays;
    }
    
    public int getAbsentDays() {
        return absentDays;
    }
    
    public void setAbsentDays(int absentDays) {
        this.absentDays = absentDays;
    }
    
    public int getLateDays() {
        return lateDays;
    }
    
    public void setLateDays(int lateDays) {
        this.lateDays = lateDays;
    }
    
    public int getEarlyDepartures() {
        return earlyDepartures;
    }
    
    public void setEarlyDepartures(int earlyDepartures) {
        this.earlyDepartures = earlyDepartures;
    }
    
    public double getTotalHours() {
        return totalHours;
    }
    
    public void setTotalHours(double totalHours) {
        this.totalHours = totalHours;
    }
    
    public double getOvertimeHours() {
        return overtimeHours;
    }
    
    public void setOvertimeHours(double overtimeHours) {
        this.overtimeHours = overtimeHours;
    }
    
    public int getLeaveBalance() {
        return leaveBalance;
    }
    
    public void setLeaveBalance(int leaveBalance) {
        this.leaveBalance = leaveBalance;
    }
    
    public int getPendingLeaveRequests() {
        return pendingLeaveRequests;
    }
    
    public void setPendingLeaveRequests(int pendingLeaveRequests) {
        this.pendingLeaveRequests = pendingLeaveRequests;
    }
    
    public int getPendingCorrections() {
        return pendingCorrections;
    }
    
    public void setPendingCorrections(int pendingCorrections) {
        this.pendingCorrections = pendingCorrections;
    }
    
    public double getThisMonthHours() {
        return thisMonthHours;
    }
    
    public void setThisMonthHours(double thisMonthHours) {
        this.thisMonthHours = thisMonthHours;
    }
    
    public double getAttendancePercentage() {
        return attendancePercentage;
    }
    
    public void setAttendancePercentage(double attendancePercentage) {
        this.attendancePercentage = attendancePercentage;
    }
    
    public double getPunctualityScore() {
        return punctualityScore;
    }
    
    public void setPunctualityScore(double punctualityScore) {
        this.punctualityScore = punctualityScore;
    }
    
    public String getLastClockIn() {
        return lastClockIn;
    }
    
    public void setLastClockIn(String lastClockIn) {
        this.lastClockIn = lastClockIn;
    }
    
    public String getLastClockOut() {
        return lastClockOut;
    }
    
    public void setLastClockOut(String lastClockOut) {
        this.lastClockOut = lastClockOut;
    }
    
    public boolean isClockedIn() {
        return isClockedIn;
    }
    
    public void setClockedIn(boolean clockedIn) {
        isClockedIn = clockedIn;
    }
    
    // Utility methods
    public String getFormattedTotalHours() {
        int hours = (int) totalHours;
        int minutes = (int) ((totalHours - hours) * 60);
        return String.format("%dh %dm", hours, minutes);
    }
    
    public String getFormattedOvertimeHours() {
        int hours = (int) overtimeHours;
        int minutes = (int) ((overtimeHours - hours) * 60);
        return String.format("%dh %dm", hours, minutes);
    }
    
    public String getFormattedThisMonthHours() {
        int hours = (int) thisMonthHours;
        int minutes = (int) ((thisMonthHours - hours) * 60);
        return String.format("%dh %dm", hours, minutes);
    }
    
    public String getAttendancePercentageString() {
        return String.format("%.1f%%", attendancePercentage);
    }
    
    public String getPunctualityScoreString() {
        return String.format("%.1f%%", punctualityScore);
    }
}
