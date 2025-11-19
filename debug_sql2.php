<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Detailed Debug - receive_items & purchase_order_items</h2>";
echo "<pre>";

// ตรวจสอบ receive_items ที่มี receive_id 33, 32, 24
$sql = "
SELECT 
    r.receive_id,
    r.item_id,
    r.receive_qty,
    r.created_at,
    poi.temp_product_id,
    poi.product_id,
    tp.product_name,
    tp.provisional_sku,
    tp.provisional_barcode
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
WHERE r.receive_id IN (33, 32, 24)
ORDER BY r.receive_id DESC";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total matching receive_items: " . count($rows) . "\n\n";
    
    foreach($rows as $idx => $row) {
        echo "=== receive_id: " . $row['receive_id'] . " ===\n";
        echo "  item_id: " . $row['item_id'] . "\n";
        echo "  temp_product_id (from poi): " . $row['temp_product_id'] . "\n";
        echo "  product_id (from poi): " . $row['product_id'] . "\n";
        echo "  product_name: " . $row['product_name'] . "\n";
        echo "  provisional_sku: " . $row['provisional_sku'] . "\n";
        echo "  provisional_barcode: " . $row['provisional_barcode'] . "\n";
        echo "  receive_qty: " . $row['receive_qty'] . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบ temp_products ที่มี product_name = 'sdfsdfsdf'
echo "\n<h2>temp_products with product_name = 'sdfsdfsdf'</h2>";
echo "<pre>";

$sql2 = "SELECT * FROM temp_products WHERE product_name = 'sdfsdfsdf'";

try {
    $stmt2 = $pdo->query($sql2);
    $products = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total records: " . count($products) . "\n\n";
    
    foreach($products as $p) {
        echo "temp_product_id: " . $p['temp_product_id'] . "\n";
        echo "  product_name: " . $p['product_name'] . "\n";
        echo "  provisional_sku: " . $p['provisional_sku'] . "\n";
        echo "  provisional_barcode: " . $p['provisional_barcode'] . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

?>
