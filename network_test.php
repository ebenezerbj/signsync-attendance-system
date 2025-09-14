<?php
/**
 * Network Diagnostic Tool for WearOS Development
 * Helps identify connectivity issues
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'success' => true,
    'timestamp' => time(),
    'server_info' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
        'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
    ],
    'network_info' => [
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
    ]
];

// If it's a POST request, echo back the data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $response['received_data'] = [
        'raw_input' => $rawInput,
        'json_decoded' => json_decode($rawInput, true),
        'post_data' => $_POST
    ];
}

// Add connectivity test
$response['connectivity_test'] = [
    'can_write_logs' => is_writable('.'),
    'database_available' => file_exists('db.php'),
    'api_files' => [
        'signsync_pin_api.php' => file_exists('signsync_pin_api.php'),
        'wearos_api.php' => file_exists('wearos_api.php'),
        'clockinout.php' => file_exists('clockinout.php')
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
