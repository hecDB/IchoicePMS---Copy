<?php
// API: รับ receive_id แล้วคืน row_code, bin, shelf ของสินค้านั้น
header('Content-Type: application/json');
require '../config/db_connect.php';

$receive_id = isset($_GET['receive_id']) ? intval($_GET['receive_id']) : 0;
if ($receive_id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'ไม่พบ ID']);
    exit;
}

try {
    // หา item_id, ราคา และ product_id จาก receive_items
    $sql = "SELECT poi.product_id, poi.temp_product_id, poi.price_per_unit, poi.sale_price, r.remark, r.expiry_date
            FROM receive_items r
            LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
            WHERE r.receive_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$receive_id]);
    $receiveData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receiveData || (!$receiveData['product_id'] && !$receiveData['temp_product_id'])) {
        echo json_encode(['success' => false, 'msg' => 'ไม่พบสินค้า']);
        exit;
    }

    $locationData = null;
    if (!empty($receiveData['product_id'])) {
        $sql2 = "SELECT l.location_id, l.row_code, l.bin, l.shelf
                 FROM product_location pl
                 LEFT JOIN locations l ON pl.location_id = l.location_id
                 WHERE pl.product_id = ? LIMIT 1";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$receiveData['product_id']]);
        $locationData = $stmt2->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($receiveData['temp_product_id'])) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS temp_product_locations (
            temp_product_id INT PRIMARY KEY,
            location_id INT DEFAULT NULL,
            row_code VARCHAR(50) DEFAULT NULL,
            bin VARCHAR(50) DEFAULT NULL,
            shelf VARCHAR(50) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $sqlPending = "SELECT tpl.location_id, COALESCE(l.row_code, tpl.row_code) AS row_code,
                               COALESCE(l.bin, tpl.bin) AS bin,
                               COALESCE(l.shelf, tpl.shelf) AS shelf
                        FROM temp_product_locations tpl
                        LEFT JOIN locations l ON tpl.location_id = l.location_id
                        WHERE tpl.temp_product_id = ? LIMIT 1";
        $stmtPending = $pdo->prepare($sqlPending);
        $stmtPending->execute([$receiveData['temp_product_id']]);
        $locationData = $stmtPending->fetch(PDO::FETCH_ASSOC);
    }

    // รวมข้อมูลทั้งหมด
    $result = [
        'success' => true,
        'location_id' => $locationData['location_id'] ?? null,
        'row_code' => $locationData['row_code'] ?? '',
        'bin' => $locationData['bin'] ?? '',
        'shelf' => $locationData['shelf'] ?? '',
        'price_per_unit' => $receiveData['price_per_unit'] ?? '',
        'sale_price' => $receiveData['sale_price'] ?? '',
        'remark' => $receiveData['remark'] ?? '',
        'expiry_date' => $receiveData['expiry_date'] ?? ''
    ];
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
