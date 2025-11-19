<?php
session_start();
require 'config/db_connect.php';

// SQL Query เดียวกับใน transaction_view_separated.php
$sql = "
(SELECT 
    'receive' as transaction_type,
    r.receive_id as transaction_id, 
    tp.product_image as image,
    u.name AS created_by, 
    r.created_at, 
    r.receive_qty as quantity, 
    r.expiry_date,
    tp.product_name AS product_name,
    tp.product_category,
    tp.temp_product_id,
    tp.provisional_sku,
    tp.provisional_barcode
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
LEFT JOIN users u ON r.created_by = u.user_id
WHERE COALESCE(poi.temp_product_id, 0) > 0)

UNION ALL

(SELECT 
    'issue' as transaction_type,
    ii.issue_id as transaction_id,
    tp.product_image as image,
    u.name AS created_by,
    ii.created_at,
    ii.issue_qty as quantity,
    ri.expiry_date,
    tp.product_name AS product_name,
    tp.product_category,
    tp.temp_product_id,
    tp.provisional_sku,
    tp.provisional_barcode
FROM issue_items ii
LEFT JOIN receive_items ri ON ii.receive_id = ri.receive_id
LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
LEFT JOIN users u ON ii.issued_by = u.user_id
WHERE COALESCE(poi.temp_product_id, 0) > 0)
ORDER BY created_at DESC LIMIT 500";

echo "<h2>Debug SQL Query Results</h2>";
echo "<pre>";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total Rows: " . count($rows) . "\n\n";
    
    foreach($rows as $idx => $row) {
        echo "=== Row " . ($idx + 1) . " ===\n";
        echo "  temp_product_id: " . $row['temp_product_id'] . "\n";
        echo "  transaction_type: " . $row['transaction_type'] . "\n";
        echo "  transaction_id: " . $row['transaction_id'] . "\n";
        echo "  product_name: " . $row['product_name'] . "\n";
        echo "  provisional_sku: " . $row['provisional_sku'] . "\n";
        echo "  provisional_barcode: " . $row['provisional_barcode'] . "\n";
        echo "  quantity: " . $row['quantity'] . "\n";
        echo "  created_by: " . $row['created_by'] . "\n";
        echo "  created_at: " . $row['created_at'] . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบ temp_products table
echo "\n<h2>Direct temp_products Table Check</h2>";
echo "<pre>";

$sql2 = "SELECT * FROM temp_products LIMIT 5";
$stmt2 = $pdo->query($sql2);
$products = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total Records: " . count($products) . "\n\n";
foreach($products as $idx => $p) {
    echo "=== temp_product_id: " . $p['temp_product_id'] . " ===\n";
    echo "  product_name: " . $p['product_name'] . "\n";
    echo "  provisional_sku: " . $p['provisional_sku'] . "\n";
    echo "  provisional_barcode: " . $p['provisional_barcode'] . "\n";
    echo "\n";
}

echo "</pre>";

// ตรวจสอบ purchase_order_items
echo "\n<h2>purchase_order_items Check (with temp_product_id > 0)</h2>";
echo "<pre>";

$sql3 = "SELECT * FROM purchase_order_items WHERE temp_product_id > 0 LIMIT 10";
$stmt3 = $pdo->query($sql3);
$poi = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total Records: " . count($poi) . "\n\n";
foreach($poi as $idx => $p) {
    echo "=== item_id: " . $p['item_id'] . " ===\n";
    echo "  temp_product_id: " . $p['temp_product_id'] . "\n";
    echo "  product_id: " . $p['product_id'] . "\n";
    echo "\n";
}

echo "</pre>";

?>
