<?php
require 'db_connect.php';

$id = $_POST['id'] ?? null; // product_id

if($id){
    try {
        $pdo->beginTransaction();

        // 1. หา item_id ที่ถูกอ้างถึงสินค้า
        $stmtItems = $pdo->prepare("SELECT item_id FROM purchase_order_items WHERE product_id = ?");
        $stmtItems->execute([$id]);
        $item_ids = $stmtItems->fetchAll(PDO::FETCH_COLUMN);

        if(!empty($item_ids)){
            // 2. ลบใน receive_items where item_id in (...)
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $stmtReceive = $pdo->prepare("DELETE FROM receive_items WHERE item_id IN ($placeholders)");
            $stmtReceive->execute($item_ids);

            // 3. ลบใน purchase_order_items
            $stmtPOI = $pdo->prepare("DELETE FROM purchase_order_items WHERE product_id = ?");
            $stmtPOI->execute([$id]);
        } else {
            // ถ้าไม่มี item_id (ตำแหน่งสินค้าไม่เคยถูกสั่งซื้อ) ข้ามขั้นตอนนี้ได้
            $stmtPOI = $pdo->prepare("DELETE FROM purchase_order_items WHERE product_id = ?");
            $stmtPOI->execute([$id]);
        }

        // 4. ลบใน products
        $stmtPro = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        $success = $stmtPro->execute([$id]);

        $pdo->commit();

        echo json_encode(['success' => $success]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ product id']);
}
?>