package com.signsync.attendance.model;

import com.google.gson.annotations.SerializedName;

public class GamificationData {
    @SerializedName("points")
    private int points;

    @SerializedName("level")
    private int level;

    @SerializedName("badges")
    private String[] badges;

    @SerializedName("streak_days")
    private int streakDays;

    @SerializedName("rank")
    private int rank;

    @SerializedName("total_employees")
    private int totalEmployees;

    public int getPoints() {
        return points;
    }

    public void setPoints(int points) {
        this.points = points;
    }

    public int getLevel() {
        return level;
    }

    public void setLevel(int level) {
        this.level = level;
    }

    public String[] getBadges() {
        return badges;
    }

    public void setBadges(String[] badges) {
        this.badges = badges;
    }

    public int getStreakDays() {
        return streakDays;
    }

    public int getStreak() {
        return streakDays;
    }

    public void setStreakDays(int streakDays) {
        this.streakDays = streakDays;
    }

    public int getRank() {
        return rank;
    }

    public void setRank(int rank) {
        this.rank = rank;
    }

    public int getTotalEmployees() {
        return totalEmployees;
    }

    public void setTotalEmployees(int totalEmployees) {
        this.totalEmployees = totalEmployees;
    }
}
