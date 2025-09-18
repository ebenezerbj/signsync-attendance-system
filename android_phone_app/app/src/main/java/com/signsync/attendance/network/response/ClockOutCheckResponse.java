package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;
import java.util.List;

public class ClockOutCheckResponse extends BaseResponse {
    @SerializedName("missed_clock_outs")
    private List<MissedClockOut> missedClockOuts;

    public List<MissedClockOut> getMissedClockOuts() {
        return missedClockOuts;
    }

    public void setMissedClockOuts(List<MissedClockOut> missedClockOuts) {
        this.missedClockOuts = missedClockOuts;
    }

    public static class MissedClockOut {
        @SerializedName("employee_id")
        private int employeeId;

        @SerializedName("employee_name")
        private String employeeName;

        @SerializedName("phone_number")
        private String phoneNumber;

        @SerializedName("clock_in_time")
        private String clockInTime;

        @SerializedName("hours_worked")
        private double hoursWorked;

        public int getEmployeeId() {
            return employeeId;
        }

        public void setEmployeeId(int employeeId) {
            this.employeeId = employeeId;
        }

        public String getEmployeeName() {
            return employeeName;
        }

        public void setEmployeeName(String employeeName) {
            this.employeeName = employeeName;
        }

        public String getPhoneNumber() {
            return phoneNumber;
        }

        public void setPhoneNumber(String phoneNumber) {
            this.phoneNumber = phoneNumber;
        }

        public String getClockInTime() {
            return clockInTime;
        }

        public void setClockInTime(String clockInTime) {
            this.clockInTime = clockInTime;
        }

        public double getHoursWorked() {
            return hoursWorked;
        }

        public void setHoursWorked(double hoursWorked) {
            this.hoursWorked = hoursWorked;
        }
    }
}
