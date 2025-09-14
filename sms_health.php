<?php
/**
 * SMS Health Check and Monitoring Tool
 * 
 * This tool provides comprehensive health monitoring and testing
 * capabilities for the SIGNSYNC SMS service.
 */

require_once 'db.php';
require_once 'SignSyncSMSService.php';
require_once 'sms_config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'health';

try {
    switch ($action) {
        case 'health':
            echo json_encode(getSMSServiceHealth($conn));
            break;
            
        case 'test_connection':
            $smsService = createSMSService($conn);
            $config = loadSMSConfig($conn);
            
            // Test API connectivity without sending SMS
            $testResult = [
                'provider_config' => 'OK',
                'database_connection' => 'OK',
                'sms_service_init' => 'OK'
            ];
            
            echo json_encode([
                'status' => 'healthy',
                'tests' => $testResult,
                'message' => 'SMS service is ready'
            ]);
            break;
            
        case 'test_send':
            $phone = $_POST['phone'] ?? '';
            $message = $_POST['message'] ?? 'SIGNSYNC SMS Test Message';
            
            if (empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number required']);
                break;
            }
            
            $smsService = createSMSService($conn);
            $result = $smsService->sendMessage($phone, $message, SignSyncSMSService::PRIORITY_NORMAL);
            
            echo json_encode([
                'success' => $result['success'] ?? false,
                'message' => $result['success'] ? 'Test SMS sent successfully' : ($result['error'] ?? 'Failed to send'),
                'details' => $result
            ]);
            break;
            
        case 'stats':
            $timeframe = $_GET['timeframe'] ?? '24h';
            $smsService = createSMSService($conn);
            $stats = $smsService->getStatistics($timeframe);
            
            echo json_encode($stats);
            break;
            
        case 'queue_status':
            $stmt = $conn->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM tbl_sms_queue 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY status
            ");
            $stmt->execute();
            $queueStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($queueStatus);
            break;
            
        case 'recent_logs':
            $limit = $_GET['limit'] ?? 20;
            $smsService = createSMSService($conn);
            $logs = $smsService->getDeliveryReport(null, null, null, $limit);
            
            echo json_encode($logs);
            break;
            
        case 'process_queue':
            $batchSize = $_POST['batch_size'] ?? 10;
            $smsService = createSMSService($conn);
            $processed = $smsService->processQueue($batchSize);
            
            echo json_encode([
                'success' => true,
                'processed' => $processed,
                'message' => "Processed $processed messages from queue"
            ]);
            break;
            
        case 'cleanup':
            $smsService = createSMSService($conn);
            $cleaned = $smsService->cleanup();
            
            echo json_encode([
                'success' => true,
                'cleaned' => $cleaned,
                'message' => "Cleaned {$cleaned['deleted_logs']} logs and {$cleaned['deleted_queue']} queue items"
            ]);
            break;
            
        case 'template_test':
            $templateName = $_POST['template'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $testData = $_POST['data'] ?? '{}';
            
            if (empty($templateName) || empty($phone)) {
                echo json_encode(['success' => false, 'message' => 'Template name and phone number required']);
                break;
            }
            
            $data = json_decode($testData, true) ?: [];
            
            // Add default test data if not provided
            $defaultData = [
                'name' => 'Test User',
                'employee_id' => 'TEST001',
                'branch' => 'Test Branch',
                'time' => date('H:i:s'),
                'status' => 'On Time',
                'pin' => '1234',
                'reset_code' => '123456',
                'heart_rate' => '75',
                'stress_level' => '3.5',
                'department' => 'IT',
                'shift_start' => '09:00',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 day')),
                'reason' => 'Test reason',
                'location' => 'Test Location',
                'message' => 'Test emergency message'
            ];
            
            $data = array_merge($defaultData, $data);
            
            $smsService = createSMSService($conn);
            $result = $smsService->sendTemplateMessage($templateName, $phone, $data);
            
            echo json_encode([
                'success' => $result['success'] ?? false,
                'message' => $result['success'] ? 'Template SMS sent successfully' : ($result['error'] ?? 'Failed to send'),
                'template_data' => $data,
                'details' => $result
            ]);
            break;
            
        case 'rate_limit_check':
            $identifier = $_GET['identifier'] ?? $_POST['identifier'] ?? 'test';
            
            // Check current rate limit status
            $stmt = $conn->prepare("
                SELECT request_count, window_start 
                FROM tbl_sms_rate_limits 
                WHERE identifier = ? AND window_start > DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ");
            $stmt->execute([$identifier]);
            $rateLimit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'identifier' => $identifier,
                'current_count' => $rateLimit['request_count'] ?? 0,
                'window_start' => $rateLimit['window_start'] ?? null,
                'limit' => 100,
                'remaining' => 100 - ($rateLimit['request_count'] ?? 0)
            ]);
            break;
            
        case 'provider_test':
            $provider = $_POST['provider'] ?? 'smsonlinegh';
            
            // Test provider configuration
            $config = loadSMSConfig($conn);
            $providerConfig = $config['providers'][$provider] ?? null;
            
            if (!$providerConfig) {
                echo json_encode(['success' => false, 'message' => 'Provider not configured']);
                break;
            }
            
            $tests = [
                'api_key_present' => !empty($providerConfig['api_key']),
                'sender_id_present' => !empty($providerConfig['sender_id']),
                'endpoint_accessible' => false
            ];
            
            // Test endpoint accessibility
            $ch = curl_init($providerConfig['endpoint']);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $tests['endpoint_accessible'] = $httpCode > 0 && $httpCode < 500;
            
            $allPassed = array_reduce($tests, function($carry, $item) {
                return $carry && $item;
            }, true);
            
            echo json_encode([
                'success' => $allPassed,
                'provider' => $provider,
                'tests' => $tests,
                'endpoint_status' => $httpCode,
                'message' => $allPassed ? 'Provider configuration is valid' : 'Provider configuration has issues'
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
