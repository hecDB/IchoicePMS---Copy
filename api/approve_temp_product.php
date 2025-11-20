<?php
session_start();
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$temp_product_id = isset($_POST['temp_product_id']) ? (int)$_POST['temp_product_id'] : 0;

if (!$temp_product_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID สินค้า']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // ดึงข้อมูลจาก temp_products
    $sql_get = "SELECT * FROM temp_products WHERE temp_product_id = :temp_product_id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([':temp_product_id' => $temp_product_id]);
    $temp_product = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if (!$temp_product) {
        throw new Exception('ไม่พบข้อมูลสินค้า');
    }
    
    // ตรวจสอบว่ามีข้อมูล SKU และ Barcode หรือยัง
    if (empty($temp_product['provisional_sku']) || empty($temp_product['provisional_barcode'])) {
        throw new Exception('กรุณาเพิ่ม SKU และ Barcode ก่อนอนุมัติ');
    }
    
    // ตรวจสอบว่า SKU ซ้ำในตาราง products หรือไม่
    $sql_check = "SELECT product_id FROM products WHERE sku = :sku";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':sku' => $temp_product['provisional_sku']]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('SKU นี้มีในระบบแล้ว กรุณาใช้ SKU อื่น');
    }
    
    // สร้างสินค้าใหม่ในตาราง products
    $sql_insert = "INSERT INTO products 
                   (name, sku, barcode, product_category_id, image, remark_color, created_by, created_at) 
                   VALUES 
                   (:product_name, :sku, :barcode, :product_category_id, :product_image, :remark, :created_by, NOW())";
    
    $stmt_insert = $pdo->prepare($sql_insert);
    
    // ดึง category_id จาก category_name
    $category_id = null;
    if (!empty($temp_product['product_category'])) {
        $sql_cat = "SELECT category_id FROM product_category WHERE category_name = :category_name LIMIT 1";
        $stmt_cat = $pdo->prepare($sql_cat);
        $stmt_cat->execute([':category_name' => $temp_product['product_category']]);
        $category_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
        $category_id = $category_row['category_id'] ?? 1; // Default to 1 if not found
    }
    
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
    
    // อัพเดทสถานะ temp_products เป็น 'converted'
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
    
    // อัพเดท purchase_order_items ให้ชี้ไปที่ product_id ใหม่
    $sql_update_poi = "UPDATE purchase_order_items 
                       SET product_id = :product_id 
                       WHERE temp_product_id = :temp_product_id";
    
    $stmt_update_poi = $pdo->prepare($sql_update_poi);
    $stmt_update_poi->execute([
        ':product_id' => $new_product_id,
        ':temp_product_id' => $temp_product_id
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'อนุมัติสำเร็จ! สินค้าถูกย้ายไปคลังปกติแล้ว',
        'new_product_id' => $new_product_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
