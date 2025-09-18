package com.signsync.attendance.activity.reports;

import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.view.MenuItem;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.cardview.widget.CardView;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import com.github.mikephil.charting.charts.BarChart;
import com.github.mikephil.charting.charts.LineChart;
import com.github.mikephil.charting.charts.PieChart;
import com.github.mikephil.charting.components.Description;
import com.github.mikephil.charting.components.XAxis;
import com.github.mikephil.charting.data.*;
import com.github.mikephil.charting.formatter.IndexAxisValueFormatter;
import com.github.mikephil.charting.utils.ColorTemplate;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.datepicker.MaterialDatePicker;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.signsync.attendance.R;
import com.signsync.attendance.adapter.ReportSummaryAdapter;
import com.signsync.attendance.model.ReportSummary;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.response.ReportsResponse;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class ReportsActivity extends AppCompatActivity {
    
    // UI Components
    private Toolbar toolbar;
    private ChipGroup reportTypeChipGroup;
    private TextView dateRangeText, totalEmployeesText, presentTodayText, absentTodayText, lateArrivalsText;
    private CardView dateRangeCard;
    private RecyclerView summaryRecyclerView;
    private FloatingActionButton exportFab;
    
    // Charts
    private PieChart attendancePieChart;
    private BarChart weeklyAttendanceChart;
    private LineChart monthlyTrendChart;
    private LinearLayout chartsContainer;
    
    // Data
    private AttendanceApiService apiService;
    private ReportSummaryAdapter summaryAdapter;
    private List<ReportSummary> reportSummaries = new ArrayList<>();
    
    // Date Range
    private long startDateMillis = 0;
    private long endDateMillis = 0;
    private String currentReportType = "daily";
    
    private SimpleDateFormat dateFormat = new SimpleDateFormat("MMM dd, yyyy", Locale.getDefault());
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_reports);
        
        initializeViews();
        setupToolbar();
        setupDateRange();
        setupReportTypeFilters();
        setupRecyclerView();
        setupCharts();
        initializeApiService();
        setDefaultDateRange();
        loadReportsData();
    }
    
    private void initializeViews() {
        toolbar = findViewById(R.id.toolbar);
        reportTypeChipGroup = findViewById(R.id.reportTypeChipGroup);
        dateRangeText = findViewById(R.id.dateRangeText);
        dateRangeCard = findViewById(R.id.dateRangeCard);
        summaryRecyclerView = findViewById(R.id.summaryRecyclerView);
        exportFab = findViewById(R.id.exportFab);
        
        // Summary cards
        totalEmployeesText = findViewById(R.id.totalEmployeesText);
        presentTodayText = findViewById(R.id.presentTodayText);
        absentTodayText = findViewById(R.id.absentTodayText);
        lateArrivalsText = findViewById(R.id.lateArrivalsText);
        
        // Charts
        attendancePieChart = findViewById(R.id.attendancePieChart);
        weeklyAttendanceChart = findViewById(R.id.weeklyAttendanceChart);
        monthlyTrendChart = findViewById(R.id.monthlyTrendChart);
        chartsContainer = findViewById(R.id.chartsContainer);
        
        exportFab.setOnClickListener(v -> showExportOptions());
    }
    
    private void setupToolbar() {
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("Reports & Analytics");
        }
    }
    
    private void setupDateRange() {
        dateRangeCard.setOnClickListener(v -> showDateRangePicker());
    }
    
    private void setupReportTypeFilters() {
        // Daily Report
        Chip dailyChip = findViewById(R.id.chipDaily);
        dailyChip.setOnClickListener(v -> {
            currentReportType = "daily";
            updateChipSelection(dailyChip);
            loadReportsData();
        });
        
        // Weekly Report
        Chip weeklyChip = findViewById(R.id.chipWeekly);
        weeklyChip.setOnClickListener(v -> {
            currentReportType = "weekly";
            updateChipSelection(weeklyChip);
            loadReportsData();
        });
        
        // Monthly Report
        Chip monthlyChip = findViewById(R.id.chipMonthly);
        monthlyChip.setOnClickListener(v -> {
            currentReportType = "monthly";
            updateChipSelection(monthlyChip);
            loadReportsData();
        });
        
        // Custom Report
        Chip customChip = findViewById(R.id.chipCustom);
        customChip.setOnClickListener(v -> {
            currentReportType = "custom";
            updateChipSelection(customChip);
            showDateRangePicker();
        });
        
        // Set default selection
        updateChipSelection(dailyChip);
    }
    
    private void updateChipSelection(Chip selectedChip) {
        for (int i = 0; i < reportTypeChipGroup.getChildCount(); i++) {
            Chip chip = (Chip) reportTypeChipGroup.getChildAt(i);
            chip.setChecked(chip == selectedChip);
        }
    }
    
    private void setupRecyclerView() {
        summaryAdapter = new ReportSummaryAdapter(reportSummaries);
        summaryRecyclerView.setLayoutManager(new LinearLayoutManager(this));
        summaryRecyclerView.setAdapter(summaryAdapter);
    }
    
    private void setupCharts() {
        setupPieChart();
        setupBarChart();
        setupLineChart();
    }
    
    private void setupPieChart() {
        attendancePieChart.setUsePercentValues(true);
        attendancePieChart.getDescription().setEnabled(false);
        attendancePieChart.setExtraOffsets(5, 10, 5, 5);
        attendancePieChart.setDragDecelerationFrictionCoef(0.95f);
        attendancePieChart.setDrawHoleEnabled(true);
        attendancePieChart.setHoleColor(Color.WHITE);
        attendancePieChart.setTransparentCircleRadius(61f);
        attendancePieChart.setHoleRadius(58f);
        attendancePieChart.setRotationAngle(0);
        attendancePieChart.setRotationEnabled(true);
        attendancePieChart.setHighlightPerTapEnabled(true);
    }
    
    private void setupBarChart() {
        weeklyAttendanceChart.getDescription().setEnabled(false);
        weeklyAttendanceChart.setDrawBarShadow(false);
        weeklyAttendanceChart.setDrawValueAboveBar(true);
        weeklyAttendanceChart.setPinchZoom(false);
        weeklyAttendanceChart.setDrawGridBackground(false);
        
        XAxis xAxis = weeklyAttendanceChart.getXAxis();
        xAxis.setPosition(XAxis.XAxisPosition.BOTTOM);
        xAxis.setDrawGridLines(false);
        xAxis.setGranularity(1f);
        
        weeklyAttendanceChart.getAxisLeft().setDrawGridLines(false);
        weeklyAttendanceChart.getAxisRight().setEnabled(false);
        weeklyAttendanceChart.getLegend().setEnabled(false);
    }
    
    private void setupLineChart() {
        monthlyTrendChart.getDescription().setEnabled(false);
        monthlyTrendChart.setTouchEnabled(true);
        monthlyTrendChart.setDragEnabled(true);
        monthlyTrendChart.setScaleEnabled(true);
        monthlyTrendChart.setDrawGridBackground(false);
        monthlyTrendChart.setPinchZoom(true);
        
        XAxis xAxis = monthlyTrendChart.getXAxis();
        xAxis.setPosition(XAxis.XAxisPosition.BOTTOM);
        xAxis.setDrawGridLines(false);
        xAxis.setGranularity(1f);
        
        monthlyTrendChart.getAxisLeft().setDrawGridLines(true);
        monthlyTrendChart.getAxisRight().setEnabled(false);
    }
    
    private void initializeApiService() {
        apiService = ApiClient.getApiService();
    }
    
    private void setDefaultDateRange() {
        Calendar calendar = Calendar.getInstance();
        endDateMillis = calendar.getTimeInMillis();
        
        calendar.add(Calendar.DAY_OF_MONTH, -30); // Last 30 days
        startDateMillis = calendar.getTimeInMillis();
        
        updateDateRangeText();
    }
    
    private void updateDateRangeText() {
        String startDate = dateFormat.format(new Date(startDateMillis));
        String endDate = dateFormat.format(new Date(endDateMillis));
        dateRangeText.setText(startDate + " - " + endDate);
    }
    
    private void showDateRangePicker() {
        MaterialDatePicker<androidx.core.util.Pair<Long, Long>> dateRangePicker =
                MaterialDatePicker.Builder.dateRangePicker()
                        .setTitleText("Select Date Range")
                        .setSelection(androidx.core.util.Pair.create(startDateMillis, endDateMillis))
                        .build();
        
        dateRangePicker.show(getSupportFragmentManager(), "DATE_RANGE_PICKER");
        
        dateRangePicker.addOnPositiveButtonClickListener(selection -> {
            startDateMillis = selection.first;
            endDateMillis = selection.second;
            updateDateRangeText();
            loadReportsData();
        });
    }
    
    private void loadReportsData() {
        String startDate = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(new Date(startDateMillis));
        String endDate = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(new Date(endDateMillis));
        
        Call<ReportsResponse> call = apiService.getReports(currentReportType, startDate, endDate);
        call.enqueue(new Callback<ReportsResponse>() {
            @Override
            public void onResponse(Call<ReportsResponse> call, Response<ReportsResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    ReportsResponse reportsResponse = response.body();
                    if (reportsResponse.isSuccess()) {
                        updateSummaryCards(reportsResponse);
                        updateCharts(reportsResponse);
                        updateDetailedReports(reportsResponse);
                    } else {
                        showError("Failed to load reports: " + reportsResponse.getMessage());
                    }
                } else {
                    showError("Failed to load reports. Please try again.");
                }
            }
            
            @Override
            public void onFailure(Call<ReportsResponse> call, Throwable t) {
                showError("Network error: " + t.getMessage());
            }
        });
    }
    
    private void updateSummaryCards(ReportsResponse response) {
        totalEmployeesText.setText(String.valueOf(response.getTotalEmployees()));
        presentTodayText.setText(String.valueOf(response.getPresentToday()));
        absentTodayText.setText(String.valueOf(response.getAbsentToday()));
        lateArrivalsText.setText(String.valueOf(response.getLateArrivals()));
    }
    
    private void updateCharts(ReportsResponse response) {
        updatePieChartData(response);
        updateBarChartData(response);
        updateLineChartData(response);
    }
    
    private void updatePieChartData(ReportsResponse response) {
        ArrayList<PieEntry> entries = new ArrayList<>();
        entries.add(new PieEntry(response.getPresentToday(), "Present"));
        entries.add(new PieEntry(response.getAbsentToday(), "Absent"));
        entries.add(new PieEntry(response.getLateArrivals(), "Late"));
        
        PieDataSet dataSet = new PieDataSet(entries, "Attendance");
        dataSet.setColors(ColorTemplate.MATERIAL_COLORS);
        dataSet.setValueTextColor(Color.WHITE);
        dataSet.setValueTextSize(12f);
        
        PieData data = new PieData(dataSet);
        attendancePieChart.setData(data);
        attendancePieChart.invalidate();
    }
    
    private void updateBarChartData(ReportsResponse response) {
        // Sample weekly data - replace with actual data from response
        ArrayList<BarEntry> entries = new ArrayList<>();
        String[] days = {"Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"};
        
        for (int i = 0; i < 7; i++) {
            entries.add(new BarEntry(i, (float) (Math.random() * 100))); // Replace with actual data
        }
        
        BarDataSet dataSet = new BarDataSet(entries, "Weekly Attendance");
        dataSet.setColors(ColorTemplate.MATERIAL_COLORS);
        
        BarData data = new BarData(dataSet);
        data.setBarWidth(0.9f);
        
        weeklyAttendanceChart.setData(data);
        weeklyAttendanceChart.getXAxis().setValueFormatter(new IndexAxisValueFormatter(days));
        weeklyAttendanceChart.invalidate();
    }
    
    private void updateLineChartData(ReportsResponse response) {
        // Sample monthly trend data - replace with actual data from response
        ArrayList<Entry> entries = new ArrayList<>();
        
        for (int i = 0; i < 30; i++) {
            entries.add(new Entry(i, (float) (Math.random() * 100))); // Replace with actual data
        }
        
        LineDataSet dataSet = new LineDataSet(entries, "Monthly Trend");
        dataSet.setColor(getResources().getColor(R.color.primary_color));
        dataSet.setValueTextColor(getResources().getColor(R.color.text_primary));
        
        LineData data = new LineData(dataSet);
        monthlyTrendChart.setData(data);
        monthlyTrendChart.invalidate();
    }
    
    private void updateDetailedReports(ReportsResponse response) {
        reportSummaries.clear();
        // Add sample data - replace with actual data from response
        reportSummaries.add(new ReportSummary("Department A", "85%", "120/140"));
        reportSummaries.add(new ReportSummary("Department B", "92%", "78/85"));
        reportSummaries.add(new ReportSummary("Department C", "78%", "65/83"));
        
        summaryAdapter.notifyDataSetChanged();
    }
    
    private void showExportOptions() {
        String[] options = {"Export to PDF", "Export to Excel", "Share Report"};
        
        new androidx.appcompat.app.AlertDialog.Builder(this)
                .setTitle("Export Options")
                .setItems(options, (dialog, which) -> {
                    switch (which) {
                        case 0:
                            exportToPDF();
                            break;
                        case 1:
                            exportToExcel();
                            break;
                        case 2:
                            shareReport();
                            break;
                    }
                })
                .show();
    }
    
    private void exportToPDF() {
        // TODO: Implement PDF export
        Toast.makeText(this, "PDF export functionality coming soon", Toast.LENGTH_SHORT).show();
    }
    
    private void exportToExcel() {
        // TODO: Implement Excel export
        Toast.makeText(this, "Excel export functionality coming soon", Toast.LENGTH_SHORT).show();
    }
    
    private void shareReport() {
        // TODO: Implement report sharing
        Toast.makeText(this, "Share functionality coming soon", Toast.LENGTH_SHORT).show();
    }
    
    private void showError(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            onBackPressed();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }
}
