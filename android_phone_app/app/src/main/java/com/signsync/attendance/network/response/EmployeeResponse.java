package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;
import com.signsync.attendance.model.Employee;
import java.util.List;

public class EmployeeResponse extends BaseResponse {
    @SerializedName("employee")
    private Employee employee;

    @SerializedName("employees")
    private List<Employee> employees;

    public Employee getEmployee() {
        return employee;
    }

    public void setEmployee(Employee employee) {
        this.employee = employee;
    }

    public List<Employee> getEmployees() {
        return employees;
    }

    public void setEmployees(List<Employee> employees) {
        this.employees = employees;
    }
}
