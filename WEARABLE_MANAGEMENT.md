# Wearable Device Management System

## Overview

The SignSync Attendance System includes a comprehensive IoT wearable device management system for monitoring employee wellness through biometric data collection. This system supports stress and fatigue level tracking using smartwatches and fitness trackers.

## Features

### 🔧 Device Management
- **Device Registration**: Register IoT wearable devices (smartwatches, fitness trackers)
- **Device Assignment**: Assign specific wearables to employees
- **Device Monitoring**: Track device status, battery levels, and connectivity
- **Device Reassignment**: Transfer devices between employees
- **Device Unassignment**: Remove device assignments when needed

### 📊 Biometric Monitoring
- **Heart Rate Monitoring**: Continuous heart rate tracking
- **Heart Rate Variability (HRV)**: Stress indicator measurement
- **Skin Temperature**: Body temperature monitoring
- **Blood Oxygen Levels**: SpO2 saturation tracking
- **Step Count**: Daily activity tracking
- **Sleep Quality**: Sleep pattern analysis
- **Activity Level**: Movement intensity classification

### 🚨 Alert System
- **Stress Alerts**: High stress level notifications
- **Fatigue Alerts**: Excessive fatigue warnings
- **Health Alerts**: Critical vital sign alerts
- **Inactivity Alerts**: Prolonged sedentary behavior notifications

### 📈 Analytics & Reporting
- **Wellness Dashboard**: Real-time employee wellness overview
- **Individual Monitoring**: Employee-specific health tracking
- **Trend Analysis**: Long-term health pattern analysis
- **Wellness Scores**: Overall employee wellness ratings

## Supported Devices

### Smartwatches
- **Apple Watch Series** (Series 4 and newer)
- **Samsung Galaxy Watch** (Galaxy Watch 4 and newer)
- **Garmin Fenix/Vivosmart** series
- **Fitbit Versa/Sense** series
- **Amazfit GTR/GTS** series

### Fitness Trackers
- **Fitbit Charge** series
- **Garmin Vivosmart** series
- **Xiaomi Mi Band**
- **Honor Band**
- **Generic IoT health trackers**

## System Architecture

### Database Tables

#### `tbl_employee_wearables`
Manages device-to-employee assignments
- `WearableID`: Primary key
- `EmployeeID`: Employee identifier
- `DeviceID`: Device identifier
- `AssignedDate`: Assignment timestamp
- `IsActive`: Assignment status

#### `tbl_biometric_data`
Stores biometric readings from wearables
- `BiometricID`: Primary key
- `EmployeeID`: Employee identifier
- `DeviceID`: Device source
- `Timestamp`: Reading timestamp
- `HeartRate`: Heart rate in BPM
- `HeartRateVariability`: HRV in milliseconds
- `StressLevel`: Calculated stress level (low/moderate/high/critical)
- `FatigueLevel`: Calculated fatigue level (rested/mild/moderate/severe)
- `SkinTemperature`: Body temperature in Celsius
- `BloodOxygen`: SpO2 percentage
- `StepCount`: Daily step count
- `SleepQuality`: Sleep rating (poor/fair/good/excellent)
- `ActivityLevel`: Activity intensity (sedentary/light/moderate/vigorous)

#### `tbl_biometric_alerts`
Manages wellness alerts and notifications
- `AlertID`: Primary key
- `EmployeeID`: Employee identifier
- `AlertType`: Alert category (stress/fatigue/health/inactivity)
- `Severity`: Alert severity (low/medium/high/critical)
- `AlertMessage`: Human-readable alert description
- `IsAcknowledged`: Acknowledgment status
- `CreatedAt`: Alert timestamp

#### `tbl_wellness_reports`
Daily wellness summaries
- `ReportID`: Primary key
- `EmployeeID`: Employee identifier
- `ReportDate`: Report date
- `AvgStressLevel`: Daily average stress score
- `AvgFatigueLevel`: Daily average fatigue score
- `WellnessScore`: Overall wellness rating (0-100)

#### `tbl_biometric_thresholds`
Configurable alert thresholds
- `ThresholdID`: Primary key
- `EmployeeID`: Employee-specific thresholds (NULL for global)
- `MetricType`: Threshold type (heart_rate/stress/fatigue/etc.)
- `LowThreshold`: Low alert threshold
- `MediumThreshold`: Medium alert threshold
- `HighThreshold`: High alert threshold
- `CriticalThreshold`: Critical alert threshold

### API Endpoints

#### `biometric_api.php`
RESTful API for biometric data management

**POST** - Submit biometric data
```json
{
  "employee_id": "EMP001",
  "device_id": 1,
  "heart_rate": 72,
  "heart_rate_variability": 45,
  "skin_temperature": 36.8,
  "blood_oxygen": 98,
  "step_count": 8500,
  "sleep_quality": "good",
  "activity_level": "moderate"
}
```

**GET** - Retrieve biometric data
```
GET /biometric_api.php?employee_id=EMP001&start_date=2025-01-01&end_date=2025-01-07
```

**PUT** - Assign wearable device
```json
{
  "employee_id": "EMP001",
  "device_id": 1
}
```

## User Interfaces

### 1. Wearable Assignments (`wearable_assignments.php`)
- **Purpose**: Manage device-to-employee assignments
- **Features**:
  - View all active assignments
  - Assign new wearables to employees
  - Reassign devices between employees
  - Unassign devices from employees
  - Search and filter assignments
  - Assignment statistics and coverage rates

### 2. Wellness Dashboard (`wellness_dashboard.php`)
- **Purpose**: Monitor employee wellness in real-time
- **Features**:
  - Live biometric monitoring
  - Stress and fatigue distribution charts
  - High-risk employee identification
  - Individual employee health tracking
  - Alert management and acknowledgment
  - Overall wellness scoring

### 3. Device Registry (`device_registry.php`)
- **Purpose**: Register and manage IoT devices
- **Features**:
  - Add new wearable devices
  - Configure device settings
  - Monitor device status
  - Device group management
  - Activity logging

## Implementation Guide

### 1. Device Registration
1. Navigate to **Device Registry**
2. Select device type: **IoT**
3. Enter device details:
   - Device name (e.g., "Apple Watch Series 9")
   - Identifier (device MAC address or serial number)
   - Manufacturer and model
   - Location/branch assignment
4. Activate device

### 2. Employee Assignment
1. Navigate to **Wearable Assignments**
2. Select available employee from dropdown
3. Choose unassigned wearable device
4. Click **Assign Wearable**
5. Verify assignment in the assignments table

### 3. Biometric Data Collection
Wearable devices should send data to the biometric API endpoint:
- **Endpoint**: `POST /biometric_api.php`
- **Content-Type**: `application/json`
- **Headers**: 
  - `X-Device-ID`: Device identifier
  - `X-Employee-ID`: Employee identifier

### 4. Wellness Monitoring
1. Navigate to **Employee Wellness**
2. Monitor real-time statistics
3. Review high-risk employees
4. Acknowledge alerts as needed
5. Generate wellness reports

## Alert Thresholds

### Default Global Thresholds

| Metric | Low | Medium | High | Critical |
|--------|-----|--------|------|----------|
| Heart Rate (BPM) | 60 | 100 | 120 | 150 |
| Stress Level (1-4) | 1 | 2 | 3 | 4 |
| Fatigue Level (1-4) | 1 | 2 | 3 | 4 |
| Body Temperature (°C) | 36.0 | 37.0 | 38.0 | 39.0 |
| Blood Oxygen (%) | 95 | 90 | 85 | 80 |
| Inactivity (minutes) | 60 | 120 | 180 | 240 |

### Customizable Thresholds
- **Employee-specific**: Custom thresholds per employee
- **Branch-specific**: Department or location-based thresholds
- **Global defaults**: System-wide baseline thresholds

## Stress & Fatigue Calculation

### Stress Level Calculation
```php
function calculateStressLevel($heartRate, $hrv, $skinTemp = null) {
    $stressScore = 0;
    
    // Heart rate contribution (40% weight)
    if ($heartRate > 120) $stressScore += 4;
    elseif ($heartRate > 100) $stressScore += 3;
    elseif ($heartRate > 80) $stressScore += 2;
    else $stressScore += 1;
    
    // HRV contribution (40% weight) - lower HRV = higher stress
    if ($hrv < 20) $stressScore += 4;
    elseif ($hrv < 30) $stressScore += 3;
    elseif ($hrv < 40) $stressScore += 2;
    else $stressScore += 1;
    
    // Return calculated stress level
    $avgScore = $stressScore / 2;
    if ($avgScore >= 3.5) return 'critical';
    elseif ($avgScore >= 2.5) return 'high';
    elseif ($avgScore >= 1.5) return 'moderate';
    else return 'low';
}
```

### Fatigue Level Calculation
```php
function calculateFatigueLevel($heartRate, $stepCount, $sleepQuality, $activityLevel) {
    $fatigueScore = 0;
    
    // Resting heart rate indicator
    if ($heartRate > 80) $fatigueScore += 3;
    elseif ($heartRate > 70) $fatigueScore += 2;
    elseif ($heartRate > 60) $fatigueScore += 1;
    
    // Activity level assessment
    switch($activityLevel) {
        case 'sedentary': $fatigueScore += 3; break;
        case 'light': $fatigueScore += 2; break;
        case 'moderate': $fatigueScore += 1; break;
        case 'vigorous': $fatigueScore += 0; break;
    }
    
    // Return calculated fatigue level
    $avgScore = $fatigueScore / 4;
    if ($avgScore >= 3) return 'severe';
    elseif ($avgScore >= 2) return 'moderate';
    elseif ($avgScore >= 1) return 'mild';
    else return 'rested';
}
```

## Security Considerations

### Data Privacy
- **GDPR Compliance**: Employee consent for biometric data collection
- **Data Encryption**: Encrypt sensitive health data at rest and in transit
- **Access Control**: Role-based access to wellness data
- **Data Retention**: Configurable data retention policies

### Device Security
- **Device Authentication**: Secure device registration and verification
- **Data Integrity**: HMAC signature verification for data submissions
- **Anti-replay Protection**: Timestamp-based replay attack prevention
- **Secure Communication**: HTTPS/TLS for all API communications

## Troubleshooting

### Common Issues

1. **Device Not Connecting**
   - Verify device registration in Device Registry
   - Check network connectivity
   - Validate API credentials

2. **Missing Biometric Data**
   - Confirm device assignment to employee
   - Check device battery and connectivity
   - Verify API endpoint accessibility

3. **Alerts Not Triggering**
   - Review threshold configurations
   - Check biometric data quality
   - Verify alert acknowledgment settings

4. **Dashboard Not Updating**
   - Confirm real-time data reception
   - Check browser cache and refresh
   - Verify database connectivity

### Support
For technical support and troubleshooting assistance, consult the system logs and database error messages. All biometric operations are logged for audit and debugging purposes.

## Future Enhancements

### Planned Features
- **AI-powered wellness predictions**
- **Integration with popular fitness apps**
- **Wearable device firmware updates**
- **Advanced analytics and machine learning**
- **Employee wellness coaching recommendations**
- **Integration with occupational health systems**
