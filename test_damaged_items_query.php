<?php
/**
 * ทดสอบการ query รายการสินค้าชำรุด (แบบขายไม่ได้)
 */

require 'config/db_connect.php';

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Test Damaged Items Query</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .info { background: #e3f2fd; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; }
    .warning { background: #fff3e0; padding: 15px; margin: 10px 0; border-left: 4px solid #ff9800; }
    .error { background: #ffebee; padding: 15px; margin: 10px 0; border-left: 4px solid #f44336; }
    .success { background: #e8f5e9; padding: 15px; margin: 10px 0; border-left: 4px solid #4CAF50; }
</style></head><body>";

echo "<h1>🔍 ตรวจสอบรายการสินค้าชำรุด (แบบขายไม่ได้)</h1>";

try {
    // 1. ตรวจสอบว่ามีตาราง returned_items หรือไม่
    echo "<div class='info'><h2>ขั้นตอนที่ 1: ตรวจสอบโครงสร้างตาราง returned_items</h2>";
    $stmt = $pdo->query("DESCRIBE returned_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "<div class='error'>❌ ไม่พบตาราง returned_items!</div>";
    } else {
        echo "<div class='success'>✅ พบตาราง returned_items</div>";
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 2. นับจำนวนรายการทั้งหมดใน returned_items
    echo "<div class='info'><h2>ขั้นตอนที่ 2: นับจำนวนรายการทั้งหมด</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM returned_items");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>📊 จำนวนรายการทั้งหมดในตาราง: <strong>{$total}</strong></p>";
    
    if ($total == 0) {
        echo "<div class='warning'>⚠️ ไม่มีข้อมูลในตาราง returned_items เลย</div>";
    }
    echo "</div>";
    
    // 3. นับจำนวนรายการที่ is_returnable = 0 (ขายไม่ได้)
    echo "<div class='info'><h2>ขั้นตอนที่ 3: นับจำนวนสินค้าชำรุดขายไม่ได้</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM returned_items WHERE is_returnable = 0 OR is_returnable = '0'");
    $unsellable = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>🚫 จำนวนสินค้าชำรุดขายไม่ได้ (is_returnable = 0): <strong>{$unsellable}</strong></p>";
    
    if ($unsellable == 0) {
        echo "<div class='warning'>⚠️ ไม่มีสินค้าชำรุดที่ขายไม่ได้ในระบบ</div>";
    }
    echo "</div>";
    
    // 4. แสดงรายการสินค้าชำรุดทั้งหมด (is_returnable = 0)
    if ($unsellable > 0) {
        echo "<div class='info'><h2>ขั้นตอนที่ 4: รายการสินค้าชำรุดขายไม่ได้ทั้งหมด</h2>";
        $stmt = $pdo->query("
            SELECT 
                ri.return_id,
                ri.return_code,
                ri.po_id,
                ri.product_id,
                ri.product_name,
                ri.sku,
                ri.return_qty,
                ri.is_returnable,
                ri.created_at,
                po.po_number
            FROM returned_items ri
            LEFT JOIN purchase_orders po ON ri.po_id = po.po_id
            WHERE ri.is_returnable = 0 OR ri.is_returnable = '0'
            ORDER BY ri.created_at DESC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr>";
        echo "<th>Return ID</th>";
        echo "<th>Return Code</th>";
        echo "<th>PO ID</th>";
        echo "<th>PO Number</th>";
        echo "<th>Product Name</th>";
        echo "<th>SKU</th>";
        echo "<th>Qty</th>";
        echo "<th>is_returnable</th>";
        echo "<th>Created At</th>";
        echo "</tr>";
        
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>{$item['return_id']}</td>";
            echo "<td>{$item['return_code']}</td>";
            echo "<td><strong>{$item['po_id']}</strong></td>";
            echo "<td>{$item['po_number']}</td>";
            echo "<td>{$item['product_name']}</td>";
            echo "<td>{$item['sku']}</td>";
            echo "<td>{$item['return_qty']}</td>";
            echo "<td>{$item['is_returnable']}</td>";
            echo "<td>{$item['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    
    // 5. แสดง PO ที่มีสินค้าชำรุดขายไม่ได้
    echo "<div class='info'><h2>ขั้นตอนที่ 5: PO ที่มีสินค้าชำรุดขายไม่ได้</h2>";
    $stmt = $pdo->query("
        SELECT 
            po.po_id,
            po.po_number,
            po.status,
            COUNT(ri.return_id) as damaged_count,
            SUM(ri.return_qty) as total_damaged_qty
        FROM purchase_orders po
        INNER JOIN returned_items ri ON po.po_id = ri.po_id
        WHERE ri.is_returnable = 0 OR ri.is_returnable = '0'
        GROUP BY po.po_id, po.po_number, po.status
        ORDER BY po.po_id DESC
    ");
    $pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pos)) {
        echo "<div class='warning'>⚠️ ไม่มี PO ใดที่มีสินค้าชำรุดขายไม่ได้</div>";
    } else {
        echo "<div class='success'>✅ พบ " . count($pos) . " PO ที่มีสินค้าชำรุดขายไม่ได้</div>";
        echo "<table>";
        echo "<tr><th>PO ID</th><th>PO Number</th><th>Status</th><th>Damaged Items</th><th>Total Qty</th></tr>";
        foreach ($pos as $po) {
            echo "<tr>";
            echo "<td><strong>{$po['po_id']}</strong></td>";
            echo "<td>{$po['po_number']}</td>";
            echo "<td>{$po['status']}</td>";
            echo "<td>{$po['damaged_count']}</td>";
            echo "<td>{$po['total_damaged_qty']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 6. ทดสอบ API call สำหรับ PO ล่าสุด
    if (!empty($pos)) {
        $test_po_id = $pos[0]['po_id'];
        echo "<div class='info'><h2>ขั้นตอนที่ 6: ทดสอบ API สำหรับ PO ID: {$test_po_id}</h2>";
        echo "<p>🔗 URL: <a href='api/get_damaged_unsellable_by_po.php?po_id={$test_po_id}' target='_blank'>api/get_damaged_unsellable_by_po.php?po_id={$test_po_id}</a></p>";
        echo "<p>คลิกลิงก์เพื่อดู JSON response</p>";
        echo "</div>";
    }
    
    echo "<div class='success'><h2>✅ การทดสอบเสร็จสิ้น</h2>";
    echo "<h3>สรุปผลการตรวจสอบ:</h3>";
    echo "<ul>";
    echo "<li>รายการทั้งหมด: <strong>{$total}</strong></li>";
    echo "<li>สินค้าชำรุดขายไม่ได้: <strong>{$unsellable}</strong></li>";
    echo "<li>PO ที่มีสินค้าชำรุด: <strong>" . count($pos) . "</strong></li>";
    echo "</ul>";
    
    if ($unsellable == 0) {
        echo "<div class='warning'>";
        echo "<h3>💡 คำแนะนำ:</h3>";
        echo "<p>ไม่มีข้อมูลสินค้าชำรุดขายไม่ได้ในระบบ ต้องทำการบันทึกสินค้าชำรุดก่อน:</p>";
        echo "<ol>";
        echo "<li>เปิดหน้ารับเข้าสินค้า (receive_po_items.php)</li>";
        echo "<li>คลิกปุ่มเครื่องมือ (🔧) บนรายการสินค้า</li>";
        echo "<li>เลือก 'สินค้าชำรุดบางส่วน'</li>";
        echo "<li>เลือก 'ทิ้ง/ใช้ไม่ได้' เพื่อบันทึกเป็นสินค้าขายไม่ได้</li>";
        echo "</ol>";
        echo "</div>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
