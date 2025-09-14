<?php
// purchase_order_delete.php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // เชื่อมต่อฐานข้อมูล

// อ่านข้อมูล JSON ที่ส่งมา
$data = json_decode(file_get_contents('php://input'), true);
$po_id = $data['po_id'] ?? 0;

if (!$po_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้ระบุ ID ของใบสั่งซื้อ']);
    exit;
}

$pdo->beginTransaction();

try {
    // 1. ดึงรายการ item_id ทั้งหมดที่อยู่ในใบสั่งซื้อนี้
    $stmt = $pdo->prepare("SELECT item_id FROM purchase_order_items WHERE po_id = ?");
    $stmt->execute([$po_id]);
    $item_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($item_ids)) {
        // 2. ลบข้อมูลการรับสินค้า (receive_items) ที่เกี่ยวข้องกับรายการสินค้าในใบสั่งซื้อนี้ก่อน
        // สร้าง placeholders สำหรับ IN clause
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM receive_items WHERE item_id IN ($placeholders)");
        $stmt->execute($item_ids);

        // 3. ลบรายการสินค้าในใบสั่งซื้อ (purchase_order_items)
        $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = ?");
        $stmt->execute([$po_id]);
    }

    // 4. ลบใบสั่งซื้อหลัก (purchase_orders)
    $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE po_id = ?");
    $stmt->execute([$po_id]);

    // ตรวจสอบว่ามีการลบเกิดขึ้นจริงหรือไม่
    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        // ถ้าไม่พบ PO ID ที่จะลบ
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'ไม่พบใบสั่งซื้อที่ต้องการลบ']);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    // ใน production ควร log error แทนการแสดงผล
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()]);
}
