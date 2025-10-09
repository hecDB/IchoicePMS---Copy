<?php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้เข้าสู่ระบบ']);
    exit;
}

try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    $today = date('Y-m-d');
    
    // Insert acknowledgment record (use ON DUPLICATE KEY UPDATE for safety)
    $stmt = $pdo->prepare("
        INSERT INTO expiry_notifications (user_id, notification_date, acknowledged_at) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE acknowledged_at = NOW()
    ");
    
    $stmt->execute([$user_id, $today]);
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการรับทราบเรียบร้อย',
        'data' => [
            'user_id' => $user_id,
            'notification_date' => $today,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("Acknowledge expiry notification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการบันทึกการรับทราบ'
    ]);
}
?>