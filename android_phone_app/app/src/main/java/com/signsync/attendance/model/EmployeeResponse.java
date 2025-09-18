package com.signsync.attendance.model;

public class EmployeeResponse {
    private boolean success;
    private String message;
    private Employee employee;
    
    public EmployeeResponse() {}

    // Getters
    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public Employee getEmployee() { return employee; }

    // Setters
    public void setSuccess(boolean success) { this.success = success; }
    public void setMessage(String message) { this.message = message; }
    public void setEmployee(Employee employee) { this.employee = employee; }
}
