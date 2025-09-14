Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  WS10 ULTRA Android APK Build Assistant" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check Java installation
Write-Host "Checking Java installation..." -ForegroundColor Yellow
try {
    $javaVersion = java -version 2>&1 | Select-String "java version|openjdk version"
    Write-Host "✅ Java found: $javaVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ Java not found. Please install JDK 8 or higher" -ForegroundColor Red
    exit 1
}

# Check Android Studio installation
Write-Host "Checking for Android Studio..." -ForegroundColor Yellow
$androidStudioPaths = @(
    "C:\Program Files\Android\Android Studio",
    "C:\Users\$env:USERNAME\AppData\Local\Android\Sdk",
    "C:\Android\Sdk"
)

$androidStudioFound = $false
foreach ($path in $androidStudioPaths) {
    if (Test-Path $path) {
        Write-Host "✅ Android SDK found at: $path" -ForegroundColor Green
        $env:ANDROID_HOME = $path
        $androidStudioFound = $true
        break
    }
}

if (-not $androidStudioFound) {
    Write-Host "⚠️  Android Studio/SDK not found in common locations" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  BUILD OPTIONS" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Open project in Android Studio (Recommended)" -ForegroundColor Green
Write-Host "2. Show manual build instructions" -ForegroundColor Yellow
Write-Host "3. Attempt Gradle build (requires Android SDK)" -ForegroundColor Blue
Write-Host "4. Show project structure" -ForegroundColor Magenta
Write-Host "5. Update API URL in app" -ForegroundColor Cyan
Write-Host ""

$choice = Read-Host "Select option (1-5)"

switch ($choice) {
    "1" {
        Write-Host ""
        Write-Host "Opening project in Android Studio..." -ForegroundColor Green
        Write-Host ""
        Write-Host "If Android Studio opens:" -ForegroundColor Yellow
        Write-Host "1. Wait for Gradle sync to complete" -ForegroundColor White
        Write-Host "2. Click Build → Build Bundle(s)/APK(s) → Build APK(s)" -ForegroundColor White
        Write-Host "3. Find APK in: app\build\outputs\apk\debug\" -ForegroundColor White
        Write-Host ""
        
        # Try to open Android Studio
        $studioExe = "C:\Program Files\Android\Android Studio\bin\studio64.exe"
        if (Test-Path $studioExe) {
            Start-Process $studioExe -ArgumentList (Get-Location).Path
        } else {
            Write-Host "Android Studio executable not found. Please open manually:" -ForegroundColor Red
            Write-Host (Get-Location).Path -ForegroundColor White
        }
    }
    
    "2" {
        Write-Host ""
        Write-Host "============================================" -ForegroundColor Cyan
        Write-Host "  MANUAL BUILD INSTRUCTIONS" -ForegroundColor Cyan
        Write-Host "============================================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "STEP 1: Install Android Studio" -ForegroundColor Yellow
        Write-Host "Download from: https://developer.android.com/studio" -ForegroundColor White
        Write-Host ""
        Write-Host "STEP 2: Open Project" -ForegroundColor Yellow
        Write-Host "1. Launch Android Studio" -ForegroundColor White
        Write-Host "2. Click 'Open an existing project'" -ForegroundColor White
        Write-Host "3. Navigate to: $(Get-Location)" -ForegroundColor White
        Write-Host "4. Select this folder and click OK" -ForegroundColor White
        Write-Host ""
        Write-Host "STEP 3: Build APK" -ForegroundColor Yellow
        Write-Host "1. Wait for Gradle sync to complete" -ForegroundColor White
        Write-Host "2. Build → Build Bundle(s)/APK(s) → Build APK(s)" -ForegroundColor White
        Write-Host "3. APK will be in: app\build\outputs\apk\debug\" -ForegroundColor White
    }
    
    "3" {
        Write-Host ""
        Write-Host "Attempting Gradle build..." -ForegroundColor Blue
        
        if (Test-Path "gradlew.bat") {
            Write-Host "Found Gradle wrapper. Attempting build..." -ForegroundColor Yellow
            try {
                & .\gradlew.bat assembleDebug
                Write-Host "✅ Build completed! Check app\build\outputs\apk\debug\" -ForegroundColor Green
            } catch {
                Write-Host "❌ Gradle build failed. Try Android Studio instead." -ForegroundColor Red
                Write-Host "Error: $_" -ForegroundColor Red
            }
        } else {
            Write-Host "❌ Gradle wrapper not found. Use Android Studio instead." -ForegroundColor Red
        }
    }
    
    "4" {
        Write-Host ""
        Write-Host "============================================" -ForegroundColor Cyan
        Write-Host "  PROJECT STRUCTURE" -ForegroundColor Cyan
        Write-Host "============================================" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "✅ Complete Android project ready for compilation" -ForegroundColor Green
        Write-Host ""
        Get-ChildItem -Recurse -Name | Where-Object { $_ -like "*.java" -or $_ -like "*.xml" -or $_ -like "*.gradle" } | Sort-Object
    }
    
    "5" {
        Write-Host ""
        Write-Host "Current network configuration needed..." -ForegroundColor Yellow
        $ipConfig = ipconfig | Select-String "IPv4"
        if ($ipConfig) {
            Write-Host "Found IP addresses:" -ForegroundColor Green
            $ipConfig | ForEach-Object { Write-Host "  $_" -ForegroundColor White }
        }
        Write-Host ""
        Write-Host "Update this file with your IP:" -ForegroundColor Yellow
        Write-Host "app\src\main\java\com\attendance\wearos\DeviceRegistrationActivity.java" -ForegroundColor White
        Write-Host "Line 19: Change 192.168.8.104 to your IP address" -ForegroundColor White
    }
    
    default {
        Write-Host "Invalid selection. Please run the script again." -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "For WS10 ULTRA installation:" -ForegroundColor Yellow
Write-Host "1. Enable Developer Options on watch" -ForegroundColor White
Write-Host "2. Enable ADB Debugging" -ForegroundColor White
Write-Host "3. Connect and install: adb install app-debug.apk" -ForegroundColor White
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
