<?php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get completed Purchase Orders (100% received)
    $sql = "
        SELECT 
            po.po_id,
            po.po_number,
            s.name as supplier_name,
            po.order_date as po_date,
            po.total_amount,
            c.code as currency_code,
            po.remark,
            po.status,
            COUNT(poi.item_id) as total_items,
            COALESCE(SUM(
                CASE WHEN COALESCE(received_summary.total_received, 0) >= poi.qty THEN 1 ELSE 0 END
            ), 0) as received_items
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) received_summary ON poi.item_id = received_summary.item_id
        WHERE po.status IN ('pending', 'partial', 'completed')
        GROUP BY po.po_id, po.po_number, s.name, po.order_date, po.total_amount, c.code, po.remark, po.status
        HAVING COUNT(poi.item_id) > 0 
        AND COALESCE(SUM(
            CASE WHEN COALESCE(received_summary.total_received, 0) >= poi.qty THEN 1 ELSE 0 END
        ), 0) = COUNT(poi.item_id)
        ORDER BY po.order_date DESC, po.po_number DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->query($sql);
    $completed_pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $completed_pos,
        'count' => count($completed_pos)
    ]);
    
} catch (Exception $e) {
    error_log("Get completed POs error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล'
    ]);
}
?>