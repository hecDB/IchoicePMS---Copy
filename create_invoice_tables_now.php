<?php
/**
 * สร้างตารางใบกำกับภาษีแบบฝัง SQL ในโค้ด
 * รันไฟล์นี้เพื่อสร้างตารางได้ทันที
 */

require_once 'config/db_connect.php';

echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>สร้างตารางใบกำกับภาษี</title>";
echo "<style>body{font-family:'Prompt',Arial,sans-serif;padding:20px;background:#f5f7fb;line-height:1.6;}";
echo ".box{background:#fff;padding:30px;border-radius:12px;max-width:900px;margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,0.1);}";
echo "h2{color:#385dfa;border-bottom:3px solid #385dfa;padding-bottom:12px;margin-bottom:20px;}";
echo "h3{color:#333;margin-top:25px;border-left:4px solid #385dfa;padding-left:12px;}";
echo ".success{color:#16a34a;padding:12px;background:#e8f5e9;border-left:5px solid #16a34a;margin:12px 0;border-radius:6px;}";
echo ".error{color:#d32f2f;padding:12px;background:#ffebee;border-left:5px solid #d32f2f;margin:12px 0;border-radius:6px;}";
echo ".warning{color:#f57c00;padding:12px;background:#fff3e0;border-left:5px solid #f57c00;margin:12px 0;border-radius:6px;}";
echo ".info{color:#1976d2;padding:12px;background:#e3f2fd;border-left:5px solid #1976d2;margin:12px 0;border-radius:6px;}";
echo ".btn{display:inline-block;padding:14px 28px;background:#385dfa;color:#fff;text-decoration:none;border-radius:8px;margin:20px 10px 10px 0;font-weight:600;transition:0.3s;}";
echo ".btn:hover{background:#2948d8;transform:translateY(-2px);box-shadow:0 4px 12px rgba(56,93,250,0.3);}";
echo ".code{background:#f5f5f5;padding:15px;border-radius:6px;font-family:monospace;font-size:12px;overflow-x:auto;margin:10px 0;}";
echo "ul{margin:10px 0;padding-left:25px;}li{margin:5px 0;}";
echo "</style></head><body><div class='box'>";

echo "<h2>🗄️ สร้างตารางฐานข้อมูลใบกำกับภาษี</h2>";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='info'><strong>Database:</strong> " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "</div>";
    
    echo "<h3>ขั้นตอนที่ 1: ลบตารางเก่า (ถ้ามี)</h3>";
    
    // ลบตารางเก่า
    $pdo->exec("DROP TABLE IF EXISTS tax_invoice_items");
    echo "<div class='success'>✓ ลบตาราง tax_invoice_items (ถ้ามี)</div>";
    
    $pdo->exec("DROP TABLE IF EXISTS tax_invoices");
    echo "<div class='success'>✓ ลบตาราง tax_invoices (ถ้ามี)</div>";
    
    $pdo->exec("DROP VIEW IF EXISTS v_tax_invoices_summary");
    echo "<div class='success'>✓ ลบ VIEW v_tax_invoices_summary (ถ้ามี)</div>";
    
    echo "<h3>ขั้นตอนที่ 2: สร้างตารางใหม่</h3>";
    
    // สร้างตารางหลัก tax_invoices
    $sql_invoices = "CREATE TABLE `tax_invoices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_type` VARCHAR(50) NOT NULL COMMENT 'ประเภทเอกสาร: tax_invoice, payment_voucher, quotation, invoice',
        `inv_no` VARCHAR(100) NOT NULL UNIQUE COMMENT 'เลขที่เอกสาร',
        `sales_tag` VARCHAR(100) DEFAULT NULL COMMENT 'เลขแท็กรายการขายสินค้า',
        `inv_date` DATE NOT NULL COMMENT 'วันที่ออกเอกสาร',
        `platform` VARCHAR(100) DEFAULT NULL COMMENT 'ช่องทางการสั่งซื้อ',
        
        `customer_name` VARCHAR(255) NOT NULL COMMENT 'ชื่อลูกค้า/บริษัท',
        `customer_tax_id` VARCHAR(20) DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี',
        `customer_address` TEXT DEFAULT NULL COMMENT 'ที่อยู่ลูกค้า',
        
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'รวมเงิน',
        `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลด',
        `shipping` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ค่าจัดส่ง',
        `before_vat` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'มูลค่าก่อนภาษี',
        `vat` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ภาษีมูลค่าเพิ่ม',
        `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'รวมทั้งสิ้น',
        `special_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนลดพิเศษ',
        `payable` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงินที่ชำระ',
        `amount_text` VARCHAR(500) DEFAULT NULL COMMENT 'จำนวนเงินเป็นตัวอักษร',
        
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_by` INT DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'active' COMMENT 'สถานะเอกสาร',
        `notes` TEXT DEFAULT NULL,
        
        INDEX `idx_inv_no` (`inv_no`),
        INDEX `idx_doc_type` (`doc_type`),
        INDEX `idx_inv_date` (`inv_date`),
        INDEX `idx_sales_tag` (`sales_tag`),
        INDEX `idx_customer_name` (`customer_name`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บข้อมูลใบกำกับภาษีและเอกสารอื่นๆ'";
    
    $pdo->exec($sql_invoices);
    echo "<div class='success'><strong>✓ สร้างตาราง tax_invoices สำเร็จ!</strong></div>";
    
    // สร้างตารางรายการสินค้า
    $sql_items = "CREATE TABLE `tax_invoice_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_id` INT NOT NULL COMMENT 'อ้างอิงไปยัง tax_invoices.id',
        `seq` INT NOT NULL DEFAULT 1 COMMENT 'ลำดับรายการสินค้า',
        
        `item_name` VARCHAR(500) NOT NULL COMMENT 'รายละเอียดสินค้า/บริการ',
        `qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00 COMMENT 'จำนวน',
        `unit` VARCHAR(50) NOT NULL DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
        `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'ราคาต่อหน่วย',
        `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงิน',
        
        `product_id` INT DEFAULT NULL COMMENT 'อ้างอิงรหัสสินค้า',
        `notes` TEXT DEFAULT NULL,
        
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (`invoice_id`) REFERENCES `tax_invoices`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        INDEX `idx_invoice_id` (`invoice_id`),
        INDEX `idx_seq` (`seq`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บรายละเอียดสินค้าในใบกำกับภาษี'";
    
    $pdo->exec($sql_items);
    echo "<div class='success'><strong>✓ สร้างตาราง tax_invoice_items สำเร็จ!</strong></div>";
    
    // สร้าง VIEW
    $sql_view = "CREATE VIEW `v_tax_invoices_summary` AS
    SELECT 
        ti.id,
        ti.doc_type,
        ti.inv_no,
        ti.sales_tag,
        ti.inv_date,
        ti.platform,
        ti.customer_name,
        ti.customer_tax_id,
        ti.payable,
        ti.status,
        COUNT(tii.id) as item_count,
        ti.created_at,
        ti.updated_at
    FROM tax_invoices ti
    LEFT JOIN tax_invoice_items tii ON ti.id = tii.invoice_id
    GROUP BY ti.id
    ORDER BY ti.created_at DESC";
    
    $pdo->exec($sql_view);
    echo "<div class='success'><strong>✓ สร้าง VIEW v_tax_invoices_summary สำเร็จ!</strong></div>";
    
    echo "<h3>ขั้นตอนที่ 3: ตรวจสอบผลลัพธ์</h3>";
    
    // ตรวจสอบตาราง
    $tables = $pdo->query("SHOW TABLES LIKE 'tax_invoice%'")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='success'>";
    echo "<strong>✅ สร้างตารางสำเร็จทั้งหมด!</strong><br><br>";
    echo "<strong>ตารางที่สร้างเสร็จ:</strong><ul>";
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "<li><strong>$table</strong> - $count แถว</li>";
    }
    echo "</ul></div>";
    
    // แสดงโครงสร้างตารางหลัก
    echo "<h3>โครงสร้างตาราง tax_invoices</h3>";
    echo "<div class='code'>";
    $columns = $pdo->query("SHOW COLUMNS FROM tax_invoices")->fetchAll(PDO::FETCH_ASSOC);
    echo "<strong>Columns ทั้งหมด " . count($columns) . " คอลัมน์:</strong><br><br>";
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#f0f0f0;'><th style='padding:8px;text-align:left;border:1px solid #ddd;'>Field</th><th style='padding:8px;text-align:left;border:1px solid #ddd;'>Type</th><th style='padding:8px;text-align:left;border:1px solid #ddd;'>Null</th><th style='padding:8px;text-align:left;border:1px solid #ddd;'>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td style='padding:6px;border:1px solid #ddd;'><strong>{$col['Field']}</strong></td>";
        echo "<td style='padding:6px;border:1px solid #ddd;'>{$col['Type']}</td>";
        echo "<td style='padding:6px;border:1px solid #ddd;'>{$col['Null']}</td>";
        echo "<td style='padding:6px;border:1px solid #ddd;'>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table></div>";
    
    echo "<div class='info'>";
    echo "<strong>🎉 ระบบพร้อมใช้งานแล้ว!</strong><br>";
    echo "คุณสามารถไปที่หน้าใบกำกับภาษีและทดสอบบันทึกข้อมูลได้เลย";
    echo "</div>";
    
    echo "<a href='reports/tax_invoice.php' class='btn'>➜ ไปยังหน้าใบกำกับภาษี</a>";
    echo "<a href='javascript:location.reload()' class='btn' style='background:#6b7280;'>🔄 รีเฟรชหน้านี้</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ เกิดข้อผิดพลาด:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<strong>แนวทางแก้ไข:</strong><ul>";
    echo "<li>ตรวจสอบว่าเชื่อมต่อ database ได้หรือไม่</li>";
    echo "<li>ตรวจสอบว่า user มีสิทธิ์สร้างตารางหรือไม่</li>";
    echo "<li>ลองรีเฟรชหน้านี้อีกครั้ง</li>";
    echo "</ul></div>";
}

echo "<hr style='margin:30px 0;'>";
echo "<p style='text-align:center;color:#666;'><a href='index.php'>← กลับหน้าแรก</a></p>";
echo "</div></body></html>";
?>
