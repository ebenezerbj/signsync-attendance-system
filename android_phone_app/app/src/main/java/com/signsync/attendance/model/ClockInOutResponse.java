package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class ClockInOutResponse {
    
    @SerializedName("success")
    private boolean success;
    
    @SerializedName("message")
    private String message;
    
    @SerializedName("data")
    private ClockInOutData data;
    
    // Constructors
    public ClockInOutResponse() {}
    
    public ClockInOutResponse(boolean success, String message, ClockInOutData data) {
        this.success = success;
        this.message = message;
        this.data = data;
    }
    
    // Getters and setters
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
    
    public ClockInOutData getData() {
        return data;
    }
    
    public void setData(ClockInOutData data) {
        this.data = data;
    }
    
    // Inner class for the data object
    public static class ClockInOutData {
        @SerializedName("employee_id")
        private String employeeId;
        
        @SerializedName("clock_in_time")
        private String clockInTime;
        
        @SerializedName("clock_out_time")
        private String clockOutTime;
        
        @SerializedName("status")
        private String status;
        
        @SerializedName("action")
        private String action;
        
        // Constructors
        public ClockInOutData() {}
        
        // Getters and setters
        public String getEmployeeId() {
            return employeeId;
        }
        
        public void setEmployeeId(String employeeId) {
            this.employeeId = employeeId;
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
    }
}
