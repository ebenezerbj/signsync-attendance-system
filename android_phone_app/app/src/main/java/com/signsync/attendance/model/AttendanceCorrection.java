package com.signsync.attendance.model;

import android.os.Parcel;
import android.os.Parcelable;
import com.google.gson.annotations.SerializedName;

public class AttendanceCorrection implements Parcelable {
    
    @SerializedName("id")
    private int id;
    
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("attendance_date")
    private String attendanceDate;
    
    @SerializedName("correction_type")
    private String correctionType; // clock_in, clock_out, both
    
    @SerializedName("original_clock_in")
    private String originalClockIn;
    
    @SerializedName("original_clock_out")
    private String originalClockOut;
    
    @SerializedName("requested_clock_in")
    private String requestedClockIn;
    
    @SerializedName("requested_clock_out")
    private String requestedClockOut;
    
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
    
    @SerializedName("supporting_document")
    private String supportingDocument;
    
    @SerializedName("manager_notes")
    private String managerNotes;
    
    public AttendanceCorrection() {}
    
    protected AttendanceCorrection(Parcel in) {
        id = in.readInt();
        employeeId = in.readString();
        attendanceDate = in.readString();
        correctionType = in.readString();
        originalClockIn = in.readString();
        originalClockOut = in.readString();
        requestedClockIn = in.readString();
        requestedClockOut = in.readString();
        reason = in.readString();
        status = in.readString();
        submittedDate = in.readString();
        approvedBy = in.readString();
        approvedDate = in.readString();
        comments = in.readString();
        supportingDocument = in.readString();
        managerNotes = in.readString();
    }
    
    public static final Creator<AttendanceCorrection> CREATOR = new Creator<AttendanceCorrection>() {
        @Override
        public AttendanceCorrection createFromParcel(Parcel in) {
            return new AttendanceCorrection(in);
        }
        
        @Override
        public AttendanceCorrection[] newArray(int size) {
            return new AttendanceCorrection[size];
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
        dest.writeString(attendanceDate);
        dest.writeString(correctionType);
        dest.writeString(originalClockIn);
        dest.writeString(originalClockOut);
        dest.writeString(requestedClockIn);
        dest.writeString(requestedClockOut);
        dest.writeString(reason);
        dest.writeString(status);
        dest.writeString(submittedDate);
        dest.writeString(approvedBy);
        dest.writeString(approvedDate);
        dest.writeString(comments);
        dest.writeString(supportingDocument);
        dest.writeString(managerNotes);
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
    
    public String getAttendanceDate() {
        return attendanceDate;
    }
    
    public void setAttendanceDate(String attendanceDate) {
        this.attendanceDate = attendanceDate;
    }
    
    public String getCorrectionType() {
        return correctionType;
    }
    
    public void setCorrectionType(String correctionType) {
        this.correctionType = correctionType;
    }
    
    public String getOriginalClockIn() {
        return originalClockIn;
    }
    
    public void setOriginalClockIn(String originalClockIn) {
        this.originalClockIn = originalClockIn;
    }
    
    public String getOriginalClockOut() {
        return originalClockOut;
    }
    
    public void setOriginalClockOut(String originalClockOut) {
        this.originalClockOut = originalClockOut;
    }
    
    public String getRequestedClockIn() {
        return requestedClockIn;
    }
    
    public void setRequestedClockIn(String requestedClockIn) {
        this.requestedClockIn = requestedClockIn;
    }
    
    public String getRequestedClockOut() {
        return requestedClockOut;
    }
    
    public void setRequestedClockOut(String requestedClockOut) {
        this.requestedClockOut = requestedClockOut;
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
    
    public String getSupportingDocument() {
        return supportingDocument;
    }
    
    public void setSupportingDocument(String supportingDocument) {
        this.supportingDocument = supportingDocument;
    }
    
    public String getManagerNotes() {
        return managerNotes;
    }
    
    public void setManagerNotes(String managerNotes) {
        this.managerNotes = managerNotes;
    }
    
    // Convenience methods for adapters
    public String getDate() {
        return attendanceDate;
    }
    
    public String getType() {
        return correctionType;
    }
    
    public String getOriginalTime() {
        return originalClockIn;
    }
    
    public String getCorrectedTime() {
        return requestedClockIn;
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
    
    public boolean isClockInCorrection() {
        return "clock_in".equalsIgnoreCase(correctionType) || "both".equalsIgnoreCase(correctionType);
    }
    
    public boolean isClockOutCorrection() {
        return "clock_out".equalsIgnoreCase(correctionType) || "both".equalsIgnoreCase(correctionType);
    }
    
    public String getFormattedAttendanceDate() {
        return formatDate(attendanceDate);
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
    
    public String getFormattedOriginalClockIn() {
        return formatTime(originalClockIn);
    }
    
    public String getFormattedOriginalClockOut() {
        return formatTime(originalClockOut);
    }
    
    public String getFormattedRequestedClockIn() {
        return formatTime(requestedClockIn);
    }
    
    public String getFormattedRequestedClockOut() {
        return formatTime(requestedClockOut);
    }
    
    private String formatTime(String timeString) {
        if (timeString != null && !timeString.isEmpty()) {
            try {
                java.text.SimpleDateFormat inputFormat = new java.text.SimpleDateFormat("HH:mm:ss", java.util.Locale.getDefault());
                java.text.SimpleDateFormat outputFormat = new java.text.SimpleDateFormat("hh:mm a", java.util.Locale.getDefault());
                java.util.Date time = inputFormat.parse(timeString);
                return outputFormat.format(time);
            } catch (java.text.ParseException e) {
                return timeString;
            }
        }
        return "N/A";
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
    
    public String getCorrectionTypeDescription() {
        switch (correctionType.toLowerCase()) {
            case "clock_in":
                return "Clock In Correction";
            case "clock_out":
                return "Clock Out Correction";
            case "both":
                return "Clock In & Out Correction";
            default:
                return "Time Correction";
        }
    }
    
    public boolean hasChanges() {
        if (isClockInCorrection()) {
            if (!timeEquals(originalClockIn, requestedClockIn)) {
                return true;
            }
        }
        if (isClockOutCorrection()) {
            if (!timeEquals(originalClockOut, requestedClockOut)) {
                return true;
            }
        }
        return false;
    }
    
    private boolean timeEquals(String time1, String time2) {
        if (time1 == null && time2 == null) return true;
        if (time1 == null || time2 == null) return false;
        return time1.equals(time2);
    }
    
    public String getTimeChangesSummary() {
        StringBuilder summary = new StringBuilder();
        
        if (isClockInCorrection() && !timeEquals(originalClockIn, requestedClockIn)) {
            summary.append("Clock In: ")
                   .append(getFormattedOriginalClockIn())
                   .append(" → ")
                   .append(getFormattedRequestedClockIn());
        }
        
        if (isClockOutCorrection() && !timeEquals(originalClockOut, requestedClockOut)) {
            if (summary.length() > 0) {
                summary.append("\n");
            }
            summary.append("Clock Out: ")
                   .append(getFormattedOriginalClockOut())
                   .append(" → ")
                   .append(getFormattedRequestedClockOut());
        }
        
        return summary.toString();
    }
    
    public boolean hasSupportingDocument() {
        return supportingDocument != null && !supportingDocument.isEmpty();
    }
    
    @Override
    public String toString() {
        return "AttendanceCorrection{" +
                "id=" + id +
                ", employeeId='" + employeeId + '\'' +
                ", attendanceDate='" + attendanceDate + '\'' +
                ", correctionType='" + correctionType + '\'' +
                ", status='" + status + '\'' +
                '}';
    }
}
