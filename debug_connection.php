<?php
require 'config/db_connect.php';

echo "<h2>Debug: ตรวจสอบความเชื่อมระหว่าง sales_orders ↔ issue_items</h2>";

// ดูข้อมูล sales_orders ทั้งหมด
echo "<h3>📊 ข้อมูล sales_orders:</h3>";
try {
    $stmt = $pdo->query("
        SELECT 
            so.sale_order_id,
            so.issue_tag,
            so.created_at,
            COUNT(ii.id) as item_count
        FROM sales_orders so
        LEFT JOIN issue_items ii ON so.sale_order_id = ii.sale_order_id
        GROUP BY so.sale_order_id
        ORDER BY so.created_at DESC
        LIMIT 10
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>sale_order_id</th><th>issue_tag</th><th>created_at</th><th>จำนวนสินค้า</th></tr>";
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>" . $row['sale_order_id'] . "</td>";
        echo "<td>" . $row['issue_tag'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $row['item_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ดูข้อมูล issue_items ทั้งหมด
echo "<h3>📊 ข้อมูล issue_items (ตัวอย่าง 10 แถว):</h3>";
try {
    $stmt = $pdo->query("
        SELECT 
            ii.id,
            ii.sale_order_id,
            ii.product_id,
            p.name as product_name,
            p.sku,
            ii.quantity
        FROM issue_items ii
        LEFT JOIN products p ON ii.product_id = p.product_id
        LIMIT 10
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>id</th><th>sale_order_id</th><th>product_id</th><th>product_name</th><th>sku</th><th>quantity</th></tr>";
    foreach ($items as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['sale_order_id'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . ($row['product_name'] ?? '-') . "</td>";
        echo "<td>" . $row['sku'] . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ทดสอบค้นหาเลขแท็คแบบเฉพาะ
echo "<h3>🔍 ทดสอบค้นหา issue_tag ที่มีสินค้า:</h3>";
try {
    $stmt = $pdo->query("
        SELECT 
            so.sale_order_id,
            so.issue_tag,
            COUNT(ii.id) as item_count
        FROM sales_orders so
        LEFT JOIN issue_items ii ON so.sale_order_id = ii.sale_order_id
        GROUP BY so.sale_order_id
        HAVING COUNT(ii.id) > 0
        ORDER BY so.created_at DESC
        LIMIT 5
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "<p style='color: red;'><strong>⚠️ ไม่พบ sales_orders ที่มี issue_items เชื่อมต่ออยู่!</strong></p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>sale_order_id</th><th>issue_tag</th><th>จำนวนสินค้า</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . $row['sale_order_id'] . "</td>";
            echo "<td>" . $row['issue_tag'] . "</td>";
            echo "<td>" . $row['item_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
