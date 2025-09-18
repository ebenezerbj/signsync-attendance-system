package com.signsync.attendance.utils;

import android.content.Context;
import android.content.SharedPreferences;

public class SharedPreferencesManager {
    private static final String PREF_NAME = "SignSyncAttendance";
    private static final String KEY_AUTH_TOKEN = "auth_token";
    private static final String KEY_REFRESH_TOKEN = "refresh_token";
    private static final String KEY_USER_ID = "user_id";
    private static final String KEY_USER_NAME = "user_name";
    private static final String KEY_USER_EMAIL = "user_email";
    private static final String KEY_USER_ROLE = "user_role";
    private static final String KEY_IS_LOGGED_IN = "is_logged_in";

    private SharedPreferences preferences;
    private SharedPreferences.Editor editor;

    public SharedPreferencesManager(Context context) {
        preferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
        editor = preferences.edit();
    }

    public void saveAuthToken(String token) {
        editor.putString(KEY_AUTH_TOKEN, token);
        editor.apply();
    }

    public String getAuthToken() {
        return preferences.getString(KEY_AUTH_TOKEN, null);
    }

    public void saveRefreshToken(String refreshToken) {
        editor.putString(KEY_REFRESH_TOKEN, refreshToken);
        editor.apply();
    }

    public String getRefreshToken() {
        return preferences.getString(KEY_REFRESH_TOKEN, null);
    }

    public void saveUserData(int userId, String name, String email, String role) {
        editor.putInt(KEY_USER_ID, userId);
        editor.putString(KEY_USER_NAME, name);
        editor.putString(KEY_USER_EMAIL, email);
        editor.putString(KEY_USER_ROLE, role);
        editor.putBoolean(KEY_IS_LOGGED_IN, true);
        editor.apply();
    }

    public int getUserId() {
        return preferences.getInt(KEY_USER_ID, -1);
    }

    public String getEmployeeId() {
        return String.valueOf(getUserId());
    }

    public String getUserName() {
        return preferences.getString(KEY_USER_NAME, "");
    }

    public String getUserEmail() {
        return preferences.getString(KEY_USER_EMAIL, "");
    }

    public String getUserRole() {
        return preferences.getString(KEY_USER_ROLE, "");
    }

    public boolean isLoggedIn() {
        return preferences.getBoolean(KEY_IS_LOGGED_IN, false);
    }

    public void logout() {
        editor.clear();
        editor.apply();
    }

    public com.signsync.attendance.model.Employee getCurrentEmployee() {
        // Create a basic employee object from stored preferences
        if (!isLoggedIn()) {
            return null;
        }
        
        com.signsync.attendance.model.Employee employee = new com.signsync.attendance.model.Employee();
        employee.setEmployeeId(getEmployeeId());
        employee.setName(getUserName());
        employee.setEmail(getUserEmail());
        employee.setRole(getUserRole());
        return employee;
    }
}
