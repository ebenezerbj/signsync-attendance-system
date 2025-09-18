package com.signsync.attendance.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.cardview.widget.CardView;
import androidx.recyclerview.widget.RecyclerView;
import com.signsync.attendance.R;
import com.signsync.attendance.model.ReportSummary;
import java.util.List;

public class ReportSummaryAdapter extends RecyclerView.Adapter<ReportSummaryAdapter.ReportViewHolder> {
    
    private List<ReportSummary> reportSummaries;
    private OnReportClickListener listener;
    
    public interface OnReportClickListener {
        void onReportClick(ReportSummary reportSummary, int position);
    }
    
    public ReportSummaryAdapter(List<ReportSummary> reportSummaries) {
        this.reportSummaries = reportSummaries;
    }
    
    public ReportSummaryAdapter(List<ReportSummary> reportSummaries, OnReportClickListener listener) {
        this.reportSummaries = reportSummaries;
        this.listener = listener;
    }
    
    @NonNull
    @Override
    public ReportViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_report_summary, parent, false);
        return new ReportViewHolder(view);
    }
    
    @Override
    public void onBindViewHolder(@NonNull ReportViewHolder holder, int position) {
        ReportSummary reportSummary = reportSummaries.get(position);
        holder.bind(reportSummary, position);
    }
    
    @Override
    public int getItemCount() {
        return reportSummaries.size();
    }
    
    class ReportViewHolder extends RecyclerView.ViewHolder {
        
    private CardView reportCard;
    private TextView titleTextView;
    private TextView dateTextView;
    private TextView summaryTextView;
    private TextView countTextView;
    private TextView statusTextView;
    private ImageView trendImageView;
        
        public ReportViewHolder(@NonNull View itemView) {
            super(itemView);
            
            // The layout root is a MaterialCardView; keep a reference via cast
            reportCard = (CardView) itemView;
            titleTextView = itemView.findViewById(R.id.titleTextView);
            dateTextView = itemView.findViewById(R.id.dateTextView);
            summaryTextView = itemView.findViewById(R.id.summaryTextView);
            countTextView = itemView.findViewById(R.id.countTextView);
            statusTextView = itemView.findViewById(R.id.statusTextView);
            trendImageView = itemView.findViewById(R.id.trendIcon);
        }
        
        public void bind(ReportSummary reportSummary, int position) {
            titleTextView.setText(reportSummary.getTitle());
            if (reportSummary.getDateText() != null) {
                dateTextView.setText(reportSummary.getDateText());
                dateTextView.setVisibility(View.VISIBLE);
            } else {
                dateTextView.setVisibility(View.GONE);
            }
            summaryTextView.setText(reportSummary.getDetails());
            if (reportSummary.getCountText() != null) {
                countTextView.setText(reportSummary.getCountText());
            }
            if (reportSummary.getStatusText() != null) {
                statusTextView.setText(reportSummary.getStatusText());
            }
            
            // Set trend indicator
            String trendDirection = reportSummary.getTrendDirection();
            if (trendDirection != null) {
                switch (trendDirection.toLowerCase()) {
                    case "up":
                        trendImageView.setImageResource(R.drawable.ic_trending_up);
                        trendImageView.setColorFilter(itemView.getContext().getResources().getColor(R.color.success_green));
                        trendImageView.setVisibility(View.VISIBLE);
                        break;
                    case "down":
                        trendImageView.setImageResource(R.drawable.ic_trending_down);
                        trendImageView.setColorFilter(itemView.getContext().getResources().getColor(R.color.error_red));
                        trendImageView.setVisibility(View.VISIBLE);
                        break;
                    case "stable":
                        trendImageView.setImageResource(R.drawable.ic_trending_flat);
                        trendImageView.setColorFilter(itemView.getContext().getResources().getColor(R.color.warning_orange));
                        trendImageView.setVisibility(View.VISIBLE);
                        break;
                    default:
                        trendImageView.setVisibility(View.GONE);
                        break;
                }
            } else {
                trendImageView.setVisibility(View.GONE);
            }
            
            // Set click listener
            reportCard.setOnClickListener(v -> {
                if (listener != null) {
                    listener.onReportClick(reportSummary, position);
                }
            });
        }
    }
    
    // Method to update the entire list
    public void updateReports(List<ReportSummary> newReports) {
        this.reportSummaries.clear();
        this.reportSummaries.addAll(newReports);
        notifyDataSetChanged();
    }
    
    // Method to add a single report
    public void addReport(ReportSummary reportSummary) {
        this.reportSummaries.add(reportSummary);
        notifyItemInserted(reportSummaries.size() - 1);
    }
    
    // Method to remove a report
    public void removeReport(int position) {
        if (position >= 0 && position < reportSummaries.size()) {
            reportSummaries.remove(position);
            notifyItemRemoved(position);
        }
    }
    
    // Method to update a single report
    public void updateReport(int position, ReportSummary reportSummary) {
        if (position >= 0 && position < reportSummaries.size()) {
            reportSummaries.set(position, reportSummary);
            notifyItemChanged(position);
        }
    }
    
    // Method to get report at position
    public ReportSummary getReport(int position) {
        if (position >= 0 && position < reportSummaries.size()) {
            return reportSummaries.get(position);
        }
        return null;
    }
    
    // Method to set click listener
    public void setOnReportClickListener(OnReportClickListener listener) {
        this.listener = listener;
    }
}
