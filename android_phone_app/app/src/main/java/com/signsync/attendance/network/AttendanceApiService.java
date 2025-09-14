package com.signsync.attendance.network;

import com.signsync.attendance.model.AttendanceRecord;
import com.signsync.attendance.model.LoginResponse;
import com.signsync.attendance.model.ClockInOutResponse;

import retrofit2.Call;
import retrofit2.http.Field;
import retrofit2.http.FormUrlEncoded;
import retrofit2.http.POST;

public interface AttendanceApiService {
    
    @FormUrlEncoded
    @POST("login_api.php")
    Call<LoginResponse> login(
        @Field("employee_id") String employeeId,
        @Field("pin") String pin
    );
    
    @FormUrlEncoded
    @POST("change_pin_api.php")
    Call<Object> changePin(
        @Field("employee_id") String employeeId,
        @Field("current_pin") String currentPin,
        @Field("new_pin") String newPin
    );
    
    // Legacy clock in/out methods for backward compatibility
    @FormUrlEncoded
    @POST("clockinout_api.php")
    Call<ClockInOutResponse> clockIn(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("branch_id") int branchId
    );
    
    @FormUrlEncoded
    @POST("clockinout_api.php")
    Call<ClockInOutResponse> clockOut(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("branch_id") int branchId
    );
    
    // Enhanced clock in/out methods with photo and reason
    @FormUrlEncoded
    @POST("enhanced_clockinout_api.php")
    Call<ClockInOutResponse> enhancedClockIn(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("snapshot") String snapshot,
        @Field("reason") String reason
    );
    
    @FormUrlEncoded
    @POST("enhanced_clockinout_api.php")
    Call<ClockInOutResponse> enhancedClockOut(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("snapshot") String snapshot,
        @Field("reason") String reason
    );
    
    @FormUrlEncoded
    @POST("employee_details_api.php")
    Call<Object> getEmployeeDetails(
        @Field("employee_id") String employeeId
    );
    
    @FormUrlEncoded
    @POST("attendance_status_api.php")
    Call<AttendanceRecord> getTodayAttendanceStatus(
        @Field("employee_id") String employeeId,
        @Field("date") String date
    );
}
