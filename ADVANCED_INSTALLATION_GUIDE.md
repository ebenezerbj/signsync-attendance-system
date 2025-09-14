# Advanced WearOS Installation Troubleshooting

## 🚨 **Persistent "App Not Installed" Error Solutions**

### **Available APK Files:**
1. **Minimal Test APK**: `signsync-wearos-MINIMAL-TEST.apk` (1.6MB)
   - Simplified permissions
   - Basic functionality only
   - Better compatibility

2. **Full Featured APK**: `signsync-wearos-1.7.3-v1.4-installation-fixed.apk` 
   - Complete attendance functionality
   - All network features

---

## **🔧 Advanced Installation Methods**

### **Method 1: Force Install via ADB**
```bash
# Enable all debugging options
adb shell settings put global development_settings_enabled 1
adb shell settings put global adb_enabled 1

# Force install with all flags
adb install -r -t -g -d "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk"

# If still fails, try:
adb install -r -t -g -d --force-queryable "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk"
```

### **Method 2: Package Manager Direct Install**
```bash
# Push APK to device first
adb push "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk" /data/local/tmp/test.apk

# Install via package manager
adb shell pm install -r -t -g /data/local/tmp/test.apk

# Clean up
adb shell rm /data/local/tmp/test.apk
```

### **Method 3: Install as System App (Root Required)**
```bash
# If device is rooted
adb push "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk" /system/app/SignSync.apk
adb shell chmod 644 /system/app/SignSync.apk
adb reboot
```

### **Method 4: Split APK Installation**
```bash
# Create smaller install sessions
adb install-create -r -t
adb install-write [SESSION_ID] base.apk "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk"
adb install-commit [SESSION_ID]
```

---

## **🔍 Device Diagnostics**

### **Check Device Compatibility**
```bash
# Check WearOS version
adb shell getprop ro.build.version.release

# Check API level
adb shell getprop ro.build.version.sdk

# Check available storage
adb shell df /data

# Check package installer
adb shell pm list packages | grep installer
```

### **Clear Installation Cache**
```bash
# Clear package installer cache
adb shell pm clear com.android.packageinstaller
adb shell pm clear com.google.android.packageinstaller

# Clear download manager
adb shell pm clear com.android.providers.downloads

# Restart package manager service
adb shell killall system_server
```

---

## **🔨 Device Preparation Steps**

### **1. Enable All Developer Options**
```
Settings > About > Build number (tap 7 times)
Settings > Developer Options:
  ✅ USB Debugging
  ✅ Install via USB
  ✅ USB Debugging (Security Settings)
  ✅ Verify apps over USB (DISABLE this)
  ✅ Unknown Sources
```

### **2. Disable Security Features Temporarily**
```
Settings > Security:
  ❌ Verify apps (Google Play Protect) - DISABLE
  ❌ Scan device for security threats - DISABLE
  ✅ Unknown sources - ENABLE
```

### **3. Clear System Cache**
```
Power + Volume buttons to enter recovery mode
Select: "Wipe cache partition"
Reboot device
```

---

## **🛠️ Alternative APK Creation**

If all methods fail, try these APK modifications:

### **Create Unsigned APK for Manual Signing**
```bash
# Build unsigned
.\gradlew.bat assembleDebug --stacktrace

# Manual signing (if you have keystore)
jarsigner -verbose -sigalg SHA1withRSA -digestalg SHA1 -keystore debug.keystore app-debug.apk androiddebugkey

# Align APK
zipalign -v 4 app-debug.apk signed-aligned.apk
```

### **Create Minimal Test App**
Try installing the minimal test APK first:
- File: `signsync-wearos-MINIMAL-TEST.apk`
- Only basic functionality
- Fewer permissions
- Better compatibility

---

## **📱 Device-Specific Solutions**

### **For Very Old WearOS Devices**
```bash
# Use older install method
adb install -l -r "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk"

# Or install to SD card if available
adb install -s "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk"
```

### **For Devices with Limited Storage**
```bash
# Install to external storage
adb install -f "C:\laragon\www\attendance_register\signsync-wearos-MINIMAL-TEST.apk"

# Move existing apps to free space
adb shell pm move-package [PACKAGE_NAME] external
```

---

## **🔄 Factory Reset Alternative**

### **Reset Package Manager (Without Factory Reset)**
```bash
# Stop package manager
adb shell killall system_server

# Clear package manager data
adb shell rm -rf /data/system/packages.xml
adb shell rm -rf /data/system/packages-backup.xml

# Restart device (will rebuild package database)
adb reboot
```

---

## **📊 Installation Success Testing**

After successful installation, test:

```bash
# Check if app is installed
adb shell pm list packages | grep signsync

# Launch app
adb shell am start -n com.signsync.attendance/.MainActivity

# Check app permissions
adb shell dumpsys package com.signsync.attendance | grep permission
```

---

## **🆘 Last Resort Solutions**

### **1. Use Different Package Name**
Edit `applicationId` in build.gradle to:
- `com.attendance.signsync`
- `com.wearos.attendance`
- `com.test.attendance`

### **2. Side-load via Web**
1. Upload APK to cloud storage
2. Access via WearOS browser
3. Download and install directly

### **3. Use APK Installer Apps**
Install an APK installer app first, then use it to install SignSync:
- ES File Explorer
- APK Installer
- Package Installer

---

## **🎯 Recommended Sequence**

1. **Start with Minimal Test APK** (`signsync-wearos-MINIMAL-TEST.apk`)
2. **Use ADB force install** with all flags
3. **Clear all caches** if first attempt fails
4. **Try package manager direct install**
5. **Modify device security settings**
6. **Use unsigned APK with manual signing**
7. **Last resort: Factory reset and try again**

The minimal test APK has the highest chance of successful installation due to simplified permissions and reduced complexity.
