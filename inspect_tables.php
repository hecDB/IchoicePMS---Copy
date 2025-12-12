<?php
/**
 * ตรวจสอบตารางที่อ้างอิง และแสดงคำแนะนำ
 */

require 'config/db_connect.php';

try {
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif; background-color: #f8fafc;'>";
    echo "<div style='max-width: 1200px; margin: 0 auto;'>";
    
    // ตรวจสอบตารางที่มีอยู่
    $result = $pdo->query("SHOW TABLES");
    $existing_tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📊 ตารางที่มีอยู่ในฐานข้อมูล</h2>";
    echo "<ul>";
    foreach ($existing_tables as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
    
    echo "<hr style='margin: 2rem 0;'>";
    
    // ตรวจสอบแต่ละตาราง
    echo "<h2>🔍 ข้อมูลโครงสร้าง</h2>";
    
    $tables = ['products', 'users', 'return_reasons'];
    
    foreach ($tables as $table) {
        if (!in_array($table, $existing_tables)) {
            echo "<h3 style='color: #d32f2f;'>❌ ตาราง $table ไม่มีอยู่!</h3>";
            continue;
        }
        
        echo "<h3>📋 ตาราง: $table</h3>";
        
        $result = $pdo->query("DESC `$table`");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table style='width: 100%; border-collapse: collapse; margin-bottom: 2rem;'>";
        echo "<tr style='background-color: #667eea; color: white;'>";
        echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Field</th>";
        echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Type</th>";
        echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Null</th>";
        echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Key</th>";
        echo "</tr>";
        
        foreach ($columns as $col) {
            $bg = ($col['Key'] === 'PRI') ? '#fff3e0' : 'white';
            echo "<tr style='background-color: $bg; border-bottom: 1px solid #ddd;'>";
            echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'><strong>{$col['Field']}</strong></td>";
            echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Type']}</td>";
            echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Null']}</td>";
            echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Key']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; color: #d32f2f; font-family: Sarabun, sans-serif;'>";
    echo "<h2>✗ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
