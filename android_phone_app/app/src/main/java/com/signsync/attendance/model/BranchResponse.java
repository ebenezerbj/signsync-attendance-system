package com.signsync.attendance.model;

public class BranchResponse {
    private boolean success;
    private String message;
    private Branch branch;
    
    public BranchResponse() {}

    // Getters
    public boolean isSuccess() { return success; }
    public String getMessage() { return message; }
    public Branch getBranch() { return branch; }

    // Setters
    public void setSuccess(boolean success) { this.success = success; }
    public void setMessage(String message) { this.message = message; }
    public void setBranch(Branch branch) { this.branch = branch; }
}
