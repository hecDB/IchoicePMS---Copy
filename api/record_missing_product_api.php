<?php
/**
 * API for recording missing/lost products
 * Creates:
 * 1. Record in missing_products table
 * 2. Negative transaction in receive_items table (for movement tracking)
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity_missing = isset($_POST['quantity_missing']) ? floatval($_POST['quantity_missing']) : 0;
$remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
$reported_by = isset($_POST['reported_by']) ? intval($_POST['reported_by']) : 0;

error_log("=== MISSING PRODUCT RECORD START ===");
error_log("product_id: $product_id, quantity: $quantity_missing, reported_by: $reported_by");

// Validation
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเลือกสินค้า']);
    exit;
}

if ($quantity_missing <= 0) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกจำนวนที่สูญหายมากกว่า 0']);
    exit;
}

if ($reported_by <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้งาน']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // ดึงข้อมูลสินค้า
    $sqlProduct = "SELECT p.product_id, p.sku, p.barcode, p.name FROM products p WHERE p.product_id = ?";
    $stmtProduct = $pdo->prepare($sqlProduct);
    $stmtProduct->execute([$product_id]);
    $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('ไม่พบข้อมูลสินค้า');
    }
    
    error_log("Product found: " . json_encode($product));
    
    // 1. บันทึกในตาราง missing_products
    $sqlInsertMissing = "INSERT INTO missing_products (product_id, sku, barcode, product_name, quantity_missing, remark, reported_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtInsertMissing = $pdo->prepare($sqlInsertMissing);
    $stmtInsertMissing->execute([
        $product_id,
        $product['sku'],
        $product['barcode'],
        $product['name'],
        $quantity_missing,
        $remark,
        $reported_by
    ]);
    
    $missing_id = $pdo->lastInsertId();
    error_log("Missing product record created: missing_id=$missing_id");
    
    // 2. ดึง po_id ล่าสุดของสินค้า
    $sqlPO = "SELECT MAX(po.po_id) as po_id 
              FROM purchase_orders po
              INNER JOIN purchase_order_items poi ON po.po_id = poi.po_id
              WHERE poi.product_id = ?
              ORDER BY po.po_id DESC LIMIT 1";
    $stmtPO = $pdo->prepare($sqlPO);
    $stmtPO->execute([$product_id]);
    $poResult = $stmtPO->fetch(PDO::FETCH_ASSOC);
    $po_id = $poResult['po_id'] ?: 0;
    
    // 3. สร้างรายการ transaction ในตาราง receive_items (ลบออกจากสต็อก)
    // ใช้ quantity ที่เป็นลบ และ remark ว่า "สินค้าสูญหาย"
    $transactionRemark = "สินค้าสูญหาย (Missing ID: $missing_id)";
    if (!empty($remark)) {
        $transactionRemark .= " - " . $remark;
    }
    
    // Get item_id for the product from purchase_order_items
    $sqlGetItemId = "SELECT poi.item_id FROM purchase_order_items poi WHERE poi.product_id = ? LIMIT 1";
    $stmtGetItemId = $pdo->prepare($sqlGetItemId);
    $stmtGetItemId->execute([$product_id]);
    $itemIdResult = $stmtGetItemId->fetch(PDO::FETCH_ASSOC);
    $item_id = $itemIdResult['item_id'] ?? 0;
    
    if ($item_id <= 0) {
        throw new Exception('ไม่พบ item_id สำหรับสินค้านี้');
    }
    
    $sqlInsertTransaction = "INSERT INTO receive_items (item_id, po_id, receive_qty, remark, created_by) 
                           VALUES (?, ?, ?, ?, ?)";
    $stmtInsertTransaction = $pdo->prepare($sqlInsertTransaction);
    $stmtInsertTransaction->execute([
        $item_id,
        $po_id,
        -$quantity_missing,  // negative quantity (removal)
        $transactionRemark,
        $reported_by
    ]);
    
    $receive_id = $pdo->lastInsertId();
    error_log("Transaction record created: receive_id=$receive_id");
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกสินค้าสูญหายสำเร็จ',
        'missing_id' => $missing_id,
        'product_id' => $product_id,
        'product_name' => $product['name'],
        'quantity' => $quantity_missing,
        'receive_id' => $receive_id
    ]);
    
    error_log("Missing product record completed successfully");
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error recording missing product: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
