<?php
session_start();
require './config/db_connect.php';

try {
    // ตรวจสอบว่าคอลัมน์ is_active มีอยู่หรือไม่
    $check_sql = "SHOW COLUMNS FROM products LIKE 'is_active'";
    $check_stmt = $pdo->query($check_sql);
    
    if ($check_stmt->rowCount() === 0) {
        // เพิ่มคอลัมน์ is_active
        $add_column_sql = "ALTER TABLE products ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER remark_split";
        $pdo->exec($add_column_sql);
        echo "✓ เพิ่มคอลัมน์ is_active สำเร็จ\n";
    } else {
        echo "✓ คอลัมน์ is_active มีอยู่แล้ว\n";
    }
    
    // ตั้งค่า is_active = 1 สำหรับสินค้าทั้งหมด (ถ้ายังไม่ได้ตั้ง)
    $update_sql = "UPDATE products SET is_active = 1 WHERE is_active IS NULL";
    $pdo->exec($update_sql);
    echo "✓ ตั้งค่าสถานะเริ่มต้นสำเร็จ\n";
    
    echo "\n✓ ทั้งหมดเรียบร้อย!";
} catch (Exception $e) {
    echo "✗ เกิดข้อผิดพลาด: " . $e->getMessage();
}
