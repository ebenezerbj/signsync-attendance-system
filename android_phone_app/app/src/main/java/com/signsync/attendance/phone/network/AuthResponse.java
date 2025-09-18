package com.signsync.attendance.phone.network;

public class AuthResponse {
    private boolean success;
    private String message;
    private String employeeName;
    private String department;
    private String token;
    private String employeeId;
    
    public boolean isSuccess() {
        return success;
    }
    
    public String getMessage() {
        return message;
    }
    
    public String getEmployeeName() {
        return employeeName;
    }
    
    public String getDepartment() {
        return department;
    }
    
    public String getToken() {
        return token;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    // Setters
    public void setSuccess(boolean success) {
        this.success = success;
    }
    
    public void setMessage(String message) {
        this.message = message;
    }
    
    public void setEmployeeName(String employeeName) {
        this.employeeName = employeeName;
    }
    
    public void setDepartment(String department) {
        this.department = department;
    }
    
    public void setToken(String token) {
        this.token = token;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
}
