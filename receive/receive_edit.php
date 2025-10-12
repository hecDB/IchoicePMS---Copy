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
    $sqlOriginal = "SELECT r.*, poi.product_id, p.sku, p.barcode, p.name as product_name 
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
        $splitInfo = json_decode($split_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('ข้อมูลการแบ่งจำนวนไม่ถูกต้อง');
        }
        
        // จัดการการแบ่งจำนวน
        handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $expiry_date, $row_code, $bin, $shelf);
        
    } else if ($po_id > 0 && $item_id > 0) {
        // อัปเดต receive_items พร้อมเปลี่ยน PO และ item_id (แบบปกติ)
        $sql = "UPDATE receive_items SET remark=?, receive_qty=?, expiry_date=?, po_id=?, item_id=? WHERE receive_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remark, $receive_qty, $expiry_date, $po_id, $item_id, $receive_id]);
        
    } else {
        // อัปเดต receive_items ปกติ
        $sql = "UPDATE receive_items SET remark=?, receive_qty=?, expiry_date=? WHERE receive_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remark, $receive_qty, $expiry_date, $receive_id]);
    }

    // อัปเดตตำแหน่ง (location_desc, row_code, bin, shelf) ในตาราง locations ถ้ามีข้อมูล
    if ($location_desc !== '' || $row_code !== '' || $bin !== '' || $shelf !== '') {
        // หา location_id จาก receive_items
        $sqlLoc = "SELECT l.location_id FROM receive_items r
            LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
            LEFT JOIN products p ON poi.product_id = p.product_id
            LEFT JOIN product_location pl ON pl.product_id = p.product_id
            LEFT JOIN locations l ON l.location_id = pl.location_id
            WHERE r.receive_id = ? LIMIT 1";
        $stmtLoc = $pdo->prepare($sqlLoc);
        $stmtLoc->execute([$receive_id]);
        $location_id = $stmtLoc->fetchColumn();
        if ($location_id) {
            $updateLoc = "UPDATE locations SET description=?, row_code=?, bin=?, shelf=? WHERE location_id=?";
            $pdo->prepare($updateLoc)->execute([
                $location_desc,
                $row_code,
                $bin,
                $shelf,
                $location_id
            ]);
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
    echo json_encode(['success' => true]);
    
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
function handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $expiry_date, $row_code, $bin, $shelf) {
    // Log การเริ่มต้นการแบ่งจำนวน
    error_log("Starting quantity split for receive_id: $receive_id");
    
    $originalQty = abs($originalData['receive_qty']);
    $totalSplitQty = 0;
    
    // ตรวจสอบความถูกต้องของข้อมูล
    if (!isset($splitInfo['mainQty']) || !isset($splitInfo['mainPoId']) || !isset($splitInfo['mainItemId'])) {
        throw new Exception('ข้อมูล PO หลักไม่สมบูรณ์');
    }
    
    // คำนวณจำนวนรวมที่แบ่ง
    $totalSplitQty += intval($splitInfo['mainQty'] ?? 0);
    if (isset($splitInfo['additionalPOs']) && is_array($splitInfo['additionalPOs'])) {
        foreach ($splitInfo['additionalPOs'] as $addPO) {
            if (!isset($addPO['qty']) || !isset($addPO['poId']) || !isset($addPO['itemId'])) {
                throw new Exception('ข้อมูล PO เพิ่มเติมไม่สมบูรณ์');
            }
            $totalSplitQty += intval($addPO['qty'] ?? 0);
        }
    }
    
    // ตรวจสอบจำนวนรวม
    if ($totalSplitQty != $originalQty) {
        error_log("Quantity mismatch: original=$originalQty, split_total=$totalSplitQty");
        throw new Exception("จำนวนรวมที่แบ่งไม่ตรงกับจำนวนเดิม (เดิม: $originalQty, แบ่ง: $totalSplitQty)");
    }
    
    // อัปเดตรายการเดิมให้เป็น PO หลักด้วยจำนวนใหม่
    $mainQty = intval($splitInfo['mainQty'] ?? 0);
    $mainPoId = intval($splitInfo['mainPoId'] ?? 0);
    $mainItemId = intval($splitInfo['mainItemId'] ?? 0);
    
    if ($originalData['receive_qty'] < 0) {
        $mainQty = -$mainQty; // ถ้าเดิมเป็นลบ ให้จำนวนใหม่เป็นลบด้วย
    }
    
    $sqlUpdate = "UPDATE receive_items SET receive_qty=?, po_id=?, item_id=?, remark=?, expiry_date=? WHERE receive_id=?";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([$mainQty, $mainPoId, $mainItemId, $remark, $expiry_date, $receive_id]);
    
    // สร้างรายการใหม่สำหรับ PO เพิ่มเติม
    if (isset($splitInfo['additionalPOs']) && is_array($splitInfo['additionalPOs'])) {
        foreach ($splitInfo['additionalPOs'] as $addPO) {
            $addQty = intval($addPO['qty'] ?? 0);
            $addPoId = intval($addPO['poId'] ?? 0);
            $addItemId = intval($addPO['itemId'] ?? 0);
            
            if ($addQty > 0 && $addPoId > 0 && $addItemId > 0) {
                if ($originalData['receive_qty'] < 0) {
                    $addQty = -$addQty; // ถ้าเดิมเป็นลบ ให้จำนวนใหม่เป็นลบด้วย
                }
                
                // สร้างรายการความเคลื่อนไหวใหม่
                $splitRemark = $remark . ' (แบ่งจาก PO เดิม)';
                $sqlInsert = "INSERT INTO receive_items (po_id, item_id, receive_qty, remark, expiry_date, created_by, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    $addPoId, 
                    $addItemId, 
                    $addQty, 
                    $splitRemark, 
                    $expiry_date, 
                    $originalData['created_by']
                ]);
                
                // ดึง receive_id ใหม่ที่เพิ่งสร้าง
                $newReceiveId = $pdo->lastInsertId();
                
                // Log การสร้างรายการใหม่
                error_log("Created new receive item: ID=$newReceiveId, PO_ID=$addPoId, Qty=$addQty");
                
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
