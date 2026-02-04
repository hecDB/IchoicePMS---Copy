<?php
session_start();
require '../config/db_connect.php';

function columnExists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
    $stmt->execute([':table' => $table, ':column' => $column]);
    $exists = $stmt->fetchColumn() > 0;
    $cache[$key] = $exists;
    error_log("Column check {$table}.{$column}: " . ($exists ? 'yes' : 'no'));
    return $exists;
}

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
    error_log("=== APPROVE_TEMP_PRODUCT START ===");
    error_log("temp_product_id: " . $temp_product_id);
    
    // Ensure pending location table exists before starting a transaction
    $pdo->exec("CREATE TABLE IF NOT EXISTS temp_product_locations (
        temp_product_id INT PRIMARY KEY,
        location_id INT DEFAULT NULL,
        row_code VARCHAR(50) DEFAULT NULL,
        bin VARCHAR(50) DEFAULT NULL,
        shelf VARCHAR(50) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->beginTransaction();
    error_log("Transaction started");
    
    // ดึงข้อมูลจาก temp_products
    $sql_get = "SELECT * FROM temp_products WHERE temp_product_id = :temp_product_id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([':temp_product_id' => $temp_product_id]);
    $temp_product = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    error_log("Fetched temp_product: " . json_encode($temp_product));
    
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
    
    // สร้างสินค้าใหม่ในตาราง products (ตรวจสอบคอลัมน์ remark_weight ก่อนใช้งาน)
    $productColumns = ['name', 'sku', 'barcode', 'unit', 'product_category_id', 'image', 'remark_color', 'remark_split', 'is_active', 'created_by', 'created_at'];
    $productValues = [':product_name', ':sku', ':barcode', ':unit', ':product_category_id', ':product_image', ':remark_color', ':remark_split', ':is_active', ':created_by', 'NOW()'];

    $includeRemarkWeight = columnExists($pdo, 'products', 'remark_weight');
    if ($includeRemarkWeight) {
        $productColumns[] = 'remark_weight';
        $productValues[] = ':remark_weight';
    }

    $sql_insert = 'INSERT INTO products (' . implode(', ', $productColumns) . ')
                   VALUES (' . implode(', ', $productValues) . ')';
    
    error_log("SQL_INSERT: " . $sql_insert);
    error_log("Columns: " . json_encode($productColumns));
    error_log("Values: " . json_encode($productValues));
    
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
    
    $insertParams = [
        ':product_name' => $temp_product['product_name'],
        ':sku' => $temp_product['provisional_sku'],
        ':barcode' => $temp_product['provisional_barcode'],
        ':unit' => $temp_product['unit'] ?? '',
        ':product_category_id' => $category_id ?? 1,
        ':product_image' => $temp_product['product_image'],
        ':remark_color' => $temp_product['remark'] ?? '',
        ':remark_split' => $temp_product['remark_split'] ?? 0,
        ':is_active' => 1,
        ':created_by' => $_SESSION['user_id'] ?? $temp_product['created_by']
    ];

    if ($includeRemarkWeight) {
        $insertParams[':remark_weight'] = $temp_product['remark_weight'] ?? null;
    }

    error_log("Insert params: " . json_encode($insertParams));
    
    $result = $stmt_insert->execute($insertParams);
    error_log("Insert result: " . ($result ? 'success' : 'failed'));
    
    $new_product_id = $pdo->lastInsertId();
    error_log("New product_id: " . $new_product_id);

    // Set new product as active (ขายอยู่)
    $stmtActivate = $pdo->prepare("UPDATE products SET is_active = 1 WHERE product_id = :product_id");
    $stmtActivate->execute([':product_id' => $new_product_id]);

    // Apply pending location information if available
    $stmtPendingLocation = $pdo->prepare("SELECT location_id, row_code, bin, shelf FROM temp_product_locations WHERE temp_product_id = :temp_product_id LIMIT 1");
    $stmtPendingLocation->execute([':temp_product_id' => $temp_product_id]);
    $pendingLocation = $stmtPendingLocation->fetch(PDO::FETCH_ASSOC);

    if ($pendingLocation && !empty($pendingLocation['location_id'])) {
        $locationId = (int)$pendingLocation['location_id'];

        $stmtExistingLocation = $pdo->prepare("SELECT id FROM product_location WHERE product_id = :product_id LIMIT 1");
        $stmtExistingLocation->execute([':product_id' => $new_product_id]);
        $existingLocationId = $stmtExistingLocation->fetchColumn();

        if ($existingLocationId) {
            $stmtUpdateLocation = $pdo->prepare("UPDATE product_location SET location_id = :location_id WHERE id = :id");
            $stmtUpdateLocation->execute([':location_id' => $locationId, ':id' => $existingLocationId]);
        } else {
            $stmtInsertLocation = $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (:product_id, :location_id)");
            $stmtInsertLocation->execute([':product_id' => $new_product_id, ':location_id' => $locationId]);
        }

        // Optional: update the selected location metadata if user provided new coordinates
        if (!empty($pendingLocation['row_code']) || !empty($pendingLocation['bin']) || !empty($pendingLocation['shelf'])) {
            $stmtUpdateMeta = $pdo->prepare("UPDATE locations SET row_code = COALESCE(:row_code, row_code), bin = COALESCE(:bin, bin), shelf = COALESCE(:shelf, shelf) WHERE location_id = :location_id");
            $stmtUpdateMeta->execute([
                ':row_code' => $pendingLocation['row_code'] !== null ? $pendingLocation['row_code'] : null,
                ':bin' => $pendingLocation['bin'] !== null ? $pendingLocation['bin'] : null,
                ':shelf' => $pendingLocation['shelf'] !== null ? $pendingLocation['shelf'] : null,
                ':location_id' => $locationId
            ]);
        }
    }
    
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
    
    // อัพเดท receive_items.created_at เป็นเวลาปัจจุบัน เพื่อให้แสดงในตารางความเคลื่อนไหวเป็นข้อมูลล่าสุด
    $sql_update_receive = "UPDATE receive_items 
                          SET created_at = NOW() 
                          WHERE item_id IN (
                              SELECT item_id FROM purchase_order_items 
                              WHERE temp_product_id = :temp_product_id
                          )";
    
    $stmt_update_receive = $pdo->prepare($sql_update_receive);
    $stmt_update_receive->execute([':temp_product_id' => $temp_product_id]);
    error_log("Updated receive_items.created_at for temp_product_id: " . $temp_product_id);
    
    $pdo->commit();
    error_log("Transaction committed");
    
    echo json_encode([
        'success' => true, 
        'message' => 'อนุมัติสำเร็จ! สินค้าถูกย้ายไปคลังปกติแล้ว',
        'new_product_id' => $new_product_id,
        'product_id' => $new_product_id
    ]);
    error_log("=== APPROVE_TEMP_PRODUCT SUCCESS ===");
    
} catch (Exception $e) {
    error_log("=== APPROVE_TEMP_PRODUCT EXCEPTION ===");
    error_log("Exception message: " . $e->getMessage());
    error_log("Exception code: " . $e->getCode());
    error_log("Exception trace: " . $e->getTraceAsString());
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
