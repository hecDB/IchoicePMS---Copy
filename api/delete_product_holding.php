<?php
// API ลบสินค้าพัก (คืนกลับไป receive_items)
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
    
    if (!$holdingId) {
        throw new Exception('ไม่พบ ID การพักสินค้า');
    }

    // ดึงข้อมูลสินค้าพัก
    $stmt = $pdo->prepare("
        SELECT *
        FROM product_holding
        WHERE holding_id = ? AND status = 'holding'
    ");
    $stmt->execute([$holdingId]);
    $holding = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$holding) {
        throw new Exception('ไม่พบสินค้าพักหรือสถานะไม่ถูกต้อง');
    }

    // เริ่ม transaction
    $pdo->beginTransaction();

    try {
        // คืนจำนวนสินค้าไป receive_items
        $stmt = $pdo->prepare("
            UPDATE receive_items
            SET receive_qty = receive_qty + ?
            WHERE receive_id = ?
        ");
        
        $stmt->execute([
            $holding['holding_qty'],
            $holding['receive_id']
        ]);

        // อัปเดตสถานะสินค้าพัก
        $stmt = $pdo->prepare("
            UPDATE product_holding
            SET status = 'returned_to_stock'
            WHERE holding_id = ?
        ");
        
        $stmt->execute([$holdingId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "ลบสินค้าพัก {$holding['holding_code']} สำเร็จ และคืนจำนวน {$holding['holding_qty']} ชิ้นไปยังสินค้าคงคลัง"
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
