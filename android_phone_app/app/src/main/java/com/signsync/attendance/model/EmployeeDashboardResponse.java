package com.signsync.attendance.model;

import java.util.List;

public class EmployeeDashboardResponse {
    private boolean success;
    private String message;
    private String todayStatus;
    private String todayTime;
    private double weekHours;
    private double monthHours;
    private int leaveBalance;
    private GamificationData gamificationData;
    private List<AttendanceSummary> recentAttendance;

    public EmployeeDashboardResponse() {}

    public boolean isSuccess() {
        return success;
    }

    public void setSuccess(boolean success) {
        this.success = success;
    }

    public String getMessage() {
        return message;
    }

    public void setMessage(String message) {
        this.message = message;
    }

    public String getTodayStatus() {
        return todayStatus;
    }

    public void setTodayStatus(String todayStatus) {
        this.todayStatus = todayStatus;
    }

    public String getTodayTime() {
        return todayTime;
    }

    public void setTodayTime(String todayTime) {
        this.todayTime = todayTime;
    }

    public double getWeekHours() {
        return weekHours;
    }

    public void setWeekHours(double weekHours) {
        this.weekHours = weekHours;
    }

    public double getMonthHours() {
        return monthHours;
    }

    public void setMonthHours(double monthHours) {
        this.monthHours = monthHours;
    }

    public int getLeaveBalance() {
        return leaveBalance;
    }

    public void setLeaveBalance(int leaveBalance) {
        this.leaveBalance = leaveBalance;
    }

    public GamificationData getGamificationData() {
        return gamificationData;
    }

    public void setGamificationData(GamificationData gamificationData) {
        this.gamificationData = gamificationData;
    }

    public List<AttendanceSummary> getRecentAttendance() {
        return recentAttendance;
    }

    public void setRecentAttendance(List<AttendanceSummary> recentAttendance) {
        this.recentAttendance = recentAttendance;
    }
}
