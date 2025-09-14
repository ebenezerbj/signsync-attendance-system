# SIGNSYNC Custom PIN Setup Guide

## 🎯 What's New:

Your SIGNSYNC system now supports **custom PIN creation** for enhanced security and personalization!

## 🔄 How the New PIN System Works:

### **First-Time Login Flow:**
1. **Employee enters their ID + default PIN "1234"**
2. **System authenticates successfully** 
3. **System detects: "This user needs PIN setup"**
4. **Dialog appears: "Create Your Personal PIN"**
5. **Employee creates 4-8 digit custom PIN**
6. **System saves custom PIN to database**
7. **Employee proceeds to clock in**

### **Subsequent Logins:**
1. **Employee enters their ID + custom PIN**
2. **System authenticates with custom PIN**
3. **Employee proceeds directly to clock in**

## 📱 Updated APK:
- **File**: `SIGNSYNC-v3-CustomPIN-Setup.apk`
- **Features**: All previous features + PIN setup dialog

## 🗄️ Database Changes:
Two new columns added to `tbl_employees`:
- `CustomPIN` - Stores the user's personal PIN
- `PINSetupComplete` - Tracks if user has completed PIN setup

## 🔧 Setup Instructions:

### 1. **Update Database**
Run this to add PIN support:
```bash
php setup_custom_pin_support.php
```

### 2. **Install New APK**
Install `SIGNSYNC-v3-CustomPIN-Setup.apk` on your WS10 ULTRA device

### 3. **Test the System**
Use the web tester: `http://localhost:8080/test_pin_setup.html`

## 🎮 Testing Your PIN Setup:

### **Test Scenario 1: First-Time User**
1. Employee ID: `AKCBSTF0005`
2. PIN: `1234` (default)
3. Expected: Login success + PIN setup dialog
4. Create custom PIN: `5678`
5. Expected: PIN saved + clock in proceeds

### **Test Scenario 2: Returning User**
1. Employee ID: `AKCBSTF0005`  
2. PIN: `5678` (their custom PIN)
3. Expected: Direct login + clock in

## 🔑 PIN Validation Priority:

1. **Custom PIN** (highest priority) - User's personal PIN
2. **Default PIN "1234"** - For first-time users or fallback
3. **Legacy PINs** (backward compatibility):
   - Last 4 digits of phone number
   - Last 4 digits of employee ID (padded)
   - Employee password

## 💡 Employee Instructions:

### **For First-Time Users:**
- "Use PIN **1234** for your first login"
- "You'll be asked to create your personal PIN"
- "Choose a 4-8 digit PIN you'll remember"

### **For Returning Users:**
- "Use the personal PIN you created"
- "If you forgot it, contact admin to reset"

## 🛠️ Admin Features:

### **Reset Employee PIN:**
```sql
UPDATE tbl_employees 
SET CustomPIN = NULL, PINSetupComplete = 0 
WHERE EmployeeID = 'EMPLOYEE_ID_HERE';
```

### **Check PIN Status:**
```sql
SELECT EmployeeID, FullName, 
       CASE WHEN CustomPIN IS NOT NULL THEN 'Custom PIN Set' ELSE 'Using Default' END as PINStatus,
       PINSetupComplete
FROM tbl_employees;
```

## 🔍 Testing Commands:

```bash
# Setup database
php setup_custom_pin_support.php

# Test web interface
# Open: http://localhost:8080/test_pin_setup.html

# Check employee PIN status
php -r "include 'db.php'; $stmt = $conn->query('SELECT EmployeeID, FullName, CustomPIN, PINSetupComplete FROM tbl_employees LIMIT 5'); while(\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) { echo \$row['EmployeeID'] . ' - ' . \$row['FullName'] . ' - PIN: ' . (\$row['CustomPIN'] ? 'Custom' : 'Default') . ' - Setup: ' . (\$row['PINSetupComplete'] ? 'Yes' : 'No') . PHP_EOL; }"
```

## ✅ Benefits:

- **Enhanced Security**: Each employee has unique PIN
- **User-Friendly**: Simple setup process
- **Backward Compatible**: Old PIN methods still work
- **Admin Control**: Easy PIN reset capability
- **Audit Trail**: All PIN activities logged

## 🚀 Ready to Use!

Your employees can now:
1. **First login**: Use "1234" and set up personal PIN
2. **Future logins**: Use their personal PIN
3. **Enhanced security**: Unique PIN per employee
4. **Easy reset**: Admin can reset if needed

The system automatically guides users through the PIN setup process!
