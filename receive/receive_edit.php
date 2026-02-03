<?php
// receive_edit.php
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$receive_id = isset($_POST['receive_id']) ? intval($_POST['receive_id']) : 0;
$remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
$location_desc = isset($_POST['location_desc']) ? trim($_POST['location_desc']) : '';
$price_per_unit = isset($_POST['price_per_unit']) ? floatval($_POST['price_per_unit']) : 0;
$sale_price = isset($_POST['sale_price']) ? floatval($_POST['sale_price']) : 0;
$receive_qty = isset($_POST['receive_qty']) ? intval($_POST['receive_qty']) : 0;
$expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
$row_code = isset($_POST['row_code']) ? trim($_POST['row_code']) : '';
$bin = isset($_POST['bin']) ? trim($_POST['bin']) : '';
$shelf = isset($_POST['shelf']) ? trim($_POST['shelf']) : '';

// Debug logging - DETAILED
error_log("=== RECEIVE_EDIT START ===");
error_log("receive_id: " . $receive_id);
error_log("expiry_date raw: " . var_export($expiry_date, true));
error_log("expiry_date length: " . strlen($expiry_date ?? ''));
error_log("expiry_date is_null: " . (is_null($expiry_date) ? 'yes' : 'no'));
error_log("expiry_date is_empty_string: " . ($expiry_date === '' ? 'yes' : 'no'));
error_log("receive_qty: " . $receive_qty);
error_log("remark: " . $remark);
error_log("POST keys: " . implode(', ', array_keys($_POST)));
error_log("POST expiry_date key exists: " . (isset($_POST['expiry_date']) ? 'yes' : 'no'));
error_log("FULL POST: " . json_encode($_POST));

// เพิ่มตัวแปรสำหรับการเปลี่ยน PO
$po_id = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

// เพิ่มตัวแปรสำหรับข้อมูลการแบ่งจำนวน
$split_data = isset($_POST['split_data']) ? $_POST['split_data'] : null;
$is_split = !empty($split_data);

if ($receive_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID รายการ']);
    exit;
}

try {
    // เริ่ม transaction
    $pdo->beginTransaction();
    
    // ดึงข้อมูล receive_items เดิมก่อนการแก้ไข
    $sqlOriginal = "SELECT r.*, poi.product_id, poi.temp_product_id, p.sku, p.barcode, p.name as product_name 
                   FROM receive_items r 
                   LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id 
                   LEFT JOIN products p ON poi.product_id = p.product_id 
                   WHERE r.receive_id = ?";
    $stmtOriginal = $pdo->prepare($sqlOriginal);
    $stmtOriginal->execute([$receive_id]);
    $originalData = $stmtOriginal->fetch(PDO::FETCH_ASSOC);
    
    if (!$originalData) {
        throw new Exception('ไม่พบข้อมูลรายการรับสินค้า');
    }
    
    // ตรวจสอบว่ามีการแบ่งจำนวนหรือไม่
    if ($is_split && $split_data) {
        error_log("Split data received (raw): " . var_export($split_data, true));
        $splitInfo = json_decode($split_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            throw new Exception('ข้อมูลการแบ่งจำนวนไม่ถูกต้อง: ' . json_last_error_msg());
        }
        
        error_log("Split info parsed: " . json_encode($splitInfo));
        error_log("Main expiry date: " . var_export($splitInfo['mainExpiryDate'] ?? null, true));
        error_log("New receive_qty from form: $receive_qty");
        
        // จัดการการแบ่งจำนวน (ใช้ mainExpiryDate จาก splitInfo แทน $expiry_date)
        // ส่ง $receive_qty (จำนวนใหม่) ไปด้วยเพื่อตรวจสอบการแบ่ง
        handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $row_code, $bin, $shelf, $receive_qty);
        
    } else if ($po_id > 0 && $item_id > 0) {
        // อัปเดต receive_items พร้อมเปลี่ยน PO และ item_id (แบบปกติ)
        $sql = "UPDATE receive_items SET remark=?, receive_qty=?, expiry_date=?, po_id=?, item_id=? WHERE receive_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remark, $receive_qty, $expiry_date, $po_id, $item_id, $receive_id]);
        
    } else {
        // อัปเดต receive_items ปกติ
        $sql = "UPDATE receive_items SET remark=?, receive_qty=?, expiry_date=? WHERE receive_id=?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$remark, $receive_qty, $expiry_date, $receive_id]);
        error_log("Normal update executed. Expiry_date: " . var_export($expiry_date, true) . ", Rows affected: " . $stmt->rowCount());
    }

    // อัปเดตตำแหน่ง (location_desc, row_code, bin, shelf) และสร้าง mapping ถ้ายังไม่มี
    if ($location_desc !== '' || $row_code !== '' || $bin !== '' || $shelf !== '') {
        // ดึง product/temp_product ปัจจุบันของ receive นี้ (หลังการอัปเดตด้านบน)
        $stmtCurrent = $pdo->prepare("SELECT poi.product_id, poi.temp_product_id, poi.item_id
            FROM receive_items r
            LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
            WHERE r.receive_id = ? LIMIT 1");
        $stmtCurrent->execute([$receive_id]);
        $currentLink = $stmtCurrent->fetch(PDO::FETCH_ASSOC) ?: [];
        $currentProductId = isset($currentLink['product_id']) ? (int)$currentLink['product_id'] : 0;
        $currentTempProductId = isset($currentLink['temp_product_id']) ? (int)$currentLink['temp_product_id'] : 0;

        // ถ้าไม่ส่ง description มา แต่มี row/bin/shelf ให้สร้าง description อัตโนมัติ
        $locationDescValue = $location_desc;
        if ($locationDescValue === '' && $row_code !== '' && $bin !== '' && $shelf !== '') {
            $locationDescValue = "$row_code-$bin-$shelf";
        }

        $location_id = null;

        // พยายามหา location_id ที่ map กับสินค้าอยู่แล้ว
        if ($currentProductId) {
            $stmtLoc = $pdo->prepare("SELECT l.location_id FROM product_location pl
                LEFT JOIN locations l ON l.location_id = pl.location_id
                WHERE pl.product_id = ? LIMIT 1");
            $stmtLoc->execute([$currentProductId]);
            $location_id = (int)$stmtLoc->fetchColumn();
        }

        // ถ้าไม่มี mapping ให้ลองหา location จากพิกัดที่ส่งมา
        if (!$location_id && $row_code !== '' && $bin !== '' && $shelf !== '') {
            $stmtFindLoc = $pdo->prepare("SELECT location_id FROM locations WHERE row_code=? AND bin=? AND shelf=? LIMIT 1");
            $stmtFindLoc->execute([$row_code, $bin, $shelf]);
            $location_id = (int)$stmtFindLoc->fetchColumn();
        }

        // ถ้ายังไม่เจอ ให้สร้าง location ใหม่
        if (!$location_id && $row_code !== '' && $bin !== '' && $shelf !== '') {
            $stmtInsertLoc = $pdo->prepare("INSERT INTO locations (row_code, bin, shelf, description) VALUES (?,?,?,?)");
            $stmtInsertLoc->execute([$row_code, $bin, $shelf, $locationDescValue]);
            $location_id = (int)$pdo->lastInsertId();
        }

        if ($location_id && $currentProductId) {
            // ผูก product_location (อัปเดตของเดิมหรือสร้างใหม่)
            $stmtExistingLink = $pdo->prepare("SELECT id FROM product_location WHERE product_id=? LIMIT 1");
            $stmtExistingLink->execute([$currentProductId]);
            $existingLinkId = (int)$stmtExistingLink->fetchColumn();

            if ($existingLinkId) {
                $pdo->prepare("UPDATE product_location SET location_id=? WHERE id=?")->execute([$location_id, $existingLinkId]);
            } else {
                $pdo->prepare("INSERT INTO product_location (product_id, location_id) VALUES (?, ?)")
                    ->execute([$currentProductId, $location_id]);
            }

            // อัปเดต metadata ของ location
            $pdo->prepare("UPDATE locations SET description=?, row_code=?, bin=?, shelf=? WHERE location_id=?")
                ->execute([$locationDescValue, $row_code, $bin, $shelf, $location_id]);
        } elseif ($location_id && $currentTempProductId) {
            // กรณี temp_product ให้เก็บตำแหน่งไว้ใน temp_product_locations (สำหรับอนุมัติภายหลัง)
            $pdo->exec("CREATE TABLE IF NOT EXISTS temp_product_locations (
                temp_product_id INT PRIMARY KEY,
                location_id INT DEFAULT NULL,
                row_code VARCHAR(50) DEFAULT NULL,
                bin VARCHAR(50) DEFAULT NULL,
                shelf VARCHAR(50) DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmtTempLoc = $pdo->prepare("INSERT INTO temp_product_locations (temp_product_id, location_id, row_code, bin, shelf)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE location_id=VALUES(location_id), row_code=VALUES(row_code), bin=VALUES(bin), shelf=VALUES(shelf)");
            $stmtTempLoc->execute([$currentTempProductId, $location_id, $row_code, $bin, $shelf]);
        }
    }

    // อัปเดตราคาต้นทุน/ราคาขายใน purchase_order_items
    if ($item_id > 0) {
        // ใช้ item_id ที่เปลี่ยนใหม่
        $pdo->prepare("UPDATE purchase_order_items SET price_per_unit=?, sale_price=? WHERE item_id=?")
            ->execute([$price_per_unit, $sale_price, $item_id]);
    } else {
        // ใช้ item_id เดิม
        $sqlItem = "SELECT item_id FROM receive_items WHERE receive_id=?";
        $stmtItem = $pdo->prepare($sqlItem);
        $stmtItem->execute([$receive_id]);
        $current_item_id = $stmtItem->fetchColumn();
        if ($current_item_id) {
            $pdo->prepare("UPDATE purchase_order_items SET price_per_unit=?, sale_price=? WHERE item_id=?")
                ->execute([$price_per_unit, $sale_price, $current_item_id]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // เตรียมข้อมูล response
    $response = [
        'success' => true,
        'message' => 'บันทึกสำเร็จ'
    ];
    
    // ถ้าเป็นการแบ่งจำนวน ให้เพิ่มข้อมูลรายละเอียด
    if ($is_split && $split_data) {
        $splitInfo = json_decode($split_data, true);
        if ($splitInfo) {
            $response['is_split'] = true;
            $response['splits'] = [];
            
            // เพิ่มข้อมูล PO หลัก
            $response['splits'][] = [
                'poNumber' => $splitInfo['mainPoId'] . ' (Main)',
                'quantity' => $splitInfo['mainQty'],
                'expiry_date' => $splitInfo['mainExpiryDate'] ?? 'ไม่ระบุ'
            ];
            
            // เพิ่มข้อมูล PO เพิ่มเติม
            if (isset($splitInfo['additionalPOs']) && is_array($splitInfo['additionalPOs'])) {
                foreach ($splitInfo['additionalPOs'] as $addPO) {
                    $response['splits'][] = [
                        'poNumber' => $addPO['poNumber'],
                        'quantity' => $addPO['qty'],
                        'expiry_date' => $addPO['expiry_date'] ?? 'ไม่ระบุ'
                    ];
                }
            }
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * ฟังก์ชันจัดการการแบ่งจำนวนไปยัง PO หลายตัว
 */
function handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $row_code, $bin, $shelf, $newReceiveQty = null) {
    // Log การเริ่มต้นการแบ่งจำนวน
    error_log("=== handleQuantitySplit START ===");
    error_log("receive_id: $receive_id");
    
    // ✅ FIX: ใช้จำนวนใหม่จากฟอร์ม (ถ้ามี) เพื่อตรวจสอบการแบ่ง
    // หากไม่ระบุจำนวนใหม่ จะใช้จำนวนเดิมจากฐานข้อมูล (เพื่อความเข้ากันได้)
    $expectedTotalQty = $newReceiveQty !== null ? abs($newReceiveQty) : abs($originalData['receive_qty']);
    
    error_log("Original receive_qty from DB: " . $originalData['receive_qty']);
    error_log("New receive_qty from form: " . var_export($newReceiveQty, true));
    error_log("Expected total for validation: $expectedTotalQty");
    error_log("Split info JSON: " . json_encode($splitInfo));
    
    // ตรวจสอบความถูกต้องของข้อมูล
    if (!isset($splitInfo['mainQty']) || !isset($splitInfo['mainPoId']) || !isset($splitInfo['mainItemId'])) {
        error_log("ERROR: Missing main PO data in splitInfo");
        throw new Exception('ข้อมูล PO หลักไม่สมบูรณ์');
    }
    
    // คำนวณจำนวนรวมที่แบ่ง
    $mainQtyFromSplit = intval($splitInfo['mainQty'] ?? 0);
    $totalSplitQty = 0;
    $totalSplitQty += $mainQtyFromSplit;
    error_log("Main qty from split: $mainQtyFromSplit, Total so far: $totalSplitQty");
    
    if (isset($splitInfo['additionalPOs']) && is_array($splitInfo['additionalPOs'])) {
        $addPoCount = count($splitInfo['additionalPOs']);
        error_log("Additional POs count: $addPoCount");
        
        foreach ($splitInfo['additionalPOs'] as $idx => $addPO) {
            error_log("Processing additional PO $idx: " . json_encode($addPO));
            
            if (!isset($addPO['qty']) || !isset($addPO['poId']) || !isset($addPO['itemId'])) {
                error_log("ERROR: Missing data in additional PO $idx");
                throw new Exception('ข้อมูล PO เพิ่มเติมไม่สมบูรณ์');
            }
            $addQty = intval($addPO['qty'] ?? 0);
            $totalSplitQty += $addQty;
            error_log("Additional PO $idx qty: $addQty, Total so far: $totalSplitQty");
        }
    } else {
        error_log("No additional POs");
    }
    
    error_log("Final totals - Expected: $expectedTotalQty, Split total: $totalSplitQty, Match: " . ($totalSplitQty == $expectedTotalQty ? 'YES' : 'NO'));
    
    // ✅ FIX: ตรวจสอบจำนวนรวม - ต้องเท่ากับจำนวนที่คาดหวัง (ใหม่ หรือ เดิม)
    if ($totalSplitQty != $expectedTotalQty) {
        error_log("VALIDATION FAILED: Quantity mismatch - expected=$expectedTotalQty, split_total=$totalSplitQty");
        throw new Exception("จำนวนรวมที่แบ่งไม่ตรงกับจำนวน (คาดหวัง: $expectedTotalQty, แบ่ง: $totalSplitQty)");
    }
    
    error_log("Quantity validation PASSED");
    
    // อัปเดตรายการเดิมให้เป็น PO หลักด้วยจำนวนใหม่
    $mainQty = intval($splitInfo['mainQty'] ?? 0);
    $mainPoId = intval($splitInfo['mainPoId'] ?? 0);
    $mainItemId = intval($splitInfo['mainItemId'] ?? 0);
    $mainExpiryDate = $splitInfo['mainExpiryDate'] ?? null;
    
    error_log("Split update - mainQty: $mainQty, mainPoId: $mainPoId, mainItemId: $mainItemId, mainExpiryDate: $mainExpiryDate");
    
    if ($originalData['receive_qty'] < 0) {
        $mainQty = -$mainQty; // ถ้าเดิมเป็นลบ ให้จำนวนใหม่เป็นลบด้วย
        error_log("Original qty was negative, adjusting mainQty to: $mainQty");
    }
    
    $sqlUpdate = "UPDATE receive_items SET receive_qty=?, po_id=?, item_id=?, remark=?, expiry_date=? WHERE receive_id=?";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $result = $stmtUpdate->execute([$mainQty, $mainPoId, $mainItemId, $remark, $mainExpiryDate, $receive_id]);
    error_log("Split UPDATE main PO - Result: " . ($result ? 'SUCCESS' : 'FAILED') . ", Rows affected: " . $stmtUpdate->rowCount());
    
    // สร้างรายการใหม่สำหรับ PO เพิ่มเติม
    if (isset($splitInfo['additionalPOs']) && is_array($splitInfo['additionalPOs'])) {
        foreach ($splitInfo['additionalPOs'] as $addPO) {
            $addQty = intval($addPO['qty'] ?? 0);
            $addPoId = intval($addPO['poId'] ?? 0);
            $addItemId = intval($addPO['itemId'] ?? 0);
            $addExpiryDate = $addPO['expiry_date'] ?? null;
            
            if ($addQty > 0 && $addPoId > 0 && $addItemId > 0) {
                if ($originalData['receive_qty'] < 0) {
                    $addQty = -$addQty; // ถ้าเดิมเป็นลบ ให้จำนวนใหม่เป็นลบด้วย
                }
                
                // สร้างรายการความเคลื่อนไหวใหม่ด้วย expiry_date ที่ระบุไว้
                $splitRemark = $remark . ' (แบ่งจาก PO เดิม)';
                error_log("Additional PO insert - addExpiryDate: " . var_export($addExpiryDate, true) . ", addQty: $addQty");
                $sqlInsert = "INSERT INTO receive_items (po_id, item_id, receive_qty, remark, expiry_date, created_by, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $execResult = $stmtInsert->execute([
                    $addPoId, 
                    $addItemId, 
                    $addQty, 
                    $splitRemark, 
                    $addExpiryDate, 
                    $originalData['created_by']
                ]);
                
                error_log("Additional PO INSERT result: " . var_export($execResult, true));
                
                // ดึง receive_id ใหม่ที่เพิ่งสร้าง
                $newReceiveId = $pdo->lastInsertId();
                
                // Log การสร้างรายการใหม่
                error_log("Created new receive item: ID=$newReceiveId, PO_ID=$addPoId, Qty=$addQty, Expiry=$addExpiryDate");
                
                // อัปเดตตำแหน่งสำหรับรายการใหม่ (ถ้ามี)
                updateLocationForReceiveItem($pdo, $newReceiveId, $addItemId, $row_code, $bin, $shelf);
            }
        }
    }
}

/**
 * ฟังก์ชันอัปเดตตำแหน่งสำหรับรายการรับสินค้า
 */
function updateLocationForReceiveItem($pdo, $receive_id, $item_id, $row_code, $bin, $shelf) {
    if (empty($row_code) && empty($bin) && empty($shelf)) {
        return; // ไม่มีข้อมูลตำแหน่งให้อัปเดต
    }
    
    // หา location_id จาก item_id
    $sqlLoc = "SELECT pl.location_id FROM purchase_order_items poi
               LEFT JOIN products p ON poi.product_id = p.product_id
               LEFT JOIN product_location pl ON pl.product_id = p.product_id
               WHERE poi.item_id = ? LIMIT 1";
    $stmtLoc = $pdo->prepare($sqlLoc);
    $stmtLoc->execute([$item_id]);
    $location_id = $stmtLoc->fetchColumn();
    
    if ($location_id) {
        $location_desc = '';
        if ($row_code && $bin && $shelf) {
            $location_desc = "$row_code-$bin-$shelf";
        }
        
        $updateLoc = "UPDATE locations SET description=?, row_code=?, bin=?, shelf=? WHERE location_id=?";
        $pdo->prepare($updateLoc)->execute([$location_desc, $row_code, $bin, $shelf, $location_id]);
    }
}
