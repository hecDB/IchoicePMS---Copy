<?php
require 'config/db_connect.php';

echo "<h2>Debug: ตรวจสอบโครงสร้างตาราง</h2>";

// ตรวจสอบตาราง sales_orders
echo "<h3>📋 ตาราง sales_orders:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE sales_orders");
    $cols = $stmt->fetchAll();
    echo "<pre>";
    print_r($cols);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ตรวจสอบตาราง issue_items
echo "<h3>📋 ตาราง issue_items:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE issue_items");
    $cols = $stmt->fetchAll();
    echo "<pre>";
    print_r($cols);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ลองค้นหาทั่วไป
echo "<h3>🔍 ทดสอบค้นหา issue_tag:</h3>";
try {
    $keyword = "123";
    $stmt = $pdo->prepare("
        SELECT 
            so.sale_order_id,
            so.issue_tag,
            so.created_at,
            COUNT(ii.id) as total_items
        FROM sales_orders so
        LEFT JOIN issue_items ii ON so.sale_order_id = ii.sale_order_id
        WHERE so.issue_tag LIKE :keyword
        GROUP BY so.sale_order_id
        LIMIT 5
    ");
    
    $stmt->execute([':keyword' => "%{$keyword}%"]);
    $results = $stmt->fetchAll();
    
    echo "<pre>";
    print_r($results);
    echo "</pre>";
    
    echo "จำนวนผลลัพธ์: " . count($results);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
