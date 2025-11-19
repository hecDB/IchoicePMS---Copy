<?php
session_start();
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$temp_product_id = isset($_POST['temp_product_id']) ? (int)$_POST['temp_product_id'] : 0;
$provisional_sku = isset($_POST['provisional_sku']) ? trim($_POST['provisional_sku']) : '';
$provisional_barcode = isset($_POST['provisional_barcode']) ? trim($_POST['provisional_barcode']) : '';
$expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

if (!$temp_product_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID สินค้า']);
    exit;
}

try {
    // อัปเดต temp_products - บันทึก provisional_sku และ provisional_barcode
    $sql = "UPDATE temp_products SET 
            provisional_sku = :provisional_sku,
            provisional_barcode = :provisional_barcode
            WHERE temp_product_id = :temp_product_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':provisional_sku' => $provisional_sku,
        ':provisional_barcode' => $provisional_barcode,
        ':temp_product_id' => $temp_product_id
    ]);
    
    // หากมี expiry_date ให้อัปเดต receive_items
    if (!empty($expiry_date)) {
        $sql_receive = "UPDATE receive_items ri
                        SET ri.expiry_date = :expiry_date
                        WHERE ri.item_id IN (
                            SELECT poi.item_id FROM purchase_order_items poi
                            WHERE poi.temp_product_id = :temp_product_id
                        )";
        
        $stmt_receive = $pdo->prepare($sql_receive);
        $stmt_receive->execute([
            ':expiry_date' => $expiry_date,
            ':temp_product_id' => $temp_product_id
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'บันทึกสำเร็จ']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>
