<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Check purchase_order_items for item_id 43</h2>";
echo "<pre>";

$sql = "SELECT * FROM purchase_order_items WHERE item_id = 43";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total records with item_id 43: " . count($rows) . "\n\n";
    
    foreach($rows as $row) {
        echo "item_id: " . $row['item_id'] . "\n";
        echo "  temp_product_id: " . $row['temp_product_id'] . "\n";
        echo "  product_id: " . $row['product_id'] . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบ receive_items ว่า item_id 43 ถูก reference กี่ครั้ง
echo "\n<h2>How many receive_items reference item_id 43?</h2>";
echo "<pre>";

$sql2 = "SELECT receive_id, item_id, receive_qty FROM receive_items WHERE item_id = 43";

try {
    $stmt2 = $pdo->query($sql2);
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total receive_items with item_id 43: " . count($rows2) . "\n\n";
    
    foreach($rows2 as $row) {
        echo "receive_id: " . $row['receive_id'] . " | item_id: " . $row['item_id'] . " | qty: " . $row['receive_qty'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบว่าควรมี item_id กี่ตัว
echo "\n<h2>Check all temp_products with product_name = 'sdfsdfsdf'</h2>";
echo "<pre>";

$sql3 = "SELECT temp_product_id, product_name, provisional_sku FROM temp_products WHERE product_name = 'sdfsdfsdf'";

try {
    $stmt3 = $pdo->query($sql3);
    $rows3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total temp_products: " . count($rows3) . "\n\n";
    
    foreach($rows3 as $row) {
        echo "temp_product_id: " . $row['temp_product_id'] . " | sku: " . ($row['provisional_sku'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

?>
