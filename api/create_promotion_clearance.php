<?php
// API ขัดคุณภาพสินค้าใกล้หมดอายุ สร้างโปรโมชั่นและออกสินค้า
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';

try {
    // Validate user logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('ไม่ได้เข้าสู่ระบบ');
    }

    // Get JSON data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Debug: Log what we received
    error_log('Raw input: ' . $rawInput);
    error_log('Decoded input: ' . json_encode($input));
    
    if (!$input) {
        throw new Exception('ไม่สามารถแปลง JSON ได้: ' . json_last_error_msg());
    }
    
    if (empty($input['products']) || !is_array($input['products']) || count($input['products']) === 0) {
        throw new Exception('ไม่พบสินค้าที่เลือก หรือข้อมูลไม่ถูกต้อง');
    }

    $promoName = $input['promo_name'] ?? 'โปรโมชั่นสินค้าใกล้หมดอายุ';
    $promoDiscount = floatval($input['promo_discount'] ?? 0);
    $promoReason = $input['promo_reason'] ?? 'ใกล้หมดอายุ';
    $products = $input['products'];
    $userId = $_SESSION['user_id'];

    // Start transaction
    $pdo->beginTransaction();

    try {
        // ออกสินค้าจากตาราง receive_items และบันทึกไปตารางพักสินค้า (product_holding)
        $totalItemsHeld = 0;
        $issueErrors = [];

        foreach ($products as $product) {
            $productId = intval($product['product_id']);
            $stock = intval($product['stock']);
            
            if ($stock <= 0) {
                $issueErrors[] = "สินค้า {$product['name']} จำนวน 0 ไม่สามารถพักไว้ได้";
                continue;
            }

            // หา receive_item ที่สอดคล้องเพื่อนำออก
            $stmt = $pdo->prepare("
                SELECT ri.receive_id, ri.item_id, ri.po_id, poi.price_per_unit, ri.expiry_date, p.sku
                FROM receive_items ri
                LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
                LEFT JOIN products p ON poi.product_id = p.product_id
                WHERE poi.product_id = ? AND ri.receive_qty > 0
                ORDER BY ri.expiry_date ASC, ri.receive_id ASC
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $receiveItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receiveItem) {
                $issueErrors[] = "ไม่พบการรับเข้าของ {$product['name']}";
                continue;
            }

            // คำนวณราคาขายหลังส่วนลด
            $costPrice = floatval($receiveItem['price_per_unit'] ?? 0);
            $salePrice = $costPrice * (1 - $promoDiscount / 100);
            
            // คำนวณจำนวนวันที่เหลือ
            $expiryDate = $receiveItem['expiry_date'];
            $daysToExpire = (strtotime($expiryDate) - time()) / 86400;

            // สร้างบันทึกในตารางพักสินค้า (product_holding)
            $stmt = $pdo->prepare("
                INSERT INTO product_holding (
                    holding_code, product_id, receive_id,
                    original_sku, holding_qty, cost_price, sale_price,
                    holding_reason, promo_name, promo_discount,
                    expiry_date, days_to_expire, status,
                    created_at, created_by, remark
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, 'holding',
                    NOW(), ?, ?
                )
            ");

            $holdingCode = 'HOLD-' . date('Ymd') . '-' . uniqid();
            $stmt->execute([
                $holdingCode,
                $productId,
                $receiveItem['receive_id'],
                $receiveItem['sku'] ?? '',
                $stock,
                $costPrice,
                $salePrice,
                'สินค้าใกล้หมดอายุ - ต้องแก้ไข SKU',
                $promoName,
                $promoDiscount,
                $expiryDate,
                intval($daysToExpire),
                $userId,
                "โปรโมชั่น: {$promoName} | เหตุผล: {$promoReason}"
            ]);

            // ลดจำนวน receive_item
            $stmt = $pdo->prepare("
                UPDATE receive_items 
                SET receive_qty = receive_qty - ?
                WHERE receive_id = ?
            ");
            $stmt->execute([$stock, $receiveItem['receive_id']]);

            $totalItemsHeld++;
        }

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'promo_name' => $promoName,
            'promo_discount' => $promoDiscount,
            'item_count' => $totalItemsHeld,
            'errors' => $issueErrors,
            'message' => "สร้างโปรโมชั่นและพักสินค้าสำเร็จ ($totalItemsHeld รายการ)"
        ]);

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log('API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'error_code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
