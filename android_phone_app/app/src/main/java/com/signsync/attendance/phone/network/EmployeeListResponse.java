package com.signsync.attendance.phone.network;

import java.util.List;

public class EmployeeListResponse {
    private boolean success;
    private String message;
    private List<Employee> employees;
    
    public boolean isSuccess() {
        return success;
    }
    
    public String getMessage() {
        return message;
    }
    
    public List<Employee> getEmployees() {
        return employees;
    }
    
    public static class Employee {
        private String employeeId;
        private String name;
        private String department;
        private String position;
        private String status;
        
        // Getters and setters
        public String getEmployeeId() { return employeeId; }
        public String getName() { return name; }
        public String getDepartment() { return department; }
        public String getPosition() { return position; }
        public String getStatus() { return status; }
        
        public void setEmployeeId(String employeeId) { this.employeeId = employeeId; }
        public void setName(String name) { this.name = name; }
        public void setDepartment(String department) { this.department = department; }
        public void setPosition(String position) { this.position = position; }
        public void setStatus(String status) { this.status = status; }
    }
}
