<?php
/**
 * SMS Service Database Migration
 * 
 * This script creates all the necessary database tables for the SMS service
 * and initializes the system with default configuration and templates.
 */

require_once 'db.php';
require_once 'sms_config.php';

try {
    echo "🚀 Starting SMS Service Database Migration...\n\n";
    
    // Initialize SMS system
    if (initializeSMSSystem($conn)) {
        echo "✅ SMS System initialized successfully!\n\n";
    } else {
        echo "❌ Failed to initialize SMS system.\n";
        exit(1);
    }
    
    // Check system health
    echo "🔍 Checking SMS Service Health...\n";
    $health = getSMSServiceHealth($conn);
    
    echo "Status: " . strtoupper($health['status']) . "\n\n";
    
    echo "Database Checks:\n";
    foreach ($health['checks'] as $check => $status) {
        $icon = strpos($status, 'OK') !== false ? '✅' : '❌';
        echo "  $icon $check: $status\n";
    }
    
    echo "\nStatistics:\n";
    if (is_array($health['statistics'])) {
        foreach ($health['statistics'] as $key => $value) {
            echo "  📊 $key: $value\n";
        }
    } else {
        echo "  📊 " . $health['statistics'] . "\n";
    }
    
    // Setup environment template
    echo "\n🔧 Setting up environment configuration...\n";
    $envSetup = setupSMSEnvironment();
    
    if ($envSetup['created']) {
        echo "✅ Environment template created: " . $envSetup['file'] . "\n";
        echo "📝 " . $envSetup['message'] . "\n";
    } else {
        echo "ℹ️  " . $envSetup['message'] . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 SMS Service Migration Completed Successfully!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "📋 Next Steps:\n";
    echo "1. Update your SMS API credentials in the environment file\n";
    echo "2. Test the SMS service using the testing tools\n";
    echo "3. Configure SMS templates in the admin dashboard\n";
    echo "4. Update existing code to use the new SMS service\n\n";
    
    echo "🔗 Available Tools:\n";
    echo "• Admin Dashboard: admin_dashboard.php\n";
    echo "• SMS Management: sms_admin.php\n";
    echo "• SMS Testing: sms_test.php\n";
    echo "• Health Check: sms_health.php\n\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
