<?php
// purchase_order_api.php
header('Content-Type: application/json');
ini_set('display_errors', 0); // ปิดแสดง error
error_reporting(E_ALL);

require_once '../config/db_connect.php'; // เชื่อมต่อฐานข้อมูล

try {
    $po_id = intval($_GET['id'] ?? 0);
    if(!$po_id) throw new Exception("PO ID ไม่ถูกต้อง");

    // 1. ดึงข้อมูลใบสั่งซื้อพร้อมสกุลเงิน
    $stmt = $pdo->prepare("SELECT po.*, c.code as currency_code, c.name as currency_name, c.symbol as currency_symbol
                           FROM purchase_orders po
                           LEFT JOIN currencies c ON po.currency_id = c.currency_id
                           WHERE po.po_id = ?");
    $stmt->execute([$po_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order) throw new Exception("ไม่พบใบสั่งซื้อ");

    // 2. ดึงรายการสินค้าพร้อมข้อมูลสกุลเงิน
    $stmt = $pdo->prepare("SELECT poi.*, p.name AS product_name, p.sku, p.barcode, p.image, p.unit,
                           c.code as item_currency_code, c.symbol as item_currency_symbol
                           FROM purchase_order_items poi
                           LEFT JOIN products p ON poi.product_id = p.product_id
                           LEFT JOIN currencies c ON poi.currency_id = c.currency_id
                           WHERE poi.po_id = ?");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงข้อมูลผู้สั่งซื้อ (user)
    $stmt = $pdo->prepare("SELECT user_id, name, department FROM users WHERE user_id = ?");
    $stmt->execute([$order['ordered_by'] ?? $order['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user) $user = ['name' => 'ไม่ระบุ', 'department' => ''];

    // 4. ดึงข้อมูลผู้จำหน่าย (supplier)
    $stmt = $pdo->prepare("SELECT supplier_id, name, phone, email, address FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$order['supplier_id']]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$supplier) $supplier = ['name' => 'ไม่ระบุ', 'phone' => '', 'email' => '', 'address' => ''];

    // 5. ดึงรายการสกุลเงินทั้งหมดสำหรับการแก้ไข
    $stmt = $pdo->prepare("SELECT currency_id, code, name, symbol, exchange_rate FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code ASC");
    $stmt->execute();
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. ส่ง JSON
    echo json_encode([
        'order' => $order,
        'items' => $items,
        'user' => $user,
        'supplier' => $supplier,
        'currencies' => $currencies
    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
