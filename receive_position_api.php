<?php
// API: รับ receive_id แล้วคืน row_code, bin, shelf ของสินค้านั้น
header('Content-Type: application/json');
require 'db_connect.php';

$receive_id = isset($_GET['receive_id']) ? intval($_GET['receive_id']) : 0;
if ($receive_id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'ไม่พบ ID']);
    exit;
}

try {
    // หา item_id จาก receive_items
    $sql = "SELECT poi.product_id
            FROM receive_items r
            LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
            WHERE r.receive_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$receive_id]);
    $product_id = $stmt->fetchColumn();
    if (!$product_id) {
        echo json_encode(['success' => false, 'msg' => 'ไม่พบสินค้า']);
        exit;
    }
    // หา row_code, bin, shelf จาก product_location + locations
    $sql2 = "SELECT l.row_code, l.bin, l.shelf
             FROM product_location pl
             LEFT JOIN locations l ON pl.location_id = l.location_id
             WHERE pl.product_id = ? LIMIT 1";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$product_id]);
    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'row_code' => $row['row_code'], 'bin' => $row['bin'], 'shelf' => $row['shelf']]);
    } else {
        echo json_encode(['success' => true, 'row_code' => '', 'bin' => '', 'shelf' => '']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
