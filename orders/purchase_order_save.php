<?php
session_start();
include '../config/db_connect.php';

// รับค่าจากแบบฟอร์ม
$supplier_id = $_POST['supplier_id'] ?? null;
$order_date  = $_POST['order_date'] ?? date('Y-m-d');
$remark      = $_POST['remark'] ?? '';
$ordered_by  = $_SESSION['user_id'] ?? 1;
$orderItems  = isset($_POST['orderItems']) ? json_decode($_POST['orderItems'], true) : [];

if(!$supplier_id || empty($orderItems)) {
    echo json_encode(['success'=>false, 'error'=>'ข้อมูลไม่ครบ']);
    exit;
}

// คำนวณราคารวม
$total_amount = 0;
foreach($orderItems as $item){
    $sum = floatval($item['qty']) * floatval($item['price']);
    $total_amount += $sum;
}

// สร้างเลข PO
function generateAutoPONumber($pdo){
    $prefix = 'PO'.date('Ym');
    $stmt = $pdo->prepare("SELECT po_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY po_number DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $last = $stmt->fetchColumn();
    if($last){
        $num = intval(substr($last, strlen($prefix)));
        return $prefix . str_pad($num+1, 3, '0', STR_PAD_LEFT);
    } else {
        return $prefix . '001';
    }
}

// เพิ่มใบสั่งซื้อ
try {
    $pdo->beginTransaction();

    $po_number = generateAutoPONumber($pdo);
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
