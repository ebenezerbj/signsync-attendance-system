/**
 * Location Verification Management JavaScript
 */

class LocationVerificationManager {
    constructor() {
        this.init();
    }

    init() {
        this.loadDashboard();
        this.setupEventListeners();
        this.loadBoundaries();
        this.loadSettings();
    }

    setupEventListeners() {
        // Tab change events
        document.getElementById('analytics-tab').addEventListener('click', () => {
            setTimeout(() => this.loadAnalytics(), 100);
        });

        document.getElementById('history-tab').addEventListener('click', () => {
            setTimeout(() => this.loadHistory(), 100);
        });

        // Form submissions
        document.getElementById('settingsForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSettings();
        });

        document.getElementById('addBoundaryForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.addBoundary();
        });

        // Filter events
        document.getElementById('historyDateFilter').addEventListener('change', () => {
            this.loadHistory();
        });

        document.getElementById('analyticsTimeframe').addEventListener('change', () => {
            this.loadAnalytics();
        });
    }

    async apiCall(endpoint, method = 'POST', data = {}) {
        try {
            const formData = new FormData();
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch(endpoint, {
                method: method,
                body: formData
            });

            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            this.showAlert('API call failed: ' + error.message, 'danger');
            return null;
        }
    }

    async loadDashboard() {
        try {
            // Load basic stats
            const stats = await this.apiCall('location_verification_api.php', 'POST', {
                action: 'get_dashboard_stats'
            });

            if (stats && stats.success) {
                document.getElementById('total-boundaries').textContent = stats.data.total_boundaries || 0;
                document.getElementById('avg-accuracy').textContent = stats.data.avg_accuracy ? Math.round(stats.data.avg_accuracy) + 'm' : '-';
                document.getElementById('avg-score').textContent = stats.data.avg_score ? Math.round(stats.data.avg_score) + '%' : '-';
                document.getElementById('alert-count').textContent = stats.data.alert_count || 0;
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
        }
    }

    async loadBoundaries() {
        const tbody = document.getElementById('boundariesTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        const data = await this.apiCall('location_verification_api.php', 'POST', {
            action: 'get_boundaries'
        });

        if (data && data.success) {
            let html = '';
            data.data.forEach(boundary => {
                const status = boundary.is_active == 1 ? 
                    '<span class="badge bg-success">Active</span>' : 
                    '<span class="badge bg-secondary">Inactive</span>';

                html += `
                    <tr>
                        <td>${boundary.branch_id}</td>
                        <td>${boundary.boundary_name}</td>
                        <td>${parseFloat(boundary.center_latitude).toFixed(6)}, ${parseFloat(boundary.center_longitude).toFixed(6)}</td>
                        <td>${boundary.radius_meters}m</td>
                        <td>${boundary.boundary_type}</td>
                        <td>${status}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="locationManager.editBoundary(${boundary.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="locationManager.deleteBoundary(${boundary.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html || '<tr><td colspan="7" class="text-center text-muted">No boundaries configured</td></tr>';
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load boundaries</td></tr>';
        }
    }

    async loadSettings() {
        const data = await this.apiCall('location_verification_api.php', 'POST', {
            action: 'get_settings'
        });

        if (data && data.success) {
            const settings = data.data;
            document.getElementById('defaultRadius').value = settings.default_workplace_radius || 200;
            document.getElementById('minAccuracy').value = settings.min_gps_accuracy || 50;
            document.getElementById('minScore').value = settings.min_location_score || 60;
            document.getElementById('alertThreshold').value = settings.distance_alert_threshold || 300;
            document.getElementById('requireLocation').checked = settings.require_location_for_clockin !== false;
            document.getElementById('autoDetectBranch').checked = settings.auto_detect_branch !== false;
        }
    }

    async saveSettings() {
        const settings = {
            action: 'update_settings',
            default_workplace_radius: document.getElementById('defaultRadius').value,
            min_gps_accuracy: document.getElementById('minAccuracy').value,
            min_location_score: document.getElementById('minScore').value,
            distance_alert_threshold: document.getElementById('alertThreshold').value,
            require_location_for_clockin: document.getElementById('requireLocation').checked,
            auto_detect_branch: document.getElementById('autoDetectBranch').checked
        };

        const result = await this.apiCall('location_verification_api.php', 'POST', settings);

        if (result && result.success) {
            this.showAlert('Settings saved successfully', 'success');
        } else {
            this.showAlert('Failed to save settings: ' + (result?.message || 'Unknown error'), 'danger');
        }
    }

    async addBoundary() {
        const formData = {
            action: 'add_boundary',
            branch_id: document.getElementById('branchId').value,
            boundary_name: document.getElementById('boundaryName').value,
            center_latitude: document.getElementById('centerLat').value,
            center_longitude: document.getElementById('centerLng').value,
            radius_meters: document.getElementById('radiusMeters').value,
            work_hours_start: document.getElementById('workHoursStart').value,
            work_hours_end: document.getElementById('workHoursEnd').value,
            timezone: document.getElementById('timezone').value
        };

        const result = await this.apiCall('location_verification_api.php', 'POST', formData);

        if (result && result.success) {
            this.showAlert('Boundary added successfully', 'success');
            document.getElementById('addBoundaryForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('addBoundaryModal')).hide();
            this.loadBoundaries();
            this.loadDashboard();
        } else {
            this.showAlert('Failed to add boundary: ' + (result?.message || 'Unknown error'), 'danger');
        }
    }

    async loadAnalytics() {
        const tbody = document.getElementById('analyticsTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        const timeframe = document.getElementById('analyticsTimeframe').value;
        const data = await this.apiCall('location_verification_api.php', 'POST', {
            action: 'get_analytics',
            days: timeframe
        });

        if (data && data.success) {
            let html = '';
            data.data.forEach(row => {
                const avgScore = parseFloat(row.avg_score);
                const atWorkplacePercent = row.total_verifications > 0 ? 
                    Math.round((row.at_workplace_count / row.total_verifications) * 100) : 0;

                const scoreClass = this.getScoreClass(avgScore);

                html += `
                    <tr>
                        <td>${row.employee_id}</td>
                        <td>${row.total_verifications}</td>
                        <td><span class="location-score ${scoreClass}">${Math.round(avgScore)}%</span></td>
                        <td>${Math.round(row.avg_distance)}m</td>
                        <td>${atWorkplacePercent}%</td>
                        <td>${Math.round(row.min_score)}% - ${Math.round(row.max_score)}%</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted">No analytics data available</td></tr>';
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load analytics</td></tr>';
        }
    }

    async loadHistory() {
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        const filters = {
            action: 'get_history',
            employee_id: document.getElementById('historyEmployeeFilter').value,
            verification_type: document.getElementById('historyTypeFilter').value,
            date: document.getElementById('historyDateFilter').value
        };

        const data = await this.apiCall('location_verification_api.php', 'POST', filters);

        if (data && data.success) {
            let html = '';
            data.data.forEach(row => {
                const timestamp = new Date(row.timestamp).toLocaleString();
                const scoreClass = this.getScoreClass(row.verification_score);
                const atWorkplace = row.is_at_workplace == 1 ? 
                    '<span class="badge bg-success">Yes</span>' : 
                    '<span class="badge bg-warning">No</span>';

                html += `
                    <tr>
                        <td>${row.employee_id}</td>
                        <td><span class="badge bg-info">${row.verification_type.replace('_', ' ')}</span></td>
                        <td>${parseFloat(row.latitude).toFixed(4)}, ${parseFloat(row.longitude).toFixed(4)}</td>
                        <td>${row.accuracy_meters ? Math.round(row.accuracy_meters) + 'm' : '-'}</td>
                        <td><span class="location-score ${scoreClass}">${Math.round(row.verification_score)}%</span></td>
                        <td>${atWorkplace}</td>
                        <td>${row.distance_from_workplace ? Math.round(row.distance_from_workplace) + 'm' : '-'}</td>
                        <td><small>${timestamp}</small></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html || '<tr><td colspan="8" class="text-center text-muted">No history records found</td></tr>';
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load history</td></tr>';
        }
    }

    getScoreClass(score) {
        if (score >= 90) return 'score-excellent';
        if (score >= 75) return 'score-good';
        if (score >= 60) return 'score-fair';
        if (score >= 40) return 'score-poor';
        return 'score-very-poor';
    }

    async editBoundary(boundaryId) {
        // Implementation for editing boundaries
        this.showAlert('Edit boundary feature coming soon', 'info');
    }

    async deleteBoundary(boundaryId) {
        if (!confirm('Are you sure you want to delete this boundary?')) {
            return;
        }

        const result = await this.apiCall('location_verification_api.php', 'POST', {
            action: 'delete_boundary',
            boundary_id: boundaryId
        });

        if (result && result.success) {
            this.showAlert('Boundary deleted successfully', 'success');
            this.loadBoundaries();
            this.loadDashboard();
        } else {
            this.showAlert('Failed to delete boundary: ' + (result?.message || 'Unknown error'), 'danger');
        }
    }

    showAlert(message, type = 'info') {
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alert);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.locationManager = new LocationVerificationManager();
});
