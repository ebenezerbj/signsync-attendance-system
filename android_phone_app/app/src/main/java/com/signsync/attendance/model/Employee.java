package com.signsync.attendance.model;

import android.os.Parcel;
import android.os.Parcelable;
import com.google.gson.annotations.SerializedName;

public class Employee implements Parcelable {
    
    @SerializedName("id")
    private int id;
    
    @SerializedName("employee_id")
    private String employeeId;
    
    @SerializedName("name")
    private String name;
    
    @SerializedName("email")
    private String email;
    
    @SerializedName("phone")
    private String phone;
    
    @SerializedName("department")
    private String department;
    
    @SerializedName("position")
    private String position;
    
    @SerializedName("role")
    private String role;
    
    @SerializedName("branch_id")
    private int branchId;
    
    @SerializedName("branch_name")
    private String branchName;
    
    @SerializedName("shift_id")
    private int shiftId;
    
    @SerializedName("shift_name")
    private String shiftName;
    
    @SerializedName("hire_date")
    private String hireDate;
    
    @SerializedName("salary")
    private double salary;
    
    @SerializedName("is_active")
    private boolean isActive;
    
    @SerializedName("profile_picture")
    private String profilePicture;
    
    @SerializedName("address")
    private String address;
    
    @SerializedName("emergency_contact")
    private String emergencyContact;
    
    @SerializedName("created_at")
    private String createdAt;
    
    @SerializedName("updated_at")
    private String updatedAt;
    
    // Default constructor
    public Employee() {}
    
    // Constructor with basic info
    public Employee(String employeeId, String name, String email, String department, String position) {
        this.employeeId = employeeId;
        this.name = name;
        this.email = email;
        this.department = department;
        this.position = position;
        this.isActive = true;
        this.role = "employee";
    }
    
    // Parcelable implementation
    protected Employee(Parcel in) {
        id = in.readInt();
        employeeId = in.readString();
        name = in.readString();
        email = in.readString();
        phone = in.readString();
        department = in.readString();
        position = in.readString();
        role = in.readString();
        branchId = in.readInt();
        branchName = in.readString();
        shiftId = in.readInt();
        shiftName = in.readString();
        hireDate = in.readString();
        salary = in.readDouble();
        isActive = in.readByte() != 0;
        profilePicture = in.readString();
        address = in.readString();
        emergencyContact = in.readString();
        createdAt = in.readString();
        updatedAt = in.readString();
    }
    
    public static final Creator<Employee> CREATOR = new Creator<Employee>() {
        @Override
        public Employee createFromParcel(Parcel in) {
            return new Employee(in);
        }
        
        @Override
        public Employee[] newArray(int size) {
            return new Employee[size];
        }
    };
    
    @Override
    public int describeContents() {
        return 0;
    }
    
    @Override
    public void writeToParcel(Parcel dest, int flags) {
        dest.writeInt(id);
        dest.writeString(employeeId);
        dest.writeString(name);
        dest.writeString(email);
        dest.writeString(phone);
        dest.writeString(department);
        dest.writeString(position);
        dest.writeString(role);
        dest.writeInt(branchId);
        dest.writeString(branchName);
        dest.writeInt(shiftId);
        dest.writeString(shiftName);
        dest.writeString(hireDate);
        dest.writeDouble(salary);
        dest.writeByte((byte) (isActive ? 1 : 0));
        dest.writeString(profilePicture);
        dest.writeString(address);
        dest.writeString(emergencyContact);
        dest.writeString(createdAt);
        dest.writeString(updatedAt);
    }
    
    // Getters and Setters
    public int getId() {
        return id;
    }
    
    public void setId(int id) {
        this.id = id;
    }
    
    public String getEmployeeId() {
        return employeeId;
    }
    
    public void setEmployeeId(String employeeId) {
        this.employeeId = employeeId;
    }
    
    public String getName() {
        return name;
    }
    
    public void setName(String name) {
        this.name = name;
    }
    
    public String getEmail() {
        return email;
    }
    
    public void setEmail(String email) {
        this.email = email;
    }
    
    public String getPhone() {
        return phone;
    }
    
    public void setPhone(String phone) {
        this.phone = phone;
    }
    
    public String getDepartment() {
        return department;
    }
    
    public void setDepartment(String department) {
        this.department = department;
    }
    
    public String getPosition() {
        return position;
    }
    
    public void setPosition(String position) {
        this.position = position;
    }
    
    public String getRole() {
        return role;
    }
    
    public void setRole(String role) {
        this.role = role;
    }
    
    public int getBranchId() {
        return branchId;
    }
    
    public void setBranchId(int branchId) {
        this.branchId = branchId;
    }
    
    public String getBranchName() {
        return branchName;
    }
    
    public void setBranchName(String branchName) {
        this.branchName = branchName;
    }
    
    public int getShiftId() {
        return shiftId;
    }
    
    public void setShiftId(int shiftId) {
        this.shiftId = shiftId;
    }
    
    public String getShiftName() {
        return shiftName;
    }
    
    public void setShiftName(String shiftName) {
        this.shiftName = shiftName;
    }
    
    public String getHireDate() {
        return hireDate;
    }
    
    public void setHireDate(String hireDate) {
        this.hireDate = hireDate;
    }
    
    public double getSalary() {
        return salary;
    }
    
    public void setSalary(double salary) {
        this.salary = salary;
    }
    
    public boolean isActive() {
        return isActive;
    }
    
    public void setActive(boolean active) {
        isActive = active;
    }
    
    public String getProfilePicture() {
        return profilePicture;
    }
    
    public void setProfilePicture(String profilePicture) {
        this.profilePicture = profilePicture;
    }
    
    public String getAddress() {
        return address;
    }
    
    public void setAddress(String address) {
        this.address = address;
    }
    
    public String getEmergencyContact() {
        return emergencyContact;
    }
    
    public void setEmergencyContact(String emergencyContact) {
        this.emergencyContact = emergencyContact;
    }
    
    public String getCreatedAt() {
        return createdAt;
    }
    
    public void setCreatedAt(String createdAt) {
        this.createdAt = createdAt;
    }
    
    public String getUpdatedAt() {
        return updatedAt;
    }
    
    public void setUpdatedAt(String updatedAt) {
        this.updatedAt = updatedAt;
    }
    
    // Utility methods
    public boolean isAdmin() {
        return "admin".equalsIgnoreCase(role) || "super_admin".equalsIgnoreCase(role);
    }
    
    public boolean isManager() {
        return "manager".equalsIgnoreCase(role) || isAdmin();
    }
    
    public String getDisplayName() {
        return name != null ? name : employeeId;
    }
    
    public String getDepartmentPosition() {
        StringBuilder sb = new StringBuilder();
        if (department != null && !department.isEmpty()) {
            sb.append(department);
        }
        if (position != null && !position.isEmpty()) {
            if (sb.length() > 0) {
                sb.append(" - ");
            }
            sb.append(position);
        }
        return sb.toString();
    }
    
    public String getFullContactInfo() {
        StringBuilder sb = new StringBuilder();
        if (email != null && !email.isEmpty()) {
            sb.append(email);
        }
        if (phone != null && !phone.isEmpty()) {
            if (sb.length() > 0) {
                sb.append(" | ");
            }
            sb.append(phone);
        }
        return sb.toString();
    }
    
    @Override
    public String toString() {
        return "Employee{" +
                "id=" + id +
                ", employeeId='" + employeeId + '\'' +
                ", name='" + name + '\'' +
                ", email='" + email + '\'' +
                ", department='" + department + '\'' +
                ", position='" + position + '\'' +
                ", role='" + role + '\'' +
                ", isActive=" + isActive +
                '}';
    }
    
    @Override
    public boolean equals(Object obj) {
        if (this == obj) return true;
        if (obj == null || getClass() != obj.getClass()) return false;
        
        Employee employee = (Employee) obj;
        return id == employee.id;
    }
    
    @Override
    public int hashCode() {
        return Integer.hashCode(id);
    }
}
