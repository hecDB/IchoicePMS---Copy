<?php
/**
 * API: ดึงข้อมูลสินค้าชำรุดขายไม่ได้สำหรับ PO ที่ระบุ
 * ดึง returned_items ที่มี is_returnable = 0 และเกี่ยวข้องกับ PO นั้น
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require '../config/db_connect.php';

try {
    $po_id = $_GET['po_id'] ?? null;
    
    if (!$po_id) {
        echo json_encode([
            'status' => 'success',
            'data' => [],
            'debug' => 'No PO ID provided'
        ]);
        exit;
    }
    
    // Debug: Log PO ID
    error_log("[get_damaged_unsellable_by_po] PO ID: " . $po_id);
    
    // Select all columns from returned_items to avoid column not found errors
    $sql = "
        SELECT ri.*
        FROM returned_items ri
        WHERE (ri.is_returnable = 0 OR ri.is_returnable = '0') 
        AND ri.po_id = :po_id
        ORDER BY ri.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':po_id' => (int)$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log result count
    error_log("[get_damaged_unsellable_by_po] Found items: " . count($items));
    
    echo json_encode([
        'status' => 'success',
        'data' => $items,
        'count' => count($items),
        'debug' => [
            'po_id' => (int)$po_id,
            'query' => 'returned_items where is_returnable=0 and po_id=' . (int)$po_id
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
