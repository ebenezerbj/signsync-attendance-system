package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;

public class ReportsResponse extends BaseResponse {
    
    @SerializedName("total_employees")
    private int totalEmployees;
    
    @SerializedName("present_today")
    private int presentToday;
    
    @SerializedName("absent_today")
    private int absentToday;
    
    @SerializedName("late_arrivals")
    private int lateArrivals;
    
    @SerializedName("early_departures")
    private int earlyDepartures;
    
    @SerializedName("overtime_hours")
    private double overtimeHours;
    
    @SerializedName("average_work_hours")
    private double averageWorkHours;
    
    @SerializedName("attendance_percentage")
    private double attendancePercentage;
    
    @SerializedName("department_summary")
    private java.util.List<DepartmentSummary> departmentSummary;
    
    @SerializedName("weekly_data")
    private java.util.List<WeeklyData> weeklyData;
    
    @SerializedName("monthly_data")
    private java.util.List<MonthlyData> monthlyData;
    
    public ReportsResponse() {
        super();
    }
    
    // Getters and Setters
    public int getTotalEmployees() {
        return totalEmployees;
    }
    
    public void setTotalEmployees(int totalEmployees) {
        this.totalEmployees = totalEmployees;
    }
    
    public int getPresentToday() {
        return presentToday;
    }
    
    public void setPresentToday(int presentToday) {
        this.presentToday = presentToday;
    }
    
    public int getAbsentToday() {
        return absentToday;
    }
    
    public void setAbsentToday(int absentToday) {
        this.absentToday = absentToday;
    }
    
    public int getLateArrivals() {
        return lateArrivals;
    }
    
    public void setLateArrivals(int lateArrivals) {
        this.lateArrivals = lateArrivals;
    }
    
    public int getEarlyDepartures() {
        return earlyDepartures;
    }
    
    public void setEarlyDepartures(int earlyDepartures) {
        this.earlyDepartures = earlyDepartures;
    }
    
    public double getOvertimeHours() {
        return overtimeHours;
    }
    
    public void setOvertimeHours(double overtimeHours) {
        this.overtimeHours = overtimeHours;
    }
    
    public double getAverageWorkHours() {
        return averageWorkHours;
    }
    
    public void setAverageWorkHours(double averageWorkHours) {
        this.averageWorkHours = averageWorkHours;
    }
    
    public double getAttendancePercentage() {
        return attendancePercentage;
    }
    
    public void setAttendancePercentage(double attendancePercentage) {
        this.attendancePercentage = attendancePercentage;
    }
    
    public java.util.List<DepartmentSummary> getDepartmentSummary() {
        return departmentSummary;
    }
    
    public void setDepartmentSummary(java.util.List<DepartmentSummary> departmentSummary) {
        this.departmentSummary = departmentSummary;
    }
    
    public java.util.List<WeeklyData> getWeeklyData() {
        return weeklyData;
    }
    
    public void setWeeklyData(java.util.List<WeeklyData> weeklyData) {
        this.weeklyData = weeklyData;
    }
    
    public java.util.List<MonthlyData> getMonthlyData() {
        return monthlyData;
    }
    
    public void setMonthlyData(java.util.List<MonthlyData> monthlyData) {
        this.monthlyData = monthlyData;
    }
    
    // Nested classes for complex data structures
    public static class DepartmentSummary {
        @SerializedName("department_name")
        private String departmentName;
        
        @SerializedName("total_employees")
        private int totalEmployees;
        
        @SerializedName("present_count")
        private int presentCount;
        
        @SerializedName("attendance_percentage")
        private double attendancePercentage;
        
        // Getters and Setters
        public String getDepartmentName() {
            return departmentName;
        }
        
        public void setDepartmentName(String departmentName) {
            this.departmentName = departmentName;
        }
        
        public int getTotalEmployees() {
            return totalEmployees;
        }
        
        public void setTotalEmployees(int totalEmployees) {
            this.totalEmployees = totalEmployees;
        }
        
        public int getPresentCount() {
            return presentCount;
        }
        
        public void setPresentCount(int presentCount) {
            this.presentCount = presentCount;
        }
        
        public double getAttendancePercentage() {
            return attendancePercentage;
        }
        
        public void setAttendancePercentage(double attendancePercentage) {
            this.attendancePercentage = attendancePercentage;
        }
    }
    
    public static class WeeklyData {
        @SerializedName("week_start")
        private String weekStart;
        
        @SerializedName("week_end")
        private String weekEnd;
        
        @SerializedName("average_attendance")
        private double averageAttendance;
        
        @SerializedName("total_hours")
        private double totalHours;
        
        // Getters and Setters
        public String getWeekStart() {
            return weekStart;
        }
        
        public void setWeekStart(String weekStart) {
            this.weekStart = weekStart;
        }
        
        public String getWeekEnd() {
            return weekEnd;
        }
        
        public void setWeekEnd(String weekEnd) {
            this.weekEnd = weekEnd;
        }
        
        public double getAverageAttendance() {
            return averageAttendance;
        }
        
        public void setAverageAttendance(double averageAttendance) {
            this.averageAttendance = averageAttendance;
        }
        
        public double getTotalHours() {
            return totalHours;
        }
        
        public void setTotalHours(double totalHours) {
            this.totalHours = totalHours;
        }
    }
    
    public static class MonthlyData {
        @SerializedName("month")
        private String month;
        
        @SerializedName("year")
        private int year;
        
        @SerializedName("average_attendance")
        private double averageAttendance;
        
        @SerializedName("total_working_days")
        private int totalWorkingDays;
        
        @SerializedName("total_present_days")
        private int totalPresentDays;
        
        // Getters and Setters
        public String getMonth() {
            return month;
        }
        
        public void setMonth(String month) {
            this.month = month;
        }
        
        public int getYear() {
            return year;
        }
        
        public void setYear(int year) {
            this.year = year;
        }
        
        public double getAverageAttendance() {
            return averageAttendance;
        }
        
        public void setAverageAttendance(double averageAttendance) {
            this.averageAttendance = averageAttendance;
        }
        
        public int getTotalWorkingDays() {
            return totalWorkingDays;
        }
        
        public void setTotalWorkingDays(int totalWorkingDays) {
            this.totalWorkingDays = totalWorkingDays;
        }
        
        public int getTotalPresentDays() {
            return totalPresentDays;
        }
        
        public void setTotalPresentDays(int totalPresentDays) {
            this.totalPresentDays = totalPresentDays;
        }
    }
}
