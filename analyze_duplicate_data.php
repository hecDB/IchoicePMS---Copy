<?php
require 'config/db_connect.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "ANALYSIS: DUPLICATE DATA IN damaged_return_inspections vs returned_items\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Count records
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM returned_items WHERE reason_name LIKE '%ชำรุด%'");
$stmt->execute();
$ri_count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM damaged_return_inspections");
$stmt->execute();
$di_count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

echo "📊 RECORD COUNT:\n";
echo "   returned_items (damaged): $ri_count records\n";
echo "   damaged_return_inspections: $di_count records\n\n";

// Show columns comparison
echo "📋 DUPLICATE COLUMNS (ข้อมูลซ้ำในอั้งค):\n";
$duplicate_cols = [
    'return_id', 'return_code', 'product_id', 'product_name', 'sku', 'barcode',
    'receive_id', 'po_id', 'po_number', 'return_qty', 'reason_id', 'reason_name',
    'expiry_date', 'created_by', 'created_at'
];
foreach ($duplicate_cols as $col) {
    echo "   ✗ $col\n";
}

echo "\n🆕 UNIQUE COLUMNS IN returned_items:\n";
$unique_ri = ['so_id', 'issue_tag', 'item_id', 'temp_product_id', 'original_qty', 
              'is_returnable', 'return_status', 'return_from_sales', 'condition_detail', 
              'location_id', 'approved_by', 'approved_at'];
foreach ($unique_ri as $col) {
    echo "   ✓ $col\n";
}

echo "\n🔧 UNIQUE COLUMNS IN damaged_return_inspections:\n";
$unique_di = ['status', 'new_sku', 'new_product_id', 'cost_price', 'sale_price',
              'restock_qty', 'defect_notes', 'inspected_by', 'inspected_at',
              'restocked_by', 'restocked_at'];
foreach ($unique_di as $col) {
    echo "   ✓ $col\n";
}

echo "\n\n═══════════════════════════════════════════════════════════════\n";
echo "SOLUTION RECOMMENDATION:\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "❌ CURRENT ISSUE:\n";
echo "   - Data is split across 2 tables causing redundancy\n";
echo "   - Hard to maintain data consistency\n";
echo "   - Need to update both tables when modifying records\n";
echo "   - Foreign key creates dependency issues\n\n";

echo "✅ RECOMMENDED SOLUTION:\n";
echo "   Consolidate into a SINGLE 'returned_items' table with:\n";
echo "   1. Add columns from damaged_return_inspections to returned_items\n";
echo "   2. Use return_code/return_id correctly (currently mismatched!)\n";
echo "   3. Migrate all data from damaged_return_inspections\n";
echo "   4. Drop damaged_return_inspections table\n\n";

echo "📌 DATA INTEGRITY ISSUE:\n";
$stmt = $pdo->prepare("
    SELECT di.inspection_id, di.return_id, di.return_code as di_code,
           ri.return_id as ri_return_id, ri.return_code as ri_code
    FROM damaged_return_inspections di
    LEFT JOIN returned_items ri ON di.return_id = ri.return_id
    WHERE di.return_code != ri.return_code
");
$stmt->execute();
$mismatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($mismatches)) {
    echo "   ⚠️  Found " . count($mismatches) . " return_code mismatches:\n";
    foreach ($mismatches as $m) {
        echo "   - inspection_id {$m['inspection_id']}: return_id={$m['return_id']}\n";
        echo "     damaged_return_inspections.return_code: {$m['di_code']}\n";
        echo "     returned_items.return_code: {$m['ri_code']}\n";
    }
}

echo "\n";
?>
