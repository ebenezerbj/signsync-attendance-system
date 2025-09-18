package com.signsync.attendance.network;

import com.signsync.attendance.model.AttendanceRecord;
import com.signsync.attendance.model.LoginResponse;
import com.signsync.attendance.model.ClockInOutResponse;
import com.signsync.attendance.network.response.AttendanceResponse;
import com.signsync.attendance.network.response.BranchResponse;
import com.signsync.attendance.network.response.EmployeeListResponse;
import com.signsync.attendance.network.response.EmployeeResponse;
import com.signsync.attendance.network.response.ReportsResponse;
import com.signsync.attendance.network.response.BaseResponse;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.Field;
import retrofit2.http.FormUrlEncoded;
import retrofit2.http.POST;
import retrofit2.http.Header;
import retrofit2.http.GET;

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
    
    // Fixed clockIn method to match KioskModeActivity usage (String parameters)
    @FormUrlEncoded
    @POST("clockinout_api.php")
    Call<AttendanceResponse> clockIn(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("snapshot") String snapshot,
        @Field("reason") String reason
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
    
    // Enhanced clock in/out methods with photo, reason, WiFi, and beacon verification
    @FormUrlEncoded
    @POST("enhanced_clockinout_api.php")
    Call<ClockInOutResponse> enhancedClockIn(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("snapshot") String snapshot,
        @Field("reason") String reason,
        @Field("wifi_networks") String wifiNetworks,
        @Field("beacon_data") String beaconData
    );
    
    @FormUrlEncoded
    @POST("enhanced_clockinout_api.php")
    Call<ClockInOutResponse> enhancedClockOut(
        @Field("employee_id") String employeeId,
        @Field("action") String action,
        @Field("latitude") double latitude,
        @Field("longitude") double longitude,
        @Field("snapshot") String snapshot,
        @Field("reason") String reason,
        @Field("wifi_networks") String wifiNetworks,
        @Field("beacon_data") String beaconData
    );
    
    @FormUrlEncoded
    @POST("employee_details_api.php")
    Call<EmployeeResponse> getEmployeeDetails(
        @Field("employee_id") String employeeId
    );
    
    @FormUrlEncoded
    @POST("attendance_status_api.php")
    Call<AttendanceRecord> getTodayAttendanceStatus(
        @Field("employee_id") String employeeId,
        @Field("date") String date
    );
    
    // Missing methods needed by Activities
    @FormUrlEncoded
    @POST("validate_location_api.php")
    Call<BranchResponse> validateLocation(
        @Field("latitude") double latitude,
        @Field("longitude") double longitude
    );
    
    @GET("employees_api.php")
    Call<EmployeeListResponse> getAllEmployees();
    
    @FormUrlEncoded
    @POST("employee_api.php")
    Call<BaseResponse> deleteEmployee(
        @Field("employee_id") int employeeId
    );
    
    @FormUrlEncoded
    @POST("reports_api.php")
    Call<ReportsResponse> getReports(
        @Field("report_type") String reportType,
        @Field("start_date") String startDate,
        @Field("end_date") String endDate
    );

    // Additional methods needed by fragments
    @FormUrlEncoded
    @POST("employee_dashboard_api.php")
    Call<com.signsync.attendance.model.EmployeeDashboardResponse> getEmployeeDashboard(
        @Field("employee_id") String employeeId
    );

    @FormUrlEncoded
    @POST("pulse_survey_api.php")
    Call<ApiResponse<String>> submitPulseSurvey(
        @Field("employee_id") String employeeId,
        @Field("mood") String mood,
        @Field("feedback") String feedback
    );

    @FormUrlEncoded
    @POST("employee_attendance_api.php")
    Call<ApiResponse<java.util.List<com.signsync.attendance.model.AttendanceSummary>>> getEmployeeAttendance(
        @Field("employee_id") String employeeId,
        @Field("month") int month,
        @Field("year") int year
    );

    @FormUrlEncoded
    @POST("employee_corrections_api.php")
    Call<ApiResponse<java.util.List<com.signsync.attendance.model.AttendanceCorrection>>> getEmployeeCorrections(
        @Field("employee_id") String employeeId
    );

    @FormUrlEncoded
    @POST("cancel_correction_api.php")
    Call<ApiResponse<String>> cancelCorrection(
        @Field("correction_id") int correctionId
    );

    @FormUrlEncoded
    @POST("employee_leave_api.php")
    Call<ApiResponse<java.util.List<com.signsync.attendance.model.LeaveRequest>>> getEmployeeLeaveRequests(
        @Field("employee_id") String employeeId
    );

    @FormUrlEncoded
    @POST("cancel_leave_api.php")
    Call<ApiResponse<String>> cancelLeaveRequest(
        @Field("leave_id") int leaveId
    );

    @FormUrlEncoded
    @POST("employee_profile_api.php")
    Call<ApiResponse<com.signsync.attendance.model.Employee>> getEmployeeProfile(
        @Field("employee_id") String employeeId
    );

    @POST("update_employee_profile_api.php")
    Call<ApiResponse<String>> updateEmployeeProfile(
        @Body com.signsync.attendance.model.Employee employee
    );

    @FormUrlEncoded
    @POST("attendance_history_api.php")
    Call<ApiResponse<java.util.List<com.signsync.attendance.model.AttendanceRecord>>> getAttendanceHistory(
        @Field("employee_id") String employeeId,
        @Field("month") int month,
        @Field("year") int year
    );

    @FormUrlEncoded
    @POST("change_pin_api.php")
    Call<ApiResponse<String>> changeEmployeePin(
        @Field("employee_id") String employeeId,
        @Field("current_pin") String currentPin,
        @Field("new_pin") String newPin,
        @Field("is_first_login") boolean isFirstLogin
    );

    // Service endpoints for background clock-out checks and audit logging
    @FormUrlEncoded
    @POST("check_missed_clockouts_api.php")
    Call<com.signsync.attendance.network.response.ClockOutCheckResponse> checkMissedClockOuts(
        @Header("Authorization") String authHeader,
        @Field("date") String date
    );

    @FormUrlEncoded
    @POST("send_clockout_reminder_api.php")
    Call<com.signsync.attendance.network.response.BaseResponse> sendClockOutReminder(
        @Header("Authorization") String authHeader,
        @Field("employee_id") String employeeId,
        @Field("phone_number") String phoneNumber
    );

    @FormUrlEncoded
    @POST("save_audit_log_api.php")
    Call<com.signsync.attendance.network.response.BaseResponse> saveAuditLog(
        @Header("Authorization") String authHeader,
        @Field("event_type") String eventType,
        @Field("message") String message,
        @Field("timestamp") String timestamp
    );
}
