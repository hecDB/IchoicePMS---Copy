<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$barcode = $input['barcode'] ?? '';

if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล barcode/SKU']);
    exit;
}

try {
    // Search for products by barcode, SKU, or name (partial match)
    $search_term = '%' . $barcode . '%';
    
    // Get product information and related Purchase Order items that are not fully received
    $sql = "
        SELECT 
            p.product_id,
            p.name as product_name,
            p.sku,
            p.barcode,
            p.unit,
            NULL as price,
            poi.item_id,
            poi.po_id,
            poi.qty as ordered_qty,
            poi.price_per_unit as unit_cost,
            COALESCE(ri.total_received, 0) as received_qty,
            (poi.qty - COALESCE(ri.total_received, 0)) as remaining_qty,
            po.po_number,
            po.order_date,
            s.name as supplier_name,
            c.code as currency_code,
            po.status as po_status
        FROM products p
        LEFT JOIN purchase_order_items poi ON p.product_id = poi.product_id
        LEFT JOIN purchase_orders po ON poi.po_id = po.po_id  
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        LEFT JOIN (
            SELECT 
                item_id, 
                SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) ri ON poi.item_id = ri.item_id
        WHERE (p.barcode LIKE :search1 OR p.sku LIKE :search2 OR p.name LIKE :search3)
        AND (poi.item_id IS NULL OR (poi.qty > COALESCE(ri.total_received, 0) AND po.status IN ('pending', 'partial')))
        ORDER BY 
            CASE 
                WHEN p.barcode = :exact_search1 THEN 1
                WHEN p.sku = :exact_search2 THEN 2
                WHEN p.barcode LIKE :search4 THEN 3
                WHEN p.sku LIKE :search5 THEN 4
                ELSE 5
            END,
            po.order_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':search1', $search_term);
    $stmt->bindParam(':search2', $search_term);
    $stmt->bindParam(':search3', $search_term);
    $stmt->bindParam(':search4', $search_term);
    $stmt->bindParam(':search5', $search_term);
    $stmt->bindParam(':exact_search1', $barcode);
    $stmt->bindParam(':exact_search2', $barcode);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo json_encode([
            'success' => false, 
            'message' => 'ไม่พบสินค้าที่ตรงกับ barcode/SKU: ' . htmlspecialchars($barcode)
        ]);
        exit;
    }
    
    // Group results by product
    $products = [];
    foreach ($results as $row) {
        $product_id = $row['product_id'];
        
        if (!isset($products[$product_id])) {
            $products[$product_id] = [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'sku' => $row['sku'],
                'barcode' => $row['barcode'],
                'unit' => $row['unit'],
                'price' => $row['price'],
                'purchase_orders' => []
            ];
        }
        
        // Add PO information if exists and has remaining quantity
        if ($row['item_id'] && $row['remaining_qty'] > 0) {
            $products[$product_id]['purchase_orders'][] = [
                'item_id' => $row['item_id'],
                'po_id' => $row['po_id'],
                'po_number' => $row['po_number'],
                'order_date' => $row['order_date'],
                'supplier_name' => $row['supplier_name'],
                'ordered_qty' => floatval($row['ordered_qty']),
                'received_qty' => floatval($row['received_qty'] ?? 0),
                'remaining_qty' => floatval($row['remaining_qty']),
                'unit_cost' => floatval($row['unit_cost']),
                'currency_code' => $row['currency_code'],
                'po_status' => $row['po_status']
            ];
        }
    }
    
    // Convert to array and filter out products without purchase orders
    $available_products = [];
    foreach ($products as $product) {
        if (!empty($product['purchase_orders'])) {
            $available_products[] = $product;
        }
    }
    
    if (empty($available_products)) {
        echo json_encode([
            'success' => false,
            'message' => 'พบสินค้า แต่ไม่มีใบสั่งซื้อที่ยังไม่ได้รับเข้าครบ สำหรับ: ' . htmlspecialchars($barcode)
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $available_products,
        'message' => 'พบสินค้า ' . count($available_products) . ' รายการ'
    ]);

} catch (Exception $e) {
    error_log("Barcode search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการค้นหา: ' . $e->getMessage()
    ]);
}
?>