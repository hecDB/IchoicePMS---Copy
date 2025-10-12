<?php
header('Content-Type: application/json');
require '../config/db_connect.php';

$action = $_GET['action'] ?? '';
$po_id = $_GET['po_id'] ?? 6;

try {
    switch ($action) {
        case 'check_po_items':
            $sql = "
                SELECT 
                    poi.item_id,
                    poi.product_id,
                    p.name as product_name,
                    poi.qty as order_qty,
                    poi.price_per_unit,
                    poi.total,
                    poi.currency,
                    COALESCE(SUM(ri.receive_qty), 0) as received_qty,
                    (poi.qty - COALESCE(SUM(ri.receive_qty), 0)) as remaining_qty
                FROM purchase_order_items poi
                LEFT JOIN products p ON poi.product_id = p.product_id
                LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
                WHERE poi.po_id = ?
                GROUP BY poi.item_id, poi.product_id, p.name, poi.qty, poi.price_per_unit, poi.total, poi.currency
                ORDER BY p.name
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$po_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ]);
            break;
            
        case 'check_received_items':
            $sql = "
                SELECT 
                    ri.receive_id,
                    ri.item_id,
                    ri.po_id,
                    ri.receive_qty,
                    ri.created_at,
                    ri.created_by,
                    ri.remark,
                    p.name as product_name,
                    poi.qty as ordered_qty
                FROM receive_items ri
                LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
                LEFT JOIN products p ON poi.product_id = p.product_id
                WHERE ri.po_id = ?
                ORDER BY ri.created_at DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$po_id]);
            $received = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $received,
                'count' => count($received)
            ]);
            break;
            
        case 'check_po_status':
            $sql = "
                SELECT 
                    po.*,
                    s.name as supplier_name,
                    COUNT(poi.item_id) as total_items,
                    COALESCE(SUM(CASE WHEN ri.item_id IS NOT NULL THEN 1 ELSE 0 END), 0) as received_items
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
                LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
                WHERE po.po_id = ?
                GROUP BY po.po_id
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$po_id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $po
            ]);
            break;
            
        case 'test_insert':
            // ทดสอบการ insert ข้อมูล
            $item_id = $_GET['item_id'] ?? null;
            $quantity = $_GET['quantity'] ?? 1;
            
            if (!$item_id) {
                throw new Exception('ต้องระบุ item_id');
            }
            
            $sql = "INSERT INTO receive_items (item_id, po_id, receive_qty, created_by, created_at, remark) 
                    VALUES (?, ?, ?, 1, NOW(), 'Test Insert')";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$item_id, $po_id, $quantity]);
            
            echo json_encode([
                'success' => $result,
                'message' => 'Test insert completed',
                'affected_rows' => $stmt->rowCount()
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>