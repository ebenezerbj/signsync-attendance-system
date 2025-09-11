# 🏢 SignSync - Advanced Attendance Management System

A comprehensive attendance tracking and management system with advanced features including indoor positioning, device management, and real-time monitoring.

## ✨ Features

### 🎯 **Core Attendance Management**
- **Employee Registration & Management** - Complete employee profiles with roles and permissions
- **Real-time Clock In/Out** - GPS-based and indoor positioning attendance tracking
- **Branch Management** - Multi-location support with GPS coordinates
- **Shift Management** - Flexible shift scheduling and management
- **Leave Management** - Leave requests, approvals, and tracking
- **Holiday Management** - Company-wide and branch-specific holidays

### 📱 **Advanced Positioning & Tracking**
- **GPS Attendance** - Outdoor location-based attendance with geofencing
- **Indoor Positioning** - WiFi and BLE beacon-based indoor attendance
- **Evidence Binding** - Cryptographic evidence validation for attendance integrity
- **Multi-Method Support** - GPS, WiFi, Bluetooth, and manual attendance options

### 🔧 **Device Management System**
- **IoT Device Registry** - Comprehensive tracking of all devices
- **Real-time Monitoring** - Live device status and health monitoring
- **Device Discovery** - Automatic network scanning for new devices
- **Activity Logging** - Complete audit trail of device interactions
- **Device Types Supported**:
  - 📶 WiFi Access Points
  - 📡 Bluetooth Devices  
  - 🎯 BLE Beacons
  - 🏷️ RFID Readers
  - 📹 IP Cameras
  - 🌡️ IoT Sensors

### 📊 **Reporting & Analytics**
- **Comprehensive Reports** - Attendance, leave, and performance reports
- **Export Options** - PDF and Excel export capabilities
- **Interactive Maps** - Visual attendance tracking with Google Maps integration
- **Real-time Dashboard** - Live statistics and monitoring
- **Activity Analytics** - Device usage and attendance patterns

### 🛡️ **Security & Compliance**
- **Role-based Access Control** - Admin, HR, and Employee roles
- **HMAC Signature Verification** - Cryptographic attendance validation
- **Session Management** - Secure authentication and authorization
- **Audit Trails** - Complete logging of all system activities

## 🚀 Installation

### Prerequisites
- **PHP 7.4+**
- **MySQL/MariaDB 5.7+**
- **Web Server** (Apache/Nginx)
- **Composer** (for dependencies)

### Setup Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/signsync-attendance.git
   cd signsync-attendance
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Database Setup**
   ```bash
   # Run the main migration
   php run_migration.php
   
   # Run device management migration
   php run_device_migration.php
   ```

4. **Configuration**
   - Copy `db.php.example` to `db.php`
   - Update database credentials in `db.php`
   - Configure your server settings

5. **Initial Setup**
   - Access `admin_dashboard.php`
   - Create your first admin user
   - Set up branches and employee roles

## 📱 **Mobile & API Support**

### Kiosk Mode
- **`kiosk.php`** - Touch-friendly interface for shared terminals
- **QR Code Integration** - Quick employee identification
- **Offline Capability** - Works without internet connection

### API Endpoints
- **Device Management API** - RESTful endpoints for IoT integration
- **Attendance API** - Mobile app integration support
- **Real-time Updates** - WebSocket support for live data

## 🏗️ **System Architecture**

### Core Components
```
📁 SignSync Attendance System
├── 🏠 Frontend (Bootstrap 5 + jQuery)
├── ⚙️ Backend (PHP 7.4+ with PDO)
├── 🗄️ Database (MySQL/MariaDB)
├── 📡 Device Management (IoT Integration)
├── 🔐 Security Layer (HMAC + Sessions)
└── 📊 Reporting Engine (PDF/Excel)
```

### Database Schema
- **Employee Management** - `tbl_employees`, `tbl_roles`, `tbl_employee_ranks`
- **Attendance Tracking** - `tbl_attendance`, `tbl_corrections`, `tbl_leave_requests`
- **Location Management** - `tbl_branches`, `tbl_shifts`, `tbl_holidays`
- **Device Management** - `tbl_devices`, `tbl_device_activity`, `tbl_device_groups`
- **Indoor Positioning** - `tbl_indoor_beacons`, `tbl_indoor_wifi`

## 🔧 **Advanced Configuration**

### Indoor Positioning Setup
1. **Register BLE Beacons** via Device Dashboard
2. **Configure WiFi Access Points** for location detection
3. **Set up Branch Boundaries** with GPS coordinates
4. **Test Indoor Positioning** with the diagnostic tools

### Device Integration
```php
// Example: Send device heartbeat
POST /device_api.php
{
    "action": "heartbeat",
    "identifier": "00:11:22:33:44:55",
    "device_type": "wifi",
    "metadata": {
        "signal_strength": -42,
        "connected_clients": 12
    }
}
```

## 📊 **Usage Guide**

### For Administrators
1. **Employee Management** - Add/edit employees and assign roles
2. **Branch Setup** - Configure office locations and GPS boundaries
3. **Device Registration** - Register WiFi APs, beacons, and IoT devices
4. **Report Generation** - Export attendance and performance reports
5. **System Monitoring** - Monitor device health and attendance patterns

### For HR Personnel
1. **Leave Management** - Review and approve leave requests
2. **Attendance Monitoring** - Track employee attendance patterns
3. **Report Access** - Generate departmental reports
4. **Employee Support** - Assist with attendance corrections

### For Employees
1. **Clock In/Out** - GPS or indoor positioning attendance
2. **Leave Requests** - Submit and track leave applications
3. **Attendance History** - View personal attendance records
4. **Profile Management** - Update personal information

## 🛠️ **Development**

### Project Structure
```
attendance_register/
├── 📄 Core PHP Files
│   ├── admin_dashboard.php      # Main admin interface
│   ├── employee_portal.php      # Employee self-service
│   ├── clockinout.php          # Attendance endpoint
│   └── device_api.php          # Device management API
├── 🎨 Frontend Assets
│   ├── images/                 # Logos and icons
│   └── uploads/               # User uploaded files
├── 🗄️ Database
│   ├── migrations/            # Database schema files
│   └── db.php                # Database configuration
├── 📦 Dependencies
│   ├── vendor/               # Composer packages
│   └── PhpOffice/           # Excel/PDF libraries
└── 📚 Documentation
    ├── README.md
    └── DEVICE_MANAGEMENT.md
```

### Contributing
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 🔒 **Security Considerations**

- **Input Validation** - All user inputs are sanitized and validated
- **SQL Injection Protection** - Prepared statements throughout
- **CSRF Protection** - Session-based CSRF tokens
- **Role-based Access** - Granular permission system
- **Audit Logging** - Complete activity trails
- **Data Encryption** - Sensitive data encryption at rest

## 📋 **System Requirements**

### Minimum Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Memory**: 512MB RAM
- **Storage**: 1GB available space
- **Network**: Internet connection for maps and updates

### Recommended Requirements
- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **Memory**: 2GB RAM
- **Storage**: 5GB available space
- **SSL Certificate** for production deployment

## 🚀 **Deployment**

### Production Checklist
- [ ] Update database credentials
- [ ] Enable HTTPS/SSL
- [ ] Configure proper file permissions
- [ ] Set up automated backups
- [ ] Configure email notifications
- [ ] Test all integrations
- [ ] Monitor system performance

## 📞 **Support**

For support and questions:
- 📖 **Documentation**: Check the `/docs` folder
- 🐛 **Issues**: Report bugs via GitHub Issues
- 💬 **Discussions**: Use GitHub Discussions for questions
- 📧 **Contact**: [Your contact information]

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 **Acknowledgments**

- **Bootstrap** - For the responsive UI framework
- **Chart.js** - For beautiful data visualizations
- **PhpSpreadsheet** - For Excel export functionality
- **Font Awesome** - For comprehensive iconography
- **Google Maps API** - For location services

---

**SignSync** - Revolutionizing attendance management with advanced technology and user-friendly design.

---

*Last Updated: September 2025*
*Version: 2.0.0*
