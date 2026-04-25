<?php
/**
 * แก้ไข Unique Constraint ของ inv_no
 * เปลี่ยนจาก UNIQUE(inv_no) เป็น UNIQUE(inv_no, doc_type)
 * เพื่อให้เลขที่สามารถซ้ำกันได้ในแต่ละประเภทเอกสาร
 */

require_once 'config/db_connect.php';

echo "===== แก้ไข Unique Constraint สำหรับ inv_no =====\n\n";

try {
    // ตรวจสอบว่ามี constraint หรือ index อยู่หรือไม่
    echo "1. ตรวจสอบ Constraints และ Indexes ปัจจุบัน...\n";
    
    $result = $conn->query("
        SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tax_invoices'
        AND CONSTRAINT_TYPE = 'UNIQUE'
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "   - พบ UNIQUE Constraint: " . $row['CONSTRAINT_NAME'] . "\n";
        }
    }
    
    // ตรวจสอบ indexes
    $result = $conn->query("SHOW INDEX FROM tax_invoices WHERE Key_name != 'PRIMARY'");
    if ($result) {
        echo "\n2. Indexes ปัจจุบัน:\n";
        while ($row = $result->fetch_assoc()) {
            echo "   - Index: {$row['Key_name']}, Column: {$row['Column_name']}, Non_unique: {$row['Non_unique']}\n";
        }
    }
    
    echo "\n3. ลบ UNIQUE constraint เก่า (ถ้ามี)...\n";
    
    // ลองลบ constraint ต่างๆ ที่อาจมี
    $possibleConstraints = ['inv_no', 'UNIQUE_inv_no', 'tax_invoices_inv_no_unique'];
    
    foreach ($possibleConstraints as $constraintName) {
        try {
            $conn->query("ALTER TABLE tax_invoices DROP INDEX `{$constraintName}`");
            echo "   ✓ ลบ index '{$constraintName}' สำเร็จ\n";
        } catch (Exception $e) {
            // ไม่มี constraint นี้ ข้ามไป
        }
    }
    
    echo "\n4. สร้าง UNIQUE constraint ใหม่ (inv_no + doc_type)...\n";
    
    // สร้าง unique constraint ใหม่ที่รวม doc_type ด้วย
    $sql = "ALTER TABLE tax_invoices 
            ADD UNIQUE KEY unique_inv_no_doc_type (inv_no, doc_type)";
    
    if ($conn->query($sql)) {
        echo "   ✓ สร้าง UNIQUE constraint สำเร็จ!\n";
        echo "   → ตอนนี้ inv_no สามารถซ้ำกันได้ในแต่ละ doc_type\n";
    } else {
        throw new Exception($conn->error);
    }
    
    echo "\n5. ตรวจสอบผลลัพธ์...\n";
    $result = $conn->query("SHOW INDEX FROM tax_invoices WHERE Key_name = 'unique_inv_no_doc_type'");
    if ($result && $result->num_rows > 0) {
        echo "   ✓ ยืนยัน: UNIQUE constraint ใหม่ถูกสร้างแล้ว\n";
        while ($row = $result->fetch_assoc()) {
            echo "   - Column: {$row['Column_name']}, Seq: {$row['Seq_in_index']}\n";
        }
    }
    
    echo "\n===== เสร็จสมบูรณ์! =====\n";
    echo "ตอนนี้คุณสามารถใช้เลขที่เดียวกันสำหรับเอกสารคนละประเภทได้แล้ว\n";
    echo "เช่น: 202604-001 ใช้ได้ทั้งในใบกำกับภาษี, ใบเสนอราคา, ใบแจ้งหนี้ แยกกัน\n\n";
    
} catch (Exception $e) {
    echo "\n✗ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
    echo "\nหมายเหตุ: ถ้า error บอกว่า 'Duplicate entry' แสดงว่ามีข้อมูลซ้ำอยู่แล้ว\n";
    echo "กรุณาตรวจสอบและแก้ไขข้อมูลซ้ำก่อนรัน script นี้อีกครั้ง\n\n";
}

$conn->close();
?>
