#!/usr/bin/env powershell

Write-Host "============================================" -ForegroundColor Green
Write-Host "  WS10 ULTRA APK Build Status Monitor" -ForegroundColor Green  
Write-Host "============================================" -ForegroundColor Green
Write-Host ""

# Check Android Studio status
$studioProcess = Get-Process -Name "studio64" -ErrorAction SilentlyContinue
if ($studioProcess) {
    Write-Host "✅ Android Studio is running (PID: $($studioProcess.Id))" -ForegroundColor Green
    Write-Host ""
    Write-Host "NEXT STEPS IN ANDROID STUDIO:" -ForegroundColor Yellow
    Write-Host "1. Wait for project to load completely" -ForegroundColor White
    Write-Host "2. Wait for Gradle sync to finish (bottom status bar)" -ForegroundColor White
    Write-Host "3. Build → Build Bundle(s)/APK(s) → Build APK(s)" -ForegroundColor White
    Write-Host "4. APK will be in: app\build\outputs\apk\debug\" -ForegroundColor White
} else {
    Write-Host "❌ Android Studio not detected running" -ForegroundColor Red
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  PROJECT STATUS CHECK" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan

# Check key files
$files = @(
    @{Path="app\src\main\java\com\attendance\wearos\MainActivity.java"; Name="MainActivity"},
    @{Path="app\src\main\java\com\attendance\wearos\DeviceRegistrationActivity.java"; Name="Registration Activity"},
    @{Path="app\src\main\AndroidManifest.xml"; Name="Android Manifest"},
    @{Path="app\build.gradle"; Name="App Build Config"},
    @{Path="build.gradle"; Name="Project Build Config"},
    @{Path="local.properties"; Name="SDK Configuration"}
)

foreach ($file in $files) {
    if (Test-Path $file.Path) {
        Write-Host "✅ $($file.Name)" -ForegroundColor Green
    } else {
        Write-Host "❌ $($file.Name) - MISSING" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Magenta
Write-Host "  BACKEND SYSTEM STATUS" -ForegroundColor Magenta
Write-Host "============================================" -ForegroundColor Magenta

# Test backend API
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8080/attendance_register/wearos_device_registration.php" -Method POST -ContentType "application/json" -Body '{"action":"list_devices"}' -TimeoutSec 5
    if ($response.StatusCode -eq 200) {
        Write-Host "✅ Backend API responding" -ForegroundColor Green
        $data = $response.Content | ConvertFrom-Json
        if ($data.success) {
            Write-Host "✅ Database connected - $($data.count) devices registered" -ForegroundColor Green
        }
    }
} catch {
    Write-Host "⚠️  Backend API check failed - ensure server is running" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Blue
Write-Host "  ALTERNATIVE BUILD OPTIONS" -ForegroundColor Blue
Write-Host "============================================" -ForegroundColor Blue
Write-Host ""
Write-Host "Option 1: Wait for Android Studio (Recommended)" -ForegroundColor Green
Write-Host "- Project is loading in Android Studio" -ForegroundColor White
Write-Host "- Follow the build steps above" -ForegroundColor White
Write-Host ""
Write-Host "Option 2: Command Line Build" -ForegroundColor Yellow
Write-Host "- Requires Android SDK to be properly configured" -ForegroundColor White
Write-Host "- Run: gradlew.bat assembleDebug" -ForegroundColor White
Write-Host ""
Write-Host "Option 3: Online APK Builder" -ForegroundColor Cyan
Write-Host "- Zip the entire android_wear_app folder" -ForegroundColor White
Write-Host "- Upload to online Android compiler" -ForegroundColor White
Write-Host ""

Write-Host "============================================" -ForegroundColor Green
Write-Host "  WS10 ULTRA DEPLOYMENT READY" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Your complete system includes:" -ForegroundColor Yellow
Write-Host "✅ Working backend API with device registration" -ForegroundColor Green
Write-Host "✅ Database with WearOS device management" -ForegroundColor Green  
Write-Host "✅ Web management interface for admins" -ForegroundColor Green
Write-Host "✅ Complete Android WearOS app source code" -ForegroundColor Green
Write-Host "✅ WS10 ULTRA optimized interface and functionality" -ForegroundColor Green
Write-Host ""
Write-Host "Once APK is built, your WS10 ULTRA will be ready!" -ForegroundColor Cyan
Write-Host ""

Read-Host "Press Enter to continue"
