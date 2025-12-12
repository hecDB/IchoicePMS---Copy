<?php
/**
 * ตรวจสอบรายละเอียดประเภทข้อมูลของ Foreign Key
 */

require 'config/db_connect.php';

try {
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif; background-color: #f8fafc;'>";
    echo "<div style='max-width: 1200px; margin: 0 auto;'>";
    
    echo "<h2>🔍 ตรวจสอบประเภทข้อมูล Foreign Keys</h2>";
    
    $tables_info = [];
    
    // ตรวจสอบ products
    echo "<h3 style='margin-top: 2rem; color: #667eea;'>📊 ตาราง: products</h3>";
    $stmt = $pdo->query("DESC products");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background-color: #667eea; color: white;'>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Field</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Type</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Null</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Key</th>";
    echo "</tr>";
    
    $product_id_type = null;
    foreach ($cols as $col) {
        if ($col['Field'] === 'product_id') {
            $product_id_type = $col['Type'];
        }
        $bg = ($col['Key'] === 'PRI') ? '#fff3e0' : 'white';
        echo "<tr style='background-color: $bg;'>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'><strong>{$col['Field']}</strong></td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Type']}</td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Null']}</td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // ตรวจสอบ users
    echo "<h3 style='margin-top: 2rem; color: #667eea;'>📊 ตาราง: users</h3>";
    $stmt = $pdo->query("DESC users");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background-color: #667eea; color: white;'>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Field</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Type</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Null</th>";
    echo "<th style='padding: 0.75rem; text-align: left; border: 1px solid #ddd;'>Key</th>";
    echo "</tr>";
    
    $user_id_type = null;
    foreach ($cols as $col) {
        if ($col['Field'] === 'user_id') {
            $user_id_type = $col['Type'];
        }
        $bg = ($col['Key'] === 'PRI') ? '#fff3e0' : 'white';
        echo "<tr style='background-color: $bg;'>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'><strong>{$col['Field']}</strong></td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Type']}</td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Null']}</td>";
        echo "<td style='padding: 0.75rem; border: 1px solid #ddd;'>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // สรุป
    echo "<div style='background-color: #e3f2fd; padding: 1.5rem; border-radius: 8px; margin-top: 2rem;'>";
    echo "<h3 style='color: #1565c0; margin-top: 0;'>📋 สรุปประเภทข้อมูล</h3>";
    echo "<p><strong>product_id:</strong> " . ($product_id_type ?? 'ไม่พบ') . "</p>";
    echo "<p><strong>user_id:</strong> " . ($user_id_type ?? 'ไม่พบ') . "</p>";
    echo "<p style='margin-bottom: 0;'><strong>reason_id (ที่จะสร้าง):</strong> int(11)</p>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; color: #d32f2f; font-family: Sarabun, sans-serif;'>";
    echo "<h2>✗ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    echo "</div>";
}
?>
