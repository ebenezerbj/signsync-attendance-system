# 📊 **REAL EMPLOYEE DATA GUIDE**

## 👥 **Available Employee Accounts**

### **Real Employees in Database:**

| Employee ID | Name | Phone | Branch | PIN Status |
|-------------|------|-------|--------|------------|
| **AKCBSTF0005** | STEPHEN SARFO | 233243082750 | GH1510013 | Default (1234) |
| **AKCBSTFADMIN** | System Admin | 233243082750 | GH1510010 | Default (1234) |
| **EMP001** | John Doe (Test) | +1234567890 | GH1510010 | Custom (5678) |

## 🔐 **Login Instructions**

### **For Real Employee (Stephen Sarfo):**
- **Employee ID:** `AKCBSTF0005`
- **PIN:** `1234` (default)
- **Result:** Will prompt to change PIN on first login

### **For Admin Account:**
- **Employee ID:** `AKCBSTFADMIN`
- **PIN:** `1234` (default)
- **Result:** Will prompt to change PIN on first login

### **For Test Account:**
- **Option 1:** `EMP001` / `1234` (default PIN)
- **Option 2:** `EMP001` / `5678` (custom PIN)

## 🎯 **Login Flow Behavior**

### **Default PIN (1234):**
1. ✅ Login succeeds
2. 🔄 App shows "First Login" flag
3. 📱 User redirected to Change PIN screen
4. ✅ Must set custom PIN before accessing main app

### **Custom PIN:**
1. ✅ Login succeeds
2. ✅ Direct access to main employee portal
3. 🏠 Full app functionality available

## 🧪 **Test Scenarios**

### **✅ Valid Login Tests:**
```
AKCBSTF0005 + 1234 → Success (First login)
AKCBSTFADMIN + 1234 → Success (First login)  
EMP001 + 1234 → Success (First login)
EMP001 + 5678 → Success (Direct access)
```

### **❌ Invalid Login Tests:**
```
INVALID123 + 1234 → Failed (Employee not found)
AKCBSTF0005 + 9999 → Failed (Wrong PIN)
```

## 📱 **Android App Configuration**

### **Current APK Settings:**
- **Server URL:** `http://10.0.2.2:8080/` (Emulator)
- **Login Endpoint:** `http://10.0.2.2:8080/login_api.php`
- **Database:** attendance_register_db (3 employees)

### **Recommended Test Order:**
1. **Start with Stephen Sarfo:** `AKCBSTF0005` / `1234`
2. **Test PIN change flow**
3. **Try custom PIN:** `EMP001` / `5678`
4. **Test admin account:** `AKCBSTFADMIN` / `1234`

## 🚀 **Next Steps**

1. **Install APK** on Android emulator/device
2. **Use real employee ID:** `AKCBSTF0005`
3. **Enter default PIN:** `1234`
4. **Complete PIN change** when prompted
5. **Access full employee portal**

## 📊 **Database Status**
- ✅ **3 real employees** loaded
- ✅ **1 custom PIN** configured
- ✅ **Login API** tested and working
- ✅ **All authentication flows** verified

Your Android app is now ready to work with **real employee data** from your database! 🎉
