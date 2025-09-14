package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class KioskValidationRequest {
    
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("latitude")
    private double latitude;
    
    @SerializedName("longitude")
    private double longitude;
    
    public KioskValidationRequest() {}
    
    public KioskValidationRequest(String employeeId, double latitude, double longitude) {
        this.employeeId = employeeId;
        this.latitude = latitude;
        this.longitude = longitude;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
    
    public double getLatitude() {
        return latitude;
    }
    
    public void setLatitude(double latitude) {
        this.latitude = latitude;
    }
    
    public double getLongitude() {
        return longitude;
    }
    
    public void setLongitude(double longitude) {
        this.longitude = longitude;
    }
}
