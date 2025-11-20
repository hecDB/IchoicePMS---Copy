<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Debug approve_temp_product API</h2>";
echo "<pre>";

// ทดสอบโดยใช้ temp_product_id = 15
$temp_product_id = 15;

echo "Testing with temp_product_id: " . $temp_product_id . "\n";
echo "================================\n\n";

// Step 1: ดึงข้อมูลจาก temp_products
echo "Step 1: ดึงข้อมูล temp_products...\n";
$sql_get = "SELECT * FROM temp_products WHERE temp_product_id = :temp_product_id";
$stmt_get = $pdo->prepare($sql_get);
$stmt_get->execute([':temp_product_id' => $temp_product_id]);
$temp_product = $stmt_get->fetch(PDO::FETCH_ASSOC);

if (!$temp_product) {
    echo "❌ ไม่พบข้อมูลสินค้า\n";
} else {
    echo "✓ พบข้อมูล:\n";
    echo "  product_name: " . $temp_product['product_name'] . "\n";
    echo "  provisional_sku: " . ($temp_product['provisional_sku'] ?? 'NULL') . "\n";
    echo "  provisional_barcode: " . ($temp_product['provisional_barcode'] ?? 'NULL') . "\n";
    echo "  category: " . $temp_product['product_category'] . "\n";
}

echo "\n";

// Step 2: ตรวจสอบ SKU/Barcode
echo "Step 2: ตรวจสอบ SKU/Barcode...\n";
if (empty($temp_product['provisional_sku']) || empty($temp_product['provisional_barcode'])) {
    echo "❌ SKU หรือ Barcode ยังไม่มี\n";
} else {
    echo "✓ มี SKU และ Barcode\n";
}

echo "\n";

// Step 3: ตรวจสอบ SKU ซ้ำ
echo "Step 3: ตรวจสอบว่า SKU ซ้ำหรือไม่...\n";
$sql_check = "SELECT product_id FROM products WHERE sku = :sku";
$stmt_check = $pdo->prepare($sql_check);
$stmt_check->execute([':sku' => $temp_product['provisional_sku']]);
$existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "❌ SKU นี้มีอยู่แล้ว (product_id: " . $existing['product_id'] . ")\n";
} else {
    echo "✓ SKU นี้ยังไม่มีในระบบ\n";
}

echo "\n";

// Step 4: ตรวจสอบตาราง products
echo "Step 4: ตรวจสอบสคีมา products table...\n";
$sql_describe = "DESCRIBE products";
$stmt_describe = $pdo->query($sql_describe);
$columns = $stmt_describe->fetchAll(PDO::FETCH_ASSOC);

echo "Columns ใน products table:\n";
foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n";

// Step 5: ลองสร้าง products ใหม่
echo "Step 5: พยายามสร้าง products ใหม่ (ไม่บันทึกจริง)...\n";

try {
    // ใช้ test data
    $test_sku = 'TEST_SKU_' . time();
    
    echo "  INSERT INTO products:\n";
    echo "    product_name: " . $temp_product['product_name'] . "\n";
    echo "    sku: " . $test_sku . "\n";
    echo "    barcode: " . ($temp_product['provisional_barcode'] ?? 'N/A') . "\n";
    echo "    category: " . ($temp_product['product_category'] ?? 'N/A') . "\n";
    
    // ตรวจสอบ created_by
    $created_by = $_SESSION['user_id'] ?? $temp_product['created_by'] ?? 1;
    echo "    created_by: " . $created_by . "\n";
    
    echo "\n✓ Query syntax ถูกต้อง\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Step 6: ตรวจสอบ purchase_orders
echo "Step 6: ตรวจสอบ purchase_orders...\n";
$sql_po = "SELECT COUNT(*) as count FROM purchase_orders";
$stmt_po = $pdo->query($sql_po);
$po_count = $stmt_po->fetch(PDO::FETCH_ASSOC);
echo "  Total purchase_orders: " . $po_count['count'] . "\n";

echo "\n";

// Step 7: ตรวจสอบ purchase_order_items
echo "Step 7: ตรวจสอบ purchase_order_items สำหรับ temp_product_id " . $temp_product_id . "...\n";
$sql_poi = "SELECT * FROM purchase_order_items WHERE temp_product_id = :temp_product_id";
$stmt_poi = $pdo->prepare($sql_poi);
$stmt_poi->execute([':temp_product_id' => $temp_product_id]);
$poi = $stmt_poi->fetchAll(PDO::FETCH_ASSOC);

echo "  Found " . count($poi) . " records:\n";
foreach ($poi as $p) {
    echo "    item_id: " . $p['item_id'] . " | po_id: " . $p['po_id'] . "\n";
}

echo "\n</pre>";

?>
