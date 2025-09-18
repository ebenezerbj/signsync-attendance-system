package com.signsync.attendance.phone.network;

public class AttendanceResponse {
    private boolean success;
    private String message;
    private String timestamp;
    private String action;
    private String location;
    
    public boolean isSuccess() {
        return success;
    }
    
    public String getMessage() {
        return message;
    }
    
    public String getTimestamp() {
        return timestamp;
    }
    
    public String getAction() {
        return action;
    }
    
    public String getLocation() {
        return location;
    }
    
    // Setters
    public void setSuccess(boolean success) {
        this.success = success;
    }
    
    public void setMessage(String message) {
        this.message = message;
    }
    
    public void setTimestamp(String timestamp) {
        this.timestamp = timestamp;
    }
    
    public void setAction(String action) {
        this.action = action;
    }
    
    public void setLocation(String location) {
        this.location = location;
    }
}
