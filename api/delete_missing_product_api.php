<?php
/**
 * API for deleting missing product records
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$missing_id = isset($_POST['missing_id']) ? intval($_POST['missing_id']) : 0;

if ($missing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของรายการ']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get the related receive_id from the missing product record's remark
    $sqlGetMissing = "SELECT missing_id FROM missing_products WHERE missing_id = ?";
    $stmtGetMissing = $pdo->prepare($sqlGetMissing);
    $stmtGetMissing->execute([$missing_id]);
    $missing = $stmtGetMissing->fetch(PDO::FETCH_ASSOC);
    
    if (!$missing) {
        throw new Exception('ไม่พบข้อมูลรายการสูญหาย');
    }
    
    // Delete from missing_products table
    $sqlDelete = "DELETE FROM missing_products WHERE missing_id = ?";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute([$missing_id]);
    
    // Delete the corresponding transaction record from receive_items
    // Find records with matching remark pattern
    $sqlDeleteTransaction = "DELETE FROM receive_items 
                           WHERE remark LIKE ? AND receive_qty < 0 LIMIT 1";
    $stmtDeleteTransaction = $pdo->prepare($sqlDeleteTransaction);
    $stmtDeleteTransaction->execute(['%Missing ID: ' . $missing_id . '%']);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบรายการสูญหายสำเร็จ'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting missing product: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
