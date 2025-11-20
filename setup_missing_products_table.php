<?php
require 'config/db_connect.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `missing_products` (
      `missing_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `product_id` int(11) NOT NULL,
      `sku` varchar(50) NOT NULL,
      `barcode` varchar(100) NOT NULL,
      `product_name` varchar(255) NOT NULL,
      `quantity_missing` decimal(10,2) NOT NULL COMMENT 'จำนวนที่สูญหายหรือหาไม่เจอ',
      `remark` text COMMENT 'หมายเหตุเพิ่มเติม',
      `reported_by` int(11) NOT NULL,
      `created_at` timestamp DEFAULT current_timestamp(),
      `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      KEY `idx_created_at` (`created_at`),
      KEY `idx_barcode` (`barcode`),
      KEY `idx_sku` (`sku`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "✓ ตารางถูกสร้างหรือมีอยู่แล้ว\n";
    
} catch (Exception $e) {
    echo "✗ ข้อผิดพลาด: " . $e->getMessage() . "\n";
}
?>
