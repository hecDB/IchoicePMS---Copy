<?php
// API ย้ายสินค้าจากพักไปตารางความเคลื่อนไหว (receive_items) เป็นสินค้าใหม่
// บันทึกเป็นการรับเข้าครั้งใหม่ด้วย SKU ใหม่
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

    // ดึงข้อมูลสินค้าพัก (ยอมรับสินค้าพักที่ยังอยู่หรือเพิ่งคืนสต็อก)
    $stmt = $pdo->prepare("
        SELECT * FROM product_holding
        WHERE holding_id = ? AND status IN ('holding', 'returned_to_stock')
    ");
    $stmt->execute([$holdingId]);
    $holding = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$holding) {
        throw new Exception('ไม่พบสินค้าพักหรือสถานะไม่ถูกต้อง');
    }

    // เริ่ม transaction
    $pdo->beginTransaction();

    try {
        // 1. สร้างสินค้าใหม่ (ไม่ใช่แก้ไข) ด้วย SKU ใหม่
        if (!empty($holding['new_sku'])) {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, sku, barcode, unit, 
                    created_by, created_at
                ) SELECT
                    name, ?,
                    barcode, unit,
                    ?, NOW()
                FROM products
                WHERE product_id = ?
                LIMIT 1
            ");
            
            $stmt->execute([
                $holding['new_sku'],
                $_SESSION['user_id'],
                $holding['product_id']
            ]);
            
            $newProductId = $pdo->lastInsertId();
        } else {
            throw new Exception('SKU ใหม่ยังไม่ได้ระบุ');
        }

        // 2. สร้าง receive_items ใหม่ (บันทึกการเคลื่อนไหวสินค้า) ด้วยสินค้าใหม่
        $stmt = $pdo->prepare("SELECT item_id FROM receive_items
            WHERE receive_id = ? ");
        $stmt->execute([$holding['receive_id']]);
        $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$itemInfo) {
            throw new Exception('ไม่พบ item_id จากการรับเข้า');
        }

        // ดึง po_id จากการรับเข้าเดิม
        $stmt = $pdo->prepare("
            SELECT po_id FROM receive_items
            WHERE receive_id = ?
        ");
        $stmt->execute([$holding['receive_id']]);
        $poInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // สร้าง receive_items ใหม่ด้วยสินค้าใหม่
        $stmt = $pdo->prepare("
            INSERT INTO receive_items (
                po_id, item_id,
                receive_qty, expiry_date,
                created_at, remark
            ) VALUES (
                ?, ?,
                ?, ?,
                NOW(), ?
            )
        ");
        
        $remark = "ย้ายจากสินค้าพัก {$holding['holding_code']} | SKU ใหม่: {$holding['new_sku']} | โปรโมชั่น: {$holding['promo_name']}";
        $stmt->execute([
            $poInfo['po_id'],
            $itemInfo['item_id'],
            $holding['holding_qty'],
            $holding['expiry_date'],
            $remark
        ]);

        // 3. บันทึกลงตารางความเคลื่อนไหวสินค้า (issue_items) เพื่อบันทึกการย้ายสินค้า
        $stmt = $pdo->prepare("
            INSERT INTO issue_items (
                product_id, receive_id, 
                issue_qty, sale_price, 
                issued_by, created_at, remark
            ) VALUES (
                ?, ?,
                ?, ?,
                ?, NOW(), ?
            )
        ");
        
        $stmt->execute([
            $newProductId,
            $holding['receive_id'],
            $holding['holding_qty'],
            $holding['sale_price'],
            $_SESSION['user_id'],
            "ย้ายจากสินค้าพัก {$holding['holding_code']} (โปรโมชั่น: {$holding['promo_name']})"
        ]);

        // 4. อัปเดต product_holding สถานะเป็น moved_to_sale
        $stmt = $pdo->prepare("
            UPDATE product_holding
            SET status = 'moved_to_sale',
                moved_at = NOW(),
                moved_by = ?
            WHERE holding_id = ?
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $holdingId
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "สร้างสินค้าใหม่ (SKU: {$holding['new_sku']}) จำนวน {$holding['holding_qty']} ชิ้น สำเร็จ และบันทึกลงตารางความเคลื่อนไหวแล้ว"
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log('API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
