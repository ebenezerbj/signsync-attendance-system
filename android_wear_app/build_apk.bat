@echo off
echo Building Android APK for WS10 ULTRA...
echo.

REM Check if Java is available
java -version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Java is not installed or not in PATH
    echo Please install Java Development Kit (JDK) 8 or higher
    pause
    exit /b 1
)

REM Check if Android SDK is available
if not exist "%ANDROID_HOME%" (
    echo WARNING: ANDROID_HOME environment variable not set
    echo Attempting to find Android SDK...
)

REM Alternative: Use Android Studio's embedded tools
set ANDROID_STUDIO_PATH=C:\Program Files\Android\Android Studio
if exist "%ANDROID_STUDIO_PATH%" (
    echo Found Android Studio installation
    set ANDROID_HOME=%ANDROID_STUDIO_PATH%\sdk
)

REM Create directories
if not exist "build" mkdir build
if not exist "build\outputs" mkdir build\outputs
if not exist "build\outputs\apk" mkdir build\outputs\apk

echo.
echo ========================================
echo  ANDROID APK BUILD OPTIONS
echo ========================================
echo.
echo 1. Manual Build Instructions
echo 2. Create APK using Android Studio (Recommended)
echo 3. Generate build commands
echo 4. Exit
echo.
set /p choice="Please select an option (1-4): "

if "%choice%"=="1" goto manual_build
if "%choice%"=="2" goto android_studio
if "%choice%"=="3" goto generate_commands
if "%choice%"=="4" goto end

:manual_build
echo.
echo ========================================
echo  MANUAL BUILD INSTRUCTIONS
echo ========================================
echo.
echo To build the APK manually:
echo.
echo 1. Install Android Studio from: https://developer.android.com/studio
echo 2. Open Android Studio
echo 3. Click "Open an existing Android Studio project"
echo 4. Navigate to: %cd%
echo 5. Select this folder and click OK
echo 6. Wait for Gradle sync to complete
echo 7. Click Build → Build Bundle(s)/APK(s) → Build APK(s)
echo 8. The APK will be generated in: app\build\outputs\apk\debug\
echo.
echo The generated APK can then be installed on your WS10 ULTRA device.
echo.
pause
goto end

:android_studio
echo.
echo ========================================
echo  ANDROID STUDIO BUILD
echo ========================================
echo.
echo Opening project in Android Studio...
echo.
echo If Android Studio is installed, it should open automatically.
echo If not, please install it from: https://developer.android.com/studio
echo.

REM Try to open with Android Studio
if exist "%ANDROID_STUDIO_PATH%\bin\studio64.exe" (
    echo Launching Android Studio...
    start "" "%ANDROID_STUDIO_PATH%\bin\studio64.exe" "%cd%"
) else (
    echo Android Studio not found in default location.
    echo Please manually open Android Studio and import this project:
    echo %cd%
)
echo.
pause
goto end

:generate_commands
echo.
echo ========================================
echo  BUILD COMMANDS
echo ========================================
echo.
echo If you have Android SDK and Gradle properly configured:
echo.
echo 1. gradlew clean
echo 2. gradlew assembleDebug
echo.
echo Or with full Android SDK:
echo.
echo 1. cd %cd%
echo 2. gradlew.bat clean assembleDebug
echo.
echo The APK will be generated in:
echo app\build\outputs\apk\debug\app-debug.apk
echo.
pause
goto end

:end
echo.
echo Build script completed.
echo.
echo For WS10 ULTRA installation:
echo 1. Enable Developer Options on your watch
echo 2. Enable ADB Debugging
echo 3. Connect via USB or WiFi ADB
echo 4. Use: adb install app-debug.apk
echo.
pause
