package com.signsync.attendance.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.cardview.widget.CardView;
import androidx.recyclerview.widget.RecyclerView;
import com.bumptech.glide.Glide;
import com.bumptech.glide.load.resource.bitmap.CircleCrop;
import com.google.android.material.chip.Chip;
import com.signsync.attendance.R;
import com.signsync.attendance.model.Employee;
import java.util.List;

public class EmployeeAdapter extends RecyclerView.Adapter<EmployeeAdapter.EmployeeViewHolder> {
    
    private List<Employee> employees;
    private OnEmployeeClickListener listener;
    private Context context;
    
    public interface OnEmployeeClickListener {
        void onEmployeeClick(Employee employee);
        void onEmployeeEdit(Employee employee);
        void onEmployeeDelete(Employee employee);
    }
    
    public EmployeeAdapter(List<Employee> employees, OnEmployeeClickListener listener) {
        this.employees = employees;
        this.listener = listener;
    }
    
    @NonNull
    @Override
    public EmployeeViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        context = parent.getContext();
        View view = LayoutInflater.from(context).inflate(R.layout.item_employee, parent, false);
        return new EmployeeViewHolder(view);
    }
    
    @Override
    public void onBindViewHolder(@NonNull EmployeeViewHolder holder, int position) {
        Employee employee = employees.get(position);
        holder.bind(employee);
    }
    
    @Override
    public int getItemCount() {
        return employees.size();
    }
    
    class EmployeeViewHolder extends RecyclerView.ViewHolder {
        
        private CardView employeeCard;
        private ImageView profileImageView;
        private TextView nameTextView;
        private TextView employeeIdTextView;
        private TextView departmentTextView;
        private TextView positionTextView;
        private TextView emailTextView;
        private TextView phoneTextView;
        private Chip statusChip;
        private Chip roleChip;
        private ImageView editImageView;
        private ImageView deleteImageView;
        
        public EmployeeViewHolder(@NonNull View itemView) {
            super(itemView);
            
            employeeCard = itemView.findViewById(R.id.employeeCard);
            profileImageView = itemView.findViewById(R.id.profileImageView);
            nameTextView = itemView.findViewById(R.id.nameTextView);
            employeeIdTextView = itemView.findViewById(R.id.employeeIdTextView);
            departmentTextView = itemView.findViewById(R.id.departmentTextView);
            positionTextView = itemView.findViewById(R.id.positionTextView);
            emailTextView = itemView.findViewById(R.id.emailTextView);
            phoneTextView = itemView.findViewById(R.id.phoneTextView);
            statusChip = itemView.findViewById(R.id.statusChip);
            roleChip = itemView.findViewById(R.id.roleChip);
            editImageView = itemView.findViewById(R.id.editImageView);
            deleteImageView = itemView.findViewById(R.id.deleteImageView);
        }
        
        public void bind(Employee employee) {
            // Basic info
            nameTextView.setText(employee.getName());
            employeeIdTextView.setText("ID: " + employee.getEmployeeId());
            
            // Department and position
            if (employee.getDepartment() != null && !employee.getDepartment().isEmpty()) {
                departmentTextView.setText(employee.getDepartment());
                departmentTextView.setVisibility(View.VISIBLE);
            } else {
                departmentTextView.setVisibility(View.GONE);
            }
            
            if (employee.getPosition() != null && !employee.getPosition().isEmpty()) {
                positionTextView.setText(employee.getPosition());
                positionTextView.setVisibility(View.VISIBLE);
            } else {
                positionTextView.setVisibility(View.GONE);
            }
            
            // Contact info
            if (employee.getEmail() != null && !employee.getEmail().isEmpty()) {
                emailTextView.setText(employee.getEmail());
                emailTextView.setVisibility(View.VISIBLE);
            } else {
                emailTextView.setVisibility(View.GONE);
            }
            
            if (employee.getPhone() != null && !employee.getPhone().isEmpty()) {
                phoneTextView.setText(employee.getPhone());
                phoneTextView.setVisibility(View.VISIBLE);
            } else {
                phoneTextView.setVisibility(View.GONE);
            }
            
            // Status chip
            if (employee.isActive()) {
                statusChip.setText("Active");
                statusChip.setChipBackgroundColorResource(R.color.success_green);
                statusChip.setTextColor(context.getResources().getColor(android.R.color.white));
            } else {
                statusChip.setText("Inactive");
                statusChip.setChipBackgroundColorResource(R.color.error_red);
                statusChip.setTextColor(context.getResources().getColor(android.R.color.white));
            }
            
            // Role chip
            String role = employee.getRole();
            if (role != null) {
                roleChip.setText(capitalizeFirst(role));
                roleChip.setVisibility(View.VISIBLE);
                
                // Set color based on role
                if (employee.isAdmin()) {
                    roleChip.setChipBackgroundColorResource(R.color.primary_color);
                } else if (employee.isManager()) {
                    roleChip.setChipBackgroundColorResource(R.color.secondary_color);
                } else {
                    roleChip.setChipBackgroundColorResource(R.color.accent_color);
                }
                roleChip.setTextColor(context.getResources().getColor(android.R.color.white));
            } else {
                roleChip.setVisibility(View.GONE);
            }
            
            // Profile image
            if (employee.getProfilePicture() != null && !employee.getProfilePicture().isEmpty()) {
                Glide.with(context)
                        .load(employee.getProfilePicture())
                        .transform(new CircleCrop())
                        .placeholder(R.drawable.ic_person_placeholder)
                        .error(R.drawable.ic_person_placeholder)
                        .into(profileImageView);
            } else {
                profileImageView.setImageResource(R.drawable.ic_person_placeholder);
            }
            
            // Click listeners
            employeeCard.setOnClickListener(v -> {
                if (listener != null) {
                    listener.onEmployeeClick(employee);
                }
            });
            
            editImageView.setOnClickListener(v -> {
                if (listener != null) {
                    listener.onEmployeeEdit(employee);
                }
            });
            
            deleteImageView.setOnClickListener(v -> {
                if (listener != null) {
                    listener.onEmployeeDelete(employee);
                }
            });
        }
        
        private String capitalizeFirst(String str) {
            if (str == null || str.isEmpty()) {
                return str;
            }
            return str.substring(0, 1).toUpperCase() + str.substring(1).toLowerCase();
        }
    }
    
    // Method to update the entire list
    public void updateEmployees(List<Employee> newEmployees) {
        this.employees.clear();
        this.employees.addAll(newEmployees);
        notifyDataSetChanged();
    }
    
    // Method to add a single employee
    public void addEmployee(Employee employee) {
        this.employees.add(employee);
        notifyItemInserted(employees.size() - 1);
    }
    
    // Method to remove an employee
    public void removeEmployee(int position) {
        if (position >= 0 && position < employees.size()) {
            employees.remove(position);
            notifyItemRemoved(position);
        }
    }
    
    // Method to update a single employee
    public void updateEmployee(int position, Employee employee) {
        if (position >= 0 && position < employees.size()) {
            employees.set(position, employee);
            notifyItemChanged(position);
        }
    }
    
    // Method to get employee at position
    public Employee getEmployee(int position) {
        if (position >= 0 && position < employees.size()) {
            return employees.get(position);
        }
        return null;
    }
    
    // Method to find employee position by ID
    public int findEmployeePosition(int employeeId) {
        for (int i = 0; i < employees.size(); i++) {
            if (employees.get(i).getId() == employeeId) {
                return i;
            }
        }
        return -1;
    }
}
