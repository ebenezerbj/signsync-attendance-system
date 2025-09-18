<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Get JSON input for profile updates
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    $employee_id = $data['employee_id'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $address = $data['address'] ?? '';
    $profile_photo_base64 = $data['profile_photo_base64'] ?? '';
    
    if (empty($employee_id)) {
        throw new Exception('Employee ID is required');
    }
    
    if (empty($name)) {
        throw new Exception('Name is required');
    }
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check if employee exists
    $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    
    // Check if email is already used by another employee
    $stmt = $conn->prepare("SELECT id FROM employees WHERE email = ? AND employee_id != ?");
    $stmt->execute([$email, $employee_id]);
    $existing_email = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_email) {
        throw new Exception('Email address is already in use');
    }
    
    // Prepare update query
    $update_fields = [
        'name = ?',
        'email = ?',
        'phone = ?',
        'address = ?',
        'updated_at = NOW()'
    ];
    
    $update_values = [$name, $email, $phone, $address];
    
    // Handle profile photo if provided
    if (!empty($profile_photo_base64)) {
        // Validate base64 image
        if (strpos($profile_photo_base64, 'data:image/') === 0) {
            // Extract file extension and data
            preg_match('/data:image\/(.*?);base64,(.*)/', $profile_photo_base64, $matches);
            if (count($matches) === 3) {
                $extension = $matches[1];
                $image_data = $matches[2];
                
                // Validate extension
                $allowed_extensions = ['jpeg', 'jpg', 'png', 'gif'];
                if (in_array(strtolower($extension), $allowed_extensions)) {
                    // Generate unique filename
                    $filename = 'profile_' . $employee_id . '_' . time() . '.' . $extension;
                    $upload_path = 'uploads/profiles/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_path)) {
                        mkdir($upload_path, 0755, true);
                    }
                    
                    // Save image file
                    $image_binary = base64_decode($image_data);
                    if (file_put_contents($upload_path . $filename, $image_binary)) {
                        $update_fields[] = 'profile_picture = ?';
                        $update_values[] = $upload_path . $filename;
                    }
                }
            }
        }
    }
    
    // Build and execute update query
    $sql = "UPDATE employees SET " . implode(', ', $update_fields) . " WHERE employee_id = ?";
    $update_values[] = $employee_id;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($update_values);
    
    if ($stmt->rowCount() > 0) {
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => 'Profile information has been updated'
        ];
    } else {
        $response = [
            'success' => true,
            'message' => 'No changes were made to the profile',
            'data' => 'Profile information is already up to date'
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ]);
}
?>
