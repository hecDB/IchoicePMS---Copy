<?php
require 'config/db_connect.php';

echo "<h2>ตรวจสอบโครงสร้างตาราง</h2>";

// ตาราง products
echo "<h3>1. ตาราง products</h3>";
$stmt = $pdo->query("DESCRIBE products");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
    echo "<td>{$col['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// ตาราง purchase_order_items
echo "<h3>2. ตาราง purchase_order_items</h3>";
$stmt = $pdo->query("DESCRIBE purchase_order_items");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
    echo "<td>{$col['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// ตาราง receive_items
echo "<h3>3. ตาราง receive_items</h3>";
$stmt = $pdo->query("DESCRIBE receive_items");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
    echo "<td>{$col['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// ตรวจสอบข้อมูลล่าสุดใน purchase_order_items
echo "<h3>4. ข้อมูลล่าสุดใน purchase_order_items (5 รายการล่าสุด)</h3>";
$stmt = $pdo->query("SELECT poi.*, p.sku, p.name 
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.product_id
    ORDER BY poi.created_at DESC LIMIT 5");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
if (count($items) > 0) {
    echo "<tr>";
    foreach (array_keys($items[0]) as $key) {
        echo "<th>$key</th>";
    }
    echo "</tr>";
    foreach ($items as $item) {
        echo "<tr>";
        foreach ($item as $value) {
            echo "<td>" . ($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
} else {
    echo "<tr><td>ไม่มีข้อมูล</td></tr>";
}
echo "</table>";

// ตรวจสอบข้อมูลล่าสุดใน receive_items
echo "<h3>5. ข้อมูลล่าสุดใน receive_items (5 รายการล่าสุด)</h3>";
$stmt = $pdo->query("SELECT ri.*, p.sku, p.name 
    FROM receive_items ri
    LEFT JOIN products p ON ri.product_id = p.product_id
    ORDER BY ri.created_at DESC LIMIT 5");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
if (count($items) > 0) {
    echo "<tr>";
    foreach (array_keys($items[0]) as $key) {
        echo "<th>$key</th>";
    }
    echo "</tr>";
    foreach ($items as $item) {
        echo "<tr>";
        foreach ($item as $value) {
            echo "<td>" . ($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
} else {
    echo "<tr><td>ไม่มีข้อมูล</td></tr>";
}
echo "</table>";

// ตรวจสอบ returned_items ที่รอตรวจสอบ
echo "<h3>6. returned_items ที่รอตรวจสอบ (return_status = pending, reason_id = 8)</h3>";
$stmt = $pdo->query("SELECT return_id, return_code, product_id, sku, product_name, return_qty, return_status, po_id, po_number 
    FROM returned_items 
    WHERE reason_id = 8 AND return_status = 'pending'
    ORDER BY created_at DESC LIMIT 5");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
if (count($items) > 0) {
    echo "<tr>";
    foreach (array_keys($items[0]) as $key) {
        echo "<th>$key</th>";
    }
    echo "</tr>";
    foreach ($items as $item) {
        echo "<tr>";
        foreach ($item as $value) {
            echo "<td>" . ($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
} else {
    echo "<tr><td>ไม่มีรายการรอตรวจสอบ</td></tr>";
}
echo "</table>";
