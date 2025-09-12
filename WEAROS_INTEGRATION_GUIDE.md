# WearOS Android Integration Guide
**SignSync Attendance System - Android Smartwatch Integration**
*Created: September 12, 2025*

## 🎯 Overview

The SignSync system now supports custom Android smartwatch integration for real-time employee health monitoring and stress detection. This comprehensive solution includes:

- **Custom Android APK** for Wear OS devices
- **Server-side API endpoint** for data processing
- **Real-time stress monitoring** with camera integration
- **Biometric data collection** (heart rate, stress levels, temperature)
- **Automatic alert system** for high-stress situations

## 📱 Android Application

### **Application Structure**
```
android_app/
├── app/
│   ├── build.gradle                 # Build configuration
│   └── src/main/
│       ├── AndroidManifest.xml      # App permissions & config
│       ├── java/com/signsync/wearable/
│       │   ├── MainActivity.java    # Main UI & sensor init
│       │   ├── HealthMonitoringService.java  # Background health monitoring
│       │   ├── ApiClient.java       # Server communication
│       │   └── HealthData.java      # Data model
│       └── res/
│           ├── layout/
│           │   └── activity_main.xml  # UI layout
│           └── values/
│               └── colors.xml        # App colors
```

### **Key Features**
- **Real-time Health Monitoring**: Continuous heart rate, stress level tracking
- **Background Services**: Persistent monitoring with foreground service
- **Automatic Stress Alerts**: Triggers camera monitoring when stress exceeds thresholds
- **Offline Support**: Local data storage when network unavailable
- **Employee Authentication**: PIN-based login system
- **Ambient Mode**: Battery-optimized always-on display

### **Permissions Required**
- `BODY_SENSORS` - Heart rate monitoring
- `ACTIVITY_RECOGNITION` - Step counting
- `INTERNET` - Server communication
- `FOREGROUND_SERVICE` - Background monitoring
- `WAKE_LOCK` - Continuous operation

## 🔗 Server-Side API

### **Endpoint**: `wearos_api.php`
**URL**: `http://your-domain.com/attendance_register/wearos_api.php`
**Method**: POST
**Content-Type**: application/json

### **Supported Actions**

#### **1. Ping Test**
```json
{
  "action": "ping",
  "device_type": "android_watch"
}
```
**Response**: Server status and API version

#### **2. Employee Authentication**
```json
{
  "action": "authenticate_employee",
  "employee_id": "AKCBSTF0005",
  "pin": "1234"
}
```
**Response**: Employee details and session token

#### **3. Health Data Submission**
```json
{
  "action": "submit_health_data",
  "employee_id": "AKCBSTF0005",
  "heart_rate": 85,
  "stress_level": 6.5,
  "temperature": 36.8,
  "steps": 5000,
  "timestamp": 1757636663,
  "device_type": "android_watch"
}
```
**Response**: Data ID and confirmation

#### **4. Stress Alert (Urgent)**
```json
{
  "action": "stress_alert",
  "employee_id": "AKCBSTF0005",
  "heart_rate": 105,
  "stress_level": 8.5,
  "alert_type": "high_stress",
  "urgent": true,
  "timestamp": 1757636663
}
```
**Response**: Alert ID and camera trigger status

#### **5. Employee Information**
```json
{
  "action": "get_employee_info",
  "employee_id": "AKCBSTF0005"
}
```
**Response**: Employee details and daily statistics

#### **6. Offline Data Sync**
```json
{
  "action": "sync_offline_data",
  "employee_id": "AKCBSTF0005",
  "data_batch": [
    {
      "heart_rate": 82,
      "stress_level": 5.0,
      "timestamp": 1757636600
    }
  ]
}
```
**Response**: Sync statistics (processed/failed)

## 🗄️ Database Integration

### **Tables Created/Modified**

#### **1. `tbl_biometric_data` (Enhanced)**
- Added `stress_level_numeric` for precise stress values (0.0-10.0)
- Added `device_type` for device identification
- Added `employee_id` for API compatibility
- Enhanced with WearOS-specific columns

#### **2. `tbl_biometric_alerts` (Enhanced)**
- Added real-time alert fields (heart_rate, stress_level)
- Added urgency flags and status tracking
- Integrated with camera monitoring system

#### **3. `employee_activity` (New)**
- Tracks last employee activity for monitoring
- Records health data submission timestamps

#### **4. `wearos_sessions` (New)**
- Manages authentication sessions for Android devices
- Session tokens with expiration

#### **5. `wearos_devices` (New)**
- Registry of Android smartwatch devices
- Device tracking and synchronization status

### **Database Views**
- `biometric_data` - Compatibility view for API access
- `biometric_alerts` - Unified alert access view

## 🎛️ Configuration

### **API Configuration Variables**
Located in `system_config` table:
- `wearos_api_enabled`: Enable/disable API (default: 1)
- `wearos_session_timeout`: Session duration in seconds (default: 7200)
- `wearos_stress_threshold`: Stress alert threshold (default: 7.0)
- `wearos_heart_rate_threshold`: Heart rate alert threshold (default: 100)
- `wearos_data_retention_days`: Data retention period (default: 90)
- `wearos_alert_cooldown`: Minimum seconds between alerts (default: 300)

### **Android App Configuration**
Edit `android_app/app/build.gradle`:
```gradle
buildConfigField "String", "API_BASE_URL", "\"https://your-domain.com/attendance_register/\""
buildConfigField "String", "API_ENDPOINT", "\"wearos_api.php\""
```

## 🔧 Integration with Camera System

### **Automatic Camera Triggers**
When stress levels exceed thresholds:
1. **Stress Alert Created** → `tbl_biometric_alerts`
2. **Camera Trigger** → `triggerCameraMonitoring()` function
3. **Camera Activation** → Your existing camera monitoring system
4. **Video Recording** → 30-minute session for stress incident

### **Camera Integration Points**
- Integrates with existing `camera_stress_monitor.php`
- Compatible with Hikvision PTZ camera system
- Automatic positioning and recording for stress events

## 🚀 Deployment Steps

### **1. Database Setup**
```bash
php run_wearos_migration.php
php fix_wearos_schema.php
```

### **2. API Deployment**
- Upload `wearos_api.php` to your web server
- Ensure proper file permissions (644)
- Test endpoint with `test_wearos_final.php`

### **3. Android App Build**
```bash
cd android_app
./gradlew assembleDebug  # For testing
./gradlew assembleRelease  # For production
```

### **4. Install on Smartwatch**
- Transfer APK to Android smartwatch
- Install via ADB or sideloading
- Grant required permissions

## 📊 Monitoring & Logs

### **API Logs**
Location: `logs/wearos_api.log`
```
[2025-09-12 02:24:23] [INFO] WearOS API: Received request: {"action":"ping"}
[2025-09-12 02:24:23] [WARNING] WearOS API: High stress detected for employee: AKCBSTF0005
[2025-09-12 02:24:23] [CRITICAL] WearOS API: STRESS ALERT received for employee: AKCBSTF0005
```

### **Health Data Dashboard**
Access via: `wellness_dashboard.php`
- Real-time employee health status
- Stress level trends and alerts
- Camera activation history

## 🔒 Security Features

### **Authentication**
- Session-based authentication with tokens
- 2-hour session timeout (configurable)
- Device registration and tracking

### **Data Protection**
- Encrypted data transmission (HTTPS required)
- Secure session management
- Audit logging for all activities

### **Privacy Compliance**
- Employee consent required for health monitoring
- Data retention policies configurable
- Access controls for sensitive health data

## 🧪 Testing & Validation

### **API Testing**
```bash
php test_wearos_final.php  # Comprehensive API test
php check_table_structure.php  # Database verification
```

### **Android App Testing**
- Install on test device
- Verify sensor access and data collection
- Test stress alert functionality
- Validate offline data synchronization

## 📈 Performance Optimization

### **Battery Optimization**
- Ambient mode support for always-on displays
- Intelligent sensor sampling rates
- Background service optimization

### **Network Efficiency**
- Batched data transmission (30-second intervals)
- Offline storage for network outages
- Compressed JSON payloads

### **Database Performance**
- Indexed columns for fast queries
- Optimized stress level calculations
- Efficient camera trigger mechanisms

## 🆘 Troubleshooting

### **Common Issues**

#### **API Returns 500 Error**
- Check `logs/wearos_api.log` for specific errors
- Verify database connection in `db.php`
- Ensure all required columns exist

#### **Android App Can't Connect**
- Verify API endpoint URL in build configuration
- Check network connectivity on smartwatch
- Ensure HTTPS if using SSL

#### **Stress Alerts Not Triggering**
- Verify stress threshold configuration
- Check camera system integration
- Review alert cooldown settings

#### **Data Not Syncing**
- Check employee ID validity in database
- Verify authentication session
- Review offline data storage

### **Employee ID Format**
Current system uses existing employee IDs:
- Example: `AKCBSTF0005` (Stephen Sarfo)
- Must match exactly with `tbl_employees.EmployeeID`

## 🎉 Success Metrics

✅ **API Endpoint**: Fully functional  
✅ **Database Schema**: Updated and compatible  
✅ **Android App**: Complete with all core features  
✅ **Camera Integration**: Ready for stress monitoring  
✅ **Real-time Monitoring**: Operational  
✅ **Stress Detection**: Automatic with thresholds  

The SignSync WearOS integration is now **production-ready** for comprehensive employee health monitoring and stress detection with automatic camera activation!

---
*For technical support or additional configuration, refer to the main SignSync documentation or contact the development team.*
