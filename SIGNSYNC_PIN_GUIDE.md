# SIGNSYNC PIN Validation Guide

## How PIN Validation Works

Your SIGNSYNC app now connects to your backend database for PIN validation. The PIN API (`signsync_pin_api.php`) uses multiple strategies to validate employee PINs:

### PIN Validation Strategies (in order of priority):

1. **Phone Number Strategy**: 
   - PIN = Last 4 digits of employee's phone number
   - Example: Phone "1234567890" → PIN "7890"

2. **Default PIN Strategy**: 
   - PIN = "1234" (works for all employees as fallback)

3. **Password Strategy**: 
   - PIN = Employee's actual password (if they know it)

4. **Employee ID Strategy**: 
   - PIN = Last 4 digits of Employee ID (padded with zeros)
   - Example: Employee ID "AKCBSTF001" → PIN "0001"
   - Example: Employee ID "AKCBSTF123" → PIN "0123"

### Testing Your PIN System:

1. **Find an Employee ID**: Check your `tbl_employees` table for existing employees
2. **Try these PINs in order**:
   - Last 4 digits of their phone number
   - "1234" (default)
   - Their actual password
   - Last 4 digits of Employee ID (with leading zeros)

### Backend Configuration:

- **PIN API**: `http://YOUR_SERVER_IP/attendance_register/signsync_pin_api.php`
- **Clock In/Out API**: `http://YOUR_SERVER_IP/attendance_register/signsync_clockinout_api.php`

### Android App Configuration:

Update the IP address in `MainActivity.java`:
```java
private static final String API_BASE = "http://192.168.1.100/attendance_register"; 
```
Change `192.168.1.100` to your actual server IP address.

### Database Tables Used:
- `tbl_employees` - Employee data and authentication
- `tbl_clockinout` - Attendance records
- `activity_logs` - System activity logging

### Example Test Employee:
If you have an employee with:
- Employee ID: "AKCBSTF001"
- Phone: "1234567890"

They can use any of these PINs:
- "7890" (phone strategy)
- "1234" (default strategy)  
- "0001" (employee ID strategy)

### Adding Custom PIN Column (Optional):
If you want dedicated PINs, add a PIN column to tbl_employees:
```sql
ALTER TABLE tbl_employees ADD COLUMN PIN VARCHAR(10) DEFAULT NULL;
```

Then update the PIN API to check this column first.
