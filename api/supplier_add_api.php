<?php
header('Content-Type: application/json');
include '../config/db_connect.php';

$name = trim($_POST['name']??'');
$phone = trim($_POST['phone']??'');
$email = trim($_POST['email']??'');
$address = trim($_POST['address']??'');

if(!$name) {
  echo json_encode(['success'=>false,'error'=>'กรุณากรอกชื่อผู้ขาย']);
  exit;
}

try {
  $sql = "INSERT INTO suppliers (name, phone, email, address) VALUES (?, ?, ?, ?)";
  $pdo->prepare($sql)->execute([$name, $phone, $email, $address]);
  $id = $pdo->lastInsertId();
  echo json_encode(['success'=>true, 'supplier_id'=>$id]);
} catch(Exception $ex) {
  echo json_encode(['success'=>false,'error'=>'ไม่สามารถเพิ่มข้อมูลได้: ' . $ex->getMessage()]);
}