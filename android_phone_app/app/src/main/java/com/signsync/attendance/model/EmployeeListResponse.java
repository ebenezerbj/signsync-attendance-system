package com.signsync.attendance.model;

import java.util.List;

public class EmployeeListResponse {
    private boolean success;
    private String message;
    private List<Employee> employees;
    
    public EmployeeListResponse() {}

    // Getters
    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public List<Employee> getEmployees() { return employees; }

    // Setters
    public void setSuccess(boolean success) { this.success = success; }
    public void setMessage(String message) { this.message = message; }
    public void setEmployees(List<Employee> employees) { this.employees = employees; }
}
