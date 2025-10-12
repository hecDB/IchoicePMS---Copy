<?php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้เข้าสู่ระบบ']);
    exit;
}

// รองรับทั้ง POST และ JSON input
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    // JSON input from Quick Receive
    $items = $input['items'] ?? [];
    if (!empty($items)) {
        // Quick Receive mode - handle multiple items
        try {
            $pdo->beginTransaction();
            
            $received_count = 0;
            foreach ($items as $item_data) {
                $item_id = $item_data['item_id'] ?? null;
                $product_id = $item_data['product_id'] ?? null;
                $quantity = (float)($item_data['received_qty'] ?? 0);
                $notes = $item_data['notes'] ?? '';
                $expiry_date = $item_data['expiry_date'] ?? null;

                if (!$item_id) {
                    throw new Exception('ไม่พบ item_id');
                }
                
                if ($quantity <= 0) {
                    throw new Exception('จำนวนต้องมากกว่า 0');
                }
                
                // ตรวจสอบรูปแบบวันที่หมดอายุ
                if ($expiry_date && !empty($expiry_date)) {
                    $expiry_date_obj = DateTime::createFromFormat('Y-m-d', $expiry_date);
                    if (!$expiry_date_obj) {
                        throw new Exception('รูปแบบวันที่หมดอายุไม่ถูกต้อง');
                    }
                    // ตรวจสอบว่าวันที่หมดอายุไม่เป็นอดีต
                    if ($expiry_date_obj < new DateTime('today')) {
                        throw new Exception('วันที่หมดอายุไม่สามารถเป็นวันที่ผ่านมาแล้วได้');
                    }
                } else {
                    $expiry_date = null;
                }

                // Get PO information from item_id
                $check_sql = "SELECT poi.po_id, poi.qty as ordered_qty, p.name as product_name 
                              FROM purchase_order_items poi 
                              LEFT JOIN products p ON poi.product_id = p.product_id
                              WHERE poi.item_id = ?";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$item_id]);
                $item = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new Exception('ไม่พบรายการสินค้าในใบสั่งซื้อ');
                }

                // Check already received quantity
                $received_sql = "SELECT COALESCE(SUM(receive_qty), 0) as received FROM receive_items WHERE item_id = ?";
                $received_stmt = $pdo->prepare($received_sql);
                $received_stmt->execute([$item_id]);
                $already_received = (float)$received_stmt->fetchColumn();

                $remaining = $item['ordered_qty'] - $already_received;
                if ($quantity > $remaining) {
                    throw new Exception("จำนวนที่รับมากเกินไป สำหรับ {$item['product_name']} (เหลือ $remaining)");
                }

                // Insert receive record
                $insert_sql = "INSERT INTO receive_items (item_id, po_id, receive_qty, created_by, created_at, remark, expiry_date) 
                               VALUES (?, ?, ?, ?, NOW(), ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([
                    $item_id,
                    $item['po_id'],
                    $quantity,
                    $user_id,
                    $notes,
                    $expiry_date
                ]);

                $received_count++;
            }

            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "รับเข้าสินค้าสำเร็จ จำนวน $received_count รายการ"
            ]);
            exit;

        } catch (Exception $e) {
            $pdo->rollback();
            error_log("Quick Receive Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Original POST handling for existing functionality
$action = $_POST['action'] ?? '';
$po_id = $_POST['po_id'] ?? null;

if (!$po_id || !in_array($action, ['receive_single', 'receive_multiple'])) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'receive_single') {
        $item_id = $_POST['item_id'] ?? null;
        $quantity = (int)($_POST['quantity'] ?? 0);

        if (!$item_id || $quantity <= 0) {
            throw new Exception('ข้อมูลไม่ถูกต้อง');
        }

        // Verify item belongs to PO
        $check_sql = "SELECT poi.item_id, poi.qty as quantity, p.name FROM purchase_order_items poi 
                      LEFT JOIN products p ON poi.product_id = p.product_id
                      WHERE poi.item_id = ? AND poi.po_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$item_id, $po_id]);
        $item = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception('ไม่พบรายการสินค้าในใบสั่งซื้อนี้');
        }

        // Check already received quantity
        $received_sql = "SELECT COALESCE(SUM(receive_qty), 0) as received FROM receive_items WHERE item_id = ?";
        $received_stmt = $pdo->prepare($received_sql);
        $received_stmt->execute([$item_id]);
        $already_received = (int)$received_stmt->fetchColumn();

        $remaining = $item['quantity'] - $already_received;
        if ($quantity > $remaining) {
            throw new Exception("จำนวนที่รับมากเกินไป (เหลือ $remaining)");
        }

        // ตรวจสอบวันที่หมดอายุ (ถ้ามี)
        $expiry_date = $_POST['expiry_date'] ?? null;
        if ($expiry_date && !empty($expiry_date)) {
            $expiry_date_obj = DateTime::createFromFormat('Y-m-d', $expiry_date);
            if (!$expiry_date_obj) {
                throw new Exception('รูปแบบวันที่หมดอายุไม่ถูกต้อง');
            }
            if ($expiry_date_obj < new DateTime('today')) {
                throw new Exception('วันที่หมดอายุไม่สามารถเป็นวันที่ผ่านมาแล้วได้');
            }
        } else {
            $expiry_date = null;
        }

        // Insert receive record
        $insert_sql = "INSERT INTO receive_items (item_id, receive_qty, created_by, created_at, po_id, remark, expiry_date) 
                       VALUES (?, ?, ?, NOW(), ?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            $item_id,
            $quantity,
            $user_id,
            $po_id,
            "รับสินค้าจาก PO: " . $_POST['po_number'] ?? '',
            $expiry_date
        ]);

        $message = "รับสินค้า {$item['name']} จำนวน $quantity เรียบร้อย";

    } elseif ($action === 'receive_multiple') {
        $items_json = $_POST['items'] ?? '[]';
        $items = json_decode($items_json, true);

        if (empty($items)) {
            throw new Exception('ไม่พบรายการสินค้าที่ต้องรับ');
        }

        $received_count = 0;

        foreach ($items as $item_data) {
            $item_id = $item_data['item_id'] ?? null;
            $quantity = (float)($item_data['quantity'] ?? 0);
            $expiry_date = $item_data['expiry_date'] ?? null;

            if (!$item_id || $quantity <= 0) {
                continue;
            }
            
            // ตรวจสอบวันที่หมดอายุ (ถ้ามี)
            if ($expiry_date && !empty($expiry_date)) {
                $expiry_date_obj = DateTime::createFromFormat('Y-m-d', $expiry_date);
                if (!$expiry_date_obj || $expiry_date_obj < new DateTime('today')) {
                    continue; // Skip item with invalid expiry date
                }
            } else {
                $expiry_date = null;
            }

            // Verify item belongs to PO
            $check_sql = "SELECT poi.qty as quantity FROM purchase_order_items poi WHERE poi.item_id = ? AND poi.po_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$item_id, $po_id]);
            $po_item = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po_item) {
                continue;
            }

            // Check already received quantity
            $received_sql = "SELECT COALESCE(SUM(receive_qty), 0) as received FROM receive_items WHERE item_id = ?";
            $received_stmt = $pdo->prepare($received_sql);
            $received_stmt->execute([$item_id]);
            $already_received = (float)$received_stmt->fetchColumn();

            $remaining = $po_item['quantity'] - $already_received;
            if ($quantity > $remaining) {
                $quantity = $remaining; // Adjust to remaining quantity
            }

            if ($quantity > 0) {
                // Insert receive record
                $insert_sql = "INSERT INTO receive_items (item_id, receive_qty, created_by, created_at, po_id, remark, expiry_date) 
                               VALUES (?, ?, ?, NOW(), ?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([
                    $item_id,
                    $quantity,
                    $user_id,
                    $po_id,
                    "รับสินค้าจาก PO (Batch)",
                    $expiry_date
                ]);
                $received_count++;
            }
        }

        if ($received_count === 0) {
            throw new Exception('ไม่สามารถรับสินค้าใดได้');
        }

        $message = "รับสินค้า $received_count รายการเรียบร้อย";
    }

    // Update PO status
    updatePOStatus($pdo, $po_id);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Receive PO error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function updatePOStatus($pdo, $po_id) {
    // Check completion status
    $status_sql = "
        SELECT 
            COUNT(poi.item_id) as total_items,
            SUM(CASE WHEN COALESCE(received_qty.total_received, 0) >= poi.qty THEN 1 ELSE 0 END) as completed_items
        FROM purchase_order_items poi
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) received_qty ON poi.item_id = received_qty.item_id
        WHERE poi.po_id = ?
    ";
    
    $status_stmt = $pdo->prepare($status_sql);
    $status_stmt->execute([$po_id]);
    $status_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($status_data) {
        $new_status = 'pending'; // Default
        
        if ($status_data['completed_items'] > 0) {
            if ($status_data['completed_items'] >= $status_data['total_items']) {
                $new_status = 'completed'; // Fully received
            } else {
                $new_status = 'partial'; // Partially received
            }
        }
        
        // Update PO status
        $update_sql = "UPDATE purchase_orders SET status = ? WHERE po_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_status, $po_id]);
    }
}
?>