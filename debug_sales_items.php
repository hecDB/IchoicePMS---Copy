<?php
require 'config/db_connect.php';

echo "<h2>Debug: ตรวจสอบข้อมูล sales_orders และ issue_items</h2>";

// ตรวจสอบ sales_orders
echo "<h3>📋 ตาราง sales_orders (ตัวอย่าง 3 แถว):</h3>";
try {
    $stmt = $pdo->query("SELECT sale_order_id, issue_tag, created_at FROM sales_orders LIMIT 3");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($orders);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ตรวจสอบ issue_items
echo "<h3>📋 ตาราง issue_items (ตัวอย่าง 3 แถว):</h3>";
try {
    $stmt = $pdo->query("SELECT id, sale_order_id, product_id, quantity FROM issue_items LIMIT 3");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($items);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ตรวจสอบการเชื่อม
echo "<h3>🔗 ทดสอบการเชื่อมระหว่าง sales_orders กับ issue_items:</h3>";
try {
    $stmt = $pdo->query("
        SELECT 
            so.sale_order_id,
            so.issue_tag,
            COUNT(ii.id) as item_count
        FROM sales_orders so
        LEFT JOIN issue_items ii ON so.sale_order_id = ii.sale_order_id
        GROUP BY so.sale_order_id
        LIMIT 5
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($results);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// ตรวจสอบ Query สำหรับดึงสินค้าจาก sales order
echo "<h3>🔍 ทดสอบการดึงสินค้าจาก sales_order_id = 1:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            ii.id as si_id,
            ii.sale_order_id as so_id,
            ii.product_id,
            p.sku,
            p.barcode,
            p.name as product_name,
            p.image,
            ii.quantity as issue_qty,
            COALESCE(SUM(ret.return_qty), 0) as returned_qty,
            ii.quantity - COALESCE(SUM(ret.return_qty), 0) as available_qty
        FROM issue_items ii
        LEFT JOIN products p ON ii.product_id = p.product_id
        LEFT JOIN returned_items ret ON ii.id = ret.item_id AND ret.return_status != 'rejected' AND ret.return_from_sales = 1
        WHERE ii.sale_order_id = :so_id
        GROUP BY ii.id
        ORDER BY ii.id ASC
    ");
    
    $stmt->execute([':so_id' => 1]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Result for SO ID = 1:<br>";
    echo "<pre>";
    print_r($items);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
