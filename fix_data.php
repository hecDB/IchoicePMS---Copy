<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Fix Data Structure - Create Multiple Temp Products</h2>";
echo "<pre>";

try {
    $pdo->beginTransaction();
    
    // Step 1: สร้าง 2 temp_product records ใหม่ (ประกอบกับ 1 record เดิม)
    echo "Step 1: Creating new temp_products records...\n";
    
    // Get existing record to clone
    $sql_get = "SELECT product_category, product_image, po_id, created_by FROM temp_products WHERE temp_product_id = 15";
    $stmt_get = $pdo->query($sql_get);
    $existing = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    // Insert temp_product 2 (NULL SKU/Barcode)
    $sql_insert1 = "INSERT INTO temp_products (product_name, product_category, product_image, provisional_sku, provisional_barcode, po_id, created_by, status) 
                    VALUES ('sdfsdfsdf', :category, :image, NULL, NULL, :po_id, :created_by, 'approved')";
    $stmt_insert1 = $pdo->prepare($sql_insert1);
    $stmt_insert1->execute([
        ':category' => $existing['product_category'],
        ':image' => $existing['product_image'],
        ':po_id' => $existing['po_id'],
        ':created_by' => $existing['created_by']
    ]);
    $temp_id_2 = $pdo->lastInsertId();
    echo "  ✓ Created temp_product_id: $temp_id_2 (SKU: NULL, Barcode: NULL)\n";
    
    // Insert temp_product 3 (NULL SKU/Barcode)
    $sql_insert2 = "INSERT INTO temp_products (product_name, product_category, product_image, provisional_sku, provisional_barcode, po_id, created_by, status) 
                    VALUES ('sdfsdfsdf', :category, :image, NULL, NULL, :po_id, :created_by, 'approved')";
    $stmt_insert2 = $pdo->prepare($sql_insert2);
    $stmt_insert2->execute([
        ':category' => $existing['product_category'],
        ':image' => $existing['product_image'],
        ':po_id' => $existing['po_id'],
        ':created_by' => $existing['created_by']
    ]);
    $temp_id_3 = $pdo->lastInsertId();
    echo "  ✓ Created temp_product_id: $temp_id_3 (SKU: NULL, Barcode: NULL)\n";
    
    // Step 2: สร้าง 2 purchase_order_items records ใหม่ (ประกอบกับ 1 record เดิม)
    echo "\nStep 2: Creating new purchase_order_items records...\n";
    
    // Get PO info from existing item_id 43
    $sql_get_poi = "SELECT po_id, line_id FROM purchase_order_items WHERE item_id = 43";
    $stmt_get_poi = $pdo->query($sql_get_poi);
    $existing_poi = $stmt_get_poi->fetch(PDO::FETCH_ASSOC);
    
    // Insert purchase_order_items 2
    $sql_insert_poi1 = "INSERT INTO purchase_order_items (po_id, line_id, temp_product_id) 
                        VALUES (:po_id, :line_id, :temp_product_id)";
    $stmt_insert_poi1 = $pdo->prepare($sql_insert_poi1);
    $stmt_insert_poi1->execute([
        ':po_id' => $existing_poi['po_id'],
        ':line_id' => $existing_poi['line_id'],
        ':temp_product_id' => $temp_id_2
    ]);
    $item_id_2 = $pdo->lastInsertId();
    echo "  ✓ Created item_id: $item_id_2 → temp_product_id: $temp_id_2\n";
    
    // Insert purchase_order_items 3
    $sql_insert_poi2 = "INSERT INTO purchase_order_items (po_id, line_id, temp_product_id) 
                        VALUES (:po_id, :line_id, :temp_product_id)";
    $stmt_insert_poi2 = $pdo->prepare($sql_insert_poi2);
    $stmt_insert_poi2->execute([
        ':po_id' => $existing_poi['po_id'],
        ':line_id' => $existing_poi['line_id'],
        ':temp_product_id' => $temp_id_3
    ]);
    $item_id_3 = $pdo->lastInsertId();
    echo "  ✓ Created item_id: $item_id_3 → temp_product_id: $temp_id_3\n";
    
    // Step 3: Update receive_items เพื่อให้ชี้ไปที่ item_id ที่ต่างกัน
    echo "\nStep 3: Updating receive_items to link to different item_ids...\n";
    
    $sql_update_r24 = "UPDATE receive_items SET item_id = :item_id WHERE receive_id = 24";
    $stmt_update_r24 = $pdo->prepare($sql_update_r24);
    $stmt_update_r24->execute([':item_id' => $item_id_2]);
    echo "  ✓ receive_id 24 → item_id: $item_id_2\n";
    
    $sql_update_r32 = "UPDATE receive_items SET item_id = :item_id WHERE receive_id = 32";
    $stmt_update_r32 = $pdo->prepare($sql_update_r32);
    $stmt_update_r32->execute([':item_id' => $item_id_3]);
    echo "  ✓ receive_id 32 → item_id: $item_id_3\n";
    
    echo "  ✓ receive_id 33 stays with item_id 43 (temp_product_id 15)\n";
    
    $pdo->commit();
    echo "\n✅ All updates completed successfully!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage();
}

echo "</pre>";

// ตรวจสอบผลลัพธ์
echo "\n<h2>Verification After Update</h2>";
echo "<pre>";

$sql_verify = "
SELECT 
    r.receive_id,
    r.item_id,
    poi.temp_product_id,
    tp.product_name,
    tp.provisional_sku,
    tp.provisional_barcode,
    r.receive_qty
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
WHERE r.receive_id IN (24, 32, 33)
ORDER BY r.receive_id DESC";

try {
    $stmt_verify = $pdo->query($sql_verify);
    $rows_verify = $stmt_verify->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Verification Results:\n\n";
    foreach($rows_verify as $row) {
        echo "receive_id: " . $row['receive_id'] . " | item_id: " . $row['item_id'] . " | temp_id: " . $row['temp_product_id'] . " | sku: " . ($row['provisional_sku'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

echo "</pre>";

?>
