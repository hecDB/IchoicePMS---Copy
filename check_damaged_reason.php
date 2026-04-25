<?php
/**
 * ตรวจสอบว่า return_reasons มี "สินค้าชำรุดบางส่วน" หรือไม่
 * และแสดงรายการที่บันทึกไว้แล้ว
 */

require 'config/db_connect.php';

echo "<h2>🔍 ตรวจสอบ return_reasons และ returned_items</h2>";

// 1. ตรวจสอบ return_reasons
echo "<h3>1️⃣ ตาราง return_reasons</h3>";
$stmt = $pdo->query("SELECT * FROM return_reasons ORDER BY reason_id");
$reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reasons)) {
    echo "<p style='color: red;'>❌ ตาราง return_reasons ว่างเปล่า!</p>";
    echo "<p>กรุณารันไฟล์ <strong>fix_damaged_partial_reason.php</strong> เพื่อเพิ่มข้อมูล</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>reason_id</th>";
    echo "<th>reason_name</th>";
    echo "<th>is_returnable</th>";
    echo "<th>is_active</th>";
    echo "<th>หมายเหตุ</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $hasDamagedReason = false;
    $damagedReasonId = null;
    
    foreach ($reasons as $reason) {
        $highlight = '';
        $note = '';
        
        if ($reason['reason_name'] === 'สินค้าชำรุดบางส่วน') {
            $highlight = 'background-color: #d4edda; font-weight: bold;';
            $note = '✅ ใช้สำหรับ damaged_return_inspections';
            $hasDamagedReason = true;
            $damagedReasonId = $reason['reason_id'];
        }
        
        echo "<tr style='$highlight'>";
        echo "<td>{$reason['reason_id']}</td>";
        echo "<td>{$reason['reason_name']}</td>";
        echo "<td>{$reason['is_returnable']}</td>";
        echo "<td>{$reason['is_active']}</td>";
        echo "<td>$note</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    
    if (!$hasDamagedReason) {
        echo "<p style='color: red; margin-top: 20px;'>❌ <strong>ไม่พบ reason ชื่อ 'สินค้าชำรุดบางส่วน'</strong></p>";
        echo "<p>กรุณารันไฟล์ <strong>fix_damaged_partial_reason.php</strong> เพื่อเพิ่มข้อมูล</p>";
    } else {
        echo "<p style='color: green; margin-top: 20px;'>✅ <strong>พบ reason 'สินค้าชำรุดบางส่วน' แล้ว (reason_id: $damagedReasonId)</strong></p>";
    }
}

// 2. ตรวจสอบ returned_items ที่บันทึกด้วย "สินค้าชำรุดบางส่วน"
echo "<hr>";
echo "<h3>2️⃣ รายการสินค้าชำรุดบางส่วนที่บันทึกแล้ว</h3>";

$stmt = $pdo->query("
    SELECT 
        return_id,
        return_code,
        product_name,
        return_qty,
        reason_id,
        reason_name,
        return_status,
        created_at
    FROM returned_items
    WHERE reason_name = 'สินค้าชำรุดบางส่วน'
    ORDER BY created_at DESC
    LIMIT 20
");
$damagedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($damagedItems)) {
    echo "<p style='color: orange;'>⚠️ ยังไม่มีรายการสินค้าชำรุดบางส่วนที่บันทึกไว้</p>";
    echo "<p>ทดสอบโดยกดปุ่ม 'สินค้าชำรุดบางส่วน' ในหน้ารับสินค้า</p>";
} else {
    echo "<p style='color: green;'>✅ พบรายการสินค้าชำรุดบางส่วน: <strong>" . count($damagedItems) . " รายการ</strong></p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>return_id</th>";
    echo "<th>return_code</th>";
    echo "<th>สินค้า</th>";
    echo "<th>จำนวน</th>";
    echo "<th>reason_id</th>";
    echo "<th>reason_name</th>";
    echo "<th>สถานะ</th>";
    echo "<th>บันทึกเมื่อ</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($damagedItems as $item) {
        $statusBg = $item['return_status'] === 'pending' ? 'background-color: #fff3cd;' : '';
        echo "<tr style='$statusBg'>";
        echo "<td>{$item['return_id']}</td>";
        echo "<td>{$item['return_code']}</td>";
        echo "<td>{$item['product_name']}</td>";
        echo "<td>{$item['return_qty']}</td>";
        echo "<td>{$item['reason_id']}</td>";
        echo "<td>{$item['reason_name']}</td>";
        echo "<td>{$item['return_status']}</td>";
        echo "<td>{$item['created_at']}</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

// 3. ตรวจสอบ returned_items ที่มี reason_id ไม่ตรงกับ reason_name
echo "<hr>";
echo "<h3>3️⃣ ตรวจสอบความสอดคล้องของข้อมูล</h3>";

$stmt = $pdo->query("
    SELECT 
        ri.return_id,
        ri.return_code,
        ri.reason_id as stored_reason_id,
        ri.reason_name as stored_reason_name,
        rr.reason_id as actual_reason_id,
        rr.reason_name as actual_reason_name
    FROM returned_items ri
    LEFT JOIN return_reasons rr ON ri.reason_id = rr.reason_id
    WHERE ri.reason_id != rr.reason_id OR ri.reason_name != rr.reason_name
    LIMIT 10
");
$inconsistent = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($inconsistent)) {
    echo "<p style='color: green;'>✅ ข้อมูลสอดคล้องกันทั้งหมด</p>";
} else {
    echo "<p style='color: red;'>❌ พบข้อมูลไม่สอดคล้อง: <strong>" . count($inconsistent) . " รายการ</strong></p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-top: 10px;'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>return_id</th>";
    echo "<th>return_code</th>";
    echo "<th>reason_id (บันทึก)</th>";
    echo "<th>reason_name (บันทึก)</th>";
    echo "<th>reason_id (จริง)</th>";
    echo "<th>reason_name (จริง)</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($inconsistent as $item) {
        echo "<tr>";
        echo "<td>{$item['return_id']}</td>";
        echo "<td>{$item['return_code']}</td>";
        echo "<td>{$item['stored_reason_id']}</td>";
        echo "<td>{$item['stored_reason_name']}</td>";
        echo "<td>{$item['actual_reason_id']}</td>";
        echo "<td>{$item['actual_reason_name']}</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

echo "<hr>";
echo "<h3>✅ สรุป</h3>";
echo "<ol>";
echo "<li>ตรวจสอบว่ามี reason 'สินค้าชำรุดบางส่วน' ในตาราง return_reasons</li>";
echo "<li>ตรวจสอบว่ามีรายการสินค้าชำรุดบางส่วนที่บันทึกแล้ว</li>";
echo "<li>ตรวจสอบความสอดคล้องของข้อมูล</li>";
echo "</ol>";

echo "<p><strong>หากทุกอย่างพร้อม:</strong></p>";
echo "<ul>";
echo "<li>ทดสอบโดยไปที่หน้า <a href='receive/receive_po_items.php' target='_blank'>รับสินค้า</a></li>";
echo "<li>กดปุ่ม 'สินค้าชำรุดบางส่วน' และบันทึก</li>";
echo "<li>ไปที่ <a href='returns/damaged_return_inspections.php' target='_blank'>ตรวจสอบสินค้าชำรุด</a> จะต้องเห็นรายการ</li>";
echo "</ul>";

echo "<p style='background: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; margin-top: 20px;'>";
echo "<strong>💡 หมายเหตุ:</strong> หากไม่เห็นรายการใน damaged_return_inspections.php ให้ตรวจสอบ Console ใน Browser (F12) ";
echo "เพื่อดู damagedReasonId ที่ถูกเลือก และตรวจสอบว่าตรงกับ reason_id ของ 'สินค้าชำรุดบางส่วน' หรือไม่";
echo "</p>";
?>
