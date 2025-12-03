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
    // Get completed Purchase Orders - simpler and more reliable approach
    // Step 1: Get all POs with their received and cancelled item counts
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
            SUM(CASE WHEN COALESCE(ri.total_received, 0) >= poi.qty THEN 1 ELSE 0 END) as fully_received_items,
            SUM(CASE WHEN poi.is_cancelled = 1 OR poi.is_partially_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_items
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) ri ON poi.item_id = ri.item_id
        WHERE po.status IN ('pending', 'partial', 'completed')
        AND poi.item_id IS NOT NULL
        GROUP BY po.po_id, po.po_number, s.name, po.order_date, po.total_amount, c.code, po.remark, po.status
        ORDER BY po.order_date DESC, po.po_number DESC
        LIMIT 200
    ";
    
    $stmt = $pdo->query($sql);
    $all_pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter results: keep only completed (100% received) or cancelled items
    $completed_pos = array_filter($all_pos, function($po) {
        $total = (int)$po['total_items'];
        $received = (int)($po['fully_received_items'] ?? 0);
        $cancelled = (int)($po['cancelled_items'] ?? 0);
        
        // Show PO if: (all items fully received) OR (has any cancelled items)
        $is_completed = ($total > 0 && $received === $total);
        $has_cancelled = ($cancelled > 0);
        
        return $is_completed || $has_cancelled;
    });
    
    // Add has_cancelled_items flag for frontend
    $completed_pos = array_map(function($po) {
        $po['has_cancelled_items'] = (int)($po['cancelled_items'] ?? 0) > 0 ? 1 : 0;
        return $po;
    }, $completed_pos);
    
    echo json_encode([
        'success' => true,
        'data' => array_values($completed_pos),
        'count' => count($completed_pos)
    ]);
    
} catch (Exception $e) {
    error_log("Get completed POs error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage()
    ]);
}
?>