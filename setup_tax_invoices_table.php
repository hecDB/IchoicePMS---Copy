<?php
/**
 * สคริปต์สำหรับสร้างตารางฐานข้อมูลใบกำกับภาษี
 * รันไฟล์นี้เพื่อสร้าง tax_invoices และ tax_invoice_items tables
 */

require_once 'config/db_connect.php';

echo "<h2>กำลังสร้างตารางฐานข้อมูลใบกำกับภาษี...</h2>";

try {
    // อ่านไฟล์ SQL
    $sql_file = __DIR__ . '/db/create_tax_invoices_table.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("ไม่พบไฟล์ SQL: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // แยก SQL statements (แยกตาม semicolon และ newline)
    $statements = array_filter(
        array_map('trim', preg_split('/;[\r\n]+/', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^\/\*/', $stmt);
        }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            // ตัดคำสั่ง CREATE OR REPLACE VIEW ให้ใช้เฉพาะ CREATE VIEW
            $statement = preg_replace('/CREATE\s+OR\s+REPLACE\s+VIEW/i', 'CREATE VIEW', $statement);
            
            // ลอง DROP VIEW ก่อนถ้าเป็นคำสั่ง CREATE VIEW
            if (preg_match('/CREATE\s+VIEW\s+(\w+)/i', $statement, $matches)) {
                $view_name = $matches[1];
                try {
                    $pdo->exec("DROP VIEW IF EXISTS `$view_name`");
                    echo "<p style='color: orange;'>ลบ VIEW เดิม: $view_name</p>";
                } catch (PDOException $e) {
                    // ไม่ต้องแสดง error ถ้า view ไม่มีอยู่
                }
            }
            
            $pdo->exec($statement);
            $success_count++;
            
            // แสดงประเภทคำสั่งที่สำเร็จ
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $statement, $matches)) {
                echo "<p style='color: green;'>✓ สร้างตาราง: {$matches[1]}</p>";
            } elseif (preg_match('/CREATE\s+VIEW\s+(\w+)/i', $statement, $matches)) {
                echo "<p style='color: green;'>✓ สร้าง VIEW: {$matches[1]}</p>";
            } else {
                echo "<p style='color: green;'>✓ ดำเนินการคำสั่งสำเร็จ</p>";
            }
            
        } catch (PDOException $e) {
            $error_count++;
            echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre style='color: #666; font-size: 11px;'>" . htmlspecialchars(substr($statement, 0, 200)) . "...</pre>";
        }
    }
    
    echo "<hr>";
    echo "<h3>สรุปผลการดำเนินการ</h3>";
    echo "<p><strong>สำเร็จ:</strong> $success_count คำสั่ง</p>";
    echo "<p><strong>ผิดพลาด:</strong> $error_count คำสั่ง</p>";
    
    if ($error_count === 0) {
        echo "<p style='color: green; font-size: 16px;'><strong>✓ สร้างตารางฐานข้อมูลสำเร็จทั้งหมด!</strong></p>";
        echo "<p><a href='reports/tax_invoice.php'>ไปยังหน้าสร้างใบกำกับภาษี</a></p>";
    } else {
        echo "<p style='color: orange;'>⚠ มีข้อผิดพลาดบางส่วน กรุณาตรวจสอบ</p>";
    }
    
    // แสดงตารางที่สร้างแล้ว
    echo "<hr>";
    echo "<h3>ตารางในฐานข้อมูล</h3>";
    
    $tables = $pdo->query("SHOW TABLES LIKE 'tax_invoice%'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            // นับจำนวนแถว
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "<li><strong>$table</strong> - $count แถว</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>ไม่พบตารางที่ขึ้นต้นด้วย 'tax_invoice'</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>เกิดข้อผิดพลาด:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← กลับ</a></p>";
?>
