package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class EmployeeDetails {
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("first_name")
    private String firstName;
    
    @SerializedName("last_name")
    private String lastName;
    
    @SerializedName("full_name")
    private String fullName;
    
    @SerializedName("contact_number")
    private String contactNumber;
    
    @SerializedName("email")
    private String email;
    
    @SerializedName("department")
    private String department;
    
    @SerializedName("position")
    private String position;
    
    @SerializedName("branch_id")
    private int branchId;
    
    @SerializedName("branch_name")
    private String branchName;
    
    @SerializedName("branch_location")
    private String branchLocation;
    
    @SerializedName("status")
    private String status;
    
    @SerializedName("hire_date")
    private String hireDate;
    
    // Constructors
    public EmployeeDetails() {
    }
    
    // Getters and Setters
    public String getEmployeeId() {
        return employeeId;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
    
    public String getFirstName() {
        return firstName;
    }
    
    public void setFirstName(String firstName) {
        this.firstName = firstName;
    }
    
    public String getLastName() {
        return lastName;
    }
    
    public void setLastName(String lastName) {
        this.lastName = lastName;
    }
    
    public String getFullName() {
        return fullName;
    }
    
    public void setFullName(String fullName) {
        this.fullName = fullName;
    }
    
    public String getContactNumber() {
        return contactNumber;
    }
    
    public void setContactNumber(String contactNumber) {
        this.contactNumber = contactNumber;
    }
    
    public String getEmail() {
        return email;
    }
    
    public void setEmail(String email) {
        this.email = email;
    }
    
    public String getDepartment() {
        return department;
    }
    
    public void setDepartment(String department) {
        this.department = department;
    }
    
    public String getPosition() {
        return position;
    }
    
    public void setPosition(String position) {
        this.position = position;
    }
    
    public int getBranchId() {
        return branchId;
    }
    
    public void setBranchId(int branchId) {
        this.branchId = branchId;
    }
    
    public String getBranchName() {
        return branchName;
    }
    
    public void setBranchName(String branchName) {
        this.branchName = branchName;
    }
    
    public String getBranchLocation() {
        return branchLocation;
    }
    
    public void setBranchLocation(String branchLocation) {
        this.branchLocation = branchLocation;
    }
    
    public String getStatus() {
        return status;
    }
    
    public void setStatus(String status) {
        this.status = status;
    }
    
    public String getHireDate() {
        return hireDate;
    }
    
    public void setHireDate(String hireDate) {
        this.hireDate = hireDate;
    }
}
