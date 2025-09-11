# Device Management System Documentation

## Overview
The Device Management System provides comprehensive tracking and monitoring capabilities for all IoT devices, WiFi access points, Bluetooth devices, BLE beacons, RFID readers, IP cameras, and sensors used in the attendance tracking system.

## Features

### 🏢 Device Registry
- **Comprehensive Device Support**: WiFi APs, Bluetooth devices, BLE beacons, RFID readers, IP cameras, IoT sensors
- **Device Metadata**: Track manufacturer, model, location, and custom properties
- **Branch Assignment**: Associate devices with specific branch locations
- **Status Monitoring**: Track online/offline status and last seen timestamps
- **Group Management**: Organize devices into logical groups (Access Control, Network Infrastructure, etc.)

### 📊 Device Dashboard
- **Real-time Statistics**: Total devices, online devices, recent activity
- **Interactive Device List**: Filterable by type, branch, and status
- **Activity Feed**: Real-time device activity monitoring
- **Visual Analytics**: Device distribution charts and statistics
- **Device Discovery**: Network scanning for new devices

### 🔗 RESTful API
- **Device Registration**: POST endpoints for registering new devices
- **Heartbeat Monitoring**: Real-time device health checks
- **Activity Logging**: Track device events and interactions
- **Status Updates**: Real-time device status management

## Database Schema

### Tables Created
```sql
-- Main device registry
tbl_devices (DeviceID, DeviceName, DeviceType, Identifier, BranchID, Location, Manufacturer, Model, Description, IsActive, CreatedAt, UpdatedAt, LastSeenAt, Metadata, CreatedBy)

-- Device activity logging
tbl_device_activity (ActivityID, DeviceID, ActivityType, ActivityData, Timestamp, DetectedBy)

-- Device grouping
tbl_device_groups (GroupID, GroupName, Description, CreatedAt, CreatedBy)
tbl_device_group_assignments (AssignmentID, DeviceID, GroupID, AssignedAt, AssignedBy)
```

### Default Device Groups
1. **Access Control** - RFID readers, card scanners, biometric devices
2. **Network Infrastructure** - WiFi access points, switches, routers
3. **IoT Sensors** - Temperature, humidity, motion, environmental sensors
4. **Security Cameras** - IP cameras, surveillance equipment
5. **Employee Tracking** - BLE beacons, wearable devices, location trackers

## API Endpoints

### Device Management
- `GET /device_api.php?action=devices` - List all devices with filtering
- `GET /device_api.php?action=device&id={deviceId}` - Get device details
- `POST /device_api.php` (action=register) - Register new device
- `POST /device_api.php` (action=heartbeat) - Device heartbeat
- `POST /device_api.php` (action=log_activity) - Log device activity

### Statistics & Analytics
- `GET /device_api.php?action=stats` - Device statistics
- `GET /device_api.php?action=activity` - Recent activity feed
- `GET /device_api.php?action=discover` - Device discovery scan

### API Request Examples

#### Register Device
```json
POST /device_api.php
{
    "action": "register",
    "device_name": "Main Entrance Beacon",
    "device_type": "beacon",
    "identifier": "550e8400-e29b-41d4-a716-446655440000",
    "branch_id": "BR001",
    "location": "Main Entrance",
    "manufacturer": "Estimote",
    "model": "Proximity Beacon",
    "description": "BLE beacon for entrance tracking"
}
```

#### Send Heartbeat
```json
POST /device_api.php
{
    "action": "heartbeat",
    "identifier": "00:11:22:33:44:55",
    "device_type": "wifi",
    "metadata": {
        "signal_strength": -42,
        "connected_clients": 12,
        "temperature": 35.2
    }
}
```

## Device Types Supported

### 📶 WiFi Access Points
- **Identifier Format**: MAC Address (XX:XX:XX:XX:XX:XX)
- **Metadata**: Signal strength, connected clients, uptime, temperature
- **Use Case**: Indoor positioning, network infrastructure monitoring

### 📡 Bluetooth Devices
- **Identifier Format**: MAC Address (XX:XX:XX:XX:XX:XX)
- **Metadata**: Signal strength, device class, supported profiles
- **Use Case**: Device proximity detection, asset tracking

### 🎯 BLE Beacons
- **Identifier Format**: UUID (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
- **Metadata**: Battery level, transmission power, advertising interval
- **Use Case**: Indoor positioning, location-based attendance

### 🏷️ RFID Readers
- **Identifier Format**: Serial Number or Custom ID
- **Metadata**: Read range, frequency, antenna configuration
- **Use Case**: Card-based access control, asset tracking

### 📹 IP Cameras
- **Identifier Format**: Serial Number or Custom ID
- **Metadata**: Resolution, recording status, storage usage
- **Use Case**: Security monitoring, occupancy detection

### 🌡️ IoT Sensors
- **Identifier Format**: Serial Number or Custom ID
- **Metadata**: Sensor readings, battery level, calibration data
- **Use Case**: Environmental monitoring, smart building automation

## File Structure

```
attendance_register/
├── device_registry.php          # Device registration interface
├── device_dashboard.php         # Real-time device monitoring dashboard
├── device_api.php              # RESTful API for device management
├── simulate_devices.php         # Device heartbeat simulation script
├── migrations/
│   ├── 20250910_device_registry.sql    # Database schema
│   └── run_device_migration.php        # Migration runner
└── admin_dashboard.php          # Updated with device management links
```

## Integration with Attendance System

### Indoor Positioning
The device registry integrates seamlessly with the indoor positioning system:

1. **BLE Beacons** registered in the system are automatically available for indoor positioning
2. **WiFi Access Points** provide network-based location detection
3. **Branch Assignment** ensures devices are associated with correct locations

### Evidence Binding
Device registration enables:
- **Device Validation**: Only registered devices can provide attendance evidence
- **Security Enhancement**: Device signatures and metadata validation
- **Audit Trail**: Complete device activity logging for compliance

## Deployment Instructions

### 1. Database Setup
```bash
cd /path/to/attendance_register
php run_device_migration.php
```

### 2. Device Registration
- Navigate to **Admin Dashboard → Device Management → Register Device**
- Or use the Device Registry interface directly
- Or register via API endpoints

### 3. Device Integration
- Implement heartbeat mechanism in device firmware
- Use the provided `simulate_devices.php` as reference
- Configure devices to send periodic status updates

### 4. Monitoring Setup
- Access **Device Dashboard** for real-time monitoring
- Set up automated alerts for offline devices
- Review activity logs for troubleshooting

## Security Considerations

### Authentication
- All API endpoints require valid session authentication
- Admin role required for device management functions
- Device registration requires admin privileges

### Data Validation
- Device identifiers validated by type-specific formats
- Metadata sanitized to prevent injection attacks
- Activity logging includes source validation

### Privacy Protection
- Device metadata can include sensitive information
- Access controls prevent unauthorized device data access
- Activity logs include only necessary operational data

## Monitoring & Maintenance

### Health Checks
- Monitor device heartbeat intervals
- Track battery levels for battery-powered devices
- Alert on prolonged offline periods

### Performance Optimization
- Regular cleanup of old activity logs
- Index optimization for large device deployments
- Caching strategies for frequently accessed device data

### Troubleshooting
- Check device connectivity and network configuration
- Verify device registration and identifier formats
- Review activity logs for error patterns
- Use device discovery to identify configuration issues

## Future Enhancements

### Planned Features
- **Automated Device Discovery**: Network scanning and auto-registration
- **Predictive Maintenance**: AI-powered device health predictions
- **Integration APIs**: Connect with external device management systems
- **Mobile App**: Device management mobile interface
- **Bulk Operations**: Mass device registration and configuration

### Scalability Considerations
- Database partitioning for large device deployments
- Distributed device activity processing
- Load balancing for high-frequency heartbeat traffic
- Edge computing for local device management

---

## Support & Documentation

For additional support or questions about the device management system:
1. Review the API documentation in `device_api.php`
2. Check the database schema in migration files
3. Test with the provided simulation script
4. Monitor the device dashboard for operational insights

**Last Updated**: January 2025
**Version**: 1.0.0
