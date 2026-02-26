<?php
session_start();
include '../config/db_connect.php';

// ตรวจสอบเลข PO ซ้ำ (สำหรับ AJAX)
if(isset($_POST['check_po'])) {
    $check_po = trim($_POST['check_po']);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?");
    $stmt->execute([$check_po]);
    $exists = $stmt->fetchColumn() > 0;
    echo json_encode(['exists' => $exists]);
    exit;
}

// รับค่าจากแบบฟอร์ม
$po_number   = trim($_POST['po_number'] ?? '');
$supplier_id = $_POST['supplier_id'] ?? null;
$order_date  = $_POST['order_date'] ?? date('Y-m-d');
$remark      = $_POST['remark'] ?? '';
$ordered_by  = $_SESSION['user_id'] ?? 1;
$currency_id = $_POST['currency_id'] ?? 1;
$exchange_rate = floatval($_POST['exchange_rate'] ?? 1.0);
$orderItems  = isset($_POST['orderItems']) ? json_decode($_POST['orderItems'], true) : [];

if(!$po_number || !$supplier_id || empty($orderItems)) {
    echo json_encode(['success'=>false, 'error'=>'ข้อมูลไม่ครบ กรุณากรอกเลขที่ใบสั่งซื้อ']);
    exit;
}

// ตรวจสอบเลข PO ซ้ำ
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE po_number = ?");
$checkStmt->execute([$po_number]);
if($checkStmt->fetchColumn() > 0) {
    echo json_encode(['success'=>false, 'error'=>'เลขที่ใบสั่งซื้อนี้มีอยู่แล้ว กรุณาใช้เลขอื่น']);
    exit;
}

// คำนวณราคารวม
$total_amount_original = 0;
$total_amount_base = 0;

foreach($orderItems as $item){
    $qty = floatval($item['qty']);
    $price_original = floatval($item['price_original'] ?? $item['price']); // backward compatibility
    $price_base = floatval($item['price_base'] ?? ($price_original * $exchange_rate));
    
    $total_amount_original += $qty * $price_original;
    $total_amount_base += $qty * $price_base;
}

// เพิ่มใบสั่งซื้อ
try {
    $pdo->beginTransaction();

    $status = 'pending';

    $stmt = $pdo->prepare("INSERT INTO purchase_orders
        (po_number,supplier_id,order_date,total_amount,ordered_by,status,remark,currency_id,exchange_rate,total_amount_original,total_amount_base)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$po_number, $supplier_id, $order_date, $total_amount_base, $ordered_by, $status, $remark, $currency_id, $exchange_rate, $total_amount_original, $total_amount_base]);
    $po_id = $pdo->lastInsertId();

    // เพิ่มรายการสินค้า
    $stmt2 = $pdo->prepare("INSERT INTO purchase_order_items
        (po_id, product_id, qty, price_per_unit, sale_price, total, currency_id, price_original, price_base)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach($orderItems as $item){
        $qty = floatval($item['qty']);
        $price_original = floatval($item['price_original'] ?? $item['price']);
        $price_base = floatval($item['price_base'] ?? ($price_original * $exchange_rate));
        $total_base = $qty * $price_base;
        
        $stmt2->execute([
            $po_id,
            $item['product_id'],
            $qty,
            $price_base, // เก็บราคาฐานใน price_per_unit เพื่อ backward compatibility
            0.00, // sale_price - default to 0 (will be set during receiving)
            $total_base,
            $item['currency_id'] ?? $currency_id,
            $price_original,
            $price_base
        ]);
    }

    $pdo->commit();
    echo json_encode(['success'=>true, 'po_id'=>$po_id,'po_number'=>$po_number]);
} catch (PDOException $e){
    $pdo->rollBack();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
