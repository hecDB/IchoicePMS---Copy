<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Find PO ID for receive_items 24, 32, 33</h2>";
echo "<pre>";

// ตรวจสอบ receive_items ว่าเชื่อมโยงกับ PO ไหน
$sql = "
SELECT DISTINCT
    r.receive_id,
    r.item_id,
    poi.po_id,
    po.po_id as actual_po_id,
    po.po_number
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
WHERE r.receive_id IN (24, 32, 33)
ORDER BY r.receive_id DESC";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Results:\n\n";
    foreach($rows as $row) {
        echo "receive_id: " . $row['receive_id'] . 
             " | po_id: " . $row['po_id'] . 
             " | po_number: " . $row['po_number'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบ temp_products ที่มี po_id นี้
echo "\n<h2>Existing temp_products with po_id</h2>";
echo "<pre>";

$sql2 = "SELECT temp_product_id, po_id, product_name, provisional_sku FROM temp_products WHERE po_id IN (SELECT DISTINCT poi.po_id FROM purchase_order_items poi WHERE poi.item_id = 43) LIMIT 5";

try {
    $stmt2 = $pdo->query($sql2);
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($rows2 as $row) {
        echo "temp_product_id: " . $row['temp_product_id'] . 
             " | po_id: " . $row['po_id'] . 
             " | name: " . $row['product_name'] . 
             " | sku: " . ($row['provisional_sku'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

?>
