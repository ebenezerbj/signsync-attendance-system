<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$status = [
  'php_version' => phpversion(),
  'time' => date('c'),
  'db' => 'unknown'
];

try {
  include 'db.php';
  $stmt = $conn->query("SELECT 1");
  $status['db'] = $stmt ? 'ok' : 'fail';
} catch (Throwable $e) {
  $status['db'] = 'error: ' . $e->getMessage();
}

echo json_encode(['success' => true, 'status' => $status]);
?>
