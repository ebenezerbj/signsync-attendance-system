package com.signsync.attendance.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import com.signsync.attendance.R;
import com.signsync.attendance.model.LeaveRequest;
import java.util.List;

public class LeaveRequestAdapter extends RecyclerView.Adapter<LeaveRequestAdapter.ViewHolder> {
    private List<LeaveRequest> leaveList;
    private OnLeaveRequestClickListener listener;

    public interface OnLeaveRequestClickListener {
        void onLeaveRequestClick(LeaveRequest leaveRequest);
    }

    public LeaveRequestAdapter(List<LeaveRequest> leaveList) {
        this.leaveList = leaveList;
    }

    public LeaveRequestAdapter(List<LeaveRequest> leaveList, OnLeaveRequestClickListener listener) {
        this.leaveList = leaveList;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_leave_request, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        LeaveRequest leave = leaveList.get(position);
        holder.typeText.setText(leave.getType());
        holder.fromDateText.setText(leave.getFromDate());
        holder.toDateText.setText(leave.getToDate());
        holder.statusText.setText(leave.getStatus());
        holder.reasonText.setText(leave.getReason());
        
        if (listener != null) {
            holder.itemView.setOnClickListener(v -> listener.onLeaveRequestClick(leave));
        }
    }

    @Override
    public int getItemCount() {
        return leaveList != null ? leaveList.size() : 0;
    }

    public void updateData(List<LeaveRequest> newData) {
        this.leaveList = newData;
        notifyDataSetChanged();
    }

    static class ViewHolder extends RecyclerView.ViewHolder {
        TextView typeText, fromDateText, toDateText, statusText, reasonText;

        ViewHolder(View itemView) {
            super(itemView);
            typeText = itemView.findViewById(R.id.text_type);
            fromDateText = itemView.findViewById(R.id.text_from_date);
            toDateText = itemView.findViewById(R.id.text_to_date);
            statusText = itemView.findViewById(R.id.text_status);
            reasonText = itemView.findViewById(R.id.text_reason);
        }
    }
}
