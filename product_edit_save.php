<?php
require 'db_connect.php';

$id = $_POST['product_id'];
$name = $_POST['name'];
$sku = $_POST['sku'];
$barcode = $_POST['barcode'];
$unit = $_POST['unit'];
$qty = $_POST['receive_qty'];

// 1. อัปเดตข้อมูลสินค้า
$stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, barcode=?, unit=? WHERE product_id=?");
$stmt->execute([$name, $sku, $barcode, $unit, $id]);

// 2. หา item_id ทั้งหมดที่เกี่ยวกับ product นี้
$stmtItems = $pdo->prepare("SELECT item_id FROM purchase_order_items WHERE product_id=?");
$stmtItems->execute([$id]);
$item_ids = $stmtItems->fetchAll(PDO::FETCH_COLUMN);

if(!empty($item_ids)){
    // 3. หา receive_items record ล่าสุด (latest) ของ product นี้
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $sqlLatest = "SELECT receive_id FROM receive_items WHERE item_id IN ($placeholders) ORDER BY receive_date DESC, receive_id DESC LIMIT 1";
    $stmtLatest = $pdo->prepare($sqlLatest);
    $stmtLatest->execute($item_ids);
    $latest_receive_id = $stmtLatest->fetchColumn();

    if ($latest_receive_id) {
        // 4. อัปเดต receive_qty ของ record ล่าสุดนี้ เป็นค่าจำนวนใหม่
        $stmt = $pdo->prepare("UPDATE receive_items SET receive_qty=? WHERE receive_id=?");
        $stmt->execute([$qty, $latest_receive_id]);

        // 5. อัปเดต receive_qty = 0 ของ receive_items ที่เหลือที่เกี่ยวกับ product นี้ ที่ไม่ใช่ record ล่าสุด
        $sqlOthers = "UPDATE receive_items SET receive_qty=0 WHERE item_id IN ($placeholders) AND receive_id != ?";
        $params = $item_ids;
        $params[] = $latest_receive_id;
        $stmt = $pdo->prepare($sqlOthers);
        $stmt->execute($params);
    }
}

echo json_encode(['success'=>true, 'message'=>'แก้ไขข้อมูลสินค้าสำเร็จและอัพเดทจำนวนรับเข้าแล้ว']);
?>