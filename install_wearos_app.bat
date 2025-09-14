@echo off
echo ========================================
echo SignSync WearOS Installation Script
echo ========================================
echo.

set APK_MINIMAL=signsync-wearos-MINIMAL-TEST.apk
set APK_FULL=signsync-wearos-1.7.3-v1.4-installation-fixed.apk
set APK_PATH=C:\laragon\www\attendance_register

echo Checking ADB connection...
adb devices

echo.
echo ========================================
echo METHOD 1: Installing Minimal Test APK
echo ========================================
echo Attempting force install with all flags...
adb install -r -t -g -d "%APK_PATH%\%APK_MINIMAL%"

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: Minimal APK installed!
    echo Testing app launch...
    adb shell am start -n com.signsync.attendance/.MainActivity
    goto success
)

echo.
echo ========================================
echo METHOD 2: Package Manager Installation
echo ========================================
echo Pushing APK to device...
adb push "%APK_PATH%\%APK_MINIMAL%" /data/local/tmp/test.apk

echo Installing via package manager...
adb shell pm install -r -t -g /data/local/tmp/test.apk

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: Package manager install worked!
    adb shell rm /data/local/tmp/test.apk
    goto success
)

echo.
echo ========================================
echo METHOD 3: Clear Cache and Retry
echo ========================================
echo Clearing package installer cache...
adb shell pm clear com.android.packageinstaller
adb shell pm clear com.google.android.packageinstaller

echo Retrying installation...
adb install -r -t -g -d --force-queryable "%APK_PATH%\%APK_MINIMAL%"

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: Installation after cache clear!
    goto success
)

echo.
echo ========================================
echo METHOD 4: Enable Unknown Sources
echo ========================================
echo Enabling unknown sources...
adb shell settings put global install_non_market_apps 1
adb shell settings put secure install_non_market_apps 1

echo Retrying installation...
adb install -r -t -g -d "%APK_PATH%\%APK_MINIMAL%"

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: Installation after enabling unknown sources!
    goto success
)

echo.
echo ========================================
echo ALL METHODS FAILED
echo ========================================
echo Please try manual installation:
echo 1. Copy %APK_MINIMAL% to your WearOS device
echo 2. Use file manager to install
echo 3. Check ADVANCED_INSTALLATION_GUIDE.md for more options
echo.
echo Device diagnostics:
adb shell getprop ro.build.version.release
adb shell getprop ro.build.version.sdk
adb shell df /data
goto end

:success
echo.
echo ========================================
echo INSTALLATION SUCCESSFUL!
echo ========================================
echo Verifying installation...
adb shell pm list packages | findstr signsync

echo.
echo Testing network connectivity...
echo Open browser on device and go to:
echo http://192.168.0.189:8080/test_network_api.php

echo.
echo App should now be available in your WearOS app drawer!

:end
echo.
echo Installation script completed.
pause
