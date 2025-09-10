<?php
// receive_edit.php
header('Content-Type: application/json');
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$receive_id = isset($_POST['receive_id']) ? intval($_POST['receive_id']) : 0;
$remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
$location_desc = isset($_POST['location_desc']) ? trim($_POST['location_desc']) : '';
$price_per_unit = isset($_POST['price_per_unit']) ? floatval($_POST['price_per_unit']) : 0;
$sale_price = isset($_POST['sale_price']) ? floatval($_POST['sale_price']) : 0;
$receive_qty = isset($_POST['receive_qty']) ? intval($_POST['receive_qty']) : 0;
$expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
$row_code = isset($_POST['row_code']) ? trim($_POST['row_code']) : '';
$bin = isset($_POST['bin']) ? trim($_POST['bin']) : '';
$shelf = isset($_POST['shelf']) ? trim($_POST['shelf']) : '';

if ($receive_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID รายการ']);
    exit;
}

try {
    // อัปเดต receive_items
    $sql = "UPDATE receive_items SET remark=?, receive_qty=?, expiry_date=? WHERE receive_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$remark, $receive_qty, $expiry_date, $receive_id]);

    // อัปเดตตำแหน่ง (location_desc, row_code, bin, shelf) ในตาราง locations ถ้ามีข้อมูล
    if ($location_desc !== '' || $row_code !== '' || $bin !== '' || $shelf !== '') {
        // หา location_id จาก receive_items
        $sqlLoc = "SELECT l.location_id FROM receive_items r
            LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
            LEFT JOIN products p ON poi.product_id = p.product_id
            LEFT JOIN product_location pl ON pl.product_id = p.product_id
            LEFT JOIN locations l ON l.location_id = pl.location_id
            WHERE r.receive_id = ? LIMIT 1";
        $stmtLoc = $pdo->prepare($sqlLoc);
        $stmtLoc->execute([$receive_id]);
        $location_id = $stmtLoc->fetchColumn();
        if ($location_id) {
            $updateLoc = "UPDATE locations SET description=?, row_code=?, bin=?, shelf=? WHERE location_id=?";
            $pdo->prepare($updateLoc)->execute([
                $location_desc,
                $row_code,
                $bin,
                $shelf,
                $location_id
            ]);
        }
    }

    // อัปเดตราคาต้นทุน/ราคาขายใน purchase_order_items ถ้ามีข้อมูล
    $sqlItem = "SELECT item_id FROM receive_items WHERE receive_id=?";
    $stmtItem = $pdo->prepare($sqlItem);
    $stmtItem->execute([$receive_id]);
    $item_id = $stmtItem->fetchColumn();
    if ($item_id) {
        $pdo->prepare("UPDATE purchase_order_items SET price_per_unit=?, sale_price=? WHERE item_id=?")
            ->execute([$price_per_unit, $sale_price, $item_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
