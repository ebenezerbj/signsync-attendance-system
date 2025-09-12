package com.signsync.wearable;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.os.Binder;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.Log;

import androidx.core.app.NotificationCompat;

import java.util.concurrent.atomic.AtomicBoolean;

public class HealthMonitoringService extends Service implements SensorEventListener {

    private static final String TAG = "HealthMonitoringService";
    private static final String CHANNEL_ID = "HEALTH_MONITORING_CHANNEL";
    private static final int NOTIFICATION_ID = 1001;
    
    // Sensor management
    private SensorManager sensorManager;
    private Sensor heartRateSensor;
    private Sensor stepCounterSensor;
    private Sensor temperatureSensor;
    
    // Current health data
    private HealthData currentHealthData;
    private final AtomicBoolean isMonitoring = new AtomicBoolean(false);
    
    // Data collection and transmission
    private Handler dataHandler;
    private Runnable dataCollectionRunnable;
    private SharedPreferences sharedPrefs;
    
    // Service binding
    private final IBinder binder = new LocalBinder();
    
    // API communication
    private ApiClient apiClient;
    private final AtomicBoolean isConnectedToServer = new AtomicBoolean(false);
    
    // Employee information
    private String currentEmployeeId = "";
    
    // Stress detection thresholds
    private static final int HIGH_HEART_RATE_THRESHOLD = 100;
    private static final float HIGH_STRESS_THRESHOLD = 7.0f;
    private static final long STRESS_ALERT_COOLDOWN = 300000; // 5 minutes
    private long lastStressAlertTime = 0;

    public class LocalBinder extends Binder {
        HealthMonitoringService getService() {
            return HealthMonitoringService.this;
        }
    }

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "HealthMonitoringService onCreate");
        
        // Initialize components
        initializeSensors();
        initializeNotificationChannel();
        initializeDataHandling();
        
        // Initialize shared preferences
        sharedPrefs = getSharedPreferences("SignSyncConfig", Context.MODE_PRIVATE);
        
        // Initialize API client
        apiClient = new ApiClient(this);
        
        // Initialize current health data
        currentHealthData = new HealthData();
    }

    private void initializeSensors() {
        sensorManager = (SensorManager) getSystemService(Context.SENSOR_SERVICE);
        
        if (sensorManager != null) {
            heartRateSensor = sensorManager.getDefaultSensor(Sensor.TYPE_HEART_RATE);
            stepCounterSensor = sensorManager.getDefaultSensor(Sensor.TYPE_STEP_COUNTER);
            temperatureSensor = sensorManager.getDefaultSensor(Sensor.TYPE_AMBIENT_TEMPERATURE);
            
            Log.d(TAG, "Heart rate sensor available: " + (heartRateSensor != null));
            Log.d(TAG, "Step counter sensor available: " + (stepCounterSensor != null));
            Log.d(TAG, "Temperature sensor available: " + (temperatureSensor != null));
        }
    }

    private void initializeNotificationChannel() {
        NotificationManager notificationManager = getSystemService(NotificationManager.class);
        if (notificationManager != null) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "Health Monitoring",
                NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("Continuous health monitoring for SignSync");
            notificationManager.createNotificationChannel(channel);
        }
    }

    private void initializeDataHandling() {
        dataHandler = new Handler(Looper.getMainLooper());
        
        dataCollectionRunnable = new Runnable() {
            @Override
            public void run() {
                if (isMonitoring.get()) {
                    processAndTransmitData();
                    // Schedule next data transmission in 30 seconds
                    dataHandler.postDelayed(this, 30000);
                }
            }
        };
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "HealthMonitoringService onStartCommand");
        
        // Start as foreground service
        startForeground(NOTIFICATION_ID, createNotification("Health monitoring ready"));
        
        return START_STICKY;
    }

    @Override
    public IBinder onBind(Intent intent) {
        Log.d(TAG, "HealthMonitoringService onBind");
        return binder;
    }

    public void startMonitoring(String employeeId) {
        Log.d(TAG, "Starting health monitoring for employee: " + employeeId);
        
        this.currentEmployeeId = employeeId;
        currentHealthData.setEmployeeId(employeeId);
        
        if (isMonitoring.compareAndSet(false, true)) {
            // Register sensor listeners
            registerSensorListeners();
            
            // Start data collection and transmission
            dataHandler.post(dataCollectionRunnable);
            
            // Update notification
            updateNotification("Health monitoring active");
            
            Log.d(TAG, "Health monitoring started successfully");
        }
    }

    public void stopMonitoring() {
        Log.d(TAG, "Stopping health monitoring");
        
        if (isMonitoring.compareAndSet(true, false)) {
            // Unregister sensor listeners
            unregisterSensorListeners();
            
            // Stop data collection
            dataHandler.removeCallbacks(dataCollectionRunnable);
            
            // Update notification
            updateNotification("Health monitoring stopped");
            
            Log.d(TAG, "Health monitoring stopped successfully");
        }
    }

    private void registerSensorListeners() {
        if (sensorManager != null) {
            if (heartRateSensor != null) {
                sensorManager.registerListener(this, heartRateSensor, SensorManager.SENSOR_DELAY_NORMAL);
                Log.d(TAG, "Heart rate sensor listener registered");
            }
            
            if (stepCounterSensor != null) {
                sensorManager.registerListener(this, stepCounterSensor, SensorManager.SENSOR_DELAY_NORMAL);
                Log.d(TAG, "Step counter sensor listener registered");
            }
            
            if (temperatureSensor != null) {
                sensorManager.registerListener(this, temperatureSensor, SensorManager.SENSOR_DELAY_NORMAL);
                Log.d(TAG, "Temperature sensor listener registered");
            }
        }
    }

    private void unregisterSensorListeners() {
        if (sensorManager != null) {
            sensorManager.unregisterListener(this);
            Log.d(TAG, "All sensor listeners unregistered");
        }
    }

    @Override
    public void onSensorChanged(SensorEvent event) {
        if (!isMonitoring.get()) return;
        
        switch (event.sensor.getType()) {
            case Sensor.TYPE_HEART_RATE:
                if (event.values.length > 0 && event.values[0] > 0) {
                    int heartRate = Math.round(event.values[0]);
                    currentHealthData.setHeartRate(heartRate);
                    Log.d(TAG, "Heart rate updated: " + heartRate + " bpm");
                    
                    // Calculate stress level based on heart rate
                    updateStressLevel(heartRate);
                }
                break;
                
            case Sensor.TYPE_STEP_COUNTER:
                if (event.values.length > 0) {
                    int steps = Math.round(event.values[0]);
                    currentHealthData.setSteps(steps);
                    Log.d(TAG, "Steps updated: " + steps);
                }
                break;
                
            case Sensor.TYPE_AMBIENT_TEMPERATURE:
                if (event.values.length > 0) {
                    float temperature = event.values[0];
                    currentHealthData.setTemperature(temperature);
                    Log.d(TAG, "Temperature updated: " + temperature + "°C");
                }
                break;
        }
    }

    @Override
    public void onAccuracyChanged(Sensor sensor, int accuracy) {
        Log.d(TAG, "Sensor accuracy changed: " + sensor.getName() + ", accuracy: " + accuracy);
    }

    private void updateStressLevel(int heartRate) {
        // Simple stress calculation based on heart rate
        // In a real implementation, this would use more sophisticated algorithms
        float stressLevel = 0.0f;
        
        if (heartRate < 60) {
            stressLevel = 1.0f; // Very low stress
        } else if (heartRate < 70) {
            stressLevel = 2.0f; // Low stress
        } else if (heartRate < 80) {
            stressLevel = 3.0f; // Normal
        } else if (heartRate < 90) {
            stressLevel = 5.0f; // Moderate stress
        } else if (heartRate < 100) {
            stressLevel = 7.0f; // High stress
        } else {
            stressLevel = 9.0f; // Very high stress
        }
        
        currentHealthData.setStressLevel(stressLevel);
        
        // Check for stress alert
        checkStressAlert(heartRate, stressLevel);
    }

    private void checkStressAlert(int heartRate, float stressLevel) {
        long currentTime = System.currentTimeMillis();
        
        // Check if stress level is high and cooldown period has passed
        if ((heartRate >= HIGH_HEART_RATE_THRESHOLD || stressLevel >= HIGH_STRESS_THRESHOLD) &&
            (currentTime - lastStressAlertTime) >= STRESS_ALERT_COOLDOWN) {
            
            Log.w(TAG, "High stress detected! HR: " + heartRate + ", Stress: " + stressLevel);
            
            // Send immediate stress alert
            sendStressAlert();
            lastStressAlertTime = currentTime;
            
            // Update notification with alert
            updateNotification("⚠️ High stress detected!");
        }
    }

    private void sendStressAlert() {
        if (apiClient != null && !currentEmployeeId.isEmpty()) {
            apiClient.sendStressAlert(currentEmployeeId, currentHealthData, new ApiClient.ApiCallback() {
                @Override
                public void onSuccess(String response) {
                    Log.d(TAG, "Stress alert sent successfully");
                    isConnectedToServer.set(true);
                }

                @Override
                public void onError(String error) {
                    Log.e(TAG, "Failed to send stress alert: " + error);
                    isConnectedToServer.set(false);
                }
            });
        }
    }

    private void processAndTransmitData() {
        if (currentHealthData != null && !currentEmployeeId.isEmpty()) {
            // Update timestamp
            currentHealthData.setTimestamp(System.currentTimeMillis());
            
            Log.d(TAG, "Transmitting health data: " + currentHealthData.toString());
            
            // Send data to server
            if (apiClient != null) {
                apiClient.sendHealthData(currentHealthData, new ApiClient.ApiCallback() {
                    @Override
                    public void onSuccess(String response) {
                        Log.d(TAG, "Health data transmitted successfully");
                        isConnectedToServer.set(true);
                    }

                    @Override
                    public void onError(String error) {
                        Log.e(TAG, "Failed to transmit health data: " + error);
                        isConnectedToServer.set(false);
                        
                        // Store data locally for later transmission
                        storeDataLocally(currentHealthData);
                    }
                });
            }
        }
    }

    private void storeDataLocally(HealthData data) {
        // Store data in local database or shared preferences for later transmission
        // This ensures data is not lost when network is unavailable
        SharedPreferences.Editor editor = sharedPrefs.edit();
        editor.putString("last_health_data", data.toString());
        editor.putLong("last_health_timestamp", data.getTimestamp());
        editor.apply();
        
        Log.d(TAG, "Health data stored locally for later transmission");
    }

    private Notification createNotification(String content) {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 0, 
            notificationIntent, PendingIntent.FLAG_IMMUTABLE);

        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("SignSync Health Monitor")
            .setContentText(content)
            .setSmallIcon(R.drawable.ic_health_monitoring)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .build();
    }

    private void updateNotification(String content) {
        NotificationManager notificationManager = getSystemService(NotificationManager.class);
        if (notificationManager != null) {
            notificationManager.notify(NOTIFICATION_ID, createNotification(content));
        }
    }

    // Public methods for MainActivity
    public boolean isMonitoring() {
        return isMonitoring.get();
    }

    public HealthData getLatestHealthData() {
        return currentHealthData;
    }

    public boolean isConnectedToServer() {
        return isConnectedToServer.get();
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "HealthMonitoringService onDestroy");
        
        // Stop monitoring
        stopMonitoring();
        
        // Cleanup
        if (dataHandler != null) {
            dataHandler.removeCallbacks(dataCollectionRunnable);
        }
    }
}
