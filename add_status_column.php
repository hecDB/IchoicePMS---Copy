<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "🔧 ADDING MISSING 'status' COLUMN TO returned_items\n";
echo "═════════════════════════════════════════════════════════════\n\n";

try {
    // Check if status column exists
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'returned_items' AND COLUMN_NAME = 'status'
    ");
    $checkStmt->execute();
    $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
    
    if ($exists) {
        echo "✓ Column 'status' already exists\n";
    } else {
        echo "Adding 'status' column...\n";
        $pdo->exec("
            ALTER TABLE returned_items 
            ADD COLUMN status VARCHAR(20) DEFAULT 'pending' 
            AFTER reason_name
        ");
        echo "✓ Column 'status' added successfully\n";
        echo "  └─ Type: VARCHAR(20)\n";
        echo "  └─ Default: 'pending'\n";
        echo "  └─ Nullable: YES (can store NULL)\n";
    }
    
    // Verify
    echo "\n📋 Verifying column...\n";
    $verifyStmt = $pdo->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'returned_items' AND COLUMN_NAME = 'status'
    ");
    $verifyStmt->execute();
    $col = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($col) {
        echo "✓ Column verified:\n";
        echo "  Name: {$col['COLUMN_NAME']}\n";
        echo "  Type: {$col['COLUMN_TYPE']}\n";
        echo "  Default: {$col['COLUMN_DEFAULT']}\n";
        echo "  Nullable: {$col['IS_NULLABLE']}\n";
    }
    
    echo "\n═════════════════════════════════════════════════════════════\n";
    echo "✅ SETUP COMPLETE\n";
    echo "═════════════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>
