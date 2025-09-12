package com.signsync.wearable;

public class HealthData {
    private float heartRate;
    private float stressLevel;
    private long timestamp;

    public HealthData(float heartRate, float stressLevel, long timestamp) {
        this.heartRate = heartRate;
        this.stressLevel = stressLevel;
        this.timestamp = timestamp;
    }

    public float getHeartRate() {
        return heartRate;
    }

    public float getStressLevel() {
        return stressLevel;
    }

    public long getTimestamp() {
        return timestamp;
    }
}