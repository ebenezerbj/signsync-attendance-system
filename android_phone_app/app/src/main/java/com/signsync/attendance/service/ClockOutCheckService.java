package com.signsync.attendance.service;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import androidx.core.app.NotificationCompat;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import com.signsync.attendance.R;
import com.signsync.attendance.activity.MainActivity;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.response.ClockOutCheckResponse;
import com.signsync.attendance.utils.SessionManager;
import retrofit2.Call;
import retrofit2.Response;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.List;
import java.util.Locale;

/**
 * Background service to check for employees who forgot to clock out
 * Runs periodically to send notifications and SMS alerts
 */
public class ClockOutCheckService extends Worker {
    
    private static final String NOTIFICATION_CHANNEL_ID = "clock_out_check";
    private static final String NOTIFICATION_CHANNEL_NAME = "Clock Out Reminders";
    private static final int NOTIFICATION_ID = 1001;
    
    private Context context;
    private SessionManager sessionManager;
    private AttendanceApiService apiService;
    
    public ClockOutCheckService(Context context, WorkerParameters params) {
        super(context, params);
        this.context = context;
        this.sessionManager = new SessionManager(context);
        this.apiService = ApiClient.getApiService();
    }
    
    @Override
    public Result doWork() {
        try {
            // Only run if user is admin or has appropriate permissions
            if (!sessionManager.isAdmin() && !sessionManager.isManager()) {
                return Result.success();
            }
            
            checkMissedClockOuts();
            return Result.success();
            
        } catch (Exception e) {
            android.util.Log.e("ClockOutCheckService", "Error checking clock outs", e);
            return Result.retry();
        }
    }
    
    private void checkMissedClockOuts() {
        String currentDate = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(new Date());
        
        Call<ClockOutCheckResponse> call = apiService.checkMissedClockOuts(
                "Bearer " + sessionManager.getAuthToken(),
                currentDate
        );
        
        try {
            Response<ClockOutCheckResponse> response = call.execute();
            
            if (response.isSuccessful() && response.body() != null) {
                ClockOutCheckResponse checkResponse = response.body();
                
                if (checkResponse.isSuccess()) {
                    List<ClockOutCheckResponse.MissedClockOut> missedClockOuts = checkResponse.getMissedClockOuts();
                    if (missedClockOuts != null && !missedClockOuts.isEmpty()) {
                    
                    // Send notification to admin/manager
                    sendAdminNotification(missedClockOuts.size());
                    
                    // Send SMS notifications if enabled
                    if (sessionManager.isSMSNotificationEnabled()) {
                        sendSMSNotifications(missedClockOuts);
                    }
                    
                    // Log the event
                    logClockOutCheck(missedClockOuts);
                    }
                }
            }
            
        } catch (Exception e) {
            android.util.Log.e("ClockOutCheckService", "Failed to check missed clock outs", e);
        }
    }
    
    private void sendAdminNotification(int missedCount) {
        createNotificationChannel();
        
        String title = "Clock-Out Reminder";
        String message = missedCount + " employee(s) forgot to clock out today";
        
        Intent intent = new Intent(context, MainActivity.class);
        intent.putExtra("show_attendance_alerts", true);
        
        PendingIntent pendingIntent = PendingIntent.getActivity(
                context, 
                0, 
                intent, 
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        
        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, NOTIFICATION_CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_notification)
                .setContentTitle(title)
                .setContentText(message)
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setAutoCancel(true)
                .setContentIntent(pendingIntent)
                .setStyle(new NotificationCompat.BigTextStyle()
                        .bigText(message + ". Tap to view details and send reminders."));
        
        NotificationManager notificationManager = 
                (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        
        if (notificationManager != null) {
            notificationManager.notify(NOTIFICATION_ID, builder.build());
        }
    }
    
    private void sendSMSNotifications(List<ClockOutCheckResponse.MissedClockOut> missedClockOuts) {
        for (ClockOutCheckResponse.MissedClockOut missedClockOut : missedClockOuts) {
            String employeeId = String.valueOf(missedClockOut.getEmployeeId());
            String employeeName = missedClockOut.getEmployeeName();
            String phoneNumber = missedClockOut.getPhoneNumber();
            
            if (phoneNumber != null && !phoneNumber.isEmpty()) {
                sendSMSReminder(employeeId, employeeName, phoneNumber);
            }
        }
    }
    
    private void sendSMSReminder(String employeeId, String employeeName, String phoneNumber) {
        Call<com.signsync.attendance.network.response.BaseResponse> call = 
                apiService.sendClockOutReminder(
                        "Bearer " + sessionManager.getAuthToken(),
                        employeeId,
                        phoneNumber
                );
        
        try {
            Response<com.signsync.attendance.network.response.BaseResponse> response = call.execute();
            
            if (response.isSuccessful() && response.body() != null) {
                if (response.body().isSuccess()) {
                    android.util.Log.i("ClockOutCheckService", 
                            "SMS reminder sent to " + employeeName + " (" + employeeId + ")");
                } else {
                    android.util.Log.w("ClockOutCheckService", 
                            "Failed to send SMS to " + employeeName + ": " + response.body().getMessage());
                }
            }
            
        } catch (Exception e) {
            android.util.Log.e("ClockOutCheckService", 
                    "Error sending SMS to " + employeeName, e);
        }
    }
    
    private void logClockOutCheck(List<ClockOutCheckResponse.MissedClockOut> missedClockOuts) {
        StringBuilder logMessage = new StringBuilder();
        logMessage.append("Clock-out check completed at ")
                  .append(new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(new Date()))
                  .append(". Found ")
                  .append(missedClockOuts.size())
                  .append(" missed clock-outs:\n");
        
        for (ClockOutCheckResponse.MissedClockOut missedClockOut : missedClockOuts) {
            logMessage.append("- ")
                      .append(missedClockOut.getEmployeeName())
                      .append(" (")
                      .append(missedClockOut.getEmployeeId())
                      .append(") - Clock-in: ")
                      .append(missedClockOut.getClockInTime())
                      .append("\n");
        }
        
        android.util.Log.i("ClockOutCheckService", logMessage.toString());
        
        // You could also save this to a local database or send to server for audit trail
        saveAuditLog(logMessage.toString());
    }
    
    private void saveAuditLog(String logMessage) {
        // Save audit log to local database or send to server
        Call<com.signsync.attendance.network.response.BaseResponse> call = 
                apiService.saveAuditLog(
                        "Bearer " + sessionManager.getAuthToken(),
                        "CLOCK_OUT_CHECK",
                        logMessage,
                        new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(new Date())
                );
        
        try {
            call.execute();
        } catch (Exception e) {
            android.util.Log.e("ClockOutCheckService", "Failed to save audit log", e);
        }
    }
    
    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    NOTIFICATION_CHANNEL_ID,
                    NOTIFICATION_CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Notifications for clock-out reminders and attendance alerts");
            
            NotificationManager notificationManager = 
                    (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
            
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(channel);
            }
        }
    }
    
    /**
     * Schedule periodic clock-out checks
     */
    public static void scheduleClockOutCheck(Context context) {
        androidx.work.PeriodicWorkRequest workRequest = 
                new androidx.work.PeriodicWorkRequest.Builder(
                        ClockOutCheckService.class,
                        2, // Check every 2 hours
                        java.util.concurrent.TimeUnit.HOURS
                )
                .setInitialDelay(1, java.util.concurrent.TimeUnit.HOURS) // Start after 1 hour
                .addTag("clock_out_check")
                .build();
        
        androidx.work.WorkManager.getInstance(context)
                .enqueueUniquePeriodicWork(
                        "clock_out_check",
                        androidx.work.ExistingPeriodicWorkPolicy.REPLACE,
                        workRequest
                );
        
        android.util.Log.i("ClockOutCheckService", "Clock-out check scheduled");
    }
    
    /**
     * Cancel scheduled clock-out checks
     */
    public static void cancelClockOutCheck(Context context) {
        androidx.work.WorkManager.getInstance(context)
                .cancelAllWorkByTag("clock_out_check");
        
        android.util.Log.i("ClockOutCheckService", "Clock-out check cancelled");
    }
}
