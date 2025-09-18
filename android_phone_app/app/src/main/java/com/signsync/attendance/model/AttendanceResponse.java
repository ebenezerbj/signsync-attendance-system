package com.signsync.attendance.model;

public class AttendanceResponse {
    private boolean success;
    private String message;
    private String status;
    private AttendanceRecord data;
    
    public AttendanceResponse() {}

    // Getters
    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public String getStatus() { return status; }
    public AttendanceRecord getData() { return data; }

    // Setters
    public void setSuccess(boolean success) { this.success = success; }
    public void setMessage(String message) { this.message = message; }
    public void setStatus(String status) { this.status = status; }
    public void setData(AttendanceRecord data) { this.data = data; }
}
