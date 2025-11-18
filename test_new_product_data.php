<?php
require 'config/db_connect.php';

echo "<h2>ตรวจสอบข้อมูล New Product PO</h2>";

// 1. Check purchase_orders with "New Product Purchase"
echo "<h3>1. Purchase Orders (New Product)</h3>";
$sql = "SELECT po_id, po_number, supplier_id, order_date, remark, status, currency_id 
        FROM purchase_orders 
        WHERE remark LIKE '%New Product%' 
        ORDER BY po_id DESC LIMIT 5";
$stmt = $pdo->query($sql);
$pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($pos);
echo "</pre>";

if (!empty($pos)) {
    $po_id = $pos[0]['po_id'];
    echo "<hr>";
    
    // 2. Check purchase_order_items for this PO
    echo "<h3>2. Purchase Order Items (po_id = $po_id)</h3>";
    $sql = "SELECT item_id, po_id, product_id, temp_product_id, qty, price_per_unit, total, currency_id 
            FROM purchase_order_items 
            WHERE po_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($items);
    echo "</pre>";
    
    if (!empty($items)) {
        echo "<hr>";
        
        // 3. Check temp_products
        echo "<h3>3. Temp Products (po_id = $po_id)</h3>";
        $sql = "SELECT temp_product_id, product_name, product_category, provisional_sku, provisional_barcode, unit, status, po_id, created_by 
                FROM temp_products 
                WHERE po_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$po_id]);
        $temp_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($temp_products);
        echo "</pre>";
        
        echo "<hr>";
        
        // 4. Test the actual JOIN query
        echo "<h3>4. Test JOIN Query (API Query)</h3>";
        $sql = "
            SELECT 
                poi.item_id,
                poi.temp_product_id as poi_temp_id,
                tp.temp_product_id as tp_temp_id,
                tp.product_name,
                tp.provisional_sku as sku,
                tp.provisional_barcode as barcode,
                tp.unit,
                poi.qty as order_qty,
                poi.price_per_unit as unit_price,
                c.code as currency_code,
                COALESCE(received_summary.total_received, 0) as received_qty,
                (poi.qty - COALESCE(received_summary.total_received, 0)) as remaining_qty
            FROM purchase_order_items poi
            LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
            LEFT JOIN (
                SELECT item_id, SUM(receive_qty) as total_received 
                FROM receive_items 
                GROUP BY item_id
            ) received_summary ON poi.item_id = received_summary.item_id
            LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
            LEFT JOIN currencies c ON po.currency_id = c.currency_id
            WHERE poi.po_id = ? AND poi.temp_product_id IS NOT NULL
            ORDER BY poi.item_id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$po_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        if (empty($result)) {
            echo "<p style='color: red; font-weight: bold;'>❌ JOIN Query ไม่พบข้อมูล!</p>";
            
            // Debug: Check without temp_product_id filter
            echo "<h3>5. Debug: Check WITHOUT temp_product_id filter</h3>";
            $sql = "SELECT item_id, po_id, product_id, temp_product_id, qty, price_per_unit 
                    FROM purchase_order_items 
                    WHERE po_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$po_id]);
            $debug = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($debug);
            echo "</pre>";
        } else {
            echo "<p style='color: green; font-weight: bold;'>✅ JOIN Query สำเร็จ!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ ไม่มี purchase_order_items</p>";
    }
} else {
    echo "<p style='color: red;'>❌ ไม่พบ PO สินค้าใหม่</p>";
}

// 6. Check table structure
echo "<hr>";
echo "<h3>6. Table Structure - purchase_order_items</h3>";
$sql = "DESCRIBE purchase_order_items";
$stmt = $pdo->query($sql);
$structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($structure as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>7. Table Structure - temp_products</h3>";
$sql = "DESCRIBE temp_products";
$stmt = $pdo->query($sql);
$structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($structure as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";
?>
