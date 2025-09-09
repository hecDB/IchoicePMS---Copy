<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

// รองรับทั้งแบบลบเดี่ยว (id) และหลายรายการ (ids[])
try {
    if(isset($_POST['ids']) && is_array($_POST['ids'])){
        $ids = array_map('intval', $_POST['ids']);
        if(count($ids) === 0){
            echo json_encode(['success' => false, 'msg' => 'ไม่มี ID']);
            exit;
        }
        // ลบ receive_items ตาม receive_id
        $in = str_repeat('?,', count($ids)-1) . '?';
        $stmt = $pdo->prepare("DELETE FROM receive_items WHERE receive_id IN ($in)");
        $ok = $stmt->execute($ids);
        echo json_encode(['success' => $ok]);
        exit;
    } elseif(isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM receive_items WHERE receive_id = ?");
        $ok = $stmt->execute([$id]);
        echo json_encode(['success' => $ok]);
        exit;
    } else {
        echo json_encode(['success' => false, 'msg' => 'ไม่มี ID']);
        exit;
    }
} catch (Exception $e){
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
