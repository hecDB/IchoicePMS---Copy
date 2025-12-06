<?php
/**
 * Script to add missing columns to purchase_order_items table
 * This fixes the "Unknown column 'poi.is_cancelled'" error
 */

require 'config/db_connect.php';

try {
    echo "Starting database schema update...\n\n";
    
    // Check if columns exist
    $sql_check = "SHOW COLUMNS FROM purchase_order_items WHERE Field IN ('is_cancelled', 'is_partially_cancelled', 'cancelled_by', 'cancelled_at', 'cancel_qty', 'cancel_reason', 'cancel_notes')";
    $stmt = $pdo->query($sql_check);
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    echo "Existing columns: " . implode(', ', $existing_columns) . "\n\n";
    
    // Add is_cancelled column if missing
    if (!in_array('is_cancelled', $existing_columns)) {
        echo "Adding column: is_cancelled\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN is_cancelled TINYINT(1) DEFAULT 0 AFTER qty");
        echo "✓ Column 'is_cancelled' added successfully\n\n";
    } else {
        echo "✓ Column 'is_cancelled' already exists\n\n";
    }
    
    // Add is_partially_cancelled column if missing
    if (!in_array('is_partially_cancelled', $existing_columns)) {
        echo "Adding column: is_partially_cancelled\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN is_partially_cancelled TINYINT(1) DEFAULT 0 AFTER is_cancelled");
        echo "✓ Column 'is_partially_cancelled' added successfully\n\n";
    } else {
        echo "✓ Column 'is_partially_cancelled' already exists\n\n";
    }
    
    // Add cancel_qty column if missing
    if (!in_array('cancel_qty', $existing_columns)) {
        echo "Adding column: cancel_qty\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN cancel_qty INT DEFAULT 0 AFTER is_partially_cancelled");
        echo "✓ Column 'cancel_qty' added successfully\n\n";
    } else {
        echo "✓ Column 'cancel_qty' already exists\n\n";
    }
    
    // Add cancelled_by column if missing
    if (!in_array('cancelled_by', $existing_columns)) {
        echo "Adding column: cancelled_by\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN cancelled_by INT AFTER cancel_qty");
        echo "✓ Column 'cancelled_by' added successfully\n\n";
    } else {
        echo "✓ Column 'cancelled_by' already exists\n\n";
    }
    
    // Add cancelled_at column if missing
    if (!in_array('cancelled_at', $existing_columns)) {
        echo "Adding column: cancelled_at\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN cancelled_at DATETIME AFTER cancelled_by");
        echo "✓ Column 'cancelled_at' added successfully\n\n";
    } else {
        echo "✓ Column 'cancelled_at' already exists\n\n";
    }
    
    // Add cancel_reason column if missing
    if (!in_array('cancel_reason', $existing_columns)) {
        echo "Adding column: cancel_reason\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN cancel_reason VARCHAR(100) AFTER cancelled_at");
        echo "✓ Column 'cancel_reason' added successfully\n\n";
    } else {
        echo "✓ Column 'cancel_reason' already exists\n\n";
    }
    
    // Add cancel_notes column if missing
    if (!in_array('cancel_notes', $existing_columns)) {
        echo "Adding column: cancel_notes\n";
        $pdo->exec("ALTER TABLE purchase_order_items ADD COLUMN cancel_notes TEXT AFTER cancel_reason");
        echo "✓ Column 'cancel_notes' added successfully\n\n";
    } else {
        echo "✓ Column 'cancel_notes' already exists\n\n";
    }
    
    // Verify all columns exist
    echo "\n=== Final Verification ===\n";
    $sql_final = "SHOW COLUMNS FROM purchase_order_items";
    $stmt_final = $pdo->query($sql_final);
    echo "All columns in purchase_order_items:\n";
    while ($row = $stmt_final->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    echo "\n✓ Database schema update completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
