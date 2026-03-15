<?php
/**
 * Migration Script: Add source_type column to temp_products table
 * Purpose: Track whether temp product comes from PO (NewProduct) or Damaged Inspection (Damaged)
 */

require 'config/db_connect.php';

echo "<h2>🔧 Adding source_type column to temp_products table</h2>\n";

try {
    // Check if column already exists
    $checkStmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'temp_products' 
        AND COLUMN_NAME = 'source_type'
    ");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        echo "✅ Column 'source_type' already exists in temp_products table.\n<br>";
    } else {
        // Add source_type column
        $pdo->exec("
            ALTER TABLE temp_products 
            ADD COLUMN source_type VARCHAR(20) NULL 
            COMMENT 'แหล่งที่มาของสินค้าใหม่: NewProduct=จากPO, Damaged=จากตรวจสอบชำรุด' 
            AFTER status
        ");
        
        echo "✅ Successfully added 'source_type' column to temp_products table.\n<br>";
    }
    
    // Update existing records based on remark field (best guess)
    echo "<br><h3>📝 Updating existing records...</h3>\n";
    
    // Update records that have damaged-related remarks
    $updateDamaged = $pdo->exec("
        UPDATE temp_products 
        SET source_type = 'Damaged'
        WHERE source_type IS NULL
        AND (
            remark LIKE '%สินค้าชำรุดบางส่วน%' 
            OR remark LIKE '%damaged%' 
            OR remark LIKE '%Damaged%'
            OR remark LIKE '%ตำหนิ%'
            OR provisional_sku LIKE '%ตำหนิ%'
        )
    ");
    
    echo "✅ Updated {$updateDamaged} damaged inspection records.\n<br>";
    
    // Update remaining NULL records to NewProduct (likely from PO)
    $updateNewProduct = $pdo->exec("
        UPDATE temp_products 
        SET source_type = 'NewProduct'
        WHERE source_type IS NULL
    ");
    
    echo "✅ Updated {$updateNewProduct} new product records (from PO).\n<br>";
    
    // Show statistics
    echo "<br><h3>📊 Statistics</h3>\n";
    $stats = $pdo->query("
        SELECT 
            source_type,
            COUNT(*) as count
        FROM temp_products
        GROUP BY source_type
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Source Type</th><th>Count</th></tr>\n";
    foreach ($stats as $stat) {
        echo "<tr><td>{$stat['source_type']}</td><td>{$stat['count']}</td></tr>\n";
    }
    echo "</table>\n";
    
    echo "<br><h2>✅ Migration completed successfully!</h2>\n";
    echo "<p><strong>Column Structure:</strong></p>";
    echo "<ul>";
    echo "<li><code>NewProduct</code> - สินค้าใหม่จากใบสั่งซื้อ (Purchase Order)</li>";
    echo "<li><code>Damaged</code> - สินค้าชำรุดแบบขายได้ (จากการตรวจสอบ)</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
    error_log("Migration Error: " . $e->getMessage());
}
?>
