<?php
/**
 * ตรวจสอบและแก้ไขตาราง tax_invoices
 * - ตรวจสอบว่าตารางมีอยู่หรือไม่
 * - ตรวจสอบว่ามีคอลัมน์ doc_type หรือไม่
 * - แก้ไข UNIQUE constraint
 */

// เชื่อมต่อฐานข้อมูล
$host = 'localhost';
$port = '3306';
$db   = 'ichoice_';
$user = 'root';
$pass = '';

// สร้าง mysqli connection สำหรับ script นี้
$conn = new mysqli($host, $user, $pass, $db, $port);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("
    <!DOCTYPE html>
    <html><head><meta charset='UTF-8'><title>เชื่อมต่อฐานข้อมูลไม่สำเร็จ</title>
    <style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}
    .error{color:#dc2626;background:#fee2e2;padding:20px;border-radius:8px;border:2px solid #dc2626;}
    h2{margin-top:0;}</style></head><body>
    <div class='error'>
        <h2>❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้</h2>
        <p><strong>Error:</strong> " . $conn->connect_error . "</p>
        <p>กรุณาตรวจสอบ:</p>
        <ul>
            <li>MySQL Server ทำงานอยู่หรือไม่</li>
            <li>ชื่อฐานข้อมูล: <code>{$db}</code></li>
            <li>Username: <code>{$user}</code></li>
            <li>Password ถูกต้องหรือไม่</li>
            <li>Port: <code>{$port}</code></li>
        </ul>
    </div>
    </body></html>
    ");
}

// ตั้งค่า charset
$conn->set_charset('utf8mb4');

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'><title>ตรวจสอบและแก้ไขตาราง tax_invoices</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:1200px;margin:20px auto;padding:20px;}";
echo ".success{color:#16a34a;background:#dcfce7;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".error{color:#dc2626;background:#fee2e2;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".info{color:#2563eb;background:#dbeafe;padding:10px;border-radius:5px;margin:10px 0;}";
echo ".warning{color:#ea580c;background:#fed7aa;padding:10px;border-radius:5px;margin:10px 0;}";
echo "pre{background:#f3f4f6;padding:10px;border-radius:5px;overflow-x:auto;}</style></head><body>";

echo "<h1>🔧 ตรวจสอบและแก้ไขตาราง tax_invoices</h1>";
echo "<p>วันที่: " . date('Y-m-d H:i:s') . "</p>";
echo "<div class='success'>✓ เชื่อมต่อฐานข้อมูล <strong>{$db}</strong> สำเร็จ</div>";
echo "<hr>";

try {
    // 1. ตรวจสอบว่าตารางมีอยู่หรือไม่
    echo "<h2>1. ตรวจสอบตาราง tax_invoices</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'tax_invoices'");
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ พบตาราง tax_invoices</div>";
    } else {
        echo "<div class='error'>✗ ไม่พบตาราง tax_invoices</div>";
        echo "<div class='info'>กำลังสร้างตาราง...</div>";
        
        $createTable = "CREATE TABLE IF NOT EXISTS tax_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_type VARCHAR(50) NOT NULL DEFAULT 'tax_invoice',
            inv_no VARCHAR(50) NOT NULL,
            sales_tag VARCHAR(100) DEFAULT NULL,
            inv_date DATE NOT NULL,
            platform VARCHAR(50) DEFAULT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_tax_id VARCHAR(20) DEFAULT NULL,
            customer_address TEXT DEFAULT NULL,
            subtotal DECIMAL(15,2) DEFAULT 0,
            discount DECIMAL(15,2) DEFAULT 0,
            shipping DECIMAL(15,2) DEFAULT 0,
            before_vat DECIMAL(15,2) DEFAULT 0,
            vat DECIMAL(15,2) DEFAULT 0,
            grand_total DECIMAL(15,2) DEFAULT 0,
            special_discount DECIMAL(15,2) DEFAULT 0,
            payable DECIMAL(15,2) DEFAULT 0,
            amount_text TEXT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_inv_no_doc_type (inv_no, doc_type),
            INDEX idx_doc_type (doc_type),
            INDEX idx_inv_date (inv_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($createTable)) {
            echo "<div class='success'>✓ สร้างตาราง tax_invoices สำเร็จ</div>";
        } else {
            throw new Exception("ไม่สามารถสร้างตาราง: " . $conn->error);
        }
    }
    
    // 2. ตรวจสอบโครงสร้างตาราง
    echo "<h2>2. ตรวจสอบโครงสร้างตาราง</h2>";
    $result = $conn->query("DESCRIBE tax_invoices");
    
    echo "<pre>";
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
    }
    echo "</pre>";
    
    // 3. ตรวจสอบคอลัมน์ doc_type
    echo "<h2>3. ตรวจสอบคอลัมน์ doc_type</h2>";
    if (in_array('doc_type', $columns)) {
        echo "<div class='success'>✓ พบคอลัมน์ doc_type</div>";
    } else {
        echo "<div class='error'>✗ ไม่พบคอลัมน์ doc_type</div>";
        echo "<div class='info'>กำลังเพิ่มคอลัมน์...</div>";
        
        $addColumn = "ALTER TABLE tax_invoices 
                     ADD COLUMN doc_type VARCHAR(50) NOT NULL DEFAULT 'tax_invoice' AFTER id";
        
        if ($conn->query($addColumn)) {
            echo "<div class='success'>✓ เพิ่มคอลัมน์ doc_type สำเร็จ</div>";
        } else {
            echo "<div class='warning'>⚠ อาจมีคอลัมน์อยู่แล้ว: " . $conn->error . "</div>";
        }
    }
    
    // 4. ตรวจสอบ UNIQUE constraints
    echo "<h2>4. ตรวจสอบ UNIQUE Constraints</h2>";
    $result = $conn->query("SHOW INDEX FROM tax_invoices WHERE Key_name != 'PRIMARY'");
    
    echo "<pre>";
    $hasCorrectUnique = false;
    $indexesToDrop = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "Index: {$row['Key_name']} | Column: {$row['Column_name']} | Non_unique: {$row['Non_unique']}\n";
            
            // ตรวจสอบว่ามี unique constraint ที่ถูกต้อง
            if ($row['Key_name'] === 'unique_inv_no_doc_type' && $row['Non_unique'] == 0) {
                $hasCorrectUnique = true;
            }
            
            // เก็บ index ที่เกี่ยวกับ inv_no เพื่อลบ (ยกเว้น unique_inv_no_doc_type)
            if ($row['Column_name'] === 'inv_no' && 
                $row['Key_name'] !== 'unique_inv_no_doc_type' && 
                $row['Non_unique'] == 0) {
                $indexesToDrop[] = $row['Key_name'];
            }
        }
    }
    echo "</pre>";
    
    // 5. แก้ไข UNIQUE constraints
    echo "<h2>5. แก้ไข UNIQUE Constraints</h2>";
    
    // ลบ index เก่าที่ไม่ถูกต้อง
    foreach (array_unique($indexesToDrop) as $indexName) {
        echo "<div class='info'>กำลังลบ index: {$indexName}</div>";
        try {
            $conn->query("ALTER TABLE tax_invoices DROP INDEX `{$indexName}`");
            echo "<div class='success'>✓ ลบ index '{$indexName}' สำเร็จ</div>";
        } catch (Exception $e) {
            echo "<div class='warning'>⚠ ไม่สามารถลบ index: " . $e->getMessage() . "</div>";
        }
    }
    
    // สร้าง unique constraint ที่ถูกต้อง
    if (!$hasCorrectUnique) {
        echo "<div class='info'>กำลังสร้าง UNIQUE constraint (inv_no, doc_type)...</div>";
        
        try {
            $conn->query("ALTER TABLE tax_invoices 
                         ADD UNIQUE KEY unique_inv_no_doc_type (inv_no, doc_type)");
            echo "<div class='success'>✓ สร้าง UNIQUE constraint สำเร็จ</div>";
        } catch (Exception $e) {
            echo "<div class='warning'>⚠ " . $e->getMessage() . "</div>";
            
            // ถ้ามี duplicate data ให้แสดงรายการ
            $duplicates = $conn->query("
                SELECT inv_no, doc_type, COUNT(*) as count 
                FROM tax_invoices 
                GROUP BY inv_no, doc_type 
                HAVING count > 1
            ");
            
            if ($duplicates && $duplicates->num_rows > 0) {
                echo "<div class='error'>พบข้อมูลซ้ำ:</div><pre>";
                while ($row = $duplicates->fetch_assoc()) {
                    echo "inv_no: {$row['inv_no']}, doc_type: {$row['doc_type']}, count: {$row['count']}\n";
                }
                echo "</pre>";
                echo "<div class='info'>กรุณาลบหรือแก้ไขข้อมูลซ้ำก่อนรัน script นี้อีกครั้ง</div>";
            }
        }
    } else {
        echo "<div class='success'>✓ UNIQUE constraint ถูกต้องแล้ว</div>";
    }
    
    // 6. ตรวจสอบตาราง tax_invoice_items
    echo "<h2>6. ตรวจสอบตาราง tax_invoice_items</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'tax_invoice_items'");
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ พบตาราง tax_invoice_items</div>";
    } else {
        echo "<div class='error'>✗ ไม่พบตาราง tax_invoice_items</div>";
        echo "<div class='info'>กำลังสร้างตาราง...</div>";
        
        $createItemsTable = "CREATE TABLE IF NOT EXISTS tax_invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            seq INT NOT NULL DEFAULT 1,
            item_name VARCHAR(255) NOT NULL,
            qty DECIMAL(15,2) NOT NULL DEFAULT 1,
            unit VARCHAR(50) DEFAULT NULL,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES tax_invoices(id) ON DELETE CASCADE,
            INDEX idx_invoice_id (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($createItemsTable)) {
            echo "<div class='success'>✓ สร้างตาราง tax_invoice_items สำเร็จ</div>";
        } else {
            throw new Exception("ไม่สามารถสร้างตาราง items: " . $conn->error);
        }
    }
    
    // 7. สรุปผล
    echo "<h2>7. สรุปผล</h2>";
    
    // นับจำนวนข้อมูล
    $count = $conn->query("SELECT COUNT(*) as total FROM tax_invoices")->fetch_assoc();
    echo "<div class='info'>จำนวนเอกสารทั้งหมด: {$count['total']} รายการ</div>";
    
    // นับตามประเภท
    $byType = $conn->query("SELECT doc_type, COUNT(*) as count FROM tax_invoices GROUP BY doc_type");
    if ($byType && $byType->num_rows > 0) {
        echo "<div class='info'>รายการตามประเภท:<br>";
        while ($row = $byType->fetch_assoc()) {
            echo "- {$row['doc_type']}: {$row['count']} รายการ<br>";
        }
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<div class='success'><h3>✓ เสร็จสมบูรณ์!</h3>";
    echo "<p>ตอนนี้คุณสามารถใช้งานระบบสร้างใบกำกับภาษีได้แล้ว</p>";
    echo "<p>เลขที่จะถูกสร้างในรูปแบบ: YYYYMM-NNN (เช่น 202604-001)</p>";
    echo "<p>แต่ละประเภทเอกสารจะมีเลขแยกกัน (สามารถซ้ำได้ระหว่างประเภท)</p></div>";
    
    echo "<p><a href='reports/tax_invoice.php' style='display:inline-block;padding:10px 20px;background:#385dfa;color:#fff;text-decoration:none;border-radius:8px;'>ไปยังหน้าสร้างเอกสาร</a></p>";
    
} catch (Exception $e) {
    echo "<div class='error'><h3>✗ เกิดข้อผิดพลาด</h3>";
    echo "<p>" . $e->getMessage() . "</p></div>";
    echo "<p>กรุณาตรวจสอบ:</p>";
    echo "<ul>";
    echo "<li>การเชื่อมต่อฐานข้อมูลใน config/db_connect.php</li>";
    echo "<li>สิทธิ์ในการสร้าง/แก้ไขตาราง</li>";
    echo "<li>PHP error log สำหรับข้อมูลเพิ่มเติม</li>";
    echo "</ul>";
}

$conn->close();
echo "</body></html>";
?>
