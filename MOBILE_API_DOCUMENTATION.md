# Mobile App API Documentation

## Overview
This documentation covers all API endpoints that the mobile attendance application should use. All APIs return JSON responses and use POST method unless specified otherwise.

## Base URL
```
http://your-domain.com/attendance_register/
```

## Authentication

### 1. Employee Authentication
**Endpoint:** `employee_auth_api.php`

#### PIN Authentication (for mobile app)
```http
POST /employee_auth_api.php
Content-Type: application/x-www-form-urlencoded

employee_id=EMP001&pin=1234&auth_type=pin
```

**Response:**
```json
{
    "success": true,
    "message": "Authentication successful",
    "session_token": "abc123xyz789...",
    "employee_data": {
        "EmployeeID": "EMP001",
        "FullName": "John Doe",
        "BranchID": "MAIN001"
    }
}
```

#### Password Authentication (for web interface)
```http
POST /employee_auth_api.php
Content-Type: application/x-www-form-urlencoded

employee_id=EMP001&password=mypassword&auth_type=password
```

### 2. Session Validation
All subsequent API calls must include the `session_token` received from authentication.

## Attendance Management

### 3. Clock In/Out
**Endpoint:** `enhanced_clockinout_api.php`

#### Clock In
```http
POST /enhanced_clockinout_api.php
Content-Type: application/x-www-form-urlencoded

employee_id=EMP001&
session_token=abc123xyz789...&
latitude=14.5995&
longitude=120.9842&
accuracy=15&
photo_base64=iVBORw0KGgoAAAANSUhEUgA...&
device_info={"device_model":"Samsung Galaxy S21","app_version":"1.0.0","os_version":"Android 12"}
```

**Response:**
```json
{
    "success": true,
    "message": "Clock in successful",
    "clock_in_time": "2024-01-15 08:30:00",
    "attendance_status": "On Time",
    "location_verified": true,
    "location_details": {
        "workplace_location": true,
        "distance_from_workplace": 25.5,
        "verification_score": 85.2
    },
    "attendance_id": 123,
    "clockinout_id": 456
}
```

#### Clock Out
```http
POST /enhanced_clockinout_api.php
Content-Type: application/x-www-form-urlencoded

employee_id=EMP001&
session_token=abc123xyz789...&
action=clock_out&
latitude=14.5996&
longitude=120.9843&
accuracy=12
```

**Response:**
```json
{
    "success": true,
    "message": "Clock out successful",
    "clock_out_time": "2024-01-15 17:15:00",
    "work_duration": 8.75,
    "final_status": "Completed",
    "location_verified": true
}
```

### 4. Attendance Status
**Endpoint:** `attendance_status_api.php`

```http
POST /attendance_status_api.php
Content-Type: application/x-www-form-urlencoded

employee_id=EMP001
```

**Response:**
```json
{
    "success": true,
    "employee_id": "EMP001",
    "current_status": "Clocked In",
    "is_clocked_in": true,
    "today_clock_in": "2024-01-15 08:30:00",
    "today_clock_out": null,
    "work_duration": 2.5,
    "expected_clock_out": "17:00:00",
    "attendance_details": {
        "date": "2024-01-15",
        "status": "On Time",
        "total_work_hours": 2.5,
        "break_duration": 0,
        "overtime_hours": 0
    },
    "location_info": {
        "last_known_location": {
            "latitude": 14.5995,
            "longitude": 120.9842,
            "timestamp": "2024-01-15 08:30:00"
        },
        "is_at_workplace": true,
        "distance_from_workplace": 25.5
    },
    "schedule_info": {
        "scheduled_start": "08:00:00",
        "scheduled_end": "17:00:00",
        "is_holiday": false,
        "is_weekend": false
    }
}
```

## Device Management

### 5. Device Registration
**Endpoint:** `device_api.php`

```http
POST /device_api.php
Content-Type: application/x-www-form-urlencoded

action=register&
employee_id=EMP001&
device_id=unique_device_identifier&
device_name=Samsung Galaxy S21&
device_model=SM-G991B&
os_version=Android 12&
app_version=1.0.0
```

**Response:**
```json
{
    "success": true,
    "message": "Device registered successfully",
    "device_id": "unique_device_identifier",
    "registration_time": "2024-01-15 08:00:00"
}
```

## Location Services

### 6. Location Verification
Location verification is automatically performed during clock in/out operations. The system evaluates:

- **GPS Accuracy:** Minimum 50 meters (configurable)
- **Workplace Distance:** Within configured radius (default 200m)
- **Verification Score:** Composite score based on accuracy, distance, and consistency

#### Location Requirements
- **latitude:** Decimal degrees (e.g., 14.5995)
- **longitude:** Decimal degrees (e.g., 120.9842)
- **accuracy:** GPS accuracy in meters (lower is better)

#### Scoring System
- **90-100%:** Excellent (high accuracy, at workplace)
- **75-89%:** Good (good accuracy, near workplace)
- **60-74%:** Fair (acceptable accuracy, within extended range)
- **40-59%:** Poor (low accuracy or far from workplace)
- **0-39%:** Very Poor (very low accuracy or very far)

## Error Handling

### Standard Error Response
```json
{
    "success": false,
    "message": "Error description",
    "error_code": "AUTH_001",
    "details": {
        "field": "employee_id",
        "issue": "Employee not found"
    }
}
```

### Common Error Codes
- **AUTH_001:** Invalid credentials
- **AUTH_002:** Session expired
- **AUTH_003:** Employee not found
- **LOCATION_001:** GPS accuracy too low
- **LOCATION_002:** Not at workplace
- **ATTENDANCE_001:** Already clocked in
- **ATTENDANCE_002:** Not clocked in
- **DEVICE_001:** Device not registered
- **VALIDATION_001:** Missing required fields

## Security Considerations

### 1. Session Management
- Session tokens expire after 24 hours of inactivity
- Each login generates a new session token
- Logout invalidates the current session token

### 2. Location Security
- Location data is encrypted in transit
- GPS spoofing detection through consistency scoring
- Configurable workplace boundaries for each branch

### 3. Photo Upload
- Photos are base64 encoded
- Maximum size: 2MB per photo
- Supported formats: JPEG, PNG
- Photos are stored securely on the server

## Mobile App Implementation Guidelines

### 1. Authentication Flow
1. User enters Employee ID and PIN
2. App calls authentication API
3. Store session token securely (encrypted storage)
4. Include session token in all subsequent API calls
5. Handle session expiration gracefully

### 2. Location Handling
1. Request GPS permissions
2. Ensure location services are enabled
3. Wait for good GPS accuracy (< 50m recommended)
4. Include location data in clock in/out requests
5. Handle location verification failures

### 3. Offline Capability (Recommended)
1. Store attendance data locally when offline
2. Sync with server when connection is restored
3. Show offline status to user
4. Queue API calls for later execution

### 4. Error Handling
1. Parse error responses properly
2. Show user-friendly error messages
3. Retry failed requests with exponential backoff
4. Handle network timeouts gracefully

### 5. Performance Optimization
1. Cache employee data locally
2. Compress image uploads
3. Implement request timeouts (30 seconds recommended)
4. Use background sync for non-critical operations

## Testing Endpoints

### Test Mobile Integration
**Endpoint:** `test_mobile_integration.php`

Access this endpoint to run comprehensive tests of all mobile APIs. This is useful during development and integration phases.

## Configuration

### Location Settings
Administrators can configure location verification through:
- **Endpoint:** `location_verification_management.html`
- Workplace boundaries per branch
- GPS accuracy requirements
- Verification score thresholds

### General Settings
- Work hours and schedules
- Grace periods for late arrivals
- Overtime calculations
- Holiday management

## Rate Limiting

- **Authentication:** 10 requests per minute per IP
- **Clock In/Out:** 5 requests per minute per employee
- **Status Check:** 30 requests per minute per employee
- **Other APIs:** 60 requests per minute per IP

## Support and Troubleshooting

### Common Issues

1. **Location Verification Fails**
   - Ensure GPS is enabled and accurate
   - Check if employee is within workplace boundary
   - Verify workplace boundaries are configured correctly

2. **Authentication Issues**
   - Verify Employee ID exists in system
   - Check PIN is correct (4 digits)
   - Ensure employee is active

3. **Clock In/Out Problems**
   - Verify employee is not already clocked in (for clock in)
   - Verify employee is clocked in (for clock out)
   - Check session token validity

### Debug Mode
Add `debug=1` parameter to any API call for detailed debug information (development only).

---

**Last Updated:** January 2024  
**API Version:** 2.0  
**Supported Mobile Platforms:** Android 8+, iOS 12+
