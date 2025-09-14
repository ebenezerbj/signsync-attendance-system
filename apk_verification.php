<?php
// APK Installation Verification Tool
header('Content-Type: application/json');

$apkFile = 'signsync-wearos-1.7.3-v1.4-installation-fixed.apk';
$result = [
    'verification_time' => date('Y-m-d H:i:s'),
    'apk_status' => [],
    'network_status' => [],
    'installation_ready' => false
];

// Check APK file
if (file_exists($apkFile)) {
    $fileInfo = [
        'exists' => true,
        'size' => filesize($apkFile),
        'size_mb' => round(filesize($apkFile) / 1024 / 1024, 2),
        'modified' => date('Y-m-d H:i:s', filemtime($apkFile)),
        'readable' => is_readable($apkFile)
    ];
    
    // Check if size is reasonable for WearOS app (should be 1-10MB)
    $fileInfo['size_ok'] = $fileInfo['size_mb'] >= 1 && $fileInfo['size_mb'] <= 10;
    
    $result['apk_status'] = $fileInfo;
} else {
    $result['apk_status'] = ['exists' => false, 'error' => 'APK file not found'];
}

// Check network APIs
$result['network_status'] = [
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
    'pin_api_exists' => file_exists('signsync_pin_api.php'),
    'clock_api_exists' => file_exists('wearos_api.php'),
    'network_test_exists' => file_exists('test_network_api.php')
];

// Check installation readiness
$apkReady = $result['apk_status']['exists'] ?? false;
$networkReady = $result['network_status']['pin_api_exists'] && $result['network_status']['clock_api_exists'];
$result['installation_ready'] = $apkReady && $networkReady;

// Installation recommendations
$result['recommendations'] = [];

if (!$apkReady) {
    $result['recommendations'][] = "❌ APK file missing or corrupted - rebuild required";
}

if (!$networkReady) {
    $result['recommendations'][] = "❌ API endpoints missing - check server setup";
}

if ($result['installation_ready']) {
    $result['recommendations'][] = "✅ Ready for WearOS installation";
    $result['recommendations'][] = "📱 Use ADB or file manager to install APK";
    $result['recommendations'][] = "🔧 Enable Developer Options on WearOS device";
    $result['recommendations'][] = "🌐 Ensure device is on network 192.168.0.x";
}

// Installation commands
$result['installation_commands'] = [
    'adb' => "adb install \"C:\\laragon\\www\\attendance_register\\{$apkFile}\"",
    'adb_force' => "adb install -r -d \"C:\\laragon\\www\\attendance_register\\{$apkFile}\"",
    'copy_to_device' => "Copy APK to device storage and install via file manager"
];

echo json_encode($result, JSON_PRETTY_PRINT);
?>
