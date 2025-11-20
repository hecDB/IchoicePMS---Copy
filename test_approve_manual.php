<?php
session_start();
require 'config/db_connect.php';

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['temp_product_id'] = 15;

echo "<h2>Manual Test - approve_temp_product.php</h2>";
echo "<pre>";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$temp_product_id = isset($_POST['temp_product_id']) ? (int)$_POST['temp_product_id'] : 0;

if (!$temp_product_id) {
    echo "Error: ไม่พบ ID สินค้า\n";
    exit;
}

echo "temp_product_id: $temp_product_id\n";
echo "================================\n\n";

try {
    echo "Step 1: Starting transaction...\n";
    $pdo->beginTransaction();
    echo "✓ Transaction started\n\n";
    
    // ดึงข้อมูลจาก temp_products
    echo "Step 2: Getting temp_product data...\n";
    $sql_get = "SELECT * FROM temp_products WHERE temp_product_id = :temp_product_id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([':temp_product_id' => $temp_product_id]);
    $temp_product = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if (!$temp_product) {
        throw new Exception('ไม่พบข้อมูลสินค้า');
    }
    echo "✓ Found temp_product\n";
    echo "  product_name: " . $temp_product['product_name'] . "\n";
    echo "  provisional_sku: " . $temp_product['provisional_sku'] . "\n";
    echo "  provisional_barcode: " . $temp_product['provisional_barcode'] . "\n\n";
    
    // ตรวจสอบว่ามีข้อมูล SKU และ Barcode หรือยัง
    echo "Step 3: Checking SKU/Barcode...\n";
    if (empty($temp_product['provisional_sku']) || empty($temp_product['provisional_barcode'])) {
        throw new Exception('กรุณาเพิ่ม SKU และ Barcode ก่อนอนุมัติ');
    }
    echo "✓ Has SKU and Barcode\n\n";
    
    // ตรวจสอบว่า SKU ซ้ำในตาราง products หรือไม่
    echo "Step 4: Checking for duplicate SKU...\n";
    $sql_check = "SELECT product_id FROM products WHERE sku = :sku";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':sku' => $temp_product['provisional_sku']]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('SKU นี้มีในระบบแล้ว กรุณาใช้ SKU อื่น');
    }
    echo "✓ SKU is unique\n\n";
    
    // สร้างสินค้าใหม่ในตาราง products
    echo "Step 5: Getting category_id...\n";
    $category_id = null;
    if (!empty($temp_product['product_category'])) {
        echo "  Category name: " . $temp_product['product_category'] . "\n";
        $sql_cat = "SELECT category_id FROM product_category WHERE category_name = :category_name LIMIT 1";
        $stmt_cat = $pdo->prepare($sql_cat);
        $stmt_cat->execute([':category_name' => $temp_product['product_category']]);
        $category_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
        $category_id = $category_row['category_id'] ?? 1;
        echo "  Found category_id: $category_id\n";
    }
    echo "\n";
    
    echo "Step 6: Inserting new product...\n";
    $sql_insert = "INSERT INTO products 
                   (name, sku, barcode, product_category_id, image, remark_color, created_by, created_at) 
                   VALUES 
                   (:product_name, :sku, :barcode, :product_category_id, :product_image, :remark, :created_by, NOW())";
    
    $stmt_insert = $pdo->prepare($sql_insert);
    
    echo "  Executing INSERT with:\n";
    echo "    name: " . $temp_product['product_name'] . "\n";
    echo "    sku: " . $temp_product['provisional_sku'] . "\n";
    echo "    barcode: " . $temp_product['provisional_barcode'] . "\n";
    echo "    product_category_id: " . ($category_id ?? 1) . "\n";
    echo "    created_by: " . ($_SESSION['user_id'] ?? $temp_product['created_by']) . "\n";
    
    $stmt_insert->execute([
        ':product_name' => $temp_product['product_name'],
        ':sku' => $temp_product['provisional_sku'],
        ':barcode' => $temp_product['provisional_barcode'],
        ':product_category_id' => $category_id ?? 1,
        ':product_image' => $temp_product['product_image'],
        ':remark' => $temp_product['remark'] ?? '',
        ':created_by' => $_SESSION['user_id'] ?? $temp_product['created_by']
    ]);
    
    $new_product_id = $pdo->lastInsertId();
    echo "✓ Product inserted with ID: $new_product_id\n\n";
    
    // อัพเดทสถานะ temp_products เป็น 'converted'
    echo "Step 7: Updating temp_products status...\n";
    $sql_update_status = "UPDATE temp_products 
                          SET status = 'converted', 
                              approved_by = :approved_by, 
                              approved_at = NOW() 
                          WHERE temp_product_id = :temp_product_id";
    
    $stmt_update_status = $pdo->prepare($sql_update_status);
    $stmt_update_status->execute([
        ':approved_by' => $_SESSION['user_id'] ?? 1,
        ':temp_product_id' => $temp_product_id
    ]);
    echo "✓ temp_products status updated\n\n";
    
    // อัพเดท purchase_order_items ให้ชี้ไปที่ product_id ใหม่
    echo "Step 8: Updating purchase_order_items...\n";
    $sql_update_poi = "UPDATE purchase_order_items 
                       SET product_id = :product_id 
                       WHERE temp_product_id = :temp_product_id";
    
    $stmt_update_poi = $pdo->prepare($sql_update_poi);
    $stmt_update_poi->execute([
        ':product_id' => $new_product_id,
        ':temp_product_id' => $temp_product_id
    ]);
    echo "✓ purchase_order_items updated\n\n";
    
    $pdo->commit();
    
    echo "SUCCESS! ✓ All steps completed\n";
    echo "New product_id: $new_product_id\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR ❌\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";

?>
