<?php
/**
 * อัปเดตเหตุผลการตีกลับให้ตรงกับความต้องการ
 * เอากลับเข้า stock (returnable = 1): 1,2,4,5
 * ไม่เอากลับ stock (returnable = 0): 3,6,7
 */

require 'config/db_connect.php';

try {
    // ลบเหตุผลเดิม
    $pdo->exec("DELETE FROM return_reasons");
    
    // เพิ่มเหตุผลใหม่ตามความต้องการ
    $insert_reasons = "INSERT INTO `return_reasons` 
    (`reason_code`, `reason_name`, `is_returnable`, `category`, `description`, `is_active`) VALUES 
    ('001', 'จัดส่งไม่สำเร็จ', 1, 'returnable', 'สินค้าจัดส่งไม่สำเร็จ - สามารถคืนเข้าสต็อก', 1),
    ('002', 'ยกเลิกคำสั่งซื้อ', 1, 'returnable', 'ลูกค้าขอยกเลิกคำสั่งซื้อ - สามารถคืนเข้าสต็อก', 1),
    ('003', 'ชำรุด/เสียหาย', 0, 'non-returnable', 'สินค้าชำรุดหรือเสียหาย - ไม่สามารถคืนเข้าสต็อก (ลงหมายเหตุ)', 1),
    ('004', 'ลูกค้าปฏิเสธรับสินค้า', 1, 'returnable', 'ลูกค้าปฏิเสธการรับสินค้า - สามารถคืนเข้าสต็อก', 1),
    ('005', 'ส่งผิด', 1, 'returnable', 'ส่งสินค้าผิดรายการ - สามารถคืนเข้าสต็อก', 1),
    ('006', 'สินค้าปลอม', 0, 'non-returnable', 'สินค้าปลอมหรือหลอก - ไม่สามารถคืนเข้าสต็อก (ลงหมายเหตุ)', 1),
    ('007', 'อื่นๆ', 0, 'non-returnable', 'เหตุผลอื่นๆ - ไม่สามารถคืนเข้าสต็อก (ลงหมายเหตุ)', 1)";
    
    $pdo->exec($insert_reasons);
    
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif; background-color: #f8fafc;'>";
    echo "<div style='max-width: 800px; margin: 0 auto;'>";
    echo "<h2>✓ อัปเดตเหตุผลการตีกลับสำเร็จ!</h2>";
    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 2rem;'>";
    echo "<thead style='background-color: #667eea; color: white;'>";
    echo "<tr><th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>#</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>เหตุผล</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>ประเภท</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>คำอธิบาย</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $reasons = [
        ['1', 'จัดส่งไม่สำเร็จ', 'เอากลับเข้า stock', 'สินค้าจัดส่งไม่สำเร็จ - สามารถคืนเข้าสต็อก'],
        ['2', 'ยกเลิกคำสั่งซื้อ', 'เอากลับเข้า stock', 'ลูกค้าขอยกเลิกคำสั่งซื้อ - สามารถคืนเข้าสต็อก'],
        ['3', 'ชำรุด/เสียหาย', 'ไม่เอากลับ stock (หมายเหตุ)', 'สินค้าชำรุดหรือเสียหาย - ไม่สามารถคืนเข้าสต็อก'],
        ['4', 'ลูกค้าปฏิเสธรับสินค้า', 'เอากลับเข้า stock', 'ลูกค้าปฏิเสธการรับสินค้า - สามารถคืนเข้าสต็อก'],
        ['5', 'ส่งผิด', 'เอากลับเข้า stock', 'ส่งสินค้าผิดรายการ - สามารถคืนเข้าสต็อก'],
        ['6', 'สินค้าปลอม', 'ไม่เอากลับ stock (หมายเหตุ)', 'สินค้าปลอมหรือหลอก - ไม่สามารถคืนเข้าสต็อก'],
        ['7', 'อื่นๆ', 'ไม่เอากลับ stock (หมายเหตุ)', 'เหตุผลอื่นๆ - ไม่สามารถคืนเข้าสต็อก'],
    ];
    
    foreach ($reasons as $r) {
        $bgColor = strpos($r[2], 'เอากลับเข้า stock') !== false ? '#e7f5ff' : '#fff3e0';
        echo "<tr style='background-color: {$bgColor};'>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd; text-align: center;'><strong>{$r[0]}</strong></td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$r[1]}</td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'><span style='padding: 0.25rem 0.75rem; border-radius: 4px; background-color: " . 
             (strpos($r[2], 'เอากลับเข้า stock') !== false ? '#0ea5e9' : '#f97316') . 
             "; color: white; font-size: 0.875rem;'>{$r[2]}</span></td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd; font-size: 0.9rem;'>{$r[3]}</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "<p style='margin-top: 2rem; color: #666;'><strong>สรุป:</strong><br>";
    echo "✓ เหตุผล 1, 2, 4, 5 = สามารถคืนเข้าสต็อก (returnable = 1)<br>";
    echo "✓ เหตุผล 3, 6, 7 = ไม่สามารถคืนเข้าสต็อก (returnable = 0) - ลงหมายเหตุไว้<br>";
    echo "</p>";
    echo "<a href='returns/return_items.php' style='display: inline-block; margin-top: 2rem; padding: 0.75rem 1.5rem; background-color: #667eea; color: white; text-decoration: none; border-radius: 6px;'>ไปยังหน้าสินค้าตีกลับ</a>";
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; color: #d32f2f;'>";
    echo "<h2>✗ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
