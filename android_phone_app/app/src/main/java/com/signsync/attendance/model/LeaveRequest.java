package com.signsync.attendance.model;

import android.os.Parcel;
import android.os.Parcelable;
import com.google.gson.annotations.SerializedName;
import java.util.List;

public class LeaveRequest implements Parcelable {
    
    @SerializedName("id")
    private int id;
    
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("leave_type")
    private String leaveType;
    
    @SerializedName("start_date")
    private String startDate;
    
    @SerializedName("end_date")
    private String endDate;
    
    @SerializedName("reason")
    private String reason;
    
    @SerializedName("status")
    private String status; // Pending, Approved, Rejected
    
    @SerializedName("submitted_date")
    private String submittedDate;
    
    @SerializedName("approved_by")
    private String approvedBy;
    
    @SerializedName("approved_date")
    private String approvedDate;
    
    @SerializedName("comments")
    private String comments;
    
    @SerializedName("days_requested")
    private int daysRequested;
    
    @SerializedName("is_half_day")
    private boolean isHalfDay;
    
    @SerializedName("half_day_period")
    private String halfDayPeriod; // morning, afternoon
    
    @SerializedName("emergency_contact")
    private String emergencyContact;
    
    @SerializedName("document_path")
    private String documentPath;
    
    public LeaveRequest() {}
    
    protected LeaveRequest(Parcel in) {
        id = in.readInt();
        employeeId = in.readString();
        leaveType = in.readString();
        startDate = in.readString();
        endDate = in.readString();
        reason = in.readString();
        status = in.readString();
        submittedDate = in.readString();
        approvedBy = in.readString();
        approvedDate = in.readString();
        comments = in.readString();
        daysRequested = in.readInt();
        isHalfDay = in.readByte() != 0;
        halfDayPeriod = in.readString();
        emergencyContact = in.readString();
        documentPath = in.readString();
    }
    
    public static final Creator<LeaveRequest> CREATOR = new Creator<LeaveRequest>() {
        @Override
        public LeaveRequest createFromParcel(Parcel in) {
            return new LeaveRequest(in);
        }
        
        @Override
        public LeaveRequest[] newArray(int size) {
            return new LeaveRequest[size];
        }
    };
    
    @Override
    public int describeContents() {
        return 0;
    }
    
    @Override
    public void writeToParcel(Parcel dest, int flags) {
        dest.writeInt(id);
        dest.writeString(employeeId);
        dest.writeString(leaveType);
        dest.writeString(startDate);
        dest.writeString(endDate);
        dest.writeString(reason);
        dest.writeString(status);
        dest.writeString(submittedDate);
        dest.writeString(approvedBy);
        dest.writeString(approvedDate);
        dest.writeString(comments);
        dest.writeInt(daysRequested);
        dest.writeByte((byte) (isHalfDay ? 1 : 0));
        dest.writeString(halfDayPeriod);
        dest.writeString(emergencyContact);
        dest.writeString(documentPath);
    }
    
    // Getters and Setters
    public int getId() {
        return id;
    }
    
    public void setId(int id) {
        this.id = id;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
    
    public String getLeaveType() {
        return leaveType;
    }
    
    public void setLeaveType(String leaveType) {
        this.leaveType = leaveType;
    }
    
    public String getStartDate() {
        return startDate;
    }
    
    public void setStartDate(String startDate) {
        this.startDate = startDate;
    }
    
    public String getEndDate() {
        return endDate;
    }
    
    public void setEndDate(String endDate) {
        this.endDate = endDate;
    }
    
    public String getReason() {
        return reason;
    }
    
    public void setReason(String reason) {
        this.reason = reason;
    }
    
    public String getStatus() {
        return status;
    }
    
    public void setStatus(String status) {
        this.status = status;
    }
    
    public String getSubmittedDate() {
        return submittedDate;
    }
    
    public void setSubmittedDate(String submittedDate) {
        this.submittedDate = submittedDate;
    }
    
    public String getApprovedBy() {
        return approvedBy;
    }
    
    public void setApprovedBy(String approvedBy) {
        this.approvedBy = approvedBy;
    }
    
    public String getApprovedDate() {
        return approvedDate;
    }
    
    public void setApprovedDate(String approvedDate) {
        this.approvedDate = approvedDate;
    }
    
    public String getComments() {
        return comments;
    }
    
    public void setComments(String comments) {
        this.comments = comments;
    }
    
    public int getDaysRequested() {
        return daysRequested;
    }
    
    public void setDaysRequested(int daysRequested) {
        this.daysRequested = daysRequested;
    }
    
    public boolean isHalfDay() {
        return isHalfDay;
    }
    
    public void setHalfDay(boolean halfDay) {
        isHalfDay = halfDay;
    }
    
    public String getHalfDayPeriod() {
        return halfDayPeriod;
    }
    
    public void setHalfDayPeriod(String halfDayPeriod) {
        this.halfDayPeriod = halfDayPeriod;
    }
    
    public String getEmergencyContact() {
        return emergencyContact;
    }
    
    public void setEmergencyContact(String emergencyContact) {
        this.emergencyContact = emergencyContact;
    }
    
    public String getDocumentPath() {
        return documentPath;
    }
    
    public void setDocumentPath(String documentPath) {
        this.documentPath = documentPath;
    }
    
    // Utility methods
    public boolean isPending() {
        return "Pending".equalsIgnoreCase(status);
    }
    
    public boolean isApproved() {
        return "Approved".equalsIgnoreCase(status);
    }
    
    public boolean isRejected() {
        return "Rejected".equalsIgnoreCase(status);
    }
    
    public String getFormattedStartDate() {
        return formatDate(startDate);
    }
    
    public String getFormattedEndDate() {
        return formatDate(endDate);
    }
    
    public String getFormattedSubmittedDate() {
        return formatDate(submittedDate);
    }
    
    public String getFormattedApprovedDate() {
        return formatDate(approvedDate);
    }
    
    private String formatDate(String dateString) {
        if (dateString != null && !dateString.isEmpty()) {
            try {
                java.text.SimpleDateFormat inputFormat = new java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault());
                java.text.SimpleDateFormat outputFormat = new java.text.SimpleDateFormat("MMM dd, yyyy", java.util.Locale.getDefault());
                java.util.Date date = inputFormat.parse(dateString);
                return outputFormat.format(date);
            } catch (java.text.ParseException e) {
                return dateString;
            }
        }
        return "";
    }
    
    public String getStatusColor() {
        switch (status.toLowerCase()) {
            case "approved":
                return "#10B981"; // Green
            case "rejected":
                return "#F87171"; // Red
            case "pending":
            default:
                return "#F59E0B"; // Amber
        }
    }
    
    public String getStatusIcon() {
        switch (status.toLowerCase()) {
            case "approved":
                return "✓";
            case "rejected":
                return "✗";
            case "pending":
            default:
                return "⏳";
        }
    }
    
    public String getDurationText() {
        if (isHalfDay) {
            String period = halfDayPeriod != null ? halfDayPeriod : "half";
            return "Half day (" + period + ")";
        } else if (daysRequested == 1) {
            return "1 day";
        } else {
            return daysRequested + " days";
        }
    }
    
    public boolean hasDocument() {
        return documentPath != null && !documentPath.isEmpty();
    }
    
    public boolean isCurrentLeave() {
        if (startDate == null || endDate == null || !isApproved()) {
            return false;
        }
        
        try {
            java.text.SimpleDateFormat dateFormat = new java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault());
            java.util.Date start = dateFormat.parse(startDate);
            java.util.Date end = dateFormat.parse(endDate);
            java.util.Date today = new java.util.Date();
            
            return today.compareTo(start) >= 0 && today.compareTo(end) <= 0;
        } catch (java.text.ParseException e) {
            return false;
        }
    }
    
    public boolean isUpcomingLeave() {
        if (startDate == null || !isApproved()) {
            return false;
        }
        
        try {
            java.text.SimpleDateFormat dateFormat = new java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault());
            java.util.Date start = dateFormat.parse(startDate);
            java.util.Date today = new java.util.Date();
            
            return start.after(today);
        } catch (java.text.ParseException e) {
            return false;
        }
    }
    
    @Override
    public String toString() {
        return "LeaveRequest{" +
                "id=" + id +
                ", employeeId='" + employeeId + '\'' +
                ", leaveType='" + leaveType + '\'' +
                ", startDate='" + startDate + '\'' +
                ", endDate='" + endDate + '\'' +
                ", status='" + status + '\'' +
                ", daysRequested=" + daysRequested +
                '}';
    }
    
    // Convenience methods for adapters
    public String getType() {
        return leaveType;
    }
    
    public String getFromDate() {
        return startDate;
    }
    
    public String getToDate() {
        return endDate;
    }
}
