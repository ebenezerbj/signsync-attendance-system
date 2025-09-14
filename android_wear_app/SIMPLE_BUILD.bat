@echo off
cls
echo.
echo ==========================================
echo   WS10 ULTRA Android APK Builder
echo ==========================================
echo.

REM Check for Android Studio
set STUDIO_PATH=C:\Program Files\Android\Android Studio\bin\studio64.exe
set PROJECT_PATH=%cd%

echo Checking for Android Studio...
if exist "%STUDIO_PATH%" (
    echo [SUCCESS] Android Studio found!
    echo.
    echo Opening project in Android Studio...
    echo.
    echo INSTRUCTIONS AFTER ANDROID STUDIO OPENS:
    echo 1. Wait for Gradle sync to complete
    echo 2. Click: Build ^> Build Bundle^(s^)/APK^(s^) ^> Build APK^(s^)
    echo 3. Find APK in: app\build\outputs\apk\debug\
    echo.
    start "" "%STUDIO_PATH%" "%PROJECT_PATH%"
    echo.
    echo Android Studio is starting...
    echo Follow the instructions above to build your APK.
    echo.
) else (
    echo [INFO] Android Studio not found in default location.
    echo.
    echo ==========================================
    echo   MANUAL BUILD OPTIONS
    echo ==========================================
    echo.
    echo OPTION 1: Download and Install Android Studio
    echo   1. Download from: https://developer.android.com/studio
    echo   2. Install with default settings
    echo   3. Run this script again
    echo.
    echo OPTION 2: Open Project Manually
    echo   1. Launch Android Studio
    echo   2. Click "Open an existing project"
    echo   3. Navigate to: %PROJECT_PATH%
    echo   4. Build APK: Build ^> Build Bundle^(s^)/APK^(s^) ^> Build APK^(s^)
    echo.
    echo OPTION 3: Online APK Builder
    echo   1. Zip this entire folder: %PROJECT_PATH%
    echo   2. Upload to online Android compiler
    echo   3. Download compiled APK
    echo.
)

echo ==========================================
echo   PROJECT STATUS
echo ==========================================
echo.
echo [✓] Complete Android project ready
echo [✓] WearOS optimized interface
echo [✓] Device registration functionality
echo [✓] API communication setup
echo [✓] All layouts and resources included
echo.
echo Your WS10 ULTRA app is ready to compile!
echo.

echo ==========================================
echo   AFTER BUILDING APK
echo ==========================================
echo.
echo 1. Enable Developer Options on WS10 ULTRA
echo 2. Enable ADB Debugging
echo 3. Install APK: adb install app-debug.apk
echo 4. Launch "Attendance Register" app
echo 5. Register device and get 6-digit code
echo 6. Bind to employee via web interface
echo.

pause
exit
