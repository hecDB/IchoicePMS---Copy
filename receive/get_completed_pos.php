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
    // Get completed Purchase Orders by status flag (show all completed POs regardless of quantity math)
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
            COUNT(DISTINCT poi.item_id) as total_items,
            COALESCE(SUM(
                CASE WHEN (COALESCE(received_summary.total_received, 0) + COALESCE(poi.cancel_qty, 0)) >= poi.qty THEN 1 ELSE 0 END
            ), 0) as fully_received_items,
            COALESCE(SUM(poi.qty), 0) as total_ordered_qty,
            COALESCE(SUM(COALESCE(received_summary.total_received, 0)), 0) as total_received_qty,
            COALESCE(SUM(COALESCE(poi.cancel_qty, 0)), 0) as total_cancelled_qty
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) received_summary ON poi.item_id = received_summary.item_id
        WHERE po.status = 'completed'
        GROUP BY po.po_id, po.po_number, s.name, po.order_date, po.total_amount, c.code, po.remark, po.status
        ORDER BY po.order_date DESC, po.po_number DESC
        LIMIT 500
    ";
    
    $stmt = $pdo->query($sql);
    $completed_pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data with proper types
    $formatted_data = array_map(function($po) {
        $total_ordered = floatval($po['total_ordered_qty'] ?? 0);
        $total_received = floatval($po['total_received_qty'] ?? 0);
        $total_cancelled = floatval($po['total_cancelled_qty'] ?? 0);
        $total_fulfilled = $total_received + $total_cancelled;
        
        return [
            'po_id' => intval($po['po_id']),
            'po_number' => $po['po_number'],
            'supplier_name' => $po['supplier_name'] ?? 'N/A',
            'po_date' => $po['po_date'],
            'total_amount' => $total_ordered > 0 ? floatval($po['total_amount']) : 0,
            'currency_code' => $po['currency_code'] ?? 'THB',
            'remark' => $po['remark'],
            'status' => $po['status'],
            'total_items' => intval($po['total_items'] ?? 0),
            'fully_received_items' => intval($po['fully_received_items'] ?? 0),
            'total_ordered_qty' => $total_ordered,
            'total_received_qty' => $total_received,
            'total_cancelled_qty' => $total_cancelled,
            'completion_rate' => $total_ordered > 0 ? ($total_fulfilled / $total_ordered) * 100 : 0
        ];
    }, $completed_pos);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_data,
        'count' => count($formatted_data)
    ]);
    
} catch (Exception $e) {
    error_log("Get completed POs error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage()
    ]);
}
?>
