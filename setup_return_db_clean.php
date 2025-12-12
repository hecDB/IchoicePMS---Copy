<?php
/**
 * ลบและสร้างตารางใหม่อย่างปลอดภัย
 */

require 'config/db_connect.php';

try {
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif; background-color: #f8fafc;'>";
    echo "<div style='max-width: 800px; margin: 0 auto;'>";
    
    // ลบตาราง returned_items ถ้ามี
    $pdo->exec("DROP TABLE IF EXISTS `returned_items`");
    echo "✓ ลบตาราง returned_items (ถ้ามี)<br>";
    
    // ลบตาราง return_reasons ถ้ามี
    $pdo->exec("DROP TABLE IF EXISTS `return_reasons`");
    echo "✓ ลบตาราง return_reasons (ถ้ามี)<br>";
    
    echo "<hr style='margin: 1rem 0;'>";
    
    // สร้างตาราง return_reasons (เหตุผลการตีกลับ)
    $sql_reasons = "CREATE TABLE IF NOT EXISTS `return_reasons` (
      `reason_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `reason_code` varchar(20) NOT NULL UNIQUE,
      `reason_name` varchar(255) NOT NULL,
      `is_returnable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=สามารถคืนสต็อก, 0=ไม่สามารถคืน',
      `category` varchar(50) NOT NULL COMMENT 'returnable, non-returnable',
      `description` text COMMENT 'รายละเอียดเหตุผล',
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp DEFAULT current_timestamp(),
      KEY `idx_is_active` (`is_active`),
      KEY `idx_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql_reasons);
    echo "✓ สร้างตาราง return_reasons สำเร็จ<br>";
    
    // สร้างตาราง returned_items (สินค้าตีกลับ)
    $sql_items = "CREATE TABLE IF NOT EXISTS `returned_items` (
      `return_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `return_code` varchar(50) NOT NULL UNIQUE COMMENT 'เลขที่สินค้าตีกลับ เช่น RET-2025-001',
      `po_id` int(11) COMMENT 'PO ที่เกี่ยวข้อง (NULL ถ้าตีกลับจาก sales order)',
      `po_number` varchar(50) COMMENT 'เลขที่ PO',
      `so_id` int(11) COMMENT 'Sales Order ID (ถ้าตีกลับจาก sales)',
      `issue_tag` varchar(100) COMMENT 'เลขแท็ค',
      `item_id` int(11) NOT NULL COMMENT 'item_id จาก purchase_order_items หรือ issue_items',
      `product_id` int(11) NOT NULL COMMENT 'product_id',
      `product_name` varchar(255) NOT NULL,
      `sku` varchar(50) NOT NULL,
      `barcode` varchar(100),
      `original_qty` decimal(10,2) NOT NULL COMMENT 'จำนวนออกเดิม',
      `return_qty` decimal(10,2) NOT NULL COMMENT 'จำนวนที่ตีกลับ',
      `reason_id` int(11) NOT NULL COMMENT 'เหตุผลการตีกลับ',
      `reason_name` varchar(255) NOT NULL,
      `is_returnable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=สามารถคืนสต็อก, 0=ไม่สามารถคืน',
      `return_status` varchar(50) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, completed',
      `return_from_sales` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=ตีกลับจาก sales, 0=ตีกลับจาก purchase',
      `image` longblob COMMENT 'รูปภาพของสินค้า',
      `notes` longtext COMMENT 'หมายเหตุต่างๆ',
      `expiry_date` date COMMENT 'วันหมดอายุ (หากมี)',
      `condition_detail` varchar(255) COMMENT 'รายละเอียดสภาพสินค้า',
      `location_id` int(11) COMMENT 'location_id ที่คืนสินค้า (ถ้าคืนได้)',
      `approved_by` int(11) COMMENT 'ผู้อนุมัติ',
      `approved_at` timestamp NULL COMMENT 'วันเวลาอนุมัติ',
      `created_by` int(11) NOT NULL COMMENT 'ผู้บันทึก',
      `created_at` timestamp DEFAULT current_timestamp(),
      `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      KEY `idx_return_code` (`return_code`),
      KEY `idx_po_id` (`po_id`),
      KEY `idx_so_id` (`so_id`),
      KEY `idx_product_id` (`product_id`),
      KEY `idx_reason_id` (`reason_id`),
      KEY `idx_created_by` (`created_by`),
      KEY `idx_approved_by` (`approved_by`),
      KEY `idx_return_status` (`return_status`),
      KEY `idx_is_returnable` (`is_returnable`),
      KEY `idx_return_from_sales` (`return_from_sales`),
      KEY `idx_created_at` (`created_at`),
      FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL,
      FOREIGN KEY (`reason_id`) REFERENCES `return_reasons` (`reason_id`) ON DELETE RESTRICT,
      FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
      FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql_items);
    echo "✓ สร้างตาราง returned_items สำเร็จ<br>";
    
    echo "<hr style='margin: 1rem 0;'>";
    
    // Insert default return reasons
    $insert_reasons = "INSERT INTO `return_reasons` 
    (`reason_code`, `reason_name`, `is_returnable`, `category`, `description`, `is_active`) VALUES 
    ('001', 'จัดส่งไม่สำเร็จ', 1, 'returnable', 'สินค้าจัดส่งไม่สำเร็จ - สามารถคืนเข้าสต็อก', 1),
    ('002', 'ยกเลิกคำสั่งซื้อ', 1, 'returnable', 'ลูกค้าขอยกเลิกคำสั่งซื้อ - สามารถคืนเข้าสต็อก', 1),
    ('003', 'ชำรุด/เสียหาย', 0, 'non-returnable', 'สินค้าชำรุดหรือเสียหาย - ไม่สามารถคืนเข้าสต็อก', 1),
    ('004', 'ลูกค้าปฏิเสธรับสินค้า', 1, 'returnable', 'ลูกค้าปฏิเสธการรับสินค้า - สามารถคืนเข้าสต็อก', 1),
    ('005', 'ส่งผิด', 1, 'returnable', 'ส่งสินค้าผิดรายการ - สามารถคืนเข้าสต็อก', 1),
    ('006', 'สินค้าปลอม', 0, 'non-returnable', 'สินค้าปลอมหรือหลอก - ไม่สามารถคืนเข้าสต็อก', 1),
    ('007', 'อื่นๆ', 0, 'non-returnable', 'เหตุผลอื่นๆ - ไม่สามารถคืนเข้าสต็อก', 1)";
    
    $pdo->exec($insert_reasons);
    echo "✓ เพิ่มเหตุผลการตีกลับ 7 รายการ สำเร็จ<br>";
    
    echo "<div style='background-color: #c8e6c9; padding: 1rem; border-radius: 4px; margin-top: 2rem;'>";
    echo "<h3 style='color: #2e7d32; margin: 0;'>✓ ตั้งค่าฐานข้อมูลสินค้าตีกลับสำเร็จ!</h3>";
    echo "<p style='color: #2e7d32; margin-top: 0.5rem; margin-bottom: 0;'>สามารถใช้งานระบบได้แล้ว</p>";
    echo "</div>";
    
    echo "<a href='returns/return_items.php' style='display: inline-block; margin-top: 2rem; padding: 0.75rem 1.5rem; background-color: #667eea; color: white; text-decoration: none; border-radius: 6px;'>ไปยังหน้าสินค้าตีกลับ</a>";
    
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif;'>";
    echo "<div style='max-width: 800px; margin: 0 auto;'>";
    echo "<div style='background-color: #ffcdd2; padding: 1rem; border-radius: 4px;'>";
    echo "<h2 style='color: #c62828; margin: 0;'>✗ เกิดข้อผิดพลาด</h2>";
    echo "<p style='color: #c62828; margin-top: 0.5rem; margin-bottom: 0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}
?>
