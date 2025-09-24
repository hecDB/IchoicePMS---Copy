<?php
require '../config/db_connect.php'; // เชื่อมฐานข้อมูล
header('Content-Type: application/json');

try {
    $po_id = $_POST['po_id'];
    $po_number = $_POST['po_number'];
    $order_date = $_POST['order_date'];
    $remark = $_POST['remark'] ?? '';

    // ข้อมูลรายการ
    $item_ids = $_POST['item_id'] ?? [];        // สำหรับรายการเดิม ถ้าเป็นใหม่ให้เป็น ""
    $product_ids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['price_per_unit'] ?? [];

    // 1️⃣ อัปเดตตาราง purchase_orders
    $stmt = $pdo->prepare("UPDATE purchase_orders SET po_number=?, order_date=?, remark=? WHERE po_id=?");
    $stmt->execute([$po_number, $order_date, $remark, $po_id]);

    // 2️⃣ ดึง item_id เดิมทั้งหมดใน PO
    $stmt = $pdo->prepare("SELECT item_id FROM purchase_order_items WHERE po_id=?");
    $stmt->execute([$po_id]);
    $existingItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3️⃣ จัดการรายการ
            $submittedItemIds = [];

            // เตรียม statement สำหรับเพิ่มรายการใหม่
            $stmtInsert = $pdo->prepare("
                INSERT INTO purchase_order_items (po_id, product_id, qty, price_per_unit, total)
                VALUES (?, ?, ?, ?, ?)
            ");

            // เตรียม statement สำหรับอัปเดตรายการเดิม
            $stmtUpdate = $pdo->prepare("
                UPDATE purchase_order_items
                SET product_id = ?, 
                    qty = ?, 
                    price_per_unit = ?, 
                    total = ?
                WHERE item_id = ?
            ");

            for ($i = 0; $i < count($product_ids); $i++) {
                $item_id = $item_ids[$i] ?? "";
                $product_id = $product_ids[$i];
                $qty = $qtys[$i];
                $price = $prices[$i];
                $total = $qty * $price;

                if (empty($product_id)) continue; // ข้ามถ้าไม่มีสินค้า

                if (!empty($item_id)) {
                    // แก้ไขรายการเดิม
                    $stmtUpdate->execute([$product_id, $qty, $price, $total, $item_id]);
                    $submittedItemIds[] = $item_id;
                } else {
                    // เพิ่มรายการใหม่
                    $stmtInsert->execute([$po_id, $product_id, $qty, $price, $total]);
                }
            }


    // 4️⃣ ลบรายการที่ถูกลบ (item_id เดิมที่ไม่อยู่ใน form)
    if (!empty($existingItems)) {
        $toDelete = array_diff($existingItems, $submittedItemIds);
        if (!empty($toDelete)) {
            $in  = str_repeat('?,', count($toDelete) - 1) . '?';
            $stmtDelete = $pdo->prepare("DELETE FROM purchase_order_items WHERE item_id IN ($in)");
            $stmtDelete->execute($toDelete);
        }
    }

    echo json_encode(['success'=>true]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
