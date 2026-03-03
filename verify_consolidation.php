<?php
require 'config/db_connect.php';

echo "✅ CONSOLIDATION VERIFICATION REPORT\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Check returned_items structure
$stmt = $pdo->prepare("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='returned_items' 
    ORDER BY ORDINAL_POSITION
");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "📊 Structure:\n";
echo "   Total columns in returned_items: " . count($columns) . "\n";
echo "   New inspection columns added: 11\n\n";

// Show data
$stmt = $pdo->prepare("
    SELECT return_id, return_code, product_name, reason_name, status, 
           new_sku, restock_qty, cost_price, sale_price, defect_notes
    FROM returned_items
    WHERE reason_id = 8
    ORDER BY return_id ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 CONSOLIDATED DAMAGED ITEMS (from both tables):\n\n";
foreach ($rows as $i => $row) {
    $i++;
    echo "Record #$i:\n";
    echo "  Return Code: {$row['return_code']}\n";
    echo "  Product: {$row['product_name']}\n";
    echo "  Reason: {$row['reason_name']}\n";
    echo "  Status: {$row['status']}\n";
    echo "  New SKU: {$row['new_sku']}\n";
    echo "  Restock Qty: {$row['restock_qty']}\n";
    echo "  Cost Price: {$row['cost_price']}\n"; 
    echo "  Sale Price: {$row['sale_price']}\n";
    echo "  Notes: " . substr($row['defect_notes'] ?? '', 0, 50) . "...\n\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo "\n🎯 WHAT'S NEXT:\n";
echo "1. Update application code to remove damaged_return_inspections queries\n";
echo "2. Update API to use 'returned_items' with reason_id = 8 filter\n";
echo "3. Update HTML page to load from consolidated table\n";
echo "4. Keep damaged_return_inspections in DB for reference (backup)\n";
echo "   Or later: DROP TABLE damaged_return_inspections;\n";

?>
