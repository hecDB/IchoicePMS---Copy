<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Log all incoming data
error_log('=== API TEST START ===');
error_log('METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('POST: ' . json_encode($_POST));
error_log('FILES: ' . json_encode(array_keys($_FILES)));

if (isset($_FILES['image'])) {
    $file = $_FILES['image'];
    error_log('Image file info:');
    error_log('  - name: ' . $file['name']);
    error_log('  - type: ' . $file['type']);
    error_log('  - size: ' . $file['size']);
    error_log('  - error: ' . $file['error']);
    error_log('  - tmp_name: ' . $file['tmp_name']);
    error_log('  - tmp_name exists: ' . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
}

// Check if database connection works
try {
    require '../config/db_connect.php';
    error_log('Database connected successfully');
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
}

// Check if GD library is available
error_log('GD library available: ' . (extension_loaded('gd') ? 'YES' : 'NO'));

// Check images directory
$images_dir = '../images/';
error_log('Images directory exists: ' . (is_dir($images_dir) ? 'YES' : 'NO'));
if (is_dir($images_dir)) {
    error_log('Images directory writable: ' . (is_writable($images_dir) ? 'YES' : 'NO'));
}

error_log('=== API TEST END ===');

echo json_encode([
    'status' => 'test_success',
    'gd_available' => extension_loaded('gd'),
    'images_dir_exists' => is_dir($images_dir),
    'images_dir_writable' => is_dir($images_dir) && is_writable($images_dir),
    'message' => 'Check error log for details'
]);
?>
