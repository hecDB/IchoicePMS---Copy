<?php
require '../config/db_connect.php';

header('Content-Type: application/json');

$ids_to_delete = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    // Handle multiple deletions
    $ids_to_delete = $_POST['ids'];
} elseif (isset($_POST['id'])) {
    // Handle single deletion
    $ids_to_delete[] = $_POST['id'];
}

if (empty($ids_to_delete)) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID สินค้าที่ต้องการลบ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));

    // 1. หา item_id ทั้งหมดที่เกี่ยวข้องกับ product_ids ที่จะลบ
    $stmtItems = $pdo->prepare("SELECT item_id FROM purchase_order_items WHERE product_id IN ($placeholders)");
    $stmtItems->execute($ids_to_delete);
    $item_ids = $stmtItems->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($item_ids)) {
        $item_placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        // 2. ลบข้อมูลใน receive_items ที่เกี่ยวข้อง
        $stmtReceive = $pdo->prepare("DELETE FROM receive_items WHERE item_id IN ($item_placeholders)");
        $stmtReceive->execute($item_ids);
    }

    // 3. ลบข้อมูลใน purchase_order_items
    $stmtPOI = $pdo->prepare("DELETE FROM purchase_order_items WHERE product_id IN ($placeholders)");
    $stmtPOI->execute($ids_to_delete);

    // 4. ลบสินค้าในตาราง products
    $stmtPro = $pdo->prepare("DELETE FROM products WHERE product_id IN ($placeholders)");
    $stmtPro->execute($ids_to_delete);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>