package com.signsync.attendance.model;

public class ReportSummary {
    private String title;
    private String percentage;
    private String details;
    private String trendDirection; // "up", "down", "stable"
    private int iconResource;
    
    public ReportSummary() {}
    
    public ReportSummary(String title, String percentage, String details) {
        this.title = title;
        this.percentage = percentage;
        this.details = details;
        this.trendDirection = "stable";
    }
    
    public ReportSummary(String title, String percentage, String details, String trendDirection) {
        this.title = title;
        this.percentage = percentage;
        this.details = details;
        this.trendDirection = trendDirection;
    }
    
    // Getters and Setters
    public String getTitle() {
        return title;
    }
    
    public void setTitle(String title) {
        this.title = title;
    }
    
    public String getPercentage() {
        return percentage;
    }
    
    public void setPercentage(String percentage) {
        this.percentage = percentage;
    }
    
    public String getDetails() {
        return details;
    }
    
    public void setDetails(String details) {
        this.details = details;
    }
    
    public String getTrendDirection() {
        return trendDirection;
    }
    
    public void setTrendDirection(String trendDirection) {
        this.trendDirection = trendDirection;
    }
    
    public int getIconResource() {
        return iconResource;
    }
    
    public void setIconResource(int iconResource) {
        this.iconResource = iconResource;
    }
}
