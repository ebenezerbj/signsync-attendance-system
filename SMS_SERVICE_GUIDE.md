# SIGNSYNC SMS Service - Complete Implementation Guide

## 🚀 Overview

The SIGNSYNC SMS Service is a comprehensive, enterprise-grade SMS notification system that enhances your attendance management with real-time communications. This system provides:

- **Centralized SMS Management**: Unified service for all SMS operations
- **Template-Based Messaging**: Pre-configured message templates for different scenarios
- **Queue Management**: Reliable message delivery with retry mechanisms
- **Multiple Provider Support**: Currently supports SMSOnlineGH with extensible architecture
- **Comprehensive Logging**: Full audit trail of all SMS activities
- **Rate Limiting**: Protection against spam and cost control
- **Admin Dashboard**: Web-based management interface
- **API Integration**: Seamless integration with existing attendance features

## 📁 File Structure

```
attendance_register/
├── SignSyncSMSService.php           # Core SMS service class
├── sms_config.php                   # Configuration management
├── sms_admin.php                    # Admin dashboard interface
├── sms_test.php                     # Testing and monitoring interface
├── sms_health.php                   # Health check API
├── sms_pin_reset.php                # Enhanced PIN reset with SMS
├── run_sms_migration.php            # Database setup script
├── clockinout.php                   # Updated with SMS integration
└── .env.sms                         # Environment configuration template
```

## 🛠️ Installation & Setup

### 1. Run Database Migration

```bash
cd /path/to/attendance_register
php run_sms_migration.php
```

This creates the following tables:
- `tbl_sms_queue` - Message queue for batch processing
- `tbl_sms_logs` - Complete SMS activity logs
- `tbl_sms_config` - System configuration
- `tbl_sms_templates` - Message templates
- `tbl_sms_rate_limits` - Rate limiting data
- `tbl_pin_reset_codes` - PIN reset verification codes

### 2. Configure Environment Variables

Create `.env` file with your SMS provider credentials:

```env
# SMS Service Configuration
SMS_SMSONLINEGH_API_KEY=your_actual_api_key_here
SMS_SENDER_ID=SIGNSYNC
SMS_ENVIRONMENT=production
SMS_DEBUG=false
```

### 3. Update Existing Code

The migration automatically updates `clockinout.php` to use the new SMS service. Other files that need updates:

```php
// Include SMS service in your PHP files
require_once 'SignSyncSMSService.php';
require_once 'sms_config.php';

// Initialize SMS service
$smsService = createSMSService($conn);

// Send template message
$smsService->sendTemplateMessage('attendance_clockin', $phoneNumber, [
    'name' => $employeeName,
    'branch' => $branchName,
    'time' => $clockTime,
    'status' => $status
]);
```

## 📱 SMS Templates

The system includes 12 pre-configured templates:

### Attendance Templates
- `attendance_clockin` - Clock-in notifications
- `attendance_clockout` - Clock-out notifications  
- `late_arrival` - Late arrival warnings
- `missed_clockout` - Missed clock-out reminders

### Security Templates
- `pin_reset` - PIN reset confirmations
- `pin_setup` - New employee PIN setup
- `pin_changed` - PIN change confirmations

### Health & Safety Templates
- `stress_alert` - High stress level alerts
- `emergency_alert` - Emergency notifications

### Management Templates
- `shift_reminder` - Shift start reminders
- `leave_approved` - Leave request approvals
- `leave_rejected` - Leave request rejections

### Template Usage Example

```php
// Using template with dynamic data
$templateData = [
    'name' => 'John Doe',
    'branch' => 'Main Office',
    'time' => '09:00:00',
    'status' => 'On Time'
];

$smsService->sendTemplateMessage('attendance_clockin', '233241234567', $templateData);
```

## 🎛️ Admin Dashboard Features

Access the admin dashboard at: `your-domain.com/attendance_register/sms_admin.php`

### Dashboard Tab
- Real-time SMS statistics (24h)
- System health monitoring
- Quick queue processing
- Log cleanup tools

### Send SMS Tab
- Direct message sending
- Template-based sending with test data
- Message length validation
- Priority setting

### Templates Tab
- View all available templates
- Template content preview
- Variable documentation
- Category organization

### Logs Tab
- Recent SMS delivery logs
- Status filtering
- Cost tracking
- Message content preview

### Configuration Tab
- SMS provider settings
- Rate limiting configuration
- Queue management settings
- Template management

## 🔧 API Usage

### Basic SMS Sending

```php
// Direct message
$result = $smsService->sendMessage($phoneNumber, $message);

// Template message
$result = $smsService->sendTemplateMessage($templateName, $phoneNumber, $data);

// Bulk messages
$results = $smsService->sendBulkMessage($phoneNumbers, $message);
```

### Advanced Features

```php
// Priority messaging
$smsService->sendMessage($phone, $message, SignSyncSMSService::PRIORITY_HIGH);

// Scheduled messages
$smsService->sendMessage($phone, $message, SignSyncSMSService::PRIORITY_NORMAL, '2024-01-01 09:00:00');

// Queue processing
$processed = $smsService->processQueue(10); // Process 10 messages

// Statistics
$stats = $smsService->getStatistics('24h');

// Delivery reports
$reports = $smsService->getDeliveryReport($phoneNumber);
```

## 🔒 Security Features

### Rate Limiting
- Configurable limits per phone number
- Time-window based restrictions
- Automatic cleanup of old records

### PIN Reset Security
- 6-digit verification codes
- 15-minute expiration
- Maximum 3 requests per hour
- One-time use codes

### Environment Security
- API keys stored in environment variables
- Secure configuration management
- SSL certificate validation
- Input validation and sanitization

## 📊 Monitoring & Health Checks

### Health Check API
Access: `your-domain.com/attendance_register/sms_health.php?action=health`

Returns:
```json
{
    "status": "healthy",
    "checks": {
        "database": "OK",
        "tbl_sms_queue": "OK",
        "configuration": "OK"
    },
    "statistics": {
        "sent": 150,
        "failed": 2,
        "success_rate": 98.67
    }
}
```

### Testing Interface
Access: `your-domain.com/attendance_register/sms_test.php`

Features:
- Real-time system monitoring
- Direct SMS testing
- Template testing with sample data
- Provider connectivity tests
- Queue management tools
- Auto-refresh capabilities

## 💰 Cost Management

### Cost Tracking
- Per-SMS cost recording
- Daily/weekly/monthly reports
- Provider-specific cost analysis
- Budget alerts (configurable)

### Cost Optimization
- Queue batching reduces API calls
- Template reuse eliminates duplication
- Rate limiting prevents spam costs
- Failed message retry limits

## 🔄 Queue Management

### Queue States
- `pending` - Ready for sending
- `queued` - Scheduled for later
- `sent` - Successfully sent
- `failed` - Failed after retries
- `delivered` - Confirmed delivery

### Queue Processing
```php
// Manual processing
$processed = $smsService->processQueue(10);

// Automatic processing (recommended for cron)
php -f run_queue_processor.php
```

### Retry Logic
- 3 retry attempts by default
- 5-minute delay between retries
- Exponential backoff for persistent failures
- Failed message isolation

## 🚨 Error Handling

### Common Issues & Solutions

**SMS Not Sending**
1. Check API key configuration
2. Verify phone number format
3. Check rate limits
4. Review error logs

**Template Not Found**
1. Verify template name spelling
2. Check template is active
3. Review template variables

**Queue Processing Slow**
1. Increase batch size
2. Check API response times
3. Review network connectivity

### Error Logging
All errors are logged to:
- `tbl_sms_logs` - Database logs
- PHP error log - Server logs
- Activity logs - Application logs

## 📈 Integration Examples

### Attendance Integration
```php
// Clock-in with SMS notification
if ($clockInSuccess) {
    $smsService->sendTemplateMessage('attendance_clockin', $employee['PhoneNumber'], [
        'name' => $employee['FullName'],
        'branch' => $branch['BranchName'],
        'time' => date('H:i:s'),
        'status' => $status
    ]);
}
```

### PIN Reset Integration
```php
// PIN reset with SMS verification
$result = requestPINReset($conn, $smsService, $employeeId);
if ($result['success']) {
    echo "Reset code sent to your phone";
}
```

### Emergency Alerts
```php
// Emergency notification to supervisors
$supervisors = getSupervisors($branchId);
foreach ($supervisors as $supervisor) {
    $smsService->sendTemplateMessage('emergency_alert', $supervisor['PhoneNumber'], [
        'message' => 'Employee injury reported',
        'name' => $employee['FullName'],
        'employee_id' => $employeeId,
        'location' => $branch['BranchName'],
        'time' => date('Y-m-d H:i:s')
    ], SignSyncSMSService::PRIORITY_URGENT);
}
```

## 🔄 Maintenance

### Daily Tasks
- Monitor delivery rates
- Check failed messages
- Review cost reports

### Weekly Tasks
- Process old logs cleanup
- Review template performance
- Update configuration as needed

### Monthly Tasks
- Cost analysis and optimization
- Template effectiveness review
- System performance evaluation

### Automated Cleanup
```php
// Run monthly
$cleaned = $smsService->cleanup();
echo "Cleaned {$cleaned['deleted_logs']} logs and {$cleaned['deleted_queue']} queue items";
```

## 🆘 Support & Troubleshooting

### Debug Mode
Enable debug mode in environment:
```env
SMS_DEBUG=true
```

### Log Analysis
Check recent logs:
```php
$logs = $smsService->getDeliveryReport(null, date('Y-m-d'), null, 100);
```

### Health Monitoring
Set up automated health checks:
```bash
# Add to crontab - check every 5 minutes
*/5 * * * * curl -s http://your-domain.com/attendance_register/sms_health.php?action=health
```

## 🎯 Performance Optimization

### Best Practices
1. Use templates instead of custom messages
2. Batch similar messages together
3. Set appropriate priorities
4. Monitor and adjust rate limits
5. Regular log cleanup
6. Cache frequently used data

### Scaling Considerations
- Database indexing on phone numbers and timestamps
- Message queue optimization for high volume
- API rate limiting coordination
- Load balancing for multiple servers

## ✅ Success Metrics

Your SMS service is working correctly when you see:
- ✅ 95%+ delivery success rate
- ✅ Average delivery time < 30 seconds
- ✅ Zero failed health checks
- ✅ Templates rendering correctly
- ✅ Queue processing smoothly
- ✅ Cost tracking accurate
- ✅ Admin dashboard responsive

## 🔗 Related Documentation

- [SIGNSYNC Admin Guide](admin_dashboard.php)
- [PIN Management Guide](ADMIN_PIN_MANAGEMENT_GUIDE.md)
- [Device Management](DEVICE_MANAGEMENT.md)
- [WearOS Integration](WEAROS_INTEGRATION_GUIDE.md)

---

**SIGNSYNC SMS Service** - Comprehensive communication for modern attendance management.

*For support and questions, contact your system administrator or refer to the testing interface at `sms_test.php`.*
