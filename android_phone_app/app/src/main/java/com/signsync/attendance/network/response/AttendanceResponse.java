package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;

public class AttendanceResponse extends BaseResponse {
    @SerializedName("attendance_id")
    private int attendanceId;

    @SerializedName("clock_time")
    private String clockTime;

    @SerializedName("status")
    private String status;

    @SerializedName("location")
    private LocationData location;

    public int getAttendanceId() {
        return attendanceId;
    }

    public void setAttendanceId(int attendanceId) {
        this.attendanceId = attendanceId;
    }

    public String getClockTime() {
        return clockTime;
    }

    public void setClockTime(String clockTime) {
        this.clockTime = clockTime;
    }

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public LocationData getLocation() {
        return location;
    }

    public void setLocation(LocationData location) {
        this.location = location;
    }

    public static class LocationData {
        @SerializedName("latitude")
        private double latitude;

        @SerializedName("longitude")
        private double longitude;

        @SerializedName("address")
        private String address;

        public double getLatitude() {
            return latitude;
        }

        public void setLatitude(double latitude) {
            this.latitude = latitude;
        }

        public double getLongitude() {
            return longitude;
        }

        public void setLongitude(double longitude) {
            this.longitude = longitude;
        }

        public String getAddress() {
            return address;
        }

        public void setAddress(String address) {
            this.address = address;
        }
    }
}
