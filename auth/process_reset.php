<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();
require_once '../config/db_connect.php';
header('Content-Type: application/json');

if($_SESSION['user_role']!=='admin'){
    echo json_encode(['success'=>false,'message'=>'สิทธิ์ไม่ถูกต้อง']); exit;
}

if(!isset($_POST['id'])){
    echo json_encode(['success'=>false,'message'=>'ไม่พบ ID']); exit;
}

$id = intval($_POST['id']);

// ดึงข้อมูล request
$stmt = $pdo->prepare("SELECT * FROM password_reset_requests WHERE id=? AND status='pending'");
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$req){
    echo json_encode(['success'=>false,'message'=>'คำขอไม่ถูกต้อง']); exit;
}

// สุ่มรหัสผ่านใหม่
$new_password = bin2hex(random_bytes(4)); // 8 ตัวอักษร

// อัปเดตรหัสผ่านใน users table
$update = $pdo->prepare("UPDATE users SET password=?, require_password_change=1  WHERE email=?");
$update->execute([password_hash($new_password,PASSWORD_DEFAULT), $req['user_email']]);

// อัปเดตสถานะ request
$pdo->prepare("UPDATE password_reset_requests SET status='processed' WHERE id=?")->execute([$id]);

echo json_encode(['success'=>true,'new_password'=>$new_password]);
exit;
