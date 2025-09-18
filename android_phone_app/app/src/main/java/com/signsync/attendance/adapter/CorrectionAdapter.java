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

public class CorrectionAdapter extends RecyclerView.Adapter<CorrectionAdapter.CorrectionViewHolder> {

    private List<AttendanceCorrection> correctionsList;

    public CorrectionAdapter(List<AttendanceCorrection> correctionsList) {
        this.correctionsList = correctionsList;
    }

    @NonNull
    @Override
    public CorrectionViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_correction, parent, false);
        return new CorrectionViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull CorrectionViewHolder holder, int position) {
        AttendanceCorrection correction = correctionsList.get(position);
        holder.bind(correction);
    }

    @Override
    public int getItemCount() {
        return correctionsList.size();
    }

    public class CorrectionViewHolder extends RecyclerView.ViewHolder {
        private TextView dateTextView;
        private TextView originalTimeTextView;
        private TextView correctedTimeTextView;
        private TextView reasonTextView;
        private TextView statusTextView;

        public CorrectionViewHolder(@NonNull View itemView) {
            super(itemView);
            dateTextView = itemView.findViewById(R.id.dateTextView);
            originalTimeTextView = itemView.findViewById(R.id.originalTimeTextView);
            correctedTimeTextView = itemView.findViewById(R.id.correctedTimeTextView);
            reasonTextView = itemView.findViewById(R.id.reasonTextView);
            statusTextView = itemView.findViewById(R.id.statusTextView);
        }

        public void bind(AttendanceCorrection correction) {
            dateTextView.setText(correction.getDate());
            originalTimeTextView.setText("Original: " + correction.getOriginalTime());
            correctedTimeTextView.setText("Corrected: " + correction.getCorrectedTime());
            reasonTextView.setText(correction.getReason());
            statusTextView.setText(correction.getStatus());
        }
    }
}
