<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if(!isset($_POST['id'])){
    echo json_encode(['success' => false, 'msg' => 'ไม่มี ID']);
    exit;
}

$id = intval($_POST['id']);

try {
    // ก่อนลบ ตรวจสอบว่าสินค้ามีอยู่หรือไม่
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$product){
        echo json_encode(['success' => false, 'msg' => 'ไม่พบสินค้า']);
        exit;
    }

    // ลบข้อมูลใน receive_items ที่อ้างอิงถึงสินค้า (ถ้ามี FK อาจจะติด constraint)
    $pdo->prepare("DELETE ri FROM receive_items ri 
                   JOIN purchase_order_items poi ON ri.item_id = poi.item_id 
                   WHERE poi.product_id = :id")->execute([':id' => $id]);

    // ลบข้อมูลใน purchase_order_items ที่อ้างถึงสินค้า
    $pdo->prepare("DELETE FROM purchase_order_items WHERE product_id = :id")->execute([':id' => $id]);

    // ลบสินค้าในตาราง products
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :id");
    $ok = $stmt->execute([':id' => $id]);

    if($ok){
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'ลบไม่สำเร็จ']);
    }
} catch (Exception $e){
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
