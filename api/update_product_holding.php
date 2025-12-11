<?php
// API อัปเดตข้อมูลสินค้าพัก (new_sku, sale_price, reason)
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('ไม่ได้เข้าสู่ระบบ');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('ไม่สามารถแปลง JSON ได้');
    }
    
    $holdingId = intval($input['holding_id'] ?? 0);
    $newSku = trim($input['new_sku'] ?? '');
    $newPrice = floatval($input['new_price'] ?? 0);
    $reason = trim($input['reason'] ?? '');
    $newExpiry = trim($input['new_expiry'] ?? '');
    
    if (!$holdingId) {
        throw new Exception('ไม่พบ ID การพักสินค้า');
    }
    
    if (!$newSku) {
        throw new Exception('SKU ใหม่ห้ามว่าง');
    }
    
    if ($newPrice <= 0) {
        throw new Exception('ราคาขายต้องมากกว่า 0');
    }
    
    if (!$newExpiry) {
        throw new Exception('วันหมดอายุห้ามว่าง');
    }

    // ตรวจสอบข้อมูลสินค้าพัก
    $stmt = $pdo->prepare("
        SELECT status
        FROM product_holding
        WHERE holding_id = ?
    ");
    $stmt->execute([$holdingId]);
    $holding = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$holding) {
        throw new Exception('ไม่พบสินค้าพักหรือถูกลบไปแล้ว');
    }

    if (!in_array($holding['status'], ['holding', 'returned_to_stock'], true)) {
        throw new Exception('สถานะปัจจุบันไม่สามารถแก้ไขได้');
    }

    $daysToExpire = null;
    if ($newExpiry) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $newExpiry);
        if (!$expiryDate) {
            throw new Exception('รูปแบบวันหมดอายุไม่ถูกต้อง');
        }

        $today = new DateTime('today');
        $daysToExpire = (int)$today->diff($expiryDate)->format('%r%a');
    }

    // เริ่ม transaction
    $pdo->beginTransaction();

    try {
        // อัปเดต product_holding พร้อมคำนวณจำนวนวันที่เหลือ
        $stmt = $pdo->prepare("
            UPDATE product_holding
            SET new_sku = ?,
                sale_price = ?,
                holding_reason = ?,
                expiry_date = ?,
                days_to_expire = ?,
                status = ?
            WHERE holding_id = ?
        ");
        
        $stmt->execute([
            $newSku,
            $newPrice,
            $reason,
            $newExpiry,
            $daysToExpire,
            'returned_to_stock',
            $holdingId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('ไม่สามารถอัปเดตข้อมูลได้ กรุณาลองอีกครั้ง');
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'อัปเดตข้อมูลสินค้าสำเร็จ'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
