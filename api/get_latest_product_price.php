<?php
require '../config/db_connect.php';

header('Content-Type: application/json');

try {
    $product_id = $_GET['product_id'] ?? null;
    
    if (!$product_id) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบ product_id'
        ]);
        exit;
    }
    
    // ดึงราคาต่อหน่วยล่าสุดจาก purchase_order_items
    // ลำดับความสำคัญ: price_original > price_per_unit > price_base
    $sql = "
        SELECT 
            poi.product_id,
            COALESCE(poi.price_original, poi.price_per_unit, poi.price_base, 0) as price,
            COALESCE(poi.currency_id, 1) as currency_id,
            c.symbol as currency_symbol,
            c.code as currency_code,
            c.exchange_rate,
            po.order_date as last_order_date
        FROM purchase_order_items poi
        INNER JOIN purchase_orders po ON poi.po_id = po.po_id
        LEFT JOIN currencies c ON poi.currency_id = c.currency_id
        WHERE poi.product_id = ? 
        AND COALESCE(poi.price_original, poi.price_per_unit, poi.price_base, 0) > 0
        ORDER BY po.order_date DESC, poi.item_id DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && floatval($result['price']) > 0) {
        echo json_encode([
            'success' => true,
            'product_id' => $result['product_id'],
            'price' => floatval($result['price']),
            'currency_id' => intval($result['currency_id'] ?? 1),
            'currency_symbol' => $result['currency_symbol'] ?? '$',
            'currency_code' => $result['currency_code'] ?? 'USD',
            'exchange_rate' => floatval($result['exchange_rate'] ?? 1),
            'last_order_date' => $result['last_order_date']
        ]);
    } else {
        // ถ้าไม่เจอ ส่งเป็น success แต่ price = 0 เพื่อให้สามารถป้อนราคาเองได้
        echo json_encode([
            'success' => true,
            'product_id' => $product_id,
            'price' => 0,
            'currency_id' => 1,
            'currency_symbol' => '$',
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'last_order_date' => null,
            'note' => 'ยังไม่เคยสั่งซื้อสินค้านี้'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
