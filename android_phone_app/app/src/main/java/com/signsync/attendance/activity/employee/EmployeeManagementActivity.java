package com.signsync.attendance.activity.employee;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.SearchView;
import androidx.appcompat.widget.Toolbar;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.android.material.textfield.TextInputEditText;
import com.signsync.attendance.R;
import com.signsync.attendance.adapter.EmployeeAdapter;
import com.signsync.attendance.model.Employee;
import com.signsync.attendance.network.ApiClient;
import com.signsync.attendance.network.AttendanceApiService;
import com.signsync.attendance.network.response.EmployeeListResponse;
import java.util.ArrayList;
import java.util.List;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class EmployeeManagementActivity extends AppCompatActivity implements EmployeeAdapter.OnEmployeeClickListener {
    
    private RecyclerView employeeRecyclerView;
    private EmployeeAdapter employeeAdapter;
    private SwipeRefreshLayout swipeRefreshLayout;
    private TextInputEditText searchEditText;
    private ChipGroup filterChipGroup;
    private LinearLayout emptyStateLayout;
    private FloatingActionButton addEmployeeFab;
    
    private List<Employee> allEmployees = new ArrayList<>();
    private List<Employee> filteredEmployees = new ArrayList<>();
    private AttendanceApiService apiService;
    
    private String currentFilter = "all";
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_employee_management);
        
        initializeViews();
        setupToolbar();
        setupRecyclerView();
        setupSearch();
        setupFilters();
        setupSwipeRefresh();
        initializeApiService();
        loadEmployees();
    }
    
    private void initializeViews() {
        employeeRecyclerView = findViewById(R.id.employeeRecyclerView);
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);
        searchEditText = findViewById(R.id.searchEditText);
        filterChipGroup = findViewById(R.id.filterChipGroup);
        emptyStateLayout = findViewById(R.id.emptyStateLayout);
        addEmployeeFab = findViewById(R.id.addEmployeeFab);
        
        addEmployeeFab.setOnClickListener(v -> {
            startActivity(new Intent(this, AddEditEmployeeActivity.class));
        });
    }
    
    private void setupToolbar() {
        Toolbar toolbar = findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("Employee Management");
        }
    }
    
    private void setupRecyclerView() {
        employeeAdapter = new EmployeeAdapter(filteredEmployees, this);
        employeeRecyclerView.setLayoutManager(new LinearLayoutManager(this));
        employeeRecyclerView.setAdapter(employeeAdapter);
    }
    
    private void setupSearch() {
        searchEditText.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            
            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                filterEmployees(s.toString(), currentFilter);
            }
            
            @Override
            public void afterTextChanged(Editable s) {}
        });
    }
    
    private void setupFilters() {
        // All employees chip
        Chip allChip = findViewById(R.id.chipAll);
        allChip.setOnClickListener(v -> {
            currentFilter = "all";
            updateChipSelection(allChip);
            filterEmployees(searchEditText.getText().toString(), currentFilter);
        });
        
        // Active employees chip
        Chip activeChip = findViewById(R.id.chipActive);
        activeChip.setOnClickListener(v -> {
            currentFilter = "active";
            updateChipSelection(activeChip);
            filterEmployees(searchEditText.getText().toString(), currentFilter);
        });
        
        // Inactive employees chip
        Chip inactiveChip = findViewById(R.id.chipInactive);
        inactiveChip.setOnClickListener(v -> {
            currentFilter = "inactive";
            updateChipSelection(inactiveChip);
            filterEmployees(searchEditText.getText().toString(), currentFilter);
        });
        
        // Admin employees chip
        Chip adminChip = findViewById(R.id.chipAdmin);
        adminChip.setOnClickListener(v -> {
            currentFilter = "admin";
            updateChipSelection(adminChip);
            filterEmployees(searchEditText.getText().toString(), currentFilter);
        });
        
        // Set default selection
        updateChipSelection(allChip);
    }
    
    private void updateChipSelection(Chip selectedChip) {
        for (int i = 0; i < filterChipGroup.getChildCount(); i++) {
            Chip chip = (Chip) filterChipGroup.getChildAt(i);
            chip.setChecked(chip == selectedChip);
        }
    }
    
    private void setupSwipeRefresh() {
        swipeRefreshLayout.setOnRefreshListener(this::loadEmployees);
        swipeRefreshLayout.setColorSchemeResources(
                R.color.primary_color,
                R.color.secondary_color,
                R.color.accent_color
        );
    }
    
    private void initializeApiService() {
        apiService = ApiClient.getApiService();
    }
    
    private void loadEmployees() {
        swipeRefreshLayout.setRefreshing(true);
        
        Call<EmployeeListResponse> call = apiService.getAllEmployees();
        call.enqueue(new Callback<EmployeeListResponse>() {
            @Override
            public void onResponse(Call<EmployeeListResponse> call, Response<EmployeeListResponse> response) {
                swipeRefreshLayout.setRefreshing(false);
                
                if (response.isSuccessful() && response.body() != null) {
                    EmployeeListResponse employeeResponse = response.body();
                    if (employeeResponse.isSuccess()) {
                        allEmployees.clear();
                        allEmployees.addAll(employeeResponse.getEmployees());
                        filterEmployees(searchEditText.getText().toString(), currentFilter);
                        updateEmptyState();
                    } else {
                        showError("Failed to load employees: " + employeeResponse.getMessage());
                    }
                } else {
                    showError("Failed to load employees. Please try again.");
                }
            }
            
            @Override
            public void onFailure(Call<EmployeeListResponse> call, Throwable t) {
                swipeRefreshLayout.setRefreshing(false);
                showError("Network error: " + t.getMessage());
            }
        });
    }
    
    private void filterEmployees(String searchQuery, String filter) {
        filteredEmployees.clear();
        
        for (Employee employee : allEmployees) {
            boolean matchesSearch = searchQuery.isEmpty() || 
                    employee.getName().toLowerCase().contains(searchQuery.toLowerCase()) ||
                    employee.getEmployeeId().toLowerCase().contains(searchQuery.toLowerCase()) ||
                    employee.getEmail().toLowerCase().contains(searchQuery.toLowerCase());
            
            boolean matchesFilter = true;
            switch (filter) {
                case "active":
                    matchesFilter = employee.isActive();
                    break;
                case "inactive":
                    matchesFilter = !employee.isActive();
                    break;
                case "admin":
                    matchesFilter = "admin".equalsIgnoreCase(employee.getRole()) || 
                                  "super_admin".equalsIgnoreCase(employee.getRole());
                    break;
                case "all":
                default:
                    matchesFilter = true;
                    break;
            }
            
            if (matchesSearch && matchesFilter) {
                filteredEmployees.add(employee);
            }
        }
        
        employeeAdapter.notifyDataSetChanged();
        updateEmptyState();
    }
    
    private void updateEmptyState() {
        if (filteredEmployees.isEmpty()) {
            emptyStateLayout.setVisibility(View.VISIBLE);
            employeeRecyclerView.setVisibility(View.GONE);
        } else {
            emptyStateLayout.setVisibility(View.GONE);
            employeeRecyclerView.setVisibility(View.VISIBLE);
        }
    }
    
    private void showError(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }
    
    @Override
    public void onEmployeeClick(Employee employee) {
        Intent intent = new Intent(this, EmployeeDetailActivity.class);
        intent.putExtra("employee_id", employee.getId());
        intent.putExtra("employee_data", employee);
        startActivity(intent);
    }
    
    @Override
    public void onEmployeeEdit(Employee employee) {
        Intent intent = new Intent(this, AddEditEmployeeActivity.class);
        intent.putExtra("employee_id", employee.getId());
        intent.putExtra("employee_data", employee);
        intent.putExtra("is_edit_mode", true);
        startActivity(intent);
    }
    
    @Override
    public void onEmployeeDelete(Employee employee) {
        new androidx.appcompat.app.AlertDialog.Builder(this)
                .setTitle("Delete Employee")
                .setMessage("Are you sure you want to delete " + employee.getName() + "? This action cannot be undone.")
                .setPositiveButton("Delete", (dialog, which) -> deleteEmployee(employee))
                .setNegativeButton("Cancel", null)
                .show();
    }
    
    private void deleteEmployee(Employee employee) {
        Call<com.signsync.attendance.network.response.BaseResponse> call = 
                apiService.deleteEmployee(employee.getId());
        
        call.enqueue(new Callback<com.signsync.attendance.network.response.BaseResponse>() {
            @Override
            public void onResponse(Call<com.signsync.attendance.network.response.BaseResponse> call, 
                                 Response<com.signsync.attendance.network.response.BaseResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    if (response.body().isSuccess()) {
                        Toast.makeText(EmployeeManagementActivity.this, 
                                     "Employee deleted successfully", Toast.LENGTH_SHORT).show();
                        loadEmployees(); // Refresh the list
                    } else {
                        showError("Failed to delete employee: " + response.body().getMessage());
                    }
                } else {
                    showError("Failed to delete employee. Please try again.");
                }
            }
            
            @Override
            public void onFailure(Call<com.signsync.attendance.network.response.BaseResponse> call, Throwable t) {
                showError("Network error: " + t.getMessage());
            }
        });
    }
    
    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.menu_employee_management, menu);
        return true;
    }
    
    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        int itemId = item.getItemId();
        
        if (itemId == android.R.id.home) {
            onBackPressed();
            return true;
        } else if (itemId == R.id.action_export) {
            exportEmployeeList();
            return true;
        } else if (itemId == R.id.action_import) {
            importEmployees();
            return true;
        } else if (itemId == R.id.action_sync) {
            loadEmployees();
            return true;
        }
        
        return super.onOptionsItemSelected(item);
    }
    
    private void exportEmployeeList() {
        // TODO: Implement employee list export functionality
        Toast.makeText(this, "Export functionality coming soon", Toast.LENGTH_SHORT).show();
    }
    
    private void importEmployees() {
        // TODO: Implement employee import functionality
        Toast.makeText(this, "Import functionality coming soon", Toast.LENGTH_SHORT).show();
    }
    
    @Override
    protected void onResume() {
        super.onResume();
        // Refresh employee list when returning from other activities
        loadEmployees();
    }
}
