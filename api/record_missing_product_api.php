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
$selected_item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$selected_receive_id = isset($_POST['receive_id']) ? intval($_POST['receive_id']) : 0;
$selected_po_id = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
$selected_expiry_date = isset($_POST['expiry_date']) && $_POST['expiry_date'] !== '' ? $_POST['expiry_date'] : null;
$expiryDateValidator = null;
if ($selected_expiry_date !== null) {
    $expiryDateValidator = DateTime::createFromFormat('Y-m-d', $selected_expiry_date);
    if (!$expiryDateValidator) {
        $selected_expiry_date = null;
    } else {
        $selected_expiry_date = $expiryDateValidator->format('Y-m-d');
    }
}
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
    
    // 2. หา item_id, po_id, expiry_date ตามข้อมูลที่ส่งมา (ผูกกับการรับเข้า)
    $po_id = $selected_po_id;
    $item_id = $selected_item_id;
    $expiry_for_transaction = $selected_expiry_date;

    if ($selected_receive_id > 0) {
        $stmtReceive = $pdo->prepare("SELECT item_id, po_id, expiry_date FROM receive_items WHERE receive_id = ? LIMIT 1");
        $stmtReceive->execute([$selected_receive_id]);
        if ($receiveRow = $stmtReceive->fetch(PDO::FETCH_ASSOC)) {
            if ($item_id <= 0) {
                $item_id = intval($receiveRow['item_id']);
            }
            if ($po_id <= 0) {
                $po_id = intval($receiveRow['po_id']);
            }
            if ($expiry_for_transaction === null && !empty($receiveRow['expiry_date'])) {
                $expiry_for_transaction = $receiveRow['expiry_date'];
            }
        }
    }

    if ($item_id <= 0) {
        $sqlGetItemId = "SELECT poi.item_id, poi.po_id
                          FROM purchase_order_items poi
                          WHERE poi.product_id = ?
                          ORDER BY poi.item_id DESC
                          LIMIT 1";
        $stmtGetItemId = $pdo->prepare($sqlGetItemId);
        $stmtGetItemId->execute([$product_id]);
        $itemRow = $stmtGetItemId->fetch(PDO::FETCH_ASSOC);
        if ($itemRow) {
            $item_id = intval($itemRow['item_id']);
            if ($po_id <= 0 && isset($itemRow['po_id'])) {
                $po_id = intval($itemRow['po_id']);
            }
        }
    }

    if ($po_id <= 0) {
        $sqlPO = "SELECT po.po_id
                   FROM purchase_orders po
                   INNER JOIN purchase_order_items poi ON po.po_id = poi.po_id
                   WHERE poi.product_id = ?
                   ORDER BY po.po_id DESC
                   LIMIT 1";
        $stmtPO = $pdo->prepare($sqlPO);
        $stmtPO->execute([$product_id]);
        $poResult = $stmtPO->fetch(PDO::FETCH_ASSOC);
        $po_id = $poResult['po_id'] ?? 0;
    }
    
    // 3. สร้างรายการ transaction ในตาราง receive_items (ลบออกจากสต็อก)
    // ใช้ quantity ที่เป็นลบ และ remark ว่า "สินค้าสูญหาย"
    $transactionRemark = "สินค้าสูญหาย (Missing ID: $missing_id)";
    if (!empty($remark)) {
        $transactionRemark .= " - " . $remark;
    }
    if ($selected_receive_id > 0) {
        $transactionRemark .= " | อ้างอิงรับเข้า #" . $selected_receive_id;
    }
    
    if ($item_id <= 0) {
        throw new Exception('ไม่พบ item_id สำหรับสินค้านี้');
    }
    
    $sqlInsertTransaction = "INSERT INTO receive_items (item_id, po_id, receive_qty, expiry_date, remark, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?)";
    $stmtInsertTransaction = $pdo->prepare($sqlInsertTransaction);
    $stmtInsertTransaction->execute([
        $item_id,
        $po_id,
        -$quantity_missing,  // negative quantity (removal)
        $expiry_for_transaction,
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
        'receive_id' => $receive_id,
        'po_id' => $po_id,
        'item_id' => $item_id
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
