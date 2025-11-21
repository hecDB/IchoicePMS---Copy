<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';
require_once '../auth/auth_check.php';

try {
    // Get the POST data
    $missing_id = isset($_POST['missing_id']) ? (int)$_POST['missing_id'] : null;
    $quantity_missing = isset($_POST['quantity_missing']) ? (float)$_POST['quantity_missing'] : null;
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';

    // Validate required fields
    if (!$missing_id || $quantity_missing === null) {
        throw new Exception('Missing required fields');
    }

    if ($quantity_missing <= 0) {
        throw new Exception('จำนวนที่สูญหายต้องมากกว่า 0');
    }

    // Update the missing product record
    $sql = "UPDATE missing_products 
            SET quantity_missing = ?, remark = ? 
            WHERE missing_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$quantity_missing, $remark, $missing_id]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'อัปเดตสำเร็จ'
        ]);
    } else {
        throw new Exception('ไม่สามารถอัปเดตรายการได้');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
