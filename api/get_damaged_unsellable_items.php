<?php
/**
 * API: ดึงข้อมูลสินค้าชำรุดขายไม่ได้
 * ดึง returned_items ที่มี is_returnable = 0 (ชำรุดขายไม่ได้)
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require '../config/db_connect.php';

try {
    $sql = "
        SELECT 
            ri.return_id,
            ri.return_code,
            ri.product_id,
            ri.product_name,
            ri.sku,
            ri.return_qty,
            ri.return_status,
            ri.is_returnable,
            ri.image_path,
            ri.notes as return_notes,
            ri.expiry_date,
            ri.created_at,
            ri.po_id
        FROM returned_items ri
        WHERE ri.is_returnable = 0
        ORDER BY ri.created_at DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
