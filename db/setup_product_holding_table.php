<?php
// Script สำหรับสร้างตาราง product_holding ใหม่
// รัน: php setup_product_holding_table.php

include '../config/db_connect.php';

try {
    $sql = file_get_contents('create_product_holding_table.sql');
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'สร้างตาราง product_holding สำเร็จ'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
