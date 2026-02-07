<?php
/**
 * Diagnostic Tool: ตรวจสอบปัญหาการแสดงสินค้าชำรุดในพอปอัพ
 */

header('Content-Type: text/html; charset=utf-8');
require 'config/db_connect.php';

echo "<h1>🔍 ตรวจสอบปัญหาการแสดงสินค้าชำรุด</h1>";

// ขั้นที่ 1: ตรวจสอบ return_reasons
echo "<h2>ขั้นที่ 1: ตรวจสอบ return_reasons</h2>";
$reasonStmt = $pdo->prepare("
    SELECT reason_id, reason_code, reason_name, is_returnable, category 
    FROM return_reasons 
    ORDER BY reason_id ASC
");
$reasonStmt->execute();
$reasons = $reasonStmt->fetchAll(PDO::FETCH_ASSOC);

$damagedPartialExists = false;
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>reason_id</th><th>reason_code</th><th>reason_name</th><th>is_returnable</th><th>category</th></tr>";
foreach ($reasons as $reason) {
    $mark = ($reason['reason_name'] === 'สินค้าชำรุดบางส่วน') ? "✅ TARGET" : "";
    echo "<tr>";
    echo "<td>" . $reason['reason_id'] . "</td>";
    echo "<td>" . $reason['reason_code'] . "</td>";
    echo "<td>" . $reason['reason_name'] . " $mark</td>";
    echo "<td>" . $reason['is_returnable'] . "</td>";
    echo "<td>" . $reason['category'] . "</td>";
    echo "</tr>";
    
    if ($reason['reason_name'] === 'สินค้าชำรุดบางส่วน') {
        $damagedPartialExists = true;
    }
}
echo "</table>";

if (!$damagedPartialExists) {
    echo "<div style='color: red; margin-top: 10px;'>";
    echo "❌ ไม่พบ reason 'สินค้าชำรุดบางส่วน' ในฐานข้อมูล<br>";
    echo "<a href='fix_damaged_partial_reason.php'>👉 กดที่นี่เพื่อเพิ่ม reason นี้</a>";
    echo "</div>";
} else {
    echo "<div style='color: green; margin-top: 10px;'>";
    echo "✅ พบ reason 'สินค้าชำรุดบางส่วน' แล้ว";
    echo "</div>";
}

// ขั้นที่ 2: ตรวจสอบ returned_items ที่มี is_returnable = 0
echo "<h2 style='margin-top: 30px;'>ขั้นที่ 2: ตรวจสอบ returned_items ที่มี is_returnable = 0</h2>";
$returnedStmt = $pdo->prepare("
    SELECT 
        return_id,
        return_code,
        po_id,
        po_number,
        item_id,
        product_id,
        product_name,
        sku,
        return_qty,
        reason_name,
        is_returnable,
        return_status,
        created_at
    FROM returned_items
    WHERE is_returnable = 0
    ORDER BY created_at DESC
    LIMIT 20
");
$returnedStmt->execute();
$returnedItems = $returnedStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($returnedItems)) {
    echo "<div style='color: orange; margin-top: 10px;'>";
    echo "⚠️ ไม่พบ returned_items ที่มี is_returnable = 0<br>";
    echo "นี่อาจหมายความว่า:<br>";
    echo "1. ยังไม่มีสินค้าชำรุดใดที่ถูกสร้างขึ้นมา<br>";
    echo "2. หรือ reason ที่ใช้ไม่มี is_returnable = 0<br>";
    echo "</div>";
} else {
    echo "<div style='color: green; margin-top: 10px;'>";
    echo "✅ พบ " . count($returnedItems) . " รายการที่มี is_returnable = 0<br>";
    echo "</div>";
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>return_id</th><th>return_code</th><th>po_id</th><th>reason_name</th><th>is_returnable</th><th>created_at</th></tr>";
    foreach ($returnedItems as $item) {
        echo "<tr>";
        echo "<td>" . $item['return_id'] . "</td>";
        echo "<td>" . $item['return_code'] . "</td>";
        echo "<td>" . ($item['po_id'] ?: 'NULL ❌') . "</td>";
        echo "<td>" . $item['reason_name'] . "</td>";
        echo "<td>" . $item['is_returnable'] . "</td>";
        echo "<td>" . $item['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ขั้นที่ 3: ทดสอบ API get_damaged_unsellable_by_po
echo "<h2 style='margin-top: 30px;'>ขั้นที่ 3: ทดสอบ API สำหรับ PO ที่มีสินค้าชำรุด</h2>";

if (!empty($returnedItems)) {
    $testPoId = $returnedItems[0]['po_id'];
    
    if ($testPoId) {
        echo "<p>ทดสอบ API กับ po_id = $testPoId</p>";
        
        $testStmt = $pdo->prepare("
            SELECT 
                ri.return_id,
                ri.return_code,
                ri.product_id,
                ri.product_name,
                ri.sku,
                ri.return_qty,
                ri.return_status,
                ri.is_returnable,
                ri.image_path,
                ri.notes as return_notes,
                ri.expiry_date,
                ri.created_at
            FROM returned_items ri
            WHERE ri.is_returnable = 0 AND ri.po_id = :po_id
            ORDER BY ri.created_at DESC
        ");
        $testStmt->execute([':po_id' => $testPoId]);
        $testResults = $testStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>ผลลัพธ์: " . count($testResults) . " รายการ</p>";
        
        if (!empty($testResults)) {
            echo "<table border='1' cellpadding='10'>";
            echo "<tr><th>return_id</th><th>return_code</th><th>product_name</th><th>return_qty</th><th>is_returnable</th></tr>";
            foreach ($testResults as $result) {
                echo "<tr>";
                echo "<td>" . $result['return_id'] . "</td>";
                echo "<td>" . $result['return_code'] . "</td>";
                echo "<td>" . $result['product_name'] . "</td>";
                echo "<td>" . $result['return_qty'] . "</td>";
                echo "<td>" . $result['is_returnable'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div style='color: red;'>";
        echo "❌ ไม่พบ po_id ที่ไม่เป็น NULL<br>";
        echo "นี่อาจเป็นปัญหา: returned_items อาจไม่ได้บันทึก po_id";
        echo "</div>";
    }
} else {
    echo "<p>ไม่มี returned_items ที่มี is_returnable = 0 ให้ทดสอบ</p>";
}

// ขั้นที่ 4: สรุป
echo "<h2 style='margin-top: 30px;'>🎯 สรุปและแนวทางแก้ไข</h2>";
echo "<ol>";
echo "<li>ตรวจสอบว่า return_reasons มี 'สินค้าชำรุดบางส่วน' หรือไม่";
if (!$damagedPartialExists) {
    echo " - ❌ ไม่พบ ให้เพิ่มโดยใช้ fix_damaged_partial_reason.php";
} else {
    echo " - ✅ พบแล้ว";
}
echo "</li>";
echo "<li>เมื่อสร้าง return item ให้ตรวจสอบว่า po_id ถูกบันทึกหรือไม่</li>";
echo "<li>หากไม่เจอปัญหา ให้ดู log ในไฟล์ error log ของ Apache/PHP";
echo "</ol>";

?>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2 { color: #333; }
    table { border-collapse: collapse; margin: 10px 0; }
    table th { background: #f0f0f0; }
    table td { vertical-align: middle; }
    a { color: blue; text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>
