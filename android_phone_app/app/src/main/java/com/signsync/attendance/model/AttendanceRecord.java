package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class AttendanceRecord {
    
    @SerializedName("id")
    private int id;
    
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("date")
    private String date;
    
    @SerializedName("clock_in_time")
    private String clockInTime;
    
    @SerializedName("clock_out_time")
    private String clockOutTime;
    
    @SerializedName("status")
    private String status;
    
    @SerializedName("action")
    private String action;
    
    @SerializedName("latitude")
    private double latitude;
    
    @SerializedName("longitude")
    private double longitude;
    
    @SerializedName("branch_id")
    private String branchId;
    
    @SerializedName("branch_name")
    private String branchName;
    
    @SerializedName("hours_worked")
    private double hoursWorked;
    
    @SerializedName("reason")
    private String reason;
    
    @SerializedName("timestamp")
    private String timestamp;
    
    @SerializedName("is_clocked_in")
    private boolean isClockedIn;
    
    // Constructors
    public AttendanceRecord() {}
    
    public AttendanceRecord(String employeeId, String action) {
        this.employeeId = employeeId;
        this.action = action;
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
    
    public String getDate() {
        return date;
    }
    
    public void setDate(String date) {
        this.date = date;
    }
    
    public String getClockInTime() {
        return clockInTime;
    }
    
    public void setClockInTime(String clockInTime) {
        this.clockInTime = clockInTime;
    }
    
    public String getClockOutTime() {
        return clockOutTime;
    }
    
    public void setClockOutTime(String clockOutTime) {
        this.clockOutTime = clockOutTime;
    }
    
    public String getStatus() {
        return status;
    }
    
    public void setStatus(String status) {
        this.status = status;
    }
    
    public String getAction() {
        return action;
    }
    
    public void setAction(String action) {
        this.action = action;
    }
    
    public double getLatitude() {
        return latitude;
    }
    
    public void setLatitude(double latitude) {
        this.latitude = latitude;
    }
    
    public double getLongitude() {
        return longitude;
    }
    
    public void setLongitude(double longitude) {
        this.longitude = longitude;
    }
    
    public String getBranchId() {
        return branchId;
    }
    
    public void setBranchId(String branchId) {
        this.branchId = branchId;
    }
    
    public String getBranchName() {
        return branchName;
    }
    
    public void setBranchName(String branchName) {
        this.branchName = branchName;
    }
    
    public double getHoursWorked() {
        return hoursWorked;
    }
    
    public void setHoursWorked(double hoursWorked) {
        this.hoursWorked = hoursWorked;
    }
    
    public String getReason() {
        return reason;
    }
    
    public void setReason(String reason) {
        this.reason = reason;
    }
    
    public String getTimestamp() {
        return timestamp;
    }
    
    public void setTimestamp(String timestamp) {
        this.timestamp = timestamp;
    }
    
    public boolean isClockedIn() {
        return isClockedIn;
    }
    
    public void setClockedIn(boolean clockedIn) {
        isClockedIn = clockedIn;
    }
}
