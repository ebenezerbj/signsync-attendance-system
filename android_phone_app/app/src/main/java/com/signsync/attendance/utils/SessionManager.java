package com.signsync.attendance.utils;

import android.content.Context;
import android.content.SharedPreferences;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import androidx.security.crypto.EncryptedSharedPreferences;
import androidx.security.crypto.MasterKey;
import java.io.IOException;
import java.security.GeneralSecurityException;

public class SessionManager {
    
    private static final String PREF_NAME = "SignSyncSession";
    private static final String KEY_IS_LOGGED_IN = "isLoggedIn";
    private static final String KEY_USER_ID = "userId";
    private static final String KEY_USER_NAME = "userName";
    private static final String KEY_USER_EMAIL = "userEmail";
    private static final String KEY_USER_ROLE = "userRole";
    private static final String KEY_EMPLOYEE_ID = "employeeId";
    private static final String KEY_BRANCH_ID = "branchId";
    private static final String KEY_BRANCH_NAME = "branchName";
    private static final String KEY_AUTH_TOKEN = "authToken";
    private static final String KEY_REFRESH_TOKEN = "refreshToken";
    private static final String KEY_TOKEN_EXPIRY = "tokenExpiry";
    private static final String KEY_LAST_LOGIN = "lastLogin";
    private static final String KEY_DEVICE_ID = "deviceId";
    private static final String KEY_PIN_HASH = "pinHash";
    private static final String KEY_BIOMETRIC_ENABLED = "biometricEnabled";
    private static final String KEY_AUTO_CLOCK_OUT = "autoClockOut";
    private static final String KEY_LOCATION_TRACKING = "locationTracking";
    private static final String KEY_OFFLINE_MODE = "offlineMode";
    private static final String KEY_SYNC_PENDING = "syncPending";
    private static final String KEY_LAST_SYNC = "lastSync";
    private static final String KEY_SMS_NOTIFICATIONS = "smsNotifications";
    
    private SharedPreferences preferences;
    private SharedPreferences.Editor editor;
    private Context context;
    
    public SessionManager(Context context) {
        this.context = context;
        initializePreferences();
    }
    
    private void initializePreferences() {
        try {
            // Create master key for encryption
            KeyGenParameterSpec keyGenParameterSpec = new KeyGenParameterSpec.Builder(
                    MasterKey.DEFAULT_MASTER_KEY_ALIAS,
                    KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
                    .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                    .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                    .setKeySize(256)
                    .build();
            
            MasterKey masterKey = new MasterKey.Builder(context)
                    .setKeyGenParameterSpec(keyGenParameterSpec)
                    .build();
            
            // Create encrypted shared preferences
            preferences = EncryptedSharedPreferences.create(
                    context,
                    PREF_NAME,
                    masterKey,
                    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            );
            
        } catch (GeneralSecurityException | IOException e) {
            // Fallback to regular SharedPreferences if encryption fails
            preferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        }
        
        editor = preferences.edit();
    }
    
    // Login session management
    public void createLoginSession(String userId, String userName, String userEmail, String userRole, 
                                 String employeeId, String authToken, String refreshToken) {
        editor.putBoolean(KEY_IS_LOGGED_IN, true);
        editor.putString(KEY_USER_ID, userId);
        editor.putString(KEY_USER_NAME, userName);
        editor.putString(KEY_USER_EMAIL, userEmail);
        editor.putString(KEY_USER_ROLE, userRole);
        editor.putString(KEY_EMPLOYEE_ID, employeeId);
        editor.putString(KEY_AUTH_TOKEN, authToken);
        editor.putString(KEY_REFRESH_TOKEN, refreshToken);
        editor.putLong(KEY_LAST_LOGIN, System.currentTimeMillis());
        editor.apply();
    }
    
    public void updateUserInfo(String userName, String userEmail, String userRole) {
        editor.putString(KEY_USER_NAME, userName);
        editor.putString(KEY_USER_EMAIL, userEmail);
        editor.putString(KEY_USER_ROLE, userRole);
        editor.apply();
    }
    
    public void updateBranchInfo(String branchId, String branchName) {
        editor.putString(KEY_BRANCH_ID, branchId);
        editor.putString(KEY_BRANCH_NAME, branchName);
        editor.apply();
    }
    
    public void updateTokens(String authToken, String refreshToken, long expiryTime) {
        editor.putString(KEY_AUTH_TOKEN, authToken);
        editor.putString(KEY_REFRESH_TOKEN, refreshToken);
        editor.putLong(KEY_TOKEN_EXPIRY, expiryTime);
        editor.apply();
    }
    
    public void logout() {
        editor.clear();
        editor.apply();
    }
    
    // Session validation
    public boolean isLoggedIn() {
        return preferences.getBoolean(KEY_IS_LOGGED_IN, false);
    }
    
    public boolean isTokenValid() {
        long expiryTime = preferences.getLong(KEY_TOKEN_EXPIRY, 0);
        return System.currentTimeMillis() < expiryTime;
    }
    
    // User data getters
    public String getUserId() {
        return preferences.getString(KEY_USER_ID, null);
    }
    
    public String getUserName() {
        return preferences.getString(KEY_USER_NAME, null);
    }
    
    public String getUserEmail() {
        return preferences.getString(KEY_USER_EMAIL, null);
    }
    
    public String getUserRole() {
        return preferences.getString(KEY_USER_ROLE, null);
    }
    
    public String getEmployeeId() {
        return preferences.getString(KEY_EMPLOYEE_ID, null);
    }
    
    public String getBranchId() {
        return preferences.getString(KEY_BRANCH_ID, null);
    }
    
    public String getBranchName() {
        return preferences.getString(KEY_BRANCH_NAME, null);
    }
    
    public String getAuthToken() {
        return preferences.getString(KEY_AUTH_TOKEN, null);
    }
    
    public String getRefreshToken() {
        return preferences.getString(KEY_REFRESH_TOKEN, null);
    }
    
    public long getTokenExpiry() {
        return preferences.getLong(KEY_TOKEN_EXPIRY, 0);
    }
    
    public long getLastLogin() {
        return preferences.getLong(KEY_LAST_LOGIN, 0);
    }
    
    // Device management
    public void setDeviceId(String deviceId) {
        editor.putString(KEY_DEVICE_ID, deviceId);
        editor.apply();
    }
    
    public String getDeviceId() {
        return preferences.getString(KEY_DEVICE_ID, null);
    }
    
    // Security settings
    public void setPinHash(String pinHash) {
        editor.putString(KEY_PIN_HASH, pinHash);
        editor.apply();
    }
    
    public String getPinHash() {
        return preferences.getString(KEY_PIN_HASH, null);
    }
    
    public void setBiometricEnabled(boolean enabled) {
        editor.putBoolean(KEY_BIOMETRIC_ENABLED, enabled);
        editor.apply();
    }
    
    public boolean isBiometricEnabled() {
        return preferences.getBoolean(KEY_BIOMETRIC_ENABLED, false);
    }
    
    // App settings
    public void setAutoClockOut(boolean enabled) {
        editor.putBoolean(KEY_AUTO_CLOCK_OUT, enabled);
        editor.apply();
    }
    
    public boolean isAutoClockOutEnabled() {
        return preferences.getBoolean(KEY_AUTO_CLOCK_OUT, true);
    }
    
    public void setLocationTracking(boolean enabled) {
        editor.putBoolean(KEY_LOCATION_TRACKING, enabled);
        editor.apply();
    }
    
    public boolean isLocationTrackingEnabled() {
        return preferences.getBoolean(KEY_LOCATION_TRACKING, true);
    }
    
    public void setOfflineMode(boolean enabled) {
        editor.putBoolean(KEY_OFFLINE_MODE, enabled);
        editor.apply();
    }
    
    public boolean isOfflineModeEnabled() {
        return preferences.getBoolean(KEY_OFFLINE_MODE, false);
    }
    
    // Sync management
    public void setSyncPending(boolean pending) {
        editor.putBoolean(KEY_SYNC_PENDING, pending);
        editor.apply();
    }
    
    public boolean isSyncPending() {
        return preferences.getBoolean(KEY_SYNC_PENDING, false);
    }
    
    public void setLastSync(long timestamp) {
        editor.putLong(KEY_LAST_SYNC, timestamp);
        editor.apply();
    }
    
    public long getLastSync() {
        return preferences.getLong(KEY_LAST_SYNC, 0);
    }
    
    // SMS notifications
    public void setSMSNotificationEnabled(boolean enabled) {
        editor.putBoolean(KEY_SMS_NOTIFICATIONS, enabled);
        editor.apply();
    }
    
    public boolean isSMSNotificationEnabled() {
        return preferences.getBoolean(KEY_SMS_NOTIFICATIONS, false);
    }
    
    // Role-based checks
    public boolean isAdmin() {
        String role = getUserRole();
        return "admin".equalsIgnoreCase(role) || "super_admin".equalsIgnoreCase(role);
    }
    
    public boolean isManager() {
        String role = getUserRole();
        return "manager".equalsIgnoreCase(role) || isAdmin();
    }
    
    public boolean isEmployee() {
        String role = getUserRole();
        return "employee".equalsIgnoreCase(role);
    }
    
    // Utility methods
    public boolean hasValidSession() {
        return isLoggedIn() && isTokenValid() && getUserId() != null;
    }
    
    public void refreshLastActivity() {
        editor.putLong("lastActivity", System.currentTimeMillis());
        editor.apply();
    }
    
    public long getLastActivity() {
        return preferences.getLong("lastActivity", 0);
    }
    
    public boolean isSessionExpired(long timeoutMillis) {
        long lastActivity = getLastActivity();
        return (System.currentTimeMillis() - lastActivity) > timeoutMillis;
    }
    
    // Clear specific data
    public void clearTokens() {
        editor.remove(KEY_AUTH_TOKEN);
        editor.remove(KEY_REFRESH_TOKEN);
        editor.remove(KEY_TOKEN_EXPIRY);
        editor.apply();
    }
    
    public void clearUserData() {
        editor.remove(KEY_USER_ID);
        editor.remove(KEY_USER_NAME);
        editor.remove(KEY_USER_EMAIL);
        editor.remove(KEY_USER_ROLE);
        editor.remove(KEY_EMPLOYEE_ID);
        editor.apply();
    }
    
    // Export session data for debugging
    public String getSessionInfo() {
        StringBuilder sb = new StringBuilder();
        sb.append("Session Info:\n");
        sb.append("Logged In: ").append(isLoggedIn()).append("\n");
        sb.append("User ID: ").append(getUserId()).append("\n");
        sb.append("User Name: ").append(getUserName()).append("\n");
        sb.append("User Role: ").append(getUserRole()).append("\n");
        sb.append("Employee ID: ").append(getEmployeeId()).append("\n");
        sb.append("Branch: ").append(getBranchName()).append("\n");
        sb.append("Token Valid: ").append(isTokenValid()).append("\n");
        sb.append("Last Login: ").append(new java.util.Date(getLastLogin())).append("\n");
        sb.append("Biometric Enabled: ").append(isBiometricEnabled()).append("\n");
        sb.append("Location Tracking: ").append(isLocationTrackingEnabled()).append("\n");
        sb.append("Offline Mode: ").append(isOfflineModeEnabled()).append("\n");
        sb.append("Sync Pending: ").append(isSyncPending()).append("\n");
        sb.append("SMS Notifications: ").append(isSMSNotificationEnabled()).append("\n");
        return sb.toString();
    }
}
