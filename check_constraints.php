<?php
require 'config/db_connect.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "🔍 CHECKING FOREIGN KEYS AND CONSTRAINTS\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Check for foreign keys
$stmt = $pdo->prepare("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'returned_items' AND CONSTRAINT_NAME != 'PRIMARY'
");
$stmt->execute();
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 FOREIGN KEYS in returned_items:\n\n";
foreach ($fks as $fk) {
    echo "Constraint: {$fk['CONSTRAINT_NAME']}\n";
    echo "  Column: {$fk['COLUMN_NAME']}\n";
    echo "  References: {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']})\n\n";
}

// Check for indexes
$stmt2 = $pdo->prepare("
    SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_NAME = 'returned_items' AND INDEX_NAME != 'PRIMARY'
    ORDER BY INDEX_NAME, COLUMN_NAME
");
$stmt2->execute();
$indexes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "\n📊 INDEXES in returned_items:\n\n";
foreach ($indexes as $idx) {
    echo "Index: {$idx['INDEX_NAME']} on {$idx['COLUMN_NAME']}\n";
}

?>
