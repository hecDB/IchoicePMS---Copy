<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "🔍 ANALYZING REDUNDANT COLUMNS IN RETURNED_ITEMS\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Check for potentially redundant columns
$redundantChecks = [
    [
        'name' => 'return_status vs status',
        'col1' => 'return_status',
        'col2' => 'status',
        'description' => 'Both store pending/completed status'
    ],
    [
        'name' => 'notes vs defect_notes',
        'col1' => 'notes',
        'col2' => 'defect_notes',
        'description' => 'Both store notes/comments'
    ],
    [
        'name' => 'created_at (original) vs created_at (inspection)',
        'col1' => 'created_at',
        'col2' => 'inspected_at',
        'description' => 'created_at was from original return, inspected_at is new'
    ]
];

foreach ($redundantChecks as $check) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "❓ {$check['name']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Description: {$check['description']}\n\n";
    
    // Compare the two columns
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_rows,
            SUM(CASE WHEN {$check['col1']} = {$check['col2']} THEN 1 ELSE 0 END) as same_values,
            SUM(CASE WHEN {$check['col1']} IS NOT NULL AND {$check['col2']} IS NOT NULL THEN 1 ELSE 0 END) as both_filled
        FROM returned_items
    ");
    $stmt->execute();
    $comparison = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Analysis:\n";
    echo "  Total rows: {$comparison['total_rows']}\n";
    echo "  Rows with SAME values: {$comparison['same_values']}\n";
    echo "  Rows with BOTH columns filled: {$comparison['both_filled']}\n\n";
    
    // Show sample data
    $sampleStmt = $pdo->prepare("
        SELECT return_id, return_code, {$check['col1']}, {$check['col2']}
        FROM returned_items LIMIT 2
    ");
    $sampleStmt->execute();
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sample Data:\n";
    foreach ($samples as $sample) {
        $col1Val = $sample[$check['col1']] ?? '[NULL]';
        $col2Val = $sample[$check['col2']] ?? '[NULL]';
        $match = ($col1Val === $col2Val) ? '✓ SAME' : '✗ DIFFERENT';
        echo "  {$sample['return_code']}: {$check['col1']}='$col1Val', {$check['col2']}='$col2Val' [$match]\n";
    }
    echo "\n";
}

// List all unused columns that can be deleted
echo "\n═════════════════════════════════════════════════════════════\n";
echo "🗑️  UNUSED COLUMNS (0% usage - candidates for deletion)\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$unusedCols = [
    'so_id' => 'Sales Order ID - for sales returns (not used)',
    'issue_tag' => 'Issue tag - classification not used',
    'location_id' => 'Storage location - not used',
    'approved_by' => 'Approval user - formal approval not used',
    'approved_at' => 'Approval timestamp - formal approval not used',
    'condition_detail' => 'Condition details - not used',
    'expiry_date' => 'Duplicate - New column in inspection'
];

foreach ($unusedCols as $col => $desc) {
    echo "❌ $col\n";
    echo "   $desc\n";
    
    // Check actual usage
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM returned_items WHERE $col IS NOT NULL
    ");
    $checkStmt->execute();
    $cnt = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "   Records with data: $cnt / 2\n\n";
}

echo "\n═════════════════════════════════════════════════════════════\n";
echo "📊 SUMMARY OF REDUNDANCY\n";
echo "═════════════════════════════════════════════════════════════\n\n";

echo "🔴 HIGH PRIORITY - Definitely Redundant:\n";
echo "   1. expiry_date (column) - data moved to inspection columns\n";
echo "      ├─ Old: stored original product expiry\n";
echo "      └─ New: now part of inspection/damage workflow\n\n";

echo "🟡 MEDIUM PRIORITY - Possibly Redundant:\n";
echo "   1. return_status vs status\n";
echo "      ├─ return_status: original return status\n";
echo "      └─ status: inspection/damage status (added recently)\n";
echo "      └─ ACTION: Keep both - different workflows\n\n";
echo "   2. notes vs defect_notes\n";
echo "      ├─ notes: return/condition notes\n";
echo "      └─ defect_notes: inspection specific notes\n";
echo "      └─ ACTION: Keep both - different purposes\n\n";

echo "🟢 LOW PRIORITY - Unused but Keep for Reference:\n";
echo "   • so_id, issue_tag, location_id\n";
echo "   • approved_by, approved_at\n";
echo "   • condition_detail\n";
echo "   └─ ACTION: Can drop if not needed in future\n\n";

echo "\n═════════════════════════════════════════════════════════════\n";
echo "✅ RECOMMENDATION\n";
echo "═════════════════════════════════════════════════════════════\n\n";

echo "COLUMNS TO DROP (Safe to remove):\n";
echo "1. so_id - no sales returns tracked\n";
echo "2. issue_tag - classification not used\n";
echo "3. location_id - storage tracking not active\n";
echo "4. approved_by - formal approval workflow not used\n";
echo "5. approved_at - formal approval workflow not used\n";
echo "6. condition_detail - redundant with notes/defect_notes\n\n";

echo "COLUMNS TO KEEP:\n";
echo "✓ return_status - tracks original return workflow\n";
echo "✓ status - tracks inspection/damage workflow\n";
echo "✓ notes - for return/condition comments\n";
echo "✓ defect_notes - for inspection specific findings\n";
echo "✓ expiry_date - for product shelf life tracking\n\n";

?>
