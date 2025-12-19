<?php
require_once 'config/db_connect.php';

echo "=== ตรวจสอบข้อมูลรูปภาพในตาราง products ===\n\n";

// ตรวจสอบจำนวน products ที่มี image
$sql1 = "SELECT COUNT(*) as total FROM products WHERE image IS NOT NULL AND image != ''";
$stmt1 = $pdo->query($sql1);
$count = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];
echo "📊 จำนวน products ที่มี image: {$count}\n\n";

// ดึง 5 รายการแรก
$sql2 = "SELECT product_id, name, image FROM products WHERE image IS NOT NULL AND image != '' LIMIT 5";
$stmt2 = $pdo->query($sql2);
$products = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "📦 ข้อมูล products ตัวอย่าง:\n";
foreach ($products as $p) {
    echo "  - ID: {$p['product_id']}, Name: {$p['name']}, Image: {$p['image']}\n";
}

echo "\n=== ตรวจสอบข้อมูลใน issue_items ===\n\n";

// ตรวจสอบ issue_items ที่เชื่อมกับ products ที่มี image
$sql3 = "
SELECT 
    ii.issue_id,
    ii.sale_order_id,
    p.name as product_name,
    p.image,
    ii.issue_qty
FROM issue_items ii
LEFT JOIN products p ON ii.product_id = p.product_id
WHERE p.image IS NOT NULL AND p.image != ''
LIMIT 5
";
$stmt3 = $pdo->query($sql3);
$items = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "🔗 issue_items ที่มี product images ตัวอย่าง:\n";
foreach ($items as $item) {
    echo "  - Issue ID: {$item['issue_id']}, Sale Order: {$item['sale_order_id']}, Product: {$item['product_name']}, Image: {$item['image']}\n";
}

echo "\n=== ตรวจสอบ sales_orders ที่มี items ====\n\n";

// ตรวจสอบ sales_orders ที่มี issue_items ที่เชื่อมกับ products
$sql4 = "
SELECT DISTINCT 
    so.sale_order_id,
    so.issue_tag,
    so.sale_date,
    COUNT(ii.issue_id) as item_count
FROM sales_orders so
LEFT JOIN issue_items ii ON ii.sale_order_id = so.sale_order_id
LEFT JOIN products p ON ii.product_id = p.product_id
WHERE p.image IS NOT NULL AND p.image != ''
GROUP BY so.sale_order_id
LIMIT 5
";
$stmt4 = $pdo->query($sql4);
$orders = $stmt4->fetchAll(PDO::FETCH_ASSOC);

echo "💳 sales_orders ที่มี items พร้อมรูปภาพ:\n";
foreach ($orders as $order) {
    echo "  - Sale Order: {$order['sale_order_id']}, Tag: {$order['issue_tag']}, Date: {$order['sale_date']}, Items: {$order['item_count']}\n";
}

?>
