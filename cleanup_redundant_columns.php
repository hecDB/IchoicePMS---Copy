<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "🗑️  REMOVING REDUNDANT COLUMNS FROM returned_items\n";
echo "═════════════════════════════════════════════════════════════\n\n";

try {
    // Note: ALTER TABLE auto-commits in MySQL, so we can't use transactions
    
    // First, drop foreign keys and indexes that might reference these columns
    echo "🔄 Removing constraints and indexes...\n";
    
    // Drop foreign key for approved_by
    $pdo->exec("ALTER TABLE returned_items DROP FOREIGN KEY fk_returned_items_approved_by");
    echo "   ✓ Dropped FK fk_returned_items_approved_by\n";
    
    // Drop indexes
    try {
        $pdo->exec("ALTER TABLE returned_items DROP INDEX idx_approved_by");
        echo "   ✓ Dropped INDEX idx_approved_by\n";
    } catch (Exception $e) {
        echo "   ℹ️  INDEX idx_approved_by doesn't exist\n";
    }

    // List of columns to drop
    $columnsToDrop = [
        'so_id',
        'issue_tag',
        'location_id',
        'approved_by',
        'approved_at',
        'condition_detail'
    ];

    echo "\n📋 COLUMNS TO DROP:\n";
    foreach ($columnsToDrop as $col) {
        echo "   $col\n";
    }
    echo "\n";

    echo "🔄 Dropping columns...\n";
    
    foreach ($columnsToDrop as $col) {
        // Check if column exists first
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'returned_items' AND COLUMN_NAME = ?
        ");
        $checkStmt->execute([$col]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
        
        if ($exists) {
            $pdo->exec("ALTER TABLE returned_items DROP COLUMN $col");
            echo "   ✓ Dropped $col\n";
        } else {
            echo "   ℹ️  Column $col doesn't exist, skipping\n";
        }
    }

    // $pdo->commit();

    echo "\n✅ COLUMNS DROPPED SUCCESSFULLY\n\n";

    // Verify new structure
    echo "───────────────────────────────────────────────────────────\n";
    echo "📊 UPDATED STRUCTURE:\n";
    echo "───────────────────────────────────────────────────────────\n\n";

    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'returned_items'
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute();
    $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "✓ Total columns now: " . count($newColumns) . " (was 41, reduced by 6)\n\n";

    echo "📋 REMAINING COLUMNS:\n";
    $groups = [
        'IDs' => ['return_id', 'po_id', 'item_id', 'product_id', 'temp_product_id', 'receive_id'],
        'PRODUCT INFO' => ['product_name', 'sku', 'barcode', 'original_qty', 'return_qty'],
        'RETURN REFS' => ['return_code', 'reason_id', 'reason_name', 'is_returnable', 'return_status', 'return_from_sales'],
        'RETURN DETAILS' => ['image_path', 'notes', 'expiry_date'],
        'AUDIT' => ['created_by', 'created_at', 'updated_at'],
        'INSPECTION' => ['status', 'new_sku', 'new_product_id', 'cost_price', 'sale_price', 'restock_qty', 'defect_notes', 'inspected_by', 'inspected_at', 'restocked_by', 'restocked_at']
    ];

    $colNames = array_column($newColumns, 'COLUMN_NAME');
    
    foreach ($groups as $group => $cols) {
        $available = array_filter($cols, fn($c) => in_array($c, $colNames));
        if ($available) {
            echo "\n  $group:\n";
            foreach ($available as $col) {
                echo "    ✓ $col\n";
            }
        }
    }

    echo "\n═════════════════════════════════════════════════════════════\n";
    echo "✅ CLEANUP COMPLETE\n";
    echo "═════════════════════════════════════════════════════════════\n\n";

    echo "📌 CHANGES SUMMARY:\n";
    echo "   Dropped: so_id, issue_tag, location_id,\n";
    echo "            approved_by, approved_at, condition_detail\n";
    echo "   Columns reduced: 41 → 35\n";
    echo "   Benefits:\n";
    echo "   • No more unused column clutter\n";
    echo "   • Cleaner data model\n";
    echo "   • Easier to maintain\n";
    echo "   • Improved query performance\n";

} catch (Exception $e) {
    // Don't rollback - ALTER TABLE already committed
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>
