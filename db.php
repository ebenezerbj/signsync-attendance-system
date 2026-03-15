<?php
// Use environment variables if available (Fly.io / production), fallback to local dev defaults
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'signsync_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

    // TiDB Cloud requires SSL/TLS connections
    if (getenv('DB_SSL') === 'true') {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
    }

    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password, $options);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>