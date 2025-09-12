package com.signsync.wearable;

public class HealthData {
    private int heartRate;
    private float stressLevel;
    private float temperature;
    private int steps;
    private long timestamp;
    private String employeeId;

    public HealthData() {
        this.timestamp = System.currentTimeMillis();
    }

    public HealthData(int heartRate, float stressLevel, float temperature, int steps, String employeeId) {
        this.heartRate = heartRate;
        this.stressLevel = stressLevel;
        this.temperature = temperature;
        this.steps = steps;
        this.employeeId = employeeId;
        this.timestamp = System.currentTimeMillis();
    }

    // Getters and setters
    public int getHeartRate() {
        return heartRate;
    }

    public void setHeartRate(int heartRate) {
        this.heartRate = heartRate;
    }

    public float getStressLevel() {
        return stressLevel;
    }

    public void setStressLevel(float stressLevel) {
        this.stressLevel = stressLevel;
    }

    public float getTemperature() {
        return temperature;
    }

    public void setTemperature(float temperature) {
        this.temperature = temperature;
    }

    public int getSteps() {
        return steps;
    }

    public void setSteps(int steps) {
        this.steps = steps;
    }

    public long getTimestamp() {
        return timestamp;
    }

    public void setTimestamp(long timestamp) {
        this.timestamp = timestamp;
    }

    public String getEmployeeId() {
        return employeeId;
    }

    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }

    @Override
    public String toString() {
        return "HealthData{" +
                "heartRate=" + heartRate +
                ", stressLevel=" + stressLevel +
                ", temperature=" + temperature +
                ", steps=" + steps +
                ", timestamp=" + timestamp +
                ", employeeId='" + employeeId + '\'' +
                '}';
    }
}
