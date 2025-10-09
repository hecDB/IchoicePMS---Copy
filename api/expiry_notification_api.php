<?php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้เข้าสู่ระบบ']);
    exit;
}

try {
    $today = date('Y-m-d');
    
    // Check if user already acknowledged today's notification
    $check_stmt = $pdo->prepare("
        SELECT id FROM expiry_notifications 
        WHERE user_id = ? AND notification_date = ?
    ");
    $check_stmt->execute([$user_id, $today]);
    $already_notified = $check_stmt->fetch();
    
    if ($already_notified) {
        echo json_encode([
            'success' => true, 
            'show_notification' => false,
            'message' => 'แจ้งเตือนวันนี้ได้รับทราบแล้ว'
        ]);
        exit;
    }
    
    // Get expired products (past expiry date)
    $sql_expired = "
        SELECT 
            p.product_id,
            p.name,
            p.sku,
            p.barcode,
            ri.expiry_date,
            SUM(ri.receive_qty) as total_qty,
            DATEDIFF(?, ri.expiry_date) as days_expired
        FROM products p
        LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
        LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
        WHERE ri.expiry_date IS NOT NULL 
          AND ri.expiry_date < ?
        GROUP BY p.product_id, p.name, p.sku, p.barcode, ri.expiry_date
        HAVING total_qty > 0
        ORDER BY ri.expiry_date ASC
        LIMIT 10
    ";
    
    // Get expiring soon products (7 days)
    $seven_days_later = date('Y-m-d', strtotime('+7 days'));
    $sql_expiring = "
        SELECT 
            p.product_id,
            p.name,
            p.sku,
            p.barcode,
            ri.expiry_date,
            SUM(ri.receive_qty) as total_qty,
            DATEDIFF(ri.expiry_date, ?) as days_to_expiry
        FROM products p
        LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
        LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
        WHERE ri.expiry_date IS NOT NULL 
          AND ri.expiry_date BETWEEN ? AND ?
        GROUP BY p.product_id, p.name, p.sku, p.barcode, ri.expiry_date
        HAVING total_qty > 0
        ORDER BY ri.expiry_date ASC
        LIMIT 15
    ";
    
    // Execute queries
    $expired_stmt = $pdo->prepare($sql_expired);
    $expired_stmt->execute([$today, $today]);
    $expired_products = $expired_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $expiring_stmt = $pdo->prepare($sql_expiring);
    $expiring_stmt->execute([$today, $today, $seven_days_later]);
    $expiring_products = $expiring_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if we have any products to notify about
    $has_notifications = !empty($expired_products) || !empty($expiring_products);
    
    if (!$has_notifications) {
        echo json_encode([
            'success' => true,
            'show_notification' => false,
            'message' => 'ไม่มีสินค้าหมดอายุหรือใกล้หมดอายุ'
        ]);
        exit;
    }
    
    // Calculate totals
    $expired_count = count($expired_products);
    $expiring_count = count($expiring_products);
    $total_expired_qty = array_sum(array_column($expired_products, 'total_qty'));
    $total_expiring_qty = array_sum(array_column($expiring_products, 'total_qty'));
    
    // Format products for display
    $formatted_expired = array_map(function($product) {
        return [
            'name' => $product['name'],
            'sku' => $product['sku'],
            'expiry_date' => $product['expiry_date'],
            'qty' => $product['total_qty'],
            'days_expired' => $product['days_expired']
        ];
    }, $expired_products);
    
    $formatted_expiring = array_map(function($product) {
        return [
            'name' => $product['name'],
            'sku' => $product['sku'],
            'expiry_date' => $product['expiry_date'],
            'qty' => $product['total_qty'],
            'days_to_expiry' => $product['days_to_expiry']
        ];
    }, $expiring_products);
    
    echo json_encode([
        'success' => true,
        'show_notification' => true,
        'data' => [
            'expired' => [
                'count' => $expired_count,
                'total_qty' => $total_expired_qty,
                'products' => $formatted_expired
            ],
            'expiring' => [
                'count' => $expiring_count,
                'total_qty' => $total_expiring_qty,
                'products' => $formatted_expiring
            ],
            'total_items' => $expired_count + $expiring_count
        ]
    ]);

} catch (Exception $e) {
    error_log("Expiry notification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการตรวจสอบสินค้าหมดอายุ'
    ]);
}
?>