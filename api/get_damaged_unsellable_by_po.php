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
            'data' => []
        ]);
        exit;
    }
    
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
            ri.created_at
        FROM returned_items ri
        WHERE ri.is_returnable = 0 AND ri.po_id = :po_id
        ORDER BY ri.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':po_id' => (int)$po_id]);
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
