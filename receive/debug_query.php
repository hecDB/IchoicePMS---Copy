<?php
session_start();
require '../config/db_connect.php';

// ทดสอบ Query อย่างละเอียด

echo "<h2>Query Debug Information</h2>";
echo "<pre>";

// 1. ตรวจสอบจำนวนข้อมูลในแต่ละตาราง
echo "=== TABLE COUNTS ===\n";

$tables = [
    'temp_products' => 'SELECT COUNT(*) as cnt FROM temp_products',
    'purchase_order_items' => 'SELECT COUNT(*) as cnt FROM purchase_order_items',
    'receive_items' => 'SELECT COUNT(*) as cnt FROM receive_items',
    'issue_items' => 'SELECT COUNT(*) as cnt FROM issue_items'
];

foreach ($tables as $table_name => $count_sql) {
    try {
        $result = $pdo->query($count_sql)->fetch(PDO::FETCH_ASSOC);
        echo "$table_name: " . $result['cnt'] . " records\n";
    } catch (Exception $e) {
        echo "$table_name: ERROR - " . $e->getMessage() . "\n";
    }
}

// 2. ตรวจสอบ temp_product_id ที่มีค่า
echo "\n=== PURCHASE ORDER ITEMS WITH TEMP_PRODUCT_ID ===\n";
$temp_id_sql = "SELECT COUNT(*) as cnt FROM purchase_order_items WHERE temp_product_id IS NOT NULL AND temp_product_id > 0";
try {
    $result = $pdo->query($temp_id_sql)->fetch(PDO::FETCH_ASSOC);
    echo "Items with temp_product_id > 0: " . $result['cnt'] . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 3. ตรวจสอบ receive_items ที่เชื่อมกับ temp_products
echo "\n=== RECEIVE ITEMS LINKED TO TEMP_PRODUCTS ===\n";
$receive_sql = "SELECT COUNT(*) as cnt FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
WHERE COALESCE(poi.temp_product_id, 0) > 0";
try {
    $result = $pdo->query($receive_sql)->fetch(PDO::FETCH_ASSOC);
    echo "Receive items linked to temp_products: " . $result['cnt'] . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 4. ตรวจสอบ issue_items ที่เชื่อมกับ temp_products
echo "\n=== ISSUE ITEMS LINKED TO TEMP_PRODUCTS ===\n";
$issue_sql = "SELECT COUNT(*) as cnt FROM issue_items ii
LEFT JOIN receive_items ri ON ii.receive_id = ri.receive_id
LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
WHERE COALESCE(poi.temp_product_id, 0) > 0";
try {
    $result = $pdo->query($issue_sql)->fetch(PDO::FETCH_ASSOC);
    echo "Issue items linked to temp_products: " . $result['cnt'] . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 5. ตรวจสอบ sample data
echo "\n=== SAMPLE TEMP_PRODUCTS ===\n";
$sample_sql = "SELECT temp_product_id, product_name, sku, barcode FROM temp_products LIMIT 5";
try {
    $results = $pdo->query($sample_sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "ID: {$row['temp_product_id']}, Name: {$row['product_name']}, SKU: {$row['sku']}, BC: {$row['barcode']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 6. ตรวจสอบ sample purchase_order_items
echo "\n=== SAMPLE PURCHASE_ORDER_ITEMS WITH TEMP_ID ===\n";
$poi_sql = "SELECT item_id, product_id, temp_product_id FROM purchase_order_items WHERE temp_product_id IS NOT NULL AND temp_product_id > 0 LIMIT 5";
try {
    $results = $pdo->query($poi_sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        echo "Item: {$row['item_id']}, Product: {$row['product_id']}, TempID: {$row['temp_product_id']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// 7. รัน Query เต็มและแสดงผล
echo "\n=== FULL QUERY RESULTS ===\n";
$full_sql = "
(SELECT 
    'receive' as transaction_type,
    r.receive_id as transaction_id, 
    tp.product_image as image,
    tp.sku,
    tp.barcode,
    u.name AS created_by, 
    r.created_at, 
    r.receive_qty as quantity, 
    r.expiry_date,
    tp.product_name AS product_name,
    'new' as product_type,
    tp.product_category,
    tp.temp_product_id
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
    tp.sku,
    tp.barcode,
    u.name AS created_by,
    ii.created_at,
    ii.issue_qty as quantity,
    ri.expiry_date,
    tp.product_name AS product_name,
    'new' as product_type,
    tp.product_category,
    tp.temp_product_id
FROM issue_items ii
LEFT JOIN receive_items ri ON ii.receive_id = ri.receive_id
LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
LEFT JOIN users u ON ii.issued_by = u.user_id
WHERE COALESCE(poi.temp_product_id, 0) > 0)
ORDER BY created_at DESC LIMIT 50";

try {
    $results = $pdo->query($full_sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "Total rows returned: " . count($results) . "\n";
    echo "\nFirst 5 rows:\n";
    for ($i = 0; $i < min(5, count($results)); $i++) {
        $row = $results[$i];
        echo "Row " . ($i+1) . ": Type=" . $row['transaction_type'] . 
             ", Product=" . $row['product_name'] . 
             ", TempID=" . $row['temp_product_id'] . 
             ", Date=" . $row['created_at'] . "\n";
    }
} catch (Exception $e) {
    echo "Query Error: " . $e->getMessage() . "\n";
    echo "SQL: " . $full_sql . "\n";
}

echo "</pre>";
?>
