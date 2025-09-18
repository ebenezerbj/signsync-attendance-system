package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;
import com.signsync.attendance.model.Employee;
import java.util.List;

public class EmployeeListResponse extends BaseResponse {
    
    @SerializedName("employees")
    private List<Employee> employees;
    
    @SerializedName("total_count")
    private int totalCount;
    
    @SerializedName("page")
    private int page;
    
    @SerializedName("page_size")
    private int pageSize;
    
    @SerializedName("total_pages")
    private int totalPages;
    
    public EmployeeListResponse() {
        super();
    }
    
    public EmployeeListResponse(boolean success, String message, List<Employee> employees) {
        this.setSuccess(success);
        this.setMessage(message);
        this.employees = employees;
        this.totalCount = employees != null ? employees.size() : 0;
    }
    
    // Getters and Setters
    public List<Employee> getEmployees() {
        return employees;
    }
    
    public void setEmployees(List<Employee> employees) {
        this.employees = employees;
        this.totalCount = employees != null ? employees.size() : 0;
    }
    
    public int getTotalCount() {
        return totalCount;
    }
    
    public void setTotalCount(int totalCount) {
        this.totalCount = totalCount;
    }
    
    public int getPage() {
        return page;
    }
    
    public void setPage(int page) {
        this.page = page;
    }
    
    public int getPageSize() {
        return pageSize;
    }
    
    public void setPageSize(int pageSize) {
        this.pageSize = pageSize;
    }
    
    public int getTotalPages() {
        return totalPages;
    }
    
    public void setTotalPages(int totalPages) {
        this.totalPages = totalPages;
    }
    
    // Utility methods
    public boolean hasEmployees() {
        return employees != null && !employees.isEmpty();
    }
    
    public int getEmployeeCount() {
        return employees != null ? employees.size() : 0;
    }
    
    public boolean hasMorePages() {
        return page < totalPages;
    }
    
    public Employee findEmployeeById(int employeeId) {
        if (employees != null) {
            for (Employee employee : employees) {
                if (employee.getId() == employeeId) {
                    return employee;
                }
            }
        }
        return null;
    }
    
    public Employee findEmployeeByEmployeeId(String employeeId) {
        if (employees != null && employeeId != null) {
            for (Employee employee : employees) {
                if (employeeId.equals(employee.getEmployeeId())) {
                    return employee;
                }
            }
        }
        return null;
    }
    
    @Override
    public String toString() {
        return "EmployeeListResponse{" +
                "success=" + isSuccess() +
                ", message='" + getMessage() + '\'' +
                ", totalCount=" + totalCount +
                ", page=" + page +
                ", pageSize=" + pageSize +
                ", totalPages=" + totalPages +
                ", employeeCount=" + getEmployeeCount() +
                '}';
    }
}
