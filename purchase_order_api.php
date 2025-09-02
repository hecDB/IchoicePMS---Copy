<?php
// purchase_order_api.php
header('Content-Type: application/json');
ini_set('display_errors', 0); // ปิดแสดง error
error_reporting(E_ALL);

require_once 'db_connect.php'; // เชื่อมต่อฐานข้อมูล

try {
    $po_id = intval($_GET['id'] ?? 0);
    if(!$po_id) throw new Exception("PO ID ไม่ถูกต้อง");

    // 1. ดึงข้อมูลใบสั่งซื้อ
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE po_id = ?");
    $stmt->execute([$po_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order) throw new Exception("ไม่พบใบสั่งซื้อ");

    // 2. ดึงรายการสินค้า
    $stmt = $pdo->prepare("SELECT poi.*, p.name AS product_name, p.sku, p.barcode, p.image ,p.unit
                           FROM purchase_order_items poi
                           LEFT JOIN products p ON poi.product_id = p.product_id
                           WHERE poi.po_id = ?");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงข้อมูลผู้สั่งซื้อ (user)
    $stmt = $pdo->prepare("SELECT user_id, name, department FROM users WHERE user_id = ?");
    $stmt->execute([$order['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. ดึงข้อมูลผู้จำหน่าย (supplier)
    $stmt = $pdo->prepare("SELECT supplier_id, name, phone, email, address FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$order['supplier_id']]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. ส่ง JSON
    echo json_encode([
        'order' => $order,
        'items' => $items,
        'user' => $user,
        'supplier' => $supplier
    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
