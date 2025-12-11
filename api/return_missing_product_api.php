<?php
/**
 * API for returning previously recorded missing products back into stock movements
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$missing_id = isset($_POST['missing_id']) ? intval($_POST['missing_id']) : 0;
$returned_by = isset($_POST['returned_by']) ? intval($_POST['returned_by']) : 0;

if ($missing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรายการสินค้าสูญหายที่ต้องการคืน']);
    exit;
}

if ($returned_by <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้งาน']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock the missing product record for update to prevent concurrent returns
    $stmtMissing = $pdo->prepare("SELECT missing_id, product_id, sku, barcode, product_name, quantity_missing, remark, reported_by FROM missing_products WHERE missing_id = ? FOR UPDATE");
    $stmtMissing->execute([$missing_id]);
    $missing = $stmtMissing->fetch(PDO::FETCH_ASSOC);

    if (!$missing) {
        throw new Exception('ไม่พบข้อมูลสินค้าสูญหาย');
    }

    $quantityMissing = isset($missing['quantity_missing']) ? abs((float)$missing['quantity_missing']) : 0.0;
    if ($quantityMissing <= 0) {
        throw new Exception('ไม่พบจำนวนที่สามารถคืนได้');
    }

    $returnRemarkPattern = '%รับคืนจากเมนูสูญหาย (Missing ID: ' . $missing_id . '%';
    $stmtExistingReturn = $pdo->prepare("SELECT receive_id FROM receive_items WHERE remark LIKE ? AND receive_qty > 0 ORDER BY receive_id DESC LIMIT 1");
    $stmtExistingReturn->execute([$returnRemarkPattern]);
    if ($stmtExistingReturn->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('มีการคืนสินค้าแล้วสำหรับรายการนี้');
    }

    $missingRemarkPattern = '%Missing ID: ' . $missing_id . '%';
    $stmtBaseMovement = $pdo->prepare("SELECT receive_id, item_id, po_id, expiry_date FROM receive_items WHERE remark LIKE ? AND receive_qty < 0 ORDER BY receive_id DESC LIMIT 1");
    $stmtBaseMovement->execute([$missingRemarkPattern]);
    $baseMovement = $stmtBaseMovement->fetch(PDO::FETCH_ASSOC);

    if (!$baseMovement) {
        throw new Exception('ไม่พบข้อมูลการตัดสต็อกเดิมของรายการสูญหาย');
    }

    $itemId = (int)$baseMovement['item_id'];
    $poId = (int)$baseMovement['po_id'];
    $expiryDate = $baseMovement['expiry_date'] ?? null;

    $returnRemark = 'รับคืนจากเมนูสูญหาย (Missing ID: ' . $missing_id . ')';

    $stmtInsert = $pdo->prepare("INSERT INTO receive_items (item_id, po_id, receive_qty, expiry_date, remark, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtInsert->execute([
        $itemId,
        $poId,
        $quantityMissing,
        $expiryDate,
        $returnRemark,
        $returned_by
    ]);

    $returnReceiveId = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'คืนสินค้าเรียบร้อย',
        'missing_id' => $missing_id,
        'receive_id' => $returnReceiveId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error returning missing product: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
