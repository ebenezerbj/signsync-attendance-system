@echo off
echo ========================================
echo SignSync Comprehensive Build Script
echo ========================================
echo.

set PHONE_APP_DIR=android_phone_app
set WEAR_APP_DIR=android_wear_companion
set OUTPUT_DIR=built_apps

echo Creating output directory...
if not exist %OUTPUT_DIR% mkdir %OUTPUT_DIR%

echo.
echo ========================================
echo Building Android Phone App
echo ========================================
cd %PHONE_APP_DIR%

echo Cleaning previous builds...
call gradlew.bat clean

echo Building debug APK...
call gradlew.bat assembleDebug

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: Phone app built successfully!
    copy app\build\outputs\apk\debug\app-debug.apk ..\%OUTPUT_DIR%\signsync-phone-app.apk
) else (
    echo ERROR: Phone app build failed!
    goto :end
)

cd ..

echo.
echo ========================================
echo Building WearOS Companion App
echo ========================================
cd %WEAR_APP_DIR%

echo Cleaning previous builds...
call gradlew.bat clean

echo Building debug APK...
call gradlew.bat assembleDebug

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: WearOS app built successfully!
    copy app\build\outputs\apk\debug\app-debug.apk ..\%OUTPUT_DIR%\signsync-wearos-companion.apk
) else (
    echo ERROR: WearOS app build failed!
    goto :end
)

cd ..

echo.
echo ========================================
echo Build Summary
echo ========================================
echo Both apps built successfully!
echo.
echo Output files:
echo - %OUTPUT_DIR%\signsync-phone-app.apk      (Android Phone App)
echo - %OUTPUT_DIR%\signsync-wearos-companion.apk (WearOS Companion)
echo.
echo Installation commands:
echo adb install %OUTPUT_DIR%\signsync-phone-app.apk
echo adb install %OUTPUT_DIR%\signsync-wearos-companion.apk
echo.
echo Server configuration:
echo Make sure to update API base URL in both apps before deployment!

:end
echo.
echo Build script completed.
pause
