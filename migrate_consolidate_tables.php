<?php
require 'config/db_connect.php';

echo "🔧 MIGRATION: Consolidating returned_items tables\n";
echo "═════════════════════════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();

    // Step 1: Add columns to returned_items
    echo "STEP 1: Adding inspection columns to returned_items...\n";
    
    $columnsToAdd = [
        'status VARCHAR(20) DEFAULT "pending"',
        'new_sku VARCHAR(100)',
        'new_product_id INT(11)',
        'cost_price DECIMAL(12,2)',
        'sale_price DECIMAL(12,2)',
        'restock_qty DECIMAL(12,2)',
        'defect_notes LONGTEXT',
        'inspected_by INT(11)',
        'inspected_at DATETIME',
        'restocked_by INT(11)',
        'restocked_at DATETIME'
    ];
    
    foreach ($columnsToAdd as $colDef) {
        $colName = explode(' ', $colDef)[0];
        
        // Check if column already exists
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'returned_items' AND COLUMN_NAME = ?
        ");
        $checkStmt->execute([$colName]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
        
        if (!$exists) {
            $pdo->exec("ALTER TABLE returned_items ADD COLUMN $colDef");
            echo "   ✓ Added $colName\n";
        } else {
            echo "   ℹ️  $colName already exists\n";
        }
    }
    
    // Step 2: Migrate data from damaged_return_inspections
    echo "\nSTEP 2: Migrating data from damaged_return_inspections...\n";
    
    $migrateStmt = $pdo->prepare("
        UPDATE returned_items ri
        INNER JOIN damaged_return_inspections di ON ri.return_id = di.return_id
        SET 
            ri.status = di.status,
            ri.new_sku = di.new_sku,
            ri.new_product_id = di.new_product_id,
            ri.cost_price = COALESCE(di.cost_price, ri.cost_price),
            ri.sale_price = COALESCE(di.sale_price, ri.sale_price),
            ri.restock_qty = di.restock_qty,
            ri.defect_notes = di.defect_notes,
            ri.inspected_by = di.inspected_by,
            ri.inspected_at = di.inspected_at,
            ri.restocked_by = di.restocked_by,
            ri.restocked_at = di.restocked_at
        WHERE ri.reason_id = 8
    ");
    $migrateStmt->execute();
    $affectedRows = $migrateStmt->rowCount();
    echo "   ✓ Updated $affectedRows records\n";
    
    // Step 3: Check migrated data
    echo "\nSTEP 3: Verifying migrated data...\n";
    $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN status IS NOT NULL THEN 1 ELSE 0 END) as with_status
        FROM returned_items
        WHERE reason_id = 8
    ");
    $verifyStmt->execute();
    $verify = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Total damaged items: {$verify['total']}\n";
    echo "   ✓ Items with inspection status: {$verify['with_status']}\n";
    
    // Step 4: Show sample migrated data
    echo "\nSTEP 4: Sample migrated records:\n";
    $sampleStmt = $pdo->prepare("
        SELECT return_id, return_code, product_name, status, new_sku, restock_qty, 
               defect_notes FROM returned_items 
        WHERE reason_id = 8 AND status IS NOT NULL
        LIMIT 2
    ");
    $sampleStmt->execute();
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($samples as $sample) {
        echo json_encode($sample, JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ MIGRATION COMPLETED SUCCESSFULLY\n";
    echo "═════════════════════════════════════════════════════════\n";
    echo "\n⚠️  NEXT STEPS:\n";
    echo "1. Update application code to use returned_items instead of damaged_return_inspections\n";
    echo "2. Test thoroughly with returned_items data\n";
    echo "3. Drop damaged_return_inspections table: DROP TABLE damaged_return_inspections;\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
