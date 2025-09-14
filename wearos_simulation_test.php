<?php
header('Content-Type: application/json');

echo json_encode([
    'test_name' => 'WearOS API Simulation Test',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'ip' => $_SERVER['SERVER_ADDR'],
        'port' => $_SERVER['SERVER_PORT'],
        'method' => $_SERVER['REQUEST_METHOD']
    ],
    'tests' => [
        'pin_validation' => testPinAPI(),
        'clock_in_out' => testClockInOutAPI()
    ]
]);

function testPinAPI() {
    // Simulate PIN validation request
    $postData = json_encode([
        'employee_id' => 'EMP001',
        'pin' => '1234'
    ]);
    
    $result = callAPI('POST', 'http://192.168.0.189:8080/signsync_pin_api.php', $postData);
    
    return [
        'endpoint' => 'signsync_pin_api.php',
        'request' => json_decode($postData, true),
        'response' => $result,
        'status' => isset($result['success']) ? 'SUCCESS' : 'ERROR'
    ];
}

function testClockInOutAPI() {
    // Simulate clock in request
    $postData = json_encode([
        'employee_id' => 'EMP001',
        'action' => 'clock_in',
        'timestamp' => date('Y-m-d H:i:s'),
        'location' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842
        ]
    ]);
    
    $result = callAPI('POST', 'http://192.168.0.189:8080/wearos_api.php', $postData);
    
    return [
        'endpoint' => 'wearos_api.php',
        'request' => json_decode($postData, true),
        'response' => $result,
        'status' => isset($result['success']) ? 'SUCCESS' : 'ERROR'
    ];
}

function callAPI($method, $url, $data = null) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
    ]);
    
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    curl_close($curl);
    
    if ($error) {
        return ['error' => $error, 'http_code' => $httpCode];
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        return ['raw_response' => $response, 'http_code' => $httpCode];
    }
    
    return $decoded;
}
?>
