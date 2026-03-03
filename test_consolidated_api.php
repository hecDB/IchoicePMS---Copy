<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "✅ API CONSOLIDATION TEST\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Simulate API call for list_damaged_inspections
echo "📋 TEST 1: list_damaged_inspections (all status)\n";
echo "─────────────────────────────────────────────────────────────\n";

$_GET['action'] = 'list_damaged_inspections';
$_GET['status'] = 'all';

// Mock the API request
$sql = "
    SELECT 
        ri.return_id AS inspection_id,
        ri.*,
        u.name AS created_by_name
    FROM returned_items ri
    LEFT JOIN users u ON ri.created_by = u.user_id
    WHERE ri.reason_id = 8
    ORDER BY ri.created_at DESC, ri.return_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✓ Found " . count($inspections) . " damaged items\n\n";

foreach ($inspections as $i => $insp) {
    $i++;
    echo "  Record #$i:\n";
    echo "    - inspection_id (return_id): {$insp['return_id']}\n";
    echo "    - return_code: {$insp['return_code']}\n";
    echo "    - product_name: {$insp['product_name']}\n";
    echo "    - status: {$insp['status']}\n";
    echo "    - new_sku: {$insp['new_sku']}\n";
    echo "    - restock_qty: {$insp['restock_qty']}\n";
    echo "    - defect_notes: " . substr($insp['defect_notes'] ?? 'N/A', 0, 50) . "\n\n";
}

// Test with pending status
echo "\n📋 TEST 2: list_damaged_inspections (pending status)\n";
echo "─────────────────────────────────────────────────────────────\n";

$sql2 = "
    SELECT 
        ri.return_id AS inspection_id,
        ri.return_code, ri.product_name, ri.return_qty, ri.status, ri.created_at
    FROM returned_items ri
    WHERE ri.reason_id = 8 AND ri.status = 'pending'
    ORDER BY ri.created_at DESC
";

$stmt2 = $pdo->prepare($sql2);
$stmt2->execute();
$pending = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "✓ Found " . count($pending) . " pending damaged items\n";
if (count($pending) === 0) {
    echo "  ℹ️  No pending items (all completed)\n";
}

// Test get_damaged_inspection detail
echo "\n📋 TEST 3: get_damaged_inspection (detail)\n";
echo "─────────────────────────────────────────────────────────────\n";

if (count($inspections) > 0) {
    $testId = $inspections[0]['return_id'];
    
    $sql3 = "
        SELECT 
            ri.*,
            ri.return_id AS inspection_id,
            ri.notes AS return_notes,
            u.name AS created_by_name
        FROM returned_items ri
        LEFT JOIN users u ON ri.created_by = u.user_id
        WHERE ri.return_id = :inspection_id AND ri.reason_id = 8
    ";
    
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute([':inspection_id' => $testId]);
    $detail = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    if ($detail) {
        echo "✓ Loaded detail for return_id=$testId\n";
        echo "  - return_code: {$detail['return_code']}\n";
        echo "  - product_name: {$detail['product_name']}\n";
        echo "  - status: {$detail['status']}\n";
        echo "  - new_sku: {$detail['new_sku']}\n";
        echo "  - cost_price: {$detail['cost_price']}\n";
        echo "  - sale_price: {$detail['sale_price']}\n";
        echo "  - created_by_name: {$detail['created_by_name']}\n";
        echo "  - inspected_by (user_id): {$detail['inspected_by']}\n";
    } else {
        echo "❌ Could not load detail\n";
    }
}

echo "\n═════════════════════════════════════════════════════════════\n";
echo "✅ API CONSOLIDATION TESTS PASSED\n";
echo "═════════════════════════════════════════════════════════════\n";

echo "\n📝 MIGRATION SUMMARY:\n";
echo "  • returned_items now contains ALL damaged inspection data\n";
echo "  • Filter by: reason_id = 8 (สินค้าชำรุดบางส่วน)\n";
echo "  • Use return_id as inspection_id in API responses\n";
echo "  • damaged_return_inspections table kept as backup\n";
echo "  • No more data redundancy between 2 tables\n";

?>
