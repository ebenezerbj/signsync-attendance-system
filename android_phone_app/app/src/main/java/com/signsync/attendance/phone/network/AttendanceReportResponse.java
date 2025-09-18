package com.signsync.attendance.phone.network;

import java.util.List;

public class AttendanceReportResponse {
    private boolean success;
    private String message;
    private List<AttendanceRecord> records;
    private Summary summary;
    
    public boolean isSuccess() {
        return success;
    }
    
    public String getMessage() {
        return message;
    }
    
    public List<AttendanceRecord> getRecords() {
        return records;
    }
    
    public Summary getSummary() {
        return summary;
    }
    
    public static class AttendanceRecord {
        private String date;
        private String clockIn;
        private String clockOut;
        private String totalHours;
        private String status;
        private String location;
        
        // Getters and setters
        public String getDate() { return date; }
        public String getClockIn() { return clockIn; }
        public String getClockOut() { return clockOut; }
        public String getTotalHours() { return totalHours; }
        public String getStatus() { return status; }
        public String getLocation() { return location; }
        
        public void setDate(String date) { this.date = date; }
        public void setClockIn(String clockIn) { this.clockIn = clockIn; }
        public void setClockOut(String clockOut) { this.clockOut = clockOut; }
        public void setTotalHours(String totalHours) { this.totalHours = totalHours; }
        public void setStatus(String status) { this.status = status; }
        public void setLocation(String location) { this.location = location; }
    }
    
    public static class Summary {
        private String totalDays;
        private String totalHours;
        private String averageHours;
        private String presentDays;
        private String absentDays;
        
        // Getters and setters
        public String getTotalDays() { return totalDays; }
        public String getTotalHours() { return totalHours; }
        public String getAverageHours() { return averageHours; }
        public String getPresentDays() { return presentDays; }
        public String getAbsentDays() { return absentDays; }
        
        public void setTotalDays(String totalDays) { this.totalDays = totalDays; }
        public void setTotalHours(String totalHours) { this.totalHours = totalHours; }
        public void setAverageHours(String averageHours) { this.averageHours = averageHours; }
        public void setPresentDays(String presentDays) { this.presentDays = presentDays; }
        public void setAbsentDays(String absentDays) { this.absentDays = absentDays; }
    }
}
