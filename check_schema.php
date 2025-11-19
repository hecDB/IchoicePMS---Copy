<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Check temp_products Table Structure</h2>";
echo "<pre>";

$sql = "DESCRIBE temp_products";

try {
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($columns as $col) {
        echo "Field: " . $col['Field'] . " | Type: " . $col['Type'] . " | Null: " . $col['Null'] . " | Key: " . $col['Key'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบ foreign key constraints
echo "\n<h2>Check Foreign Key Constraints</h2>";
echo "<pre>";

$sql_fk = "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
           FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
           WHERE TABLE_NAME = 'temp_products' AND REFERENCED_TABLE_NAME IS NOT NULL";

try {
    $stmt_fk = $pdo->query($sql_fk);
    $fks = $stmt_fk->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($fks)) {
        echo "No foreign keys found\n";
    } else {
        foreach($fks as $fk) {
            echo "Constraint: " . $fk['CONSTRAINT_NAME'] . "\n";
            echo "  Column: " . $fk['COLUMN_NAME'] . " → " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบ purchase_orders table
echo "\n<h2>Check purchase_orders Table</h2>";
echo "<pre>";

$sql_po = "SELECT COUNT(*) as count FROM purchase_orders";

try {
    $stmt_po = $pdo->query($sql_po);
    $po_count = $stmt_po->fetch(PDO::FETCH_ASSOC);
    echo "Total purchase_orders: " . $po_count['count'] . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

?>
