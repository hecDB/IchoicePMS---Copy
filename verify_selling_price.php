<?php
session_start();
require 'config/db_connect.php';

// Check if selling_price column exists in receive_items table
$check_column_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_NAME='receive_items' AND COLUMN_NAME='selling_price' 
                     AND TABLE_SCHEMA=DATABASE()";
$stmt = $pdo->query($check_column_sql);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>ตรวจสอบการแก้ไข Selling Price</h3>";
echo "<hr>";

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✓ คอลัมน์ selling_price มีอยู่ในตาราง receive_items</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ คอลัมน์ selling_price ยังไม่มีในตาราง receive_items</p>";
    echo "<p>กรุณารีเฟรชหน้านี้หรือเปิดหน้า receive/receive_po_items.php เพื่อเรียกใช้ initializeCancelSchema()</p>";
}

echo "<hr>";

// Show recent receive items with selling price
$sql = "SELECT 
            ri.receive_id,
            ri.item_id,
            ri.po_id,
            ri.receive_qty,
            ri.selling_price,
            poi.product_id,
            p.name as product_name,
            ii.sale_price as latest_issue_price
        FROM receive_items ri
        LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN (
            SELECT product_id, sale_price 
            FROM issue_items 
            WHERE product_id IS NOT NULL 
            ORDER BY created_at DESC 
            LIMIT 1
        ) ii ON poi.product_id = ii.product_id
        ORDER BY ri.receive_id DESC
        LIMIT 10";

$stmt = $pdo->query($sql);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>ข้อมูลสิ่งของรับเข้าล่าสุด (10 รายการ):</h4>";
echo "<table border='1' cellpadding='10' cellspacing='0' style='width: 100%; margin-top: 10px;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Receive ID</th>";
echo "<th>Item ID</th>";
echo "<th>สินค้า</th>";
echo "<th>จำนวน</th>";
echo "<th>Selling Price</th>";
echo "<th>Latest Issue Price</th>";
echo "<th>สถานะ</th>";
echo "</tr>";

foreach ($records as $row) {
    $status = '';
    if ($row['selling_price'] > 0) {
        $status = "✓ มีราคาขาย";
    } else if (is_null($row['selling_price']) || $row['selling_price'] == 0) {
        if ($row['latest_issue_price'] > 0) {
            $status = "⚠️ ไม่มีการขาย (มีราคาขายอ้างอิง: {$row['latest_issue_price']})";
        } else {
            $status = "❌ ไม่มีข้อมูลราคาขาย";
        }
    }
    
    echo "<tr>";
    echo "<td>{$row['receive_id']}</td>";
    echo "<td>{$row['item_id']}</td>";
    echo "<td>" . ($row['product_name'] ?? '-') . "</td>";
    echo "<td>{$row['receive_qty']}</td>";
    echo "<td>" . number_format($row['selling_price'], 2) . "</td>";
    echo "<td>" . (isset($row['latest_issue_price']) ? number_format($row['latest_issue_price'], 2) : '-') . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>";
echo "ข้อมูลการบันทึก:<br>";
echo "- Selling Price = ราคาขายล่าสุดจากตาราง issue_items<br>";
echo "- Latest Issue Price = ราคาขายล่าสุดของสินค้าจากตาราง issue_items<br>";
echo "- หากไม่มีการขาย ให้บันทึก 0<br>";
echo "</p>";
?>
