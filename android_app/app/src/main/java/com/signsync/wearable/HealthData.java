package com.signsync.wearable;

public class HealthData {
    private float heartRate;
    private float stressLevel;
    private long timestamp;
    private String employeeId;
    private int steps;
    private float temperature;
    private String activityType;
    private double locationLat;
    private double locationLng;

    public HealthData() {
        // Default constructor
    }

    public HealthData(float heartRate, float stressLevel, long timestamp) {
        this.heartRate = heartRate;
        this.stressLevel = stressLevel;
        this.timestamp = timestamp;
    }

    // Getters
    public float getHeartRate() {
        return heartRate;
    }

    public float getStressLevel() {
        return stressLevel;
    }

    public long getTimestamp() {
        return timestamp;
    }

    public String getEmployeeId() {
        return employeeId;
    }

    public int getSteps() {
        return steps;
    }

    public float getTemperature() {
        return temperature;
    }

    public String getActivityType() {
        return activityType;
    }

    public double getLocationLat() {
        return locationLat;
    }

    public double getLocationLng() {
        return locationLng;
    }

    // Setters
    public void setHeartRate(float heartRate) {
        this.heartRate = heartRate;
    }

    public void setHeartRate(int heartRate) {
        this.heartRate = (float) heartRate;
    }

    public void setStressLevel(float stressLevel) {
        this.stressLevel = stressLevel;
    }

    public void setStressLevel(double stressLevel) {
        this.stressLevel = (float) stressLevel;
    }

    public void setTimestamp(long timestamp) {
        this.timestamp = timestamp;
    }

    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }

    public void setSteps(int steps) {
        this.steps = steps;
    }

    public void setTemperature(float temperature) {
        this.temperature = temperature;
    }

    public void setActivityType(String activityType) {
        this.activityType = activityType;
    }

    public void setLocationLat(double locationLat) {
        this.locationLat = locationLat;
    }

    public void setLocationLng(double locationLng) {
        this.locationLng = locationLng;
    }
}