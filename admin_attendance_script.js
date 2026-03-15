// Admin Attendance Management JavaScript
class AttendanceAdminManager {
    constructor() {
        this.apiBase = window.location.origin;
        this.currentSection = 'dashboard';
        this.attendanceTable = null;
        this.clockinoutTable = null;
        this.init();
    }

    init() {
        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString();
        
        // Set default date filters to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('attendance-date-from').value = today;
        document.getElementById('attendance-date-to').value = today;
        document.getElementById('clockinout-date-from').value = today;
        document.getElementById('clockinout-date-to').value = today;
        
        // Load initial data
        this.loadDashboard();
        this.loadEmployeeDropdowns();
    }

    // API call helper
    async apiCall(endpoint, method = 'GET', data = null) {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            };
            
            if (data && method !== 'GET') {
                options.body = new URLSearchParams(data);
            }
            
            const url = method === 'GET' && data ? 
                `${this.apiBase}/${endpoint}?${new URLSearchParams(data)}` : 
                `${this.apiBase}/${endpoint}`;
            
            const response = await fetch(url, options);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            this.showAlert('API call failed: ' + error.message, 'danger');
            return null;
        }
    }

    // Dashboard functions
    async loadDashboard() {
        try {
            // Load statistics
            const stats = await this.apiCall('admin_dashboard_api.php', 'POST', {
                action: 'get_stats'
            });
            
            if (stats && stats.success) {
                document.getElementById('total-employees').textContent = stats.data.total_employees || 0;
                document.getElementById('present-today').textContent = stats.data.present_today || 0;
                document.getElementById('absent-today').textContent = stats.data.absent_today || 0;
                document.getElementById('late-arrivals').textContent = stats.data.late_arrivals || 0;
            }
            
            // Load recent activity
            await this.loadRecentActivity();
            await this.loadLocationAlerts();
            
        } catch (error) {
            console.error('Error loading dashboard:', error);
        }
    }

    async loadRecentActivity() {
        const container = document.getElementById('recent-activity');
        container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        const data = await this.apiCall('admin_dashboard_api.php', 'POST', {
            action: 'get_recent_activity',
            limit: 10
        });
        
        if (data && data.success) {
            let html = '';
            data.data.forEach(activity => {
                const time = new Date(activity.timestamp).toLocaleTimeString();
                const statusClass = activity.action === 'clock_in' ? 'text-success' : 'text-primary';
                html += `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                        <div>
                            <strong>${activity.employee_name}</strong>
                            <span class="${statusClass}">
                                <i class="fas fa-${activity.action === 'clock_in' ? 'sign-in-alt' : 'sign-out-alt'}"></i>
                                ${activity.action.replace('_', ' ').toUpperCase()}
                            </span>
                            <br>
                            <small class="text-muted">${time}</small>
                        </div>
                        <div>
                            <span class="badge bg-${activity.status === 'Late' ? 'warning' : 'success'}">${activity.status}</span>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html || '<p class="text-muted">No recent activity</p>';
        } else {
            container.innerHTML = '<p class="text-danger">Failed to load recent activity</p>';
        }
    }

    async loadLocationAlerts() {
        const container = document.getElementById('location-alerts');
        container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        const data = await this.apiCall('admin_dashboard_api.php', 'POST', {
            action: 'get_location_alerts'
        });
        
        if (data && data.success) {
            let html = '';
            data.data.forEach(alert => {
                html += `
                    <div class="alert alert-warning p-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${alert.employee_name}</strong>
                                <br>
                                <small>${alert.message}</small>
                            </div>
                            <div>
                                <small class="text-muted">${new Date(alert.timestamp).toLocaleTimeString()}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html || '<p class="text-muted">No location alerts</p>';
        } else {
            container.innerHTML = '<p class="text-danger">Failed to load location alerts</p>';
        }
    }

    // Attendance Records functions
    async loadAttendanceRecords() {
        if (this.attendanceTable) {
            this.attendanceTable.destroy();
        }
        
        const data = await this.apiCall('admin_attendance_api.php', 'POST', {
            action: 'get_attendance_records',
            date_from: document.getElementById('attendance-date-from').value,
            date_to: document.getElementById('attendance-date-to').value,
            employee_id: document.getElementById('attendance-employee-filter').value,
            status: document.getElementById('attendance-status-filter').value
        });
        
        if (data && data.success) {
            const tableBody = document.querySelector('#attendance-table tbody');
            let html = '';
            
            data.data.forEach(record => {
                const statusClass = this.getStatusClass(record.Status);
                const clockIn = record.ClockIn ? new Date(`${record.AttendanceDate} ${record.ClockIn}`).toLocaleTimeString() : '-';
                const clockOut = record.ClockOut ? new Date(`${record.AttendanceDate} ${record.ClockOut}`).toLocaleTimeString() : '-';
                const duration = this.calculateDuration(record.ClockIn, record.ClockOut);
                
                html += `
                    <tr>
                        <td>${record.AttendanceID}</td>
                        <td>
                            <strong>${record.EmployeeID}</strong>
                            <br>
                            <small class="text-muted">${record.EmployeeName || 'Unknown'}</small>
                        </td>
                        <td>${new Date(record.AttendanceDate).toLocaleDateString()}</td>
                        <td>${clockIn}</td>
                        <td>${clockOut}</td>
                        <td><span class="badge ${statusClass}">${record.Status || 'Unknown'}</span></td>
                        <td>${duration}</td>
                        <td>
                            ${record.ClockInPhoto ? '<i class="fas fa-camera text-success" title="Clock In Photo"></i>' : ''}
                            ${record.ClockOutPhoto ? '<i class="fas fa-camera text-primary" title="Clock Out Photo"></i>' : ''}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewAttendanceDetails(${record.AttendanceID})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="editAttendanceRecord(${record.AttendanceID})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = html;
            
            // Initialize DataTable
            this.attendanceTable = $('#attendance-table').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[2, 'desc']],
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
            });
        }
    }

    // Clock In/Out Logs functions
    async loadClockinoutLogs() {
        if (this.clockinoutTable) {
            this.clockinoutTable.destroy();
        }
        
        const data = await this.apiCall('admin_attendance_api.php', 'POST', {
            action: 'get_clockinout_logs',
            date_from: document.getElementById('clockinout-date-from').value,
            date_to: document.getElementById('clockinout-date-to').value,
            employee_id: document.getElementById('clockinout-employee-filter').value,
            source: document.getElementById('clockinout-source-filter').value
        });
        
        if (data && data.success) {
            const tableBody = document.querySelector('#clockinout-table tbody');
            let html = '';
            
            data.data.forEach(record => {
                const clockIn = record.ClockIn ? new Date(record.ClockIn).toLocaleString() : '-';
                const clockOut = record.ClockOut ? new Date(record.ClockOut).toLocaleString() : '-';
                const duration = record.WorkDuration ? parseFloat(record.WorkDuration).toFixed(2) + 'h' : '-';
                const location = record.ClockInLocation ? `${record.gps_latitude}, ${record.gps_longitude}` : '-';
                
                html += `
                    <tr>
                        <td>${record.ID}</td>
                        <td>
                            <strong>${record.EmployeeID}</strong>
                            <br>
                            <small class="text-muted">${record.EmployeeName || 'Unknown'}</small>
                        </td>
                        <td>${clockIn}</td>
                        <td>${clockOut}</td>
                        <td>${duration}</td>
                        <td>
                            <span class="badge bg-info">${record.ClockInSource || 'Unknown'}</span>
                        </td>
                        <td>
                            ${record.is_at_workplace ? 
                                '<span class="badge bg-success">Workplace</span>' : 
                                '<span class="badge bg-warning">Remote</span>'
                            }
                            <br>
                            <small class="text-muted">${location}</small>
                        </td>
                        <td>${record.ClockInDevice || '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewClockinoutDetails(${record.ID})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = html;
            
            // Initialize DataTable
            this.clockinoutTable = $('#clockinout-table').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[2, 'desc']],
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
            });
        }
    }

    // Employee Status functions
    async loadEmployeeStatus() {
        const container = document.getElementById('employee-status-grid');
        container.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
        
        const data = await this.apiCall('admin_attendance_api.php', 'POST', {
            action: 'get_employee_status'
        });
        
        if (data && data.success) {
            let html = '';
            data.data.forEach(employee => {
                const statusClass = this.getEmployeeStatusClass(employee.current_status);
                const lastActivity = employee.last_activity ? 
                    new Date(employee.last_activity).toLocaleString() : 'No activity';
                
                html += `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">${employee.FullName}</h6>
                                        <small class="text-muted">${employee.EmployeeID}</small>
                                    </div>
                                    <span class="badge ${statusClass}">${employee.current_status}</span>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <small class="text-muted">Clock In</small>
                                        <div>${employee.clock_in_time || '-'}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Hours</small>
                                        <div>${employee.hours_worked || '0'}</div>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    ${lastActivity}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="col-12 text-center text-danger">Failed to load employee status</div>';
        }
    }

    // Employee dropdown loader
    async loadEmployeeDropdowns() {
        const data = await this.apiCall('admin_attendance_api.php', 'POST', {
            action: 'get_employees'
        });
        
        if (data && data.success) {
            let options = '<option value="">All Employees</option>';
            data.data.forEach(employee => {
                options += `<option value="${employee.EmployeeID}">${employee.EmployeeID} - ${employee.FullName}</option>`;
            });
            
            document.getElementById('attendance-employee-filter').innerHTML = options;
            document.getElementById('clockinout-employee-filter').innerHTML = options;
        }
    }

    // Utility functions
    getStatusClass(status) {
        switch(status) {
            case 'Present': case 'On Time': return 'bg-success';
            case 'Late': return 'bg-warning';
            case 'Early Leave': return 'bg-info';
            case 'Absent': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }

    getEmployeeStatusClass(status) {
        switch(status) {
            case 'Clocked In': return 'bg-success';
            case 'Clocked Out': return 'bg-primary';
            case 'Not Clocked In': return 'bg-secondary';
            case 'Holiday': return 'bg-info';
            default: return 'bg-secondary';
        }
    }

    calculateDuration(clockIn, clockOut) {
        if (!clockIn || !clockOut) return '-';
        
        const start = new Date(`2000-01-01 ${clockIn}`);
        const end = new Date(`2000-01-01 ${clockOut}`);
        const diff = (end - start) / (1000 * 60 * 60); // hours
        
        return diff > 0 ? diff.toFixed(1) + 'h' : '-';
    }

    showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }

    // Filter functions
    applyAttendanceFilters() {
        this.loadAttendanceRecords();
    }

    clearAttendanceFilters() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('attendance-date-from').value = today;
        document.getElementById('attendance-date-to').value = today;
        document.getElementById('attendance-employee-filter').value = '';
        document.getElementById('attendance-status-filter').value = '';
        this.loadAttendanceRecords();
    }

    applyClockinoutFilters() {
        this.loadClockinoutLogs();
    }

    clearClockinoutFilters() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('clockinout-date-from').value = today;
        document.getElementById('clockinout-date-to').value = today;
        document.getElementById('clockinout-employee-filter').value = '';
        document.getElementById('clockinout-source-filter').value = '';
        this.loadClockinoutLogs();
    }

    // Export functions
    async exportAttendanceData() {
        const data = await this.apiCall('admin_attendance_api.php', 'POST', {
            action: 'export_attendance',
            date_from: document.getElementById('attendance-date-from').value,
            date_to: document.getElementById('attendance-date-to').value,
            employee_id: document.getElementById('attendance-employee-filter').value,
            status: document.getElementById('attendance-status-filter').value
        });
        
        if (data && data.success) {
            // Create and download CSV
            const csvContent = this.arrayToCSV(data.data);
            this.downloadCSV(csvContent, 'attendance_records.csv');
        }
    }

    async exportClockinoutData() {
        const data = await this.apiCall('admin_attendance_api.php', 'POST', {
            action: 'export_clockinout',
            date_from: document.getElementById('clockinout-date-from').value,
            date_to: document.getElementById('clockinout-date-to').value,
            employee_id: document.getElementById('clockinout-employee-filter').value,
            source: document.getElementById('clockinout-source-filter').value
        });
        
        if (data && data.success) {
            const csvContent = this.arrayToCSV(data.data);
            this.downloadCSV(csvContent, 'clockinout_logs.csv');
        }
    }

    arrayToCSV(array) {
        if (!array.length) return '';
        
        const headers = Object.keys(array[0]);
        const csvArray = [headers.join(',')];
        
        array.forEach(row => {
            const values = headers.map(header => {
                const value = row[header];
                return value !== null && value !== undefined ? `"${value}"` : '""';
            });
            csvArray.push(values.join(','));
        });
        
        return csvArray.join('\n');
    }

    downloadCSV(csvContent, filename) {
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Global functions for HTML onclick events
let adminManager;

function showSection(section) {
    // Hide all sections
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(s => s.style.display = 'none');
    
    // Show selected section
    document.getElementById(section + '-section').style.display = 'block';
    
    // Update navigation
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => link.classList.remove('active'));
    event.target.classList.add('active');
    
    adminManager.currentSection = section;
    
    // Load section data
    switch(section) {
        case 'dashboard':
            adminManager.loadDashboard();
            break;
        case 'attendance-records':
            adminManager.loadAttendanceRecords();
            break;
        case 'clockinout-logs':
            adminManager.loadClockinoutLogs();
            break;
        case 'employee-status':
            adminManager.loadEmployeeStatus();
            break;
    }
}

function refreshDashboard() {
    adminManager.loadDashboard();
}

function loadAttendanceRecords() {
    adminManager.loadAttendanceRecords();
}

function loadClockinoutLogs() {
    adminManager.loadClockinoutLogs();
}

function loadEmployeeStatus() {
    adminManager.loadEmployeeStatus();
}

function applyAttendanceFilters() {
    adminManager.applyAttendanceFilters();
}

function clearAttendanceFilters() {
    adminManager.clearAttendanceFilters();
}

function applyClockinoutFilters() {
    adminManager.applyClockinoutFilters();
}

function clearClockinoutFilters() {
    adminManager.clearClockinoutFilters();
}

function exportAttendanceData() {
    adminManager.exportAttendanceData();
}

function exportClockinoutData() {
    adminManager.exportClockinoutData();
}

// Record management functions
async function viewAttendanceDetails(attendanceId) {
    const data = await adminManager.apiCall('admin_attendance_api.php', 'POST', {
        action: 'get_attendance_details',
        attendance_id: attendanceId
    });
    
    if (data && data.success) {
        const record = data.data;
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Basic Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Employee:</strong></td><td>${record.EmployeeID} - ${record.EmployeeName || 'Unknown'}</td></tr>
                        <tr><td><strong>Date:</strong></td><td>${new Date(record.AttendanceDate).toLocaleDateString()}</td></tr>
                        <tr><td><strong>Branch:</strong></td><td>${record.BranchID}</td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="badge ${adminManager.getStatusClass(record.Status)}">${record.Status}</span></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Time Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Clock In:</strong></td><td>${record.ClockIn || '-'}</td></tr>
                        <tr><td><strong>Clock Out:</strong></td><td>${record.ClockOut || '-'}</td></tr>
                        <tr><td><strong>Duration:</strong></td><td>${adminManager.calculateDuration(record.ClockIn, record.ClockOut)}</td></tr>
                        <tr><td><strong>Method:</strong></td><td>${record.ClockInMethod || '-'}</td></tr>
                    </table>
                </div>
            </div>
        `;
        
        if (record.Remarks) {
            html += `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Remarks</h6>
                        <p class="text-muted">${record.Remarks}</p>
                    </div>
                </div>
            `;
        }
        
        if (record.ClockInPhoto || record.ClockOutPhoto) {
            html += `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Photos</h6>
                        <div class="row">
            `;
            
            if (record.ClockInPhoto) {
                html += `
                    <div class="col-md-6">
                        <label class="form-label">Clock In Photo:</label>
                        <img src="${record.ClockInPhoto}" class="img-fluid rounded" alt="Clock In Photo">
                    </div>
                `;
            }
            
            if (record.ClockOutPhoto) {
                html += `
                    <div class="col-md-6">
                        <label class="form-label">Clock Out Photo:</label>
                        <img src="${record.ClockOutPhoto}" class="img-fluid rounded" alt="Clock Out Photo">
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        }
        
        document.getElementById('details-content').innerHTML = html;
        new bootstrap.Modal(document.getElementById('viewDetailsModal')).show();
    }
}

async function editAttendanceRecord(attendanceId) {
    const data = await adminManager.apiCall('admin_attendance_api.php', 'POST', {
        action: 'get_attendance_details',
        attendance_id: attendanceId
    });
    
    if (data && data.success) {
        const record = data.data;
        
        document.getElementById('edit-attendance-id').value = record.AttendanceID;
        document.getElementById('edit-employee-id').value = record.EmployeeID;
        document.getElementById('edit-attendance-date').value = record.AttendanceDate;
        document.getElementById('edit-clock-in').value = record.ClockIn || '';
        document.getElementById('edit-clock-out').value = record.ClockOut || '';
        document.getElementById('edit-status').value = record.Status || '';
        document.getElementById('edit-remarks').value = record.Remarks || '';
        
        new bootstrap.Modal(document.getElementById('editAttendanceModal')).show();
    }
}

async function saveAttendanceEdit() {
    const formData = {
        action: 'update_attendance',
        attendance_id: document.getElementById('edit-attendance-id').value,
        attendance_date: document.getElementById('edit-attendance-date').value,
        clock_in: document.getElementById('edit-clock-in').value,
        clock_out: document.getElementById('edit-clock-out').value,
        status: document.getElementById('edit-status').value,
        remarks: document.getElementById('edit-remarks').value
    };
    
    const result = await adminManager.apiCall('admin_attendance_api.php', 'POST', formData);
    
    if (result && result.success) {
        adminManager.showAlert('Attendance record updated successfully', 'success');
        bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal')).hide();
        adminManager.loadAttendanceRecords();
    } else {
        adminManager.showAlert('Failed to update attendance record', 'danger');
    }
}

async function viewClockinoutDetails(clockinoutId) {
    const data = await adminManager.apiCall('admin_attendance_api.php', 'POST', {
        action: 'get_clockinout_details',
        clockinout_id: clockinoutId
    });
    
    if (data && data.success) {
        const record = data.data;
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Basic Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Employee:</strong></td><td>${record.EmployeeID} - ${record.EmployeeName || 'Unknown'}</td></tr>
                        <tr><td><strong>Clock In:</strong></td><td>${new Date(record.ClockIn).toLocaleString()}</td></tr>
                        <tr><td><strong>Clock Out:</strong></td><td>${record.ClockOut ? new Date(record.ClockOut).toLocaleString() : 'Not clocked out'}</td></tr>
                        <tr><td><strong>Duration:</strong></td><td>${record.WorkDuration ? parseFloat(record.WorkDuration).toFixed(2) + 'h' : '-'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Source & Device</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Clock In Source:</strong></td><td>${record.ClockInSource}</td></tr>
                        <tr><td><strong>Clock Out Source:</strong></td><td>${record.ClockOutSource || '-'}</td></tr>
                        <tr><td><strong>Device:</strong></td><td>${record.ClockInDevice}</td></tr>
                        <tr><td><strong>At Workplace:</strong></td><td>${record.is_at_workplace ? 'Yes' : 'No'}</td></tr>
                    </table>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <h6>Location Data</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Latitude:</strong></td><td>${record.gps_latitude}</td></tr>
                        <tr><td><strong>Longitude:</strong></td><td>${record.gps_longitude}</td></tr>
                        <tr><td><strong>Accuracy:</strong></td><td>${record.gps_accuracy || 'Unknown'}</td></tr>
                        <tr><td><strong>Method:</strong></td><td>${record.location_method}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Verification</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Score:</strong></td><td>${record.location_verification_score}/100</td></tr>
                        <tr><td><strong>Created:</strong></td><td>${new Date(record.CreatedAt).toLocaleString()}</td></tr>
                        <tr><td><strong>Updated:</strong></td><td>${new Date(record.UpdatedAt).toLocaleString()}</td></tr>
                    </table>
                </div>
            </div>
        `;
        
        if (record.enhanced_location_data) {
            try {
                const locationData = JSON.parse(record.enhanced_location_data);
                html += `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Enhanced Location Data</h6>
                            <pre class="bg-light p-2 rounded">${JSON.stringify(locationData, null, 2)}</pre>
                        </div>
                    </div>
                `;
            } catch (e) {
                console.error('Error parsing location data:', e);
            }
        }
        
        document.getElementById('details-content').innerHTML = html;
        new bootstrap.Modal(document.getElementById('viewDetailsModal')).show();
    }
}

// Settings functions
async function saveSettings() {
    const settings = {
        action: 'save_settings',
        grace_period: document.getElementById('grace-period').value,
        overtime_threshold: document.getElementById('overtime-threshold').value,
        location_radius: document.getElementById('location-radius').value
    };
    
    const result = await adminManager.apiCall('admin_attendance_api.php', 'POST', settings);
    
    if (result && result.success) {
        adminManager.showAlert('Settings saved successfully', 'success');
    } else {
        adminManager.showAlert('Failed to save settings', 'danger');
    }
}

async function cleanupOldData() {
    if (confirm('Are you sure you want to cleanup data older than 1 year? This action cannot be undone.')) {
        const result = await adminManager.apiCall('admin_attendance_api.php', 'POST', {
            action: 'cleanup_old_data'
        });
        
        if (result && result.success) {
            adminManager.showAlert('Old data cleaned up successfully', 'success');
        } else {
            adminManager.showAlert('Failed to cleanup old data', 'danger');
        }
    }
}

async function syncTables() {
    const result = await adminManager.apiCall('admin_attendance_api.php', 'POST', {
        action: 'sync_tables'
    });
    
    if (result && result.success) {
        adminManager.showAlert('Tables synchronized successfully', 'success');
    } else {
        adminManager.showAlert('Failed to synchronize tables', 'danger');
    }
}

async function exportAllData() {
    const result = await adminManager.apiCall('admin_attendance_api.php', 'POST', {
        action: 'export_all_data'
    });
    
    if (result && result.success) {
        adminManager.showAlert('Data export initiated. Download will start shortly.', 'info');
        // The export functionality would need to be implemented in the backend
    } else {
        adminManager.showAlert('Failed to export data', 'danger');
    }
}

// Initialize the admin manager when the page loads
document.addEventListener('DOMContentLoaded', function() {
    adminManager = new AttendanceAdminManager();
});
