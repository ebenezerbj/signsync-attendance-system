package com.signsync.attendance.model;

import java.util.List;

public class ReportsResponse {
    private boolean success;
    private String message;
    private List<Object> reports;
    
    public ReportsResponse() {}

    // Getters
    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public List<Object> getReports() { return reports; }

    // Setters
    public void setSuccess(boolean success) { this.success = success; }
    public void setMessage(String message) { this.message = message; }
    public void setReports(List<Object> reports) { this.reports = reports; }
}
