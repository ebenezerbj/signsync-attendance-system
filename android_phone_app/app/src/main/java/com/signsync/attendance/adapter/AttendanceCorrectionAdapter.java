package com.signsync.attendance.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import com.signsync.attendance.R;
import com.signsync.attendance.model.AttendanceCorrection;
import java.util.List;

public class AttendanceCorrectionAdapter extends RecyclerView.Adapter<AttendanceCorrectionAdapter.ViewHolder> {
    private List<AttendanceCorrection> correctionList;
    private OnCorrectionClickListener listener;

    public interface OnCorrectionClickListener {
        void onCorrectionClick(AttendanceCorrection correction);
    }

    public AttendanceCorrectionAdapter(List<AttendanceCorrection> correctionList) {
        this.correctionList = correctionList;
    }

    public AttendanceCorrectionAdapter(List<AttendanceCorrection> correctionList, OnCorrectionClickListener listener) {
        this.correctionList = correctionList;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_attendance_correction, parent, false);
        return new ViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        AttendanceCorrection correction = correctionList.get(position);
        holder.dateText.setText(correction.getDate());
        holder.reasonText.setText(correction.getReason());
        holder.statusText.setText(correction.getStatus());
        holder.typeText.setText(correction.getType());
        
        if (listener != null) {
            holder.itemView.setOnClickListener(v -> listener.onCorrectionClick(correction));
        }
    }

    @Override
    public int getItemCount() {
        return correctionList != null ? correctionList.size() : 0;
    }

    public void updateData(List<AttendanceCorrection> newData) {
        this.correctionList = newData;
        notifyDataSetChanged();
    }

    static class ViewHolder extends RecyclerView.ViewHolder {
        TextView dateText, reasonText, statusText, typeText;

        ViewHolder(View itemView) {
            super(itemView);
            dateText = itemView.findViewById(R.id.text_date);
            reasonText = itemView.findViewById(R.id.text_reason);
            statusText = itemView.findViewById(R.id.text_status);
            typeText = itemView.findViewById(R.id.text_type);
        }
    }
}
