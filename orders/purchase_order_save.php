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
$total_amount = 0;
foreach($orderItems as $item){
    $sum = floatval($item['qty']) * floatval($item['price']);
    $total_amount += $sum;
}

// เพิ่มใบสั่งซื้อ
try {
    $pdo->beginTransaction();

    $status = 'pending';

    $stmt = $pdo->prepare("INSERT INTO purchase_orders
        (po_number,supplier_id,order_date,total_amount,ordered_by,status,remark)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$po_number, $supplier_id, $order_date, $total_amount, $ordered_by, $status, $remark]);
    $po_id = $pdo->lastInsertId();

    // เพิ่มรายการสินค้า
    $stmt2 = $pdo->prepare("INSERT INTO purchase_order_items
        (po_id, product_id, qty, price_per_unit, total)
        VALUES (?, ?, ?, ?, ?)");
    
    foreach($orderItems as $item){
        $stmt2->execute([
            $po_id,
            $item['product_id'],             // ใช้ product_id แทน name/unit
            $item['qty'],
            $item['price'],
            floatval($item['qty']) * floatval($item['price'])
        ]);
    }

    $pdo->commit();
    echo json_encode(['success'=>true, 'po_id'=>$po_id,'po_number'=>$po_number]);
} catch (PDOException $e){
    $pdo->rollBack();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
