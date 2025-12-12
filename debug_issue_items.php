<?php
require 'config/db_connect.php';

echo "<h2>Debug: ตรวจสอบโครงสร้าง issue_items</h2>";

// ดูโครงสร้าง
echo "<h3>📋 โครงสร้าง issue_items:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE issue_items");
    $cols = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($cols as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . ($col['Key'] ?: '-') . "</td>";
        echo "<td>" . ($col['Default'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ดูข้อมูลของเลขแท็ค 12345678945676
echo "<h3>📊 ข้อมูลของ issue_tag = '12345678945676':</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            so.sale_order_id,
            so.issue_tag,
            so.created_at,
            COUNT(ii.issue_id) as item_count
        FROM sales_orders so
        LEFT JOIN issue_items ii ON so.sale_order_id = ii.sale_order_id
        WHERE so.issue_tag = :tag
        GROUP BY so.sale_order_id
    ");
    $stmt->execute([':tag' => '12345678945676']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // ดูรายละเอียดสินค้า
        echo "<h4>🔍 สินค้าที่เชื่อมต่อ (sale_order_id = " . $result['sale_order_id'] . "):</h4>";
        $stmt = $pdo->prepare("
            SELECT 
                ii.issue_id,
                ii.sale_order_id,
                ii.product_id,
                ii.issue_qty,
                p.name as product_name,
                p.sku
            FROM issue_items ii
            LEFT JOIN products p ON ii.product_id = p.product_id
            WHERE ii.sale_order_id = :so_id
        ");
        $stmt->execute([':so_id' => $result['sale_order_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            echo "<p style='color: red;'><strong>⚠️ ไม่พบสินค้า (issue_items) ที่เชื่อมกับ sale_order_id = " . $result['sale_order_id'] . "</strong></p>";
        } else {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>issue_id</th><th>sale_order_id</th><th>product_id</th><th>issue_qty</th><th>product_name</th><th>sku</th></tr>";
            foreach ($items as $item) {
                echo "<tr>";
                echo "<td>" . $item['issue_id'] . "</td>";
                echo "<td>" . ($item['sale_order_id'] ?? 'NULL') . "</td>";
                echo "<td>" . $item['product_id'] . "</td>";
                echo "<td>" . $item['issue_qty'] . "</td>";
                echo "<td>" . ($item['product_name'] ?? '-') . "</td>";
                echo "<td>" . $item['sku'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'><strong>⚠️ ไม่พบ sales_orders ที่มี issue_tag = '12345678945676'</strong></p>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
