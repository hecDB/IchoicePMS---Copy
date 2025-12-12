<?php
/**
 * ตรวจสอบชื่อ Column Primary Key ของตาราง sales_orders
 */

require 'config/db_connect.php';

try {
    // ตรวจสอบ sales_orders
    $stmt = $pdo->query("SHOW KEYS FROM sales_orders WHERE Key_name = 'PRIMARY'");
    $pk = $stmt->fetch();
    
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif;'>";
    echo "<h2>Sales Orders Primary Key:</h2>";
    echo "<pre style='background: #f5f5f5; padding: 1rem; border-radius: 4px;'>";
    echo "Column: " . $pk['Column_name'] . "\n";
    echo "Seq: " . $pk['Seq_in_index'] . "\n";
    echo "</pre>";
    
    // ตรวจสอบ so_id vs sale_order_id
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'sales_orders' AND TABLE_SCHEMA = 'ichoice_'");
    $columns = $stmt->fetchAll();
    
    echo "<h3>Columns in sales_orders:</h3>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>" . $col['COLUMN_NAME'] . "</li>";
    }
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; color: #d32f2f;'>";
    echo "<h2>✗ Error: " . $e->getMessage() . "</h2>";
    echo "</div>";
}
?>
