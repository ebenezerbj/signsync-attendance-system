package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class LoginResponse {
    @SerializedName("success")
    private boolean success;
    
    @SerializedName("message")
    private String message;
    
    @SerializedName("is_first_login")
    private boolean firstLogin;
    
    @SerializedName("employee_id")
    private String employeeId;
    
    // Constructors
    public LoginResponse() {
    }
    
    public LoginResponse(boolean success, String message, boolean firstLogin, String employeeId) {
        this.success = success;
        this.message = message;
        this.firstLogin = firstLogin;
        this.employeeId = employeeId;
    }
    
    // Getters and Setters
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
    
    public boolean isFirstLogin() {
        return firstLogin;
    }
    
    public void setFirstLogin(boolean firstLogin) {
        this.firstLogin = firstLogin;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
}
