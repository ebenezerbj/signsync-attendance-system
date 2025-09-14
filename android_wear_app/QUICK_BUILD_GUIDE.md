# Quick Build Guide for WS10 ULTRA Android App

## Option 1: Android Studio (Recommended)

1. **Download Android Studio**: https://developer.android.com/studio
2. **Install Android Studio** with default settings
3. **Open Android Studio**
4. **Click "Open"** and navigate to: `C:\laragon\www\attendance_register\android_wear_app`
5. **Wait for Gradle sync** to complete
6. **Click Build → Build Bundle(s)/APK(s) → Build APK(s)**
7. **Find APK** in: `app\build\outputs\apk\debug\app-debug.apk`

## Option 2: Online APK Builder

1. **Zip the project folder**: `android_wear_app`
2. **Upload to online builder**: 
   - https://appetize.io/build-tools
   - https://snack.expo.dev (for React Native)
   - https://buildroid.com (for Android)

## Option 3: Command Line (If SDK installed)

```batch
cd C:\laragon\www\attendance_register\android_wear_app
gradlew.bat clean
gradlew.bat assembleDebug
```

## Current Project Status

✅ **Complete Android project** ready for compilation
✅ **Source code** - All Java files created
✅ **Resources** - Layouts and strings defined
✅ **Manifest** - Permissions and activities configured
✅ **Gradle files** - Build configuration ready

## Network Configuration Required

**IMPORTANT**: Before building, update the IP address in the app:

**File**: `app\src\main\java\com\attendance\wearos\DeviceRegistrationActivity.java`
**Line 19**: Change IP to your network address

```java
private static final String API_BASE_URL = "http://YOUR_IP_ADDRESS:8080/attendance_register/";
```

Find your IP with: `ipconfig | findstr IPv4`

## Installation on WS10 ULTRA

1. **Enable Developer Options**: Settings → About → Tap Build number 7 times
2. **Enable ADB Debugging**: Developer Options → ADB Debugging → ON
3. **Install APK**:
   - Via USB: `adb install app-debug.apk`
   - Via File Manager: Copy APK to watch and install
   - Via WiFi ADB: Connect wirelessly and install

## System Requirements

- **Android Studio**: Latest version with Android SDK
- **Java**: JDK 8 or higher (✅ Java 21 detected)
- **Device**: WS10 ULTRA with Android Wear 2.0+
- **Network**: WiFi connection for device registration

The project is **ready for compilation** - just need Android Studio or SDK tools!
