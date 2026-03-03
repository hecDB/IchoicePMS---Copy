<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "✔️  VALIDATING returned_items DATA MODEL\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Get all columns info
$stmt = $pdo->prepare("
    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'returned_items'
    ORDER BY ORDINAL_POSITION
");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columns, 'COLUMN_NAME');

echo "【 REQUIREMENT 1: สินค้าเดิมที่มีการตรวจสอบว่าชำรุด 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$req1 = [
    'sku' => ['original_product_sku', 'stored in column: sku'],
    'po_id' => ['purchase_order_id', 'stored in column: po_id'],
    'return_qty' => ['quantity_damaged', 'stored in column: return_qty'],
    'cost_price' => ['unit_cost', 'stored in column: cost_price'],
    'expiry_date' => ['product_expiry', 'stored in column: expiry_date'],
    'reason_name' => ['defect_reason', 'stored in column: reason_id (FK to return_reasons)'],
    'defect_notes' => ['damage_notes', 'stored in column: defect_notes'],
    'is_returnable' => ['sellable_flag', 'stored in column: is_returnable (1=sellable, 0=discard)'],
    'created_by' => ['created_by_user', 'stored in column: created_by (FK to users)'],
    'created_at' => ['created_timestamp', 'stored in column: created_at'],
];

$req1Missing = [];
foreach ($req1 as $col => $info) {
    if (in_array($col, $colNames)) {
        echo "✓ {$info[0]}\n  └─ {$info[1]}\n\n";
    } else {
        echo "✗ {$info[0]}\n  └─ MISSING: $col\n\n";
        $req1Missing[] = $col;
    }
}

echo "\n【 REQUIREMENT 2: สินค้าชนิดใหม่ที่มีการตรวจสอบว่าชำรุด 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$req2 = [
    'new_sku' => ['generated_sku_damaged', 'stored in column: new_sku (auto-generated)'],
    'po_id' => ['purchase_order_id', 'stored in column: po_id'],
    'return_qty' => ['quantity_damaged', 'stored in column: return_qty'],
    'cost_price' => ['unit_cost_new', 'stored in column: cost_price'],
    'expiry_date' => ['product_expiry', 'stored in column: expiry_date'],
    'reason_name' => ['defect_reason', 'stored in column: reason_id (FK to return_reasons)'],
    'defect_notes' => ['damage_notes', 'stored in column: defect_notes'],
    'is_returnable' => ['sellable_flag', 'stored in column: is_returnable (1=sellable, 0=discard)'],
    'created_by' => ['created_by_user', 'stored in column: created_by (FK to users)'],
    'created_at' => ['created_timestamp', 'stored in column: created_at'],
    'temp_product_id' => ['new_product_ref', 'stored in column: temp_product_id (for new products pending approval)'],
];

$req2Missing = [];
foreach ($req2 as $col => $info) {
    if (in_array($col, $colNames)) {
        echo "✓ {$info[0]}\n  └─ {$info[1]}\n\n";
    } else {
        echo "✗ {$info[0]}\n  └─ MISSING: $col\n\n";
        $req2Missing[] = $col;
    }
}

echo "\n【 REQUIREMENT 3: เมื่อบันทึกการตรวจสอบ (ขั้นที่ 2) 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

$req3 = [
    'new_sku' => ['new_sku_with_prefix', 'stored in column: new_sku (ตำหนิ-original_sku)'],
    'status' => ['inspection_status', 'stored in column: status (pending/completed)'],
    'inspected_by' => ['inspected_by_user', 'stored in column: inspected_by (FK to users)'],
    'inspected_at' => ['inspection_timestamp', 'stored in column: inspected_at'],
    'defect_notes' => ['inspection_findings', 'stored in column: defect_notes (with [ขายได้]/[ทิ้ง] prefix)'],
    'new_product_id' => ['new_product_created', 'stored in column: new_product_id (if creating defect product)'],
];

$req3Missing = [];
foreach ($req3 as $col => $info) {
    if (in_array($col, $colNames)) {
        echo "✓ {$info[0]}\n  └─ {$info[1]}\n\n";
    } else {
        echo "✗ {$info[0]}\n  └─ MISSING: $col\n\n";
        $req3Missing[] = $col;
    }
}

// Check for barcode generation column
echo "\n【 ADDITIONAL: Barcode Column 】\n";
echo "─────────────────────────────────────────────────────────────\n\n";

if (in_array('barcode', $colNames)) {
    echo "✓ Barcode tracking\n";
    echo "  └─ stored in column: barcode\n\n";
    echo "ℹ️  NOTE: Barcode generation for new defect products\n";
    echo "    should be handled at the application/API level\n";
    echo "    (not required as database column)\n\n";
} else {
    echo "ℹ️  No dedicated barcode column\n";
    echo "    (barcode is tracked but not required for new products)\n\n";
}

// Summary
echo "\n═════════════════════════════════════════════════════════════\n";
echo "📊 VALIDATION SUMMARY\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$allMissing = array_merge($req1Missing, $req2Missing, $req3Missing);
$allMissing = array_unique($allMissing);

if (empty($allMissing)) {
    echo "✅ ALL REQUIREMENTS MET!\n\n";
    echo "The returned_items table fully supports:\n";
    echo "  1. Original damaged product tracking\n";
    echo "  2. New damaged product tracking\n";
    echo "  3. Inspection workflow & status tracking\n";
    echo "  4. Defect SKU generation (ตำหนิ-xxxx)\n";
    echo "  5. Sellable/Discard decision tracking\n";
} else {
    echo "⚠️  MISSING COLUMNS:\n";
    foreach ($allMissing as $col) {
        echo "  • $col\n";
    }
}

// Show sample data
echo "\n═════════════════════════════════════════════════════════════\n";
echo "📋 SAMPLE DATA VERIFICATION\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$sampleStmt = $pdo->prepare("
    SELECT 
        return_id,
        return_code,
        sku,
        product_id,
        po_id,
        return_qty,
        cost_price,
        expiry_date,
        reason_name,
        is_returnable,
        defect_notes,
        new_sku,
        temp_product_id,
        status,
        inspected_by,
        inspected_at,
        created_by,
        created_at
    FROM returned_items
    WHERE reason_id = 8
    LIMIT 1
");
$sampleStmt->execute();
$sample = $sampleStmt->fetch(PDO::FETCH_ASSOC);

if ($sample) {
    echo "Record: {$sample['return_code']}\n\n";
    
    echo "【Original Product Data】\n";
    echo "  SKU: {$sample['sku']}\n";
    echo "  Product ID: {$sample['product_id']}\n";
    echo "  PO ID: {$sample['po_id']}\n";
    echo "  Quantity: {$sample['return_qty']}\n";
    echo "  Cost Price: {$sample['cost_price']}\n";
    echo "  Expiry Date: {$sample['expiry_date']}\n";
    echo "  Reason: {$sample['reason_name']}\n\n";
    
    echo "【Inspection Data】\n";
    echo "  Status: {$sample['status']}\n";
    echo "  New SKU: {$sample['new_sku']}\n";
    echo "  Sellable: " . ($sample['is_returnable'] ? 'Yes (1)' : 'No (0)') . "\n";
    echo "  Defect Notes: " . substr($sample['defect_notes'] ?? '', 0, 60) . "...\n";
    echo "  Inspected By: {$sample['inspected_by']}\n";
    echo "  Inspected At: {$sample['inspected_at']}\n";
    echo "  Temp Product ID: {$sample['temp_product_id']}\n\n";
    
    echo "【Audit Data】\n";
    echo "  Created By: {$sample['created_by']}\n";
    echo "  Created At: {$sample['created_at']}\n";
} else {
    echo "No damaged items found in database yet.\n";
}

echo "\n═════════════════════════════════════════════════════════════\n";
echo "✅ VALIDATION COMPLETE\n";
echo "═════════════════════════════════════════════════════════════\n";

?>
