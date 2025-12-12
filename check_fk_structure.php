<?php
/**
 * ตรวจสอบโครงสร้างตาราง Foreign Key
 */

require 'config/db_connect.php';

try {
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif; background-color: #f8fafc;'>";
    echo "<div style='max-width: 1000px; margin: 0 auto;'>";
    echo "<h2>🔍 ตรวจสอบโครงสร้างตาราง</h2>";
    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 2rem;'>";
    
    // ตรวจสอบตารางที่อ้างอิง
    $tables_to_check = [
        'purchase_orders' => 'po_id',
        'sales_orders' => 'sale_order_id',
        'products' => 'product_id',
        'return_reasons' => 'reason_id',
        'users' => 'user_id'
    ];
    
    foreach ($tables_to_check as $table => $pk_col) {
        echo "<tr style='background-color: #667eea; color: white;'>";
        echo "<td colspan='4' style='padding: 0.75rem; font-weight: bold;'>📊 ตาราง: " . $table . "</td>";
        echo "</tr>";
        
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            
            echo "<tr style='background-color: #e3f2fd;'>";
            echo "<th style='padding: 0.75rem; border: 1px solid #ddd; text-align: left;'>Column</th>";
            echo "<th style='padding: 0.75rem; border: 1px solid #ddd; text-align: left;'>Type</th>";
            echo "<th style='padding: 0.75rem; border: 1px solid #ddd; text-align: left;'>Null</th>";
            echo "<th style='padding: 0.75rem; border: 1px solid #ddd; text-align: left;'>Key</th>";
            echo "</tr>";
            
            foreach ($columns as $col) {
                $key_class = $col['Key'] === 'PRI' ? 'style="background-color: #fff3e0; font-weight: bold;"' : '';
                echo "<tr $key_class>";
                echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Field']}</td>";
                echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Type']}</td>";
                echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Null']}</td>";
                echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Key']}</td>";
                echo "</tr>";
            }
        } catch (Exception $e) {
            echo "<tr style='background-color: #ffebee;'>";
            echo "<td colspan='4' style='padding: 0.75rem; color: #c62828;'>";
            echo "❌ ตาราง <strong>$table</strong> ไม่มีอยู่หรือไม่สามารถเข้าถึง";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "<tr><td colspan='4' style='height: 1rem;'></td></tr>";
    }
    
    echo "</table>";
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; color: #d32f2f;'>";
    echo "<h2>✗ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
