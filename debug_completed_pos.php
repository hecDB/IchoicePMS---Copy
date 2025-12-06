<?php
session_start();
require 'config/db_connect.php';

echo "<h2>ตรวจสอบ PO ที่รับครบแล้ว</h2>";

// 1. นับจำนวน PO ในแต่ละสถานะ
echo "<h3>1. จำนวน PO แต่ละสถานะ:</h3>";
$sql_count = "SELECT status, COUNT(*) as count FROM purchase_orders GROUP BY status";
$result = $pdo->query($sql_count);
$statuses = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($statuses);
echo "</pre>";

// 2. ตรวจสอบ PO ที่มี status='completed'
echo "<h3>2. PO ที่มี status='completed':</h3>";
$sql_completed = "SELECT po_id, po_number, status FROM purchase_orders WHERE status='completed' LIMIT 5";
$result = $pdo->query($sql_completed);
$completed = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($completed);
echo "</pre>";

// 3. ตรวจสอบสินค้าที่รับครบแล้ว (received + cancelled >= ordered)
echo "<h3>3. ตรวจสอบสินค้าที่รับครบแล้ว (received + cancelled >= ordered):</h3>";
$sql_items = "
    SELECT 
        po.po_id,
        po.po_number,
        po.status,
        poi.item_id,
        poi.qty as ordered_qty,
        COALESCE(SUM(ri.receive_qty), 0) as received_qty,
        COALESCE(poi.cancel_qty, 0) as cancel_qty,
        (COALESCE(SUM(ri.receive_qty), 0) + COALESCE(poi.cancel_qty, 0)) as total_fulfilled
    FROM purchase_orders po
    LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
    LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
    GROUP BY po.po_id, poi.item_id
    ORDER BY po.po_id DESC
    LIMIT 20
";

$result = $pdo->query($sql_items);
$items = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>PO ID</th><th>PO Number</th><th>Status</th><th>Item ID</th><th>Ordered</th><th>Received</th><th>Cancelled</th><th>Total</th><th>Complete?</th></tr>";

foreach ($items as $item) {
    $is_complete = $item['total_fulfilled'] >= $item['ordered_qty'] ? '✓' : '✗';
    echo "<tr>";
    echo "<td>{$item['po_id']}</td>";
    echo "<td>{$item['po_number']}</td>";
    echo "<td>{$item['status']}</td>";
    echo "<td>{$item['item_id']}</td>";
    echo "<td>{$item['ordered_qty']}</td>";
    echo "<td>{$item['received_qty']}</td>";
    echo "<td>{$item['cancel_qty']}</td>";
    echo "<td>{$item['total_fulfilled']}</td>";
    echo "<td>{$is_complete}</td>";
    echo "</tr>";
}
echo "</table>";

// 4. ตรวจสอบว่า PO ที่ควร complete แต่ยังไม่ถูกตั้ง status
echo "<h3>4. PO ที่ควรเป็น 'completed' แต่ยังเป็น 'pending' หรือ 'partial':</h3>";
$sql_should_complete = "
    SELECT 
        po.po_id,
        po.po_number,
        po.status,
        COUNT(DISTINCT poi.item_id) as total_items,
        SUM(CASE WHEN (COALESCE(received_summary.total_received, 0) + COALESCE(poi.cancel_qty, 0)) >= poi.qty THEN 1 ELSE 0 END) as complete_items
    FROM purchase_orders po
    LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
    LEFT JOIN (
        SELECT item_id, SUM(receive_qty) as total_received 
        FROM receive_items 
        GROUP BY item_id
    ) received_summary ON poi.item_id = received_summary.item_id
    WHERE po.status IN ('pending', 'partial')
    GROUP BY po.po_id, po.po_number, po.status
    HAVING complete_items = total_items AND total_items > 0
    ORDER BY po.po_id DESC
    LIMIT 10
";

$result = $pdo->query($sql_should_complete);
$should_complete = $result->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($should_complete);
echo "</pre>";

echo "<hr>";
echo "<h3>💡 สรุป:</h3>";
if (count($completed) > 0) {
    echo "<p>✓ มี " . count($completed) . " PO ที่มี status='completed'</p>";
} else {
    echo "<p>✗ ไม่มี PO ที่มี status='completed'</p>";
    if (count($should_complete) > 0) {
        echo "<p>⚠️ มี " . count($should_complete) . " PO ที่ควรเป็น 'completed' แต่ยังไม่ได้อัปเดต</p>";
        echo "<p>ต้องรันคำสั่ง UPDATE เพื่ออัปเดต status</p>";
    }
}
?>
