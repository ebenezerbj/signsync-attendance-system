package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class KioskValidationResponse {
    
    @SerializedName("valid")
    private boolean valid;
    
    @SerializedName("employee_name")
    private String employeeName;
    
    @SerializedName("branch_name")
    private String branchName;
    
    @SerializedName("shift_name")
    private String shiftName;
    
    @SerializedName("can_clock_in")
    private boolean canClockIn;
    
    @SerializedName("can_clock_out")
    private boolean canClockOut;
    
    @SerializedName("last_action")
    private String lastAction;
    
    @SerializedName("message")
    private String message;
    
    @SerializedName("location_valid")
    private boolean locationValid;
    
    @SerializedName("distance_meters")
    private double distanceMeters;
    
    public KioskValidationResponse() {}
    
    public boolean isValid() {
        return valid;
    }
    
    public void setValid(boolean valid) {
        this.valid = valid;
    }
    
    public String getEmployeeName() {
        return employeeName;
    }
    
    public void setEmployeeName(String employeeName) {
        this.employeeName = employeeName;
    }
    
    public String getBranchName() {
        return branchName;
    }
    
    public void setBranchName(String branchName) {
        this.branchName = branchName;
    }
    
    public String getShiftName() {
        return shiftName;
    }
    
    public void setShiftName(String shiftName) {
        this.shiftName = shiftName;
    }
    
    public boolean isCanClockIn() {
        return canClockIn;
    }
    
    public void setCanClockIn(boolean canClockIn) {
        this.canClockIn = canClockIn;
    }
    
    public boolean isCanClockOut() {
        return canClockOut;
    }
    
    public void setCanClockOut(boolean canClockOut) {
        this.canClockOut = canClockOut;
    }
    
    public String getLastAction() {
        return lastAction;
    }
    
    public void setLastAction(String lastAction) {
        this.lastAction = lastAction;
    }
    
    public String getMessage() {
        return message;
    }
    
    public void setMessage(String message) {
        this.message = message;
    }
    
    public boolean isLocationValid() {
        return locationValid;
    }
    
    public void setLocationValid(boolean locationValid) {
        this.locationValid = locationValid;
    }
    
    public double getDistanceMeters() {
        return distanceMeters;
    }
    
    public void setDistanceMeters(double distanceMeters) {
        this.distanceMeters = distanceMeters;
    }
}
