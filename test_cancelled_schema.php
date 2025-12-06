<?php
require 'config/db_connect.php';

echo "<h2>Database Schema Check</h2>";
echo "<pre>";

// Check purchase_order_items columns
echo "=== PURCHASE_ORDER_ITEMS TABLE COLUMNS ===\n";
$sql = "DESCRIBE purchase_order_items";
try {
    $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n\n=== TESTING CANCELLED ITEMS QUERY ===\n";
$sql_test = "
    SELECT 
        poi.item_id,
        poi.po_id,
        po.po_number,
        s.name as supplier_name,
        COALESCE(poi.unit, 'หน่วย') as unit_name
    FROM purchase_order_items poi
    LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE (poi.is_cancelled = 1 OR poi.is_partially_cancelled = 1)
    LIMIT 1
";

try {
    $results = $pdo->query($sql_test)->fetchAll(PDO::FETCH_ASSOC);
    echo "Query successful: " . count($results) . " rows\n";
    if (!empty($results)) {
        print_r($results[0]);
    } else {
        echo "No cancelled items found\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
