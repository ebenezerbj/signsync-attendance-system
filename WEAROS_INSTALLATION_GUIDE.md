# WearOS APK Installation Troubleshooting Guide

## 🔧 **"App Not Installed" Error Solutions**

### **Current APK Information:**
- **File**: `signsync-wearos-1.7.3-v1.4-installation-fixed.apk`
- **Target**: WearOS 1.7.3 devices
- **Build**: Debug signed with proper permissions
- **Network**: Fixed for IP 192.168.0.189:8080

---

## **Common Causes & Solutions**

### **1. 🔐 Installation Permissions**
**Problem**: WearOS requires explicit permission to install apps from unknown sources.

**Solution**:
```
1. On your WearOS device, go to: Settings > Apps & Notifications
2. Find "Install unknown apps" or "Unknown sources"
3. Enable installation from unknown sources
4. If using ADB: Enable "ADB debugging" in Developer options
```

### **2. 📱 Device Storage**
**Problem**: Insufficient storage space on WearOS device.

**Solution**:
```
1. Check available storage: Settings > Storage
2. Free up space by removing unused apps
3. Clear cache: Settings > Apps > [App] > Storage > Clear Cache
4. Minimum required: ~10MB free space
```

### **3. 🔄 Previous Installation Conflicts**
**Problem**: Old version or conflicting app exists.

**Solution**:
```
1. Uninstall any previous SignSync attendance app
2. Clear data: Settings > Apps > [Old App] > Storage > Clear Data
3. Restart WearOS device
4. Install the new APK
```

### **4. 📋 APK Corruption**
**Problem**: APK file was corrupted during transfer.

**Solution**:
```
1. Re-download/copy the APK file
2. Verify file size (should be ~2-5MB)
3. Use a different transfer method (ADB, email, cloud storage)
```

### **5. 🔧 Developer Options**
**Problem**: Developer options not enabled or ADB issues.

**Solution**:
```
1. Enable Developer Options:
   - Go to Settings > About > Build number
   - Tap build number 7 times
   - Go back to Settings > Developer Options

2. Enable USB Debugging
3. Enable "Install via USB" or "ADB debugging"
```

---

## **Installation Methods**

### **Method 1: ADB Installation (Recommended)**
```bash
# Windows PowerShell
adb install "C:\laragon\www\attendance_register\signsync-wearos-1.7.3-v1.4-installation-fixed.apk"

# If device not recognized
adb devices
adb connect [WATCH_IP_ADDRESS]
```

### **Method 2: File Manager Installation**
```
1. Copy APK to WearOS device (via Bluetooth, WiFi, or cable)
2. Open file manager on WearOS
3. Navigate to the APK file
4. Tap to install
5. Allow installation from unknown sources when prompted
```

### **Method 3: Cloud Storage Installation**
```
1. Upload APK to Google Drive, Dropbox, or similar
2. Access cloud storage app on WearOS
3. Download and install the APK
```

---

## **Verification Steps**

### **Before Installation:**
- [ ] WearOS device has Developer Options enabled
- [ ] USB Debugging is enabled (if using ADB)
- [ ] Unknown sources installation is allowed
- [ ] At least 10MB free storage available
- [ ] Device is connected to same network (192.168.0.x)

### **After Installation:**
- [ ] App appears in app list
- [ ] App icon is visible on watch face
- [ ] App opens without crashing
- [ ] Network connectivity test passes
- [ ] PIN authentication works

---

## **Network Configuration**

The APK is configured for:
- **Server IP**: 192.168.0.189
- **Port**: 8080
- **API Endpoints**:
  - PIN API: `/signsync_pin_api.php`
  - Clock API: `/wearos_api.php`

**Ensure your WearOS device is on the same network (192.168.0.x range)**

---

## **Advanced Troubleshooting**

### **APK Signature Issues**
```bash
# Check APK signature
keytool -printcert -jarfile signsync-wearos-1.7.3-v1.4-installation-fixed.apk

# Force install (bypass signature check)
adb install -r -d signsync-wearos-1.7.3-v1.4-installation-fixed.apk
```

### **Package Manager Issues**
```bash
# Clear package manager cache
adb shell pm clear com.android.packageinstaller

# Install with specific options
adb install -r -t -g signsync-wearos-1.7.3-v1.4-installation-fixed.apk
```

### **WearOS Specific Solutions**
```
1. Restart WearOS device completely
2. Check if watch is paired with phone properly
3. Ensure Google Play Services are updated
4. Try installing during different times (avoid sleep mode)
```

---

## **Error Messages & Solutions**

| Error Message | Solution |
|---------------|----------|
| "App not installed" | Enable unknown sources, clear storage |
| "Parse error" | Re-download APK, check file integrity |
| "Insufficient storage" | Free up space, clear cache |
| "Package corrupted" | Use different transfer method |
| "Incompatible" | Verify WearOS version (requires 1.7.3+) |

---

## **Contact Information**

If installation continues to fail:
1. **Check APK integrity**: File should be approximately 2-5MB
2. **Verify WearOS version**: Must be 1.7.3 or higher
3. **Network test**: Use `http://192.168.0.189:8080/test_network_api.php`
4. **Alternative**: Try installing simpler APK first to test device capability

---

## **Success Indicators**

✅ **Installation Successful When:**
- App appears in WearOS app drawer
- App launches without errors
- PIN authentication screen appears
- Network connectivity test passes
- Clock in/out functions work properly

**Installation complete!** Your SignSync WearOS attendance app should now be ready for use.
