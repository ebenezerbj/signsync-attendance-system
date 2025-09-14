# SIGNSYNC Admin PIN Management Guide

## 🔐 Admin PIN Reset Methods

You now have **multiple ways** to reset employee PINs as an admin:

---

## 1. 🌐 **Web Admin Interface** (Recommended)

**Access**: `http://localhost:8080/admin_pin_management.html`

### Features:
- ✅ **Visual Dashboard** - See all employees and their PIN status
- ✅ **Real-time Statistics** - Count of custom PINs vs default PINs
- ✅ **Search & Filter** - Find employees quickly
- ✅ **One-Click Reset** - Reset individual or all PINs
- ✅ **Employee Details** - View comprehensive employee information

### How to Use:
1. **Open the web interface**
2. **View PIN statistics** at the top
3. **Search for specific employees** using the search box
4. **Click "Reset PIN"** for individual employees
5. **Click "Reset All PINs"** for bulk reset (use with caution)

---

## 2. 💻 **Command Line Interface**

**File**: `admin_pin_cli.php`

### Available Commands:

```bash
# List all employees and their PIN status
php admin_pin_cli.php list

# Reset specific employee's PIN
php admin_pin_cli.php reset AKCBSTF0005

# Reset ALL employee PINs (requires confirmation)
php admin_pin_cli.php reset-all

# Show detailed employee information
php admin_pin_cli.php details AKCBSTF0005

# Show PIN usage statistics
php admin_pin_cli.php stats

# Show help
php admin_pin_cli.php help
```

### Examples:
```bash
# Reset Stephen Sarfo's PIN
php admin_pin_cli.php reset AKCBSTF0005

# View John Doe's details
php admin_pin_cli.php details EMP001

# See overall statistics
php admin_pin_cli.php stats
```

---

## 3. 🗄️ **Direct Database Method**

### Reset Individual Employee:
```sql
UPDATE tbl_employees 
SET CustomPIN = NULL, PINSetupComplete = 0 
WHERE EmployeeID = 'AKCBSTF0005';
```

### Reset All Employees:
```sql
UPDATE tbl_employees 
SET CustomPIN = NULL, PINSetupComplete = 0;
```

### Check PIN Status:
```sql
SELECT EmployeeID, FullName, 
       CASE WHEN CustomPIN IS NOT NULL THEN 'Custom PIN' ELSE 'Default PIN' END as Status,
       PINSetupComplete
FROM tbl_employees
ORDER BY EmployeeID;
```

---

## 4. 🚀 **Quick Reset Tool** (Already Exists)

**File**: `reset_employee_pin.php`

```bash
# Reset specific employee
php reset_employee_pin.php AKCBSTF0005

# List all employees
php reset_employee_pin.php
```

---

## 📋 **What Happens When You Reset a PIN:**

1. **Employee's CustomPIN** → Set to `NULL`
2. **PINSetupComplete** → Set to `0`
3. **Activity logged** → Admin action recorded
4. **Employee must**:
   - Use default PIN "1234" for next login
   - Set up a new custom PIN when prompted

---

## 🎯 **Common Admin Scenarios:**

### **Employee Forgot Their PIN:**
```bash
php admin_pin_cli.php reset EMPLOYEE_ID
```
→ Tell employee to use "1234" and create new PIN

### **New Employee Setup:**
- Employee already uses "1234" by default
- No action needed - they'll set up PIN on first login

### **Security Reset (All Employees):**
```bash
php admin_pin_cli.php reset-all
```
→ All employees must use "1234" and create new PINs

### **Check Who Needs PIN Setup:**
```bash
php admin_pin_cli.php list
```
→ Shows employees still using default PIN

---

## 🛡️ **Security Features:**

- ✅ **Confirmation prompts** for destructive actions
- ✅ **Activity logging** for all PIN resets
- ✅ **Employee verification** before reset
- ✅ **Audit trail** maintained in activity_logs table

---

## 📊 **Monitoring PIN Usage:**

### Web Dashboard Shows:
- Total employees
- How many have custom PINs
- How many still use default PIN
- Last activity for each employee

### CLI Stats Show:
- Overall adoption percentage
- Detailed employee list with status
- Individual employee activity

---

## 🔧 **Admin Best Practices:**

1. **Use Web Interface** for daily management
2. **Use CLI** for bulk operations or scripting
3. **Monitor adoption** - encourage custom PIN setup
4. **Regular audits** - check who's still using default PIN
5. **Document resets** - know why PINs were reset

---

## 🚨 **Emergency Procedures:**

### **Mass PIN Reset** (Security Breach):
1. Use web interface → "Reset All PINs"
2. Or CLI: `php admin_pin_cli.php reset-all`
3. Notify all employees to use "1234"
4. Monitor new PIN setup completion

### **Individual Employee Issues:**
1. Identify employee having trouble
2. Reset their PIN via any method
3. Guide them through "1234" → custom PIN setup
4. Verify successful login

---

Your SIGNSYNC system now has **comprehensive admin controls** for PIN management!
