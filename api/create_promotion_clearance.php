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

        $checkSkuStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
        $insertHoldingStmt = $pdo->prepare("
            INSERT INTO product_holding (
                holding_code, product_id, receive_id,
                original_sku, new_sku, holding_qty, cost_price, sale_price,
                holding_reason, promo_name, promo_discount,
                expiry_date, days_to_expire, status,
                created_at, created_by, remark
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, 'returned_to_stock',
                NOW(), ?, ?
            )
        ");

        $updateReceiveStmt = $pdo->prepare("
            UPDATE receive_items
            SET receive_qty = receive_qty - ?
            WHERE receive_id = ? AND receive_qty >= ?
        ");

        $insertProductStmt = $pdo->prepare("
            INSERT INTO products (
                name, sku, barcode, unit, image,
                remark_color, remark_split, is_active,
                created_by, created_at, product_category_id, category_name
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, NOW(), ?, ?
            )
        ");

        // Dynamically match existing columns to avoid referencing fields missing in older schemas
        $poColumnsStmt = $pdo->query("SHOW COLUMNS FROM purchase_order_items");
        $purchaseOrderItemColumns = array_map(
            static fn($column) => $column['Field'] ?? null,
            $poColumnsStmt ? $poColumnsStmt->fetchAll(PDO::FETCH_ASSOC) : []
        );
        $purchaseOrderItemColumns = array_filter($purchaseOrderItemColumns);
        $purchaseOrderItemColumnSet = array_flip($purchaseOrderItemColumns);

        $poItemColumnCandidates = [
            'po_id',
            'product_id',
            'temp_product_id',
            'qty',
            'price_per_unit',
            'sale_price',
            'total',
            'created_at',
            'currency_id',
            'price_original',
            'price_base',
            'currency',
            'original_price',
            'quantity',
            'unit_price',
            'unit',
            'po_item_amount'
        ];

        $poItemInsertColumns = array_values(array_filter(
            $poItemColumnCandidates,
            static fn($column) => isset($purchaseOrderItemColumnSet[$column])
        ));

        if (empty($poItemInsertColumns)) {
            throw new Exception('purchase_order_items table ไม่มีคอลัมน์ที่รองรับสำหรับการบันทึกข้อมูลใหม่');
        }

        $poItemPlaceholders = implode(', ', array_fill(0, count($poItemInsertColumns), '?'));
        $poItemInsertSql = sprintf(
            'INSERT INTO purchase_order_items (%s) VALUES (%s)',
            implode(', ', $poItemInsertColumns),
            $poItemPlaceholders
        );
        $insertPurchaseOrderItemStmt = $pdo->prepare($poItemInsertSql);

        $poiSelectAliasMap = [
            'price_per_unit' => 'price_per_unit',
            'sale_price' => 'current_sale_price',
            'currency_id' => 'currency_id',
            'price_original' => 'price_original',
            'price_base' => 'price_base',
            'currency' => 'currency',
            'original_price' => 'original_price',
            'quantity' => 'poi_quantity',
            'unit' => 'poi_unit',
            'po_item_amount' => 'po_item_amount'
        ];

        $poiSelectParts = [];
        foreach ($poiSelectAliasMap as $column => $alias) {
            $alias = $alias ?: $column;
            if (isset($purchaseOrderItemColumnSet[$column])) {
                $poiSelectParts[] = "poi.$column AS $alias";
            } else {
                $poiSelectParts[] = "NULL AS $alias";
            }
        }

        $poiSelectClause = implode(",\n                    ", $poiSelectParts);

        $receiveItemSql = "
            SELECT 
                ri.receive_id,
                ri.item_id,
                ri.po_id,
                ri.receive_qty,
                ri.expiry_date,
                $poiSelectClause,
                p.sku,
                p.name,
                p.barcode,
                p.unit AS product_unit,
                p.image,
                p.remark_color,
                p.remark_split,
                p.is_active,
                p.product_category_id,
                p.category_name
            FROM receive_items ri
            LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
            LEFT JOIN products p ON poi.product_id = p.product_id
            WHERE poi.product_id = ? AND ri.receive_qty > 0
            ORDER BY ri.expiry_date ASC, ri.receive_id ASC
            LIMIT 1
        ";
        $receiveItemStmt = $pdo->prepare($receiveItemSql);

        $insertReceiveItemStmt = $pdo->prepare("
            INSERT INTO receive_items (
                item_id, po_id, receive_qty, expiry_date,
                remark_color, remark_split, remark, created_by, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, NOW()
            )
        ");

        $createdPromotions = [];

        foreach ($products as $product) {
            $productId = intval($product['product_id']);
            $stockInput = floatval($product['stock']);
            $stockQty = (int) round($stockInput);
            
            if ($stockQty <= 0) {
                $issueErrors[] = "สินค้า {$product['name']} จำนวน 0 ไม่สามารถพักไว้ได้";
                continue;
            }

            // หา receive_item ที่สอดคล้องเพื่อนำออก
            $receiveItemStmt->execute([$productId]);
            $receiveItem = $receiveItemStmt->fetch(PDO::FETCH_ASSOC);
            $receiveItemStmt->closeCursor();

            if (!$receiveItem) {
                $issueErrors[] = "ไม่พบการรับเข้าของ {$product['name']}";
                continue;
            }

            $availableQty = floatval($receiveItem['receive_qty']);
            if ($stockQty > $availableQty) {
                $issueErrors[] = "จำนวนคงเหลือไม่พอสำหรับ {$product['name']} (ต้องการ {$stockQty} แต่เหลือ {$availableQty})";
                continue;
            }

            $costPrice = floatval($receiveItem['price_per_unit'] ?? 0);
            $baseSalePrice = floatval($receiveItem['current_sale_price'] ?? 0);
            if ($baseSalePrice <= 0) {
                $baseSalePrice = $costPrice;
            }

            $discountRate = max(0, min(100, $promoDiscount));
            $salePrice = round($baseSalePrice * (1 - ($discountRate / 100)), 2);
            if ($salePrice < 0) {
                $salePrice = 0;
            }
            
            // คำนวณจำนวนวันที่เหลือ
            $expiryDate = $receiveItem['expiry_date'];
            $daysToExpire = null;
            if (!empty($expiryDate) && $expiryDate !== '0000-00-00') {
                $daysToExpire = (int) floor((strtotime($expiryDate) - strtotime(date('Y-m-d'))) / 86400);
            }

            $originalSku = $receiveItem['sku'] ?? '';
            $baseSku = $originalSku ? ('Exp' . $originalSku) : ('Exp' . $productId);
            $newSku = $baseSku;
            $attempt = 1;
            while (true) {
                $checkSkuStmt->execute([$newSku]);
                if ($checkSkuStmt->fetchColumn() == 0) {
                    break;
                }
                $suffix = strtoupper(substr(uniqid(), -4));
                $newSku = $baseSku . '-' . $suffix . ($attempt > 1 ? $attempt : '');
                $attempt++;
            }

            // ลดจำนวน receive_item
            $updateReceiveStmt->execute([$stockQty, $receiveItem['receive_id'], $stockQty]);
            if ($updateReceiveStmt->rowCount() === 0) {
                $issueErrors[] = "ไม่สามารถตัดสต็อกสินค้า {$product['name']} ได้ (สต็อกไม่พอ)";
                continue;
            }

            $holdingCode = 'HOLD-' . date('Ymd') . '-' . uniqid();

            $insertHoldingStmt->execute([
                $holdingCode,
                $productId,
                $receiveItem['receive_id'],
                $originalSku,
                $newSku,
                $stockQty,
                $costPrice,
                $salePrice,
                'สินค้าใกล้หมดอายุ - ต้องแก้ไข SKU',
                $promoName,
                intval($discountRate),
                $expiryDate,
                $daysToExpire,
                $userId,
                "โปรโมชั่น: {$promoName} | เหตุผล: {$promoReason}"
            ]);

            $holdingId = $pdo->lastInsertId();

            // สร้างสินค้าใหม่สำหรับโปรโมชั่น
            $productName = $receiveItem['name'] ?? ($product['name'] ?? 'สินค้าโปรโมชัน');
            $promoProductName = $promoName ? ("{$productName} ({$promoName})") : ($productName . ' (Exp)');
            $productBarcode = $receiveItem['barcode'] ?? null;
            $productUnit = $receiveItem['product_unit'] ?? null;
            $productImage = $receiveItem['image'] ?? null;
            $productRemarkColor = $receiveItem['remark_color'] ?? null;
            $productRemarkSplitValue = $receiveItem['remark_split'] ?? null;
            $productRemarkSplit = intval($productRemarkSplitValue ?? 0);
            $productActive = intval($receiveItem['is_active'] ?? 1);
            $productCategoryId = $receiveItem['product_category_id'] ?? null;
            $productCategoryName = $receiveItem['category_name'] ?? null;

            $insertProductStmt->execute([
                $promoProductName,
                $newSku,
                $productBarcode,
                $productUnit,
                $productImage,
                $productRemarkColor,
                $productRemarkSplitValue,
                $productActive,
                $userId,
                $productCategoryId,
                $productCategoryName
            ]);

            $newProductId = $pdo->lastInsertId();

            // บันทึกลง purchase_order_items สำหรับสินค้าใหม่
            $poId = $receiveItem['po_id'];
            $poCurrencyId = $receiveItem['currency_id'] ?? 1;
            $poCurrency = $receiveItem['currency'] ?? 'THB';
            $priceOriginal = $receiveItem['price_original'] ?? $costPrice;
            $priceBase = $receiveItem['price_base'] ?? $costPrice;
            $originalPrice = $receiveItem['original_price'] ?? $salePrice;
            $poUnit = $receiveItem['poi_unit'] ?? $productUnit;
            $lineTotalCost = round($costPrice * $stockQty, 2);
            $lineTotalSale = round($salePrice * $stockQty, 2);
            $poItemAmount = $receiveItem['po_item_amount'] ?? $lineTotalSale;

            $stockDecimal = round($stockQty, 4);

            $poItemValueMap = [
                'po_id' => $poId,
                'product_id' => $newProductId,
                'temp_product_id' => null,
                'qty' => $stockDecimal,
                'price_per_unit' => $costPrice,
                'sale_price' => $salePrice,
                'total' => $lineTotalCost,
                'created_at' => date('Y-m-d H:i:s'),
                'currency_id' => $poCurrencyId,
                'price_original' => $priceOriginal,
                'price_base' => $priceBase,
                'currency' => $poCurrency,
                'original_price' => $originalPrice,
                'quantity' => $stockDecimal,
                'unit_price' => $costPrice,
                'unit' => $poUnit,
                'po_item_amount' => $poItemAmount
            ];

            $poItemValues = [];
            foreach ($poItemInsertColumns as $column) {
                $poItemValues[] = $poItemValueMap[$column] ?? null;
            }

            $insertPurchaseOrderItemStmt->execute($poItemValues);

            $newItemId = $pdo->lastInsertId();

            // บันทึก receive_items สำหรับสินค้าโปรโมชั่น
            $receiveRemark = "สินค้าโปรโมชั่น {$holdingCode} (SKU: {$newSku})";
            $insertReceiveItemStmt->execute([
                $newItemId,
                $poId,
                $stockDecimal,
                $expiryDate,
                $productRemarkColor,
                $productRemarkSplit,
                $receiveRemark,
                $userId
            ]);

            $totalItemsHeld++;
            $createdPromotions[] = [
                'holding_id' => (int) $holdingId,
                'holding_code' => $holdingCode,
                'new_product_id' => (int) $newProductId,
                'new_sku' => $newSku,
                'promotion_qty' => $stockQty,
                'sale_price' => $salePrice
            ];
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
            'created_promotions' => $createdPromotions ?? [],
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
