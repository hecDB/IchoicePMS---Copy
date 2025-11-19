<?php
session_start();
require '../config/db_connect.php';

echo "<h2>Database Schema Check</h2>";
echo "<pre>";

// Check temp_products columns
echo "=== TEMP_PRODUCTS TABLE COLUMNS ===\n";
$sql = "DESCRIBE temp_products";
try {
    $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Check purchase_order_items columns
echo "\n=== PURCHASE_ORDER_ITEMS TABLE COLUMNS ===\n";
$sql = "DESCRIBE purchase_order_items";
try {
    $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Check products table (original)
echo "\n=== PRODUCTS TABLE COLUMNS (For Reference) ===\n";
$sql = "DESCRIBE products";
try {
    $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Check receive_items columns
echo "\n=== RECEIVE_ITEMS TABLE COLUMNS ===\n";
$sql = "DESCRIBE receive_items";
try {
    $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
