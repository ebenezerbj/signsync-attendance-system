package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;
import com.signsync.attendance.model.DashboardStats;
import com.signsync.attendance.model.AttendanceSummary;
import com.signsync.attendance.model.GamificationData;
import java.util.List;

public class EmployeeDashboardResponse extends BaseResponse {
    @SerializedName("stats")
    private DashboardStats stats;

    @SerializedName("recent_attendance")
    private List<AttendanceSummary> recentAttendance;

    @SerializedName("gamification")
    private GamificationData gamification;

    public DashboardStats getStats() {
        return stats;
    }

    public void setStats(DashboardStats stats) {
        this.stats = stats;
    }

    public List<AttendanceSummary> getRecentAttendance() {
        return recentAttendance;
    }

    public void setRecentAttendance(List<AttendanceSummary> recentAttendance) {
        this.recentAttendance = recentAttendance;
    }

    public GamificationData getGamification() {
        return gamification;
    }

    public void setGamification(GamificationData gamification) {
        this.gamification = gamification;
    }
}
