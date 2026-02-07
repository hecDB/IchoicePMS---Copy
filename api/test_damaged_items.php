<?php
/**
 * TEST API: ตรวจสอบข้อมูลสินค้าชำรุดในฐานข้อมูล
 * ใช้เพื่อ debug และตรวจสอบว่าข้อมูลถูกบันทึกหรือไม่
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require '../config/db_connect.php';

try {
    $po_id = $_GET['po_id'] ?? null;
    
    // ข้อมูลทั้งหมดของ returned_items ที่เกี่ยวข้องกับ PO นี้ (ทั้งหมด)
    $sql_all = "
        SELECT 
            return_id,
            return_code,
            po_id,
            po_number,
            item_id,
            product_id,
            product_name,
            sku,
            return_qty,
            reason_name,
            is_returnable,
            return_status,
            created_at
        FROM returned_items 
        WHERE po_id = :po_id
        ORDER BY created_at DESC
    ";
    
    $stmt_all = $pdo->prepare($sql_all);
    $stmt_all->execute([':po_id' => (int)$po_id]);
    $all_items = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    
    // ข้อมูล returned_items ที่มี is_returnable = 0
    $sql_damaged = "
        SELECT 
            return_id,
            return_code,
            po_id,
            po_number,
            item_id,
            product_id,
            product_name,
            sku,
            return_qty,
            reason_name,
            is_returnable,
            return_status,
            created_at
        FROM returned_items 
        WHERE po_id = :po_id AND is_returnable = 0
        ORDER BY created_at DESC
    ";
    
    $stmt_damaged = $pdo->prepare($sql_damaged);
    $stmt_damaged->execute([':po_id' => (int)$po_id]);
    $damaged_items = $stmt_damaged->fetchAll(PDO::FETCH_ASSOC);
    
    // สรุปข้อมูล
    $summary = [
        'po_id' => (int)$po_id,
        'total_returned_items' => count($all_items),
        'damaged_unsellable_items' => count($damaged_items),
        'all_returned_items' => $all_items,
        'damaged_unsellable_items_detail' => $damaged_items
    ];
    
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
