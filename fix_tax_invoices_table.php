<?php
/**
 * สคริปต์สำหรับแก้ไขและสร้างตารางใบกำกับภาษีใหม่
 * ลบตารางเก่าออกและสร้างใหม่ตาม schema ล่าสุด
 */

require_once 'config/db_connect.php';

echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>แก้ไขตารางฐานข้อมูล</title>";
echo "<style>body{font-family:'Prompt',Arial,sans-serif;padding:20px;background:#f5f7fb;}";
echo ".box{background:#fff;padding:20px;border-radius:10px;max-width:800px;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "h2{color:#385dfa;border-bottom:2px solid #385dfa;padding-bottom:10px;}";
echo ".success{color:green;padding:10px;background:#e8f5e9;border-left:4px solid green;margin:10px 0;}";
echo ".error{color:#d32f2f;padding:10px;background:#ffebee;border-left:4px solid #d32f2f;margin:10px 0;}";
echo ".warning{color:#f57c00;padding:10px;background:#fff3e0;border-left:4px solid #f57c00;margin:10px 0;}";
echo ".btn{display:inline-block;padding:12px 24px;background:#385dfa;color:#fff;text-decoration:none;border-radius:8px;margin-top:20px;}";
echo "</style></head><body><div class='box'>";

echo "<h2>🔧 แก้ไขตารางฐานข้อมูลใบกำกับภาษี</h2>";

try {
    // ตรวจสอบตารางที่มีอยู่
    echo "<h3>ขั้นตอนที่ 1: ตรวจสอบตารางเดิม</h3>";
    
    $tables = $pdo->query("SHOW TABLES LIKE 'tax_invoice%'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<div class='warning'>";
        echo "พบตารางเก่า: <strong>" . implode(', ', $tables) . "</strong><br>";
        echo "จะทำการลบและสร้างใหม่ตาม schema ล่าสุด";
        echo "</div>";
        
        // ลบตารางเก่า
        echo "<h3>ขั้นตอนที่ 2: ลบตารางเก่า</h3>";
        
        // ลบ VIEW ก่อน
        try {
            $pdo->exec("DROP VIEW IF EXISTS v_tax_invoices_summary");
            echo "<div class='success'>✓ ลบ VIEW: v_tax_invoices_summary</div>";
        } catch (PDOException $e) {
            echo "<div class='warning'>⚠ View ไม่มีหรือลบไม่ได้</div>";
        }
        
        // ลบตาราง items ก่อน (เพราะมี foreign key)
        try {
            $pdo->exec("DROP TABLE IF EXISTS tax_invoice_items");
            echo "<div class='success'>✓ ลบตาราง: tax_invoice_items</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>✗ ลบตาราง tax_invoice_items ไม่สำเร็จ: " . $e->getMessage() . "</div>";
        }
        
        // ลบตารางหลัก
        try {
            $pdo->exec("DROP TABLE IF EXISTS tax_invoices");
            echo "<div class='success'>✓ ลบตาราง: tax_invoices</div>";
        } catch (PDOException $e) {
            echo "<div class='error'>✗ ลบตาราง tax_invoices ไม่สำเร็จ: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='warning'>ไม่พบตารางเก่า จะสร้างตารางใหม่</div>";
    }
    
    // สร้างตารางใหม่
    echo "<h3>ขั้นตอนที่ 3: สร้างตารางใหม่</h3>";
    
    $sql_file = __DIR__ . '/db/create_tax_invoices_table.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("ไม่พบไฟล์ SQL: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // แยก SQL statements
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
            // ตัดคำสั่ง CREATE OR REPLACE VIEW
            $statement = preg_replace('/CREATE\s+OR\s+REPLACE\s+VIEW/i', 'CREATE VIEW', $statement);
            
            // ลอง DROP VIEW ก่อน
            if (preg_match('/CREATE\s+VIEW\s+(\w+)/i', $statement, $matches)) {
                $view_name = $matches[1];
                try {
                    $pdo->exec("DROP VIEW IF EXISTS `$view_name`");
                } catch (PDOException $e) {
                    // ไม่สำคัญ
                }
            }
            
            $pdo->exec($statement);
            $success_count++;
            
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $statement, $matches)) {
                echo "<div class='success'>✓ สร้างตาราง: {$matches[1]}</div>";
            } elseif (preg_match('/CREATE\s+VIEW\s+(\w+)/i', $statement, $matches)) {
                echo "<div class='success'>✓ สร้าง VIEW: {$matches[1]}</div>";
            }
            
        } catch (PDOException $e) {
            $error_count++;
            echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    echo "<hr>";
    echo "<h3>สรุปผลการดำเนินการ</h3>";
    echo "<p><strong>สำเร็จ:</strong> $success_count คำสั่ง</p>";
    echo "<p><strong>ผิดพลาด:</strong> $error_count คำสั่ง</p>";
    
    if ($error_count === 0) {
        echo "<div class='success' style='font-size:16px;'>";
        echo "<strong>✓ สร้างตารางฐานข้อมูลสำเร็จทั้งหมด!</strong><br>";
        echo "ตอนนี้คุณสามารถใช้งานระบบใบกำกับภาษีได้แล้ว";
        echo "</div>";
        
        echo "<a href='reports/tax_invoice.php' class='btn'>ไปยังหน้าสร้างใบกำกับภาษี →</a>";
    } else {
        echo "<div class='error'>⚠ มีข้อผิดพลาดบางส่วน กรุณาตรวจสอบข้อความ error ด้านบน</div>";
    }
    
    // แสดงโครงสร้างตารางที่สร้างเสร็จ
    echo "<hr>";
    echo "<h3>โครงสร้างตารางที่สร้างเสร็จ</h3>";
    
    $tables = $pdo->query("SHOW TABLES LIKE 'tax_invoice%'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<ul style='line-height:2;'>";
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            echo "<li><strong>$table</strong> - $count แถว, " . count($columns) . " คอลัมน์</li>";
        }
        echo "</ul>";
        
        // แสดงคอลัมน์ของตารางหลัก
        echo "<h4>คอลัมน์ของตาราง tax_invoices:</h4>";
        echo "<ul style='columns:2;line-height:1.8;font-size:13px;'>";
        $columns = $pdo->query("SHOW COLUMNS FROM tax_invoices")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "<li><code>{$col['Field']}</code> ({$col['Type']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<div class='error'>ไม่พบตารางที่สร้าง กรุณาตรวจสอบไฟล์ SQL</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'><strong>เกิดข้อผิดพลาด:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← กลับ</a> | <a href='reports/tax_invoice.php'>ไปหน้าใบกำกับภาษี →</a></p>";
echo "</div></body></html>";
?>
