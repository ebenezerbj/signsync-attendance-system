<?php
// Health check endpoint for Fly.io and entry point redirect
if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Consul Health Check') !== false) {
    http_response_code(200);
    echo 'OK';
    exit;
}

// Redirect all visitors to the login page
header('Location: login.php');
exit;
