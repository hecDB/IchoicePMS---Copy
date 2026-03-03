<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "🔧 FIXING returned_items TABLE SCHEMA\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$fixes = [
    "DROP COLUMN return_status" => "Remove old return_status column (keep new 'status')",
    "DROP COLUMN po_number" => "Remove redundant po_number column",
    "DROP COLUMN receive_id" => "Remove unused receive_id column",
    "DROP COLUMN image_path" => "Remove unused image_path column",
    "DROP COLUMN new_bracode" => "Remove typo column new_bracode",
    "ADD COLUMN new_barcode VARCHAR(100) NULL AFTER new_sku" => "Add correct new_barcode column"
];

foreach ($fixes as $sql_part => $description) {
    try {
        echo "▶ $description\n";
        $pdo->exec("ALTER TABLE returned_items $sql_part");
        echo "  ✓ Success\n";
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "═════════════════════════════════════════════════════════════\n";
echo "✓ VERIFYING FIXED SCHEMA\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$stmt = $pdo->query("DESCRIBE returned_items");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$required_columns = [
    'return_id', 'return_code', 'po_id', 'item_id', 'product_id', 'temp_product_id',
    'product_name', 'sku', 'barcode', 'original_qty', 'return_qty', 'reason_id', 'reason_name',
    'status', 'is_returnable', 'return_from_sales', 'notes', 'expiry_date',
    'new_sku', 'new_barcode', 'new_product_id', 'cost_price', 'sale_price', 'restock_qty',
    'defect_notes', 'inspected_by', 'inspected_at', 'restocked_by', 'restocked_at',
    'created_by', 'created_at', 'updated_at'
];

$found_columns = [];
foreach ($columns as $col) {
    $found_columns[] = $col['Field'];
    echo "✓ " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n═════════════════════════════════════════════════════════════\n";
echo "✓ Total columns: " . count($columns) . " (Expected: 34)\n";

// Check for any columns that shouldn't be there
$extra_columns = array_diff($found_columns, $required_columns);
if (!empty($extra_columns)) {
    echo "\n⚠ Extra columns found:\n";
    foreach ($extra_columns as $col) {
        echo "  - $col\n";
    }
} else {
    echo "✓ No extra columns\n";
}

// Check for missing columns
$missing_columns = array_diff($required_columns, $found_columns);
if (!empty($missing_columns)) {
    echo "\n⚠ Missing columns:\n";
    foreach ($missing_columns as $col) {
        echo "  - $col\n";
    }
} else {
    echo "✓ All required columns present\n";
}

echo "\n═════════════════════════════════════════════════════════════\n";
?>
