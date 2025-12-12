<?php
/**
 * ตรวจสอบโครงสร้างเฉพาะของ Primary Keys ในตารางทั้งหมด
 */

require 'config/db_connect.php';

try {
    echo "<div style='padding: 2rem; font-family: Sarabun, sans-serif; background-color: #f8fafc;'>";
    echo "<div style='max-width: 1200px; margin: 0 auto;'>";
    echo "<h2>🔍 ตรวจสอบโครงสร้างคอลัมน์ Primary Key</h2>";
    
    // ตารางที่ต้องตรวจสอบ
    $tables_to_check = [
        'products' => 'product_id',
        'users' => 'user_id',
        'return_reasons' => 'reason_id'
    ];
    
    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 2rem;'>";
    
    foreach ($tables_to_check as $table_name => $expected_pk) {
        try {
            // ตรวจสอบ PRIMARY KEY
            $stmt = $pdo->query("SHOW KEYS FROM `$table_name` WHERE Key_name = 'PRIMARY'");
            $pk_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pk_info) {
                echo "<tr style='background-color: #ffcdd2;'>";
                echo "<td colspan='5' style='padding: 1rem; color: #c62828;'>";
                echo "❌ ตาราง <strong>$table_name</strong> ไม่มี PRIMARY KEY";
                echo "</td>";
                echo "</tr>";
                continue;
            }
            
            $actual_pk = $pk_info['Column_name'];
            
            // ตรวจสอบประเภท
            $stmt = $pdo->query("DESC `$table_name` `$actual_pk`");
            $col_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $match = ($actual_pk === $expected_pk) ? '✓' : '❌';
            $bg = ($actual_pk === $expected_pk) ? '#e8f5e9' : '#fff3e0';
            
            echo "<tr style='background-color: $bg; border-bottom: 1px solid #ddd;'>";
            echo "<td style='padding: 1rem; font-weight: bold;'>$match $table_name</td>";
            echo "<td style='padding: 1rem;'>";
            echo "<strong>ชื่อ PK ที่คาดหวัง:</strong> $expected_pk<br>";
            echo "<strong>ชื่อ PK จริง:</strong> $actual_pk";
            echo "</td>";
            echo "<td style='padding: 1rem;'>";
            echo "<strong>ประเภท:</strong> " . $col_info['Type'];
            echo "</td>";
            echo "<td style='padding: 1rem;'>";
            echo "<strong>Null:</strong> " . $col_info['Null'];
            echo "</td>";
            echo "<td style='padding: 1rem;'>";
            echo "<strong>Key:</strong> " . $col_info['Key'];
            echo "</td>";
            echo "</tr>";
            
        } catch (Exception $e) {
            echo "<tr style='background-color: #ffcdd2;'>";
            echo "<td colspan='5' style='padding: 1rem; color: #c62828;'>";
            echo "❌ ข้อผิดพลาด: ตาราง <strong>$table_name</strong> - " . htmlspecialchars($e->getMessage());
            echo "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    echo "</div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 2rem; color: #d32f2f; font-family: Sarabun, sans-serif;'>";
    echo "<h2>✗ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    echo "</div>";
}
?>
