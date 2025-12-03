<?php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้เข้าสู่ระบบ']);
    exit;
}

// Initialize database schema for cancel feature
initializeCancelSchema();

function initializeCancelSchema() {
    global $pdo;
    
    try {
        // Check and add missing columns
        $columns_to_check = [
            'is_cancelled' => "ALTER TABLE purchase_order_items ADD COLUMN is_cancelled TINYINT(1) DEFAULT 0 AFTER unit_price",
            'is_partially_cancelled' => "ALTER TABLE purchase_order_items ADD COLUMN is_partially_cancelled TINYINT(1) DEFAULT 0 AFTER is_cancelled",
            'cancel_qty' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_qty FLOAT DEFAULT 0 AFTER is_partially_cancelled",
            'cancel_qty_reason' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_qty_reason FLOAT DEFAULT 0 AFTER cancel_qty",
            'cancelled_by' => "ALTER TABLE purchase_order_items ADD COLUMN cancelled_by INT AFTER cancel_qty_reason",
            'cancelled_at' => "ALTER TABLE purchase_order_items ADD COLUMN cancelled_at DATETIME AFTER cancelled_by",
            'cancel_reason' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_reason VARCHAR(100) AFTER cancelled_at",
            'cancel_notes' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_notes TEXT AFTER cancel_reason"
        ];
        
        // Get existing columns
        $columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='purchase_order_items' AND TABLE_SCHEMA=DATABASE()";
        $stmt = $pdo->query($columns_sql);
        $existing_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
        
        // Add missing columns
        foreach ($columns_to_check as $col_name => $alter_sql) {
            if (!in_array($col_name, $existing_columns)) {
                try {
                    $pdo->exec($alter_sql);
                } catch (Exception $e) {
                    // Column might already exist or other issue, continue
                }
            }
        }
        
        // Check if activity_logs table exists
        $check_table_sql = "SHOW TABLES LIKE 'activity_logs'";
        $stmt = $pdo->query($check_table_sql);
        if ($stmt->rowCount() === 0) {
            try {
                $pdo->exec("CREATE TABLE activity_logs (
                    log_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(100),
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )");
            } catch (Exception $e) {
                // Table might already exist
            }
        }
    } catch (Exception $e) {
        // Silently fail - schema might already be set up
    }
}

// Handle update expiry date only (without quantity)
$action = $_POST['action'] ?? '';
if ($action === 'update_expiry_only') {
    try {
        $item_id = $_POST['item_id'] ?? null;
        $expiry_date = $_POST['expiry_date'] ?? null;
        
        if (!$item_id) {
            throw new Exception('ไม่พบ item_id');
        }
        
        // Validate expiry date format
        if ($expiry_date && !empty($expiry_date)) {
            $expiry_date_obj = DateTime::createFromFormat('Y-m-d', $expiry_date);
            if (!$expiry_date_obj) {
                throw new Exception('รูปแบบวันที่หมดอายุไม่ถูกต้อง');
            }
        } else {
            $expiry_date = null;
        }
        
        // Get the latest receive record for this item
        $get_receive_sql = "SELECT receive_id, po_id FROM receive_items WHERE item_id = ? ORDER BY receive_id DESC LIMIT 1";
        $get_receive_stmt = $pdo->prepare($get_receive_sql);
        $get_receive_stmt->execute([$item_id]);
        $receive_record = $get_receive_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receive_record) {
            // If no receive record exists, create one with just the expiry date
            $get_po_sql = "SELECT po_id FROM purchase_order_items WHERE item_id = ? LIMIT 1";
            $get_po_stmt = $pdo->prepare($get_po_sql);
            $get_po_stmt->execute([$item_id]);
            $po_info = $get_po_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$po_info) {
                throw new Exception('ไม่พบข้อมูลรายการสินค้า');
            }
            
            $insert_sql = "INSERT INTO receive_items (item_id, po_id, receive_qty, created_by, created_at, remark, expiry_date) 
                          VALUES (?, ?, 0, ?, NOW(), ?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $item_id,
                $po_info['po_id'],
                $user_id,
                'แก้ไขวันหมดอายุเท่านั้น',
                $expiry_date
            ]);
        } else {
            // Update the latest receive record
            $update_sql = "UPDATE receive_items SET expiry_date = ? WHERE receive_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$expiry_date, $receive_record['receive_id']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'บันทึกวันหมดอายุสำเร็จ'
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Update expiry error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
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

if ($action === 'cancel_item') {
    try {
        $item_id = $_POST['item_id'] ?? null;
        $cancel_type = $_POST['cancel_type'] ?? 'cancel_all';
        $cancel_qty = (float)($_POST['cancel_qty'] ?? 0);
        $cancel_reason = $_POST['cancel_reason'] ?? null;
        $cancel_notes = $_POST['cancel_notes'] ?? null;
        $po_number = $_POST['po_number'] ?? null;
        
        if (!$po_id || !$item_id || !$cancel_reason) {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }
        
        // Check if PO item exists and get details
        $check_sql = "SELECT item_id, qty as ordered_qty FROM purchase_order_items WHERE item_id = ? AND po_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$item_id, $po_id]);
        $item_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item_info) {
            throw new Exception('ไม่พบรายการสินค้า');
        }
        
        $ordered_qty = $item_info['ordered_qty'];
        
        // Get already received quantity
        $received_sql = "SELECT COALESCE(SUM(receive_qty), 0) as received FROM receive_items WHERE item_id = ?";
        $received_stmt = $pdo->prepare($received_sql);
        $received_stmt->execute([$item_id]);
        $received_qty = (float)$received_stmt->fetchColumn();
        
        // Start transaction
        $pdo->beginTransaction();
        
        if ($cancel_type === 'cancel_all') {
            // Cancel entire item
            // Create receive record with full quantity to mark as 100% complete
            $insert_sql = "INSERT INTO receive_items (item_id, po_id, receive_qty, created_by, created_at, remark) 
                          VALUES (?, ?, ?, ?, NOW(), ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $item_id,
                $po_id,
                $ordered_qty - $received_qty,  // Receive remaining amount to complete
                $user_id,
                'ยกเลิกสินค้าจำนวนทั้งหมด'
            ]);
            
            // Mark item as cancelled - store full qty in both fields
            $cancel_sql = "UPDATE purchase_order_items SET is_cancelled = 1, cancelled_by = ?, cancelled_at = NOW(), 
                          cancel_reason = ?, cancel_notes = ?, cancel_qty = ?, cancel_qty_reason = ? 
                          WHERE item_id = ?";
            $cancel_stmt = $pdo->prepare($cancel_sql);
            $cancel_stmt->execute([
                $user_id,
                $cancel_reason,
                $cancel_notes,
                $ordered_qty,
                $ordered_qty,
                $item_id
            ]);
            
            $message = 'ยกเลิกสินค้าทั้งหมดสำเร็จ และปิด PO ที่ 100%';
            
        } else if ($cancel_type === 'cancel_partial') {
            // Cancel partial quantity
            if ($cancel_qty <= 0) {
                throw new Exception('จำนวนที่ยกเลิกต้องมากกว่า 0');
            }
            
            if ($cancel_qty > $ordered_qty) {
                throw new Exception('จำนวนที่ยกเลิกเกินจำนวนที่สั่ง');
            }
            
            // Update the item to record partial cancellation - store cancel_qty in both fields
            $cancel_sql = "UPDATE purchase_order_items SET cancel_qty = ?, cancel_qty_reason = ?, cancelled_by = ?, cancelled_at = NOW(), 
                          cancel_reason = ?, cancel_notes = ?, is_partially_cancelled = 1
                          WHERE item_id = ?";
            $cancel_stmt = $pdo->prepare($cancel_sql);
            $cancel_stmt->execute([
                $cancel_qty,
                $cancel_qty,
                $user_id,
                $cancel_reason,
                $cancel_notes,
                $item_id
            ]);
            
            $message = "ยกเลิกสินค้า {$cancel_qty} หน่วยสำเร็จ";
        }
        
        // Log the cancellation
        try {
            $log_sql = "INSERT INTO activity_logs (user_id, action, description, created_at) 
                       VALUES (?, ?, ?, NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                $user_id, 
                'cancel_po_item', 
                "ยกเลิกสินค้าจาก PO {$po_number} ({$cancel_type}): เหตุผล={$cancel_reason}"
            ]);
        } catch (Exception $e) {
            // Silently fail if activity_logs doesn't exist
        }
        
        // Update PO status
        updatePOStatus($pdo, $po_id);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

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
    // Check completion status considering both received AND cancelled quantities
    $status_sql = "
        SELECT 
            poi.item_id,
            poi.qty as ordered_qty,
            COALESCE(received_summary.total_received, 0) as received_qty,
            COALESCE(poi.cancel_qty, 0) as cancel_qty,
            poi.is_cancelled,
            poi.is_partially_cancelled
        FROM purchase_order_items poi
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) received_summary ON poi.item_id = received_summary.item_id
        WHERE poi.po_id = ?
    ";
    
    $status_stmt = $pdo->prepare($status_sql);
    $status_stmt->execute([$po_id]);
    $items_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($items_data && count($items_data) > 0) {
        $total_items = 0;
        $fully_processed_items = 0; // Items that are fully received OR fully/partially cancelled
        $any_partial_processing = false; // Track if any item has partial processing
        
        foreach ($items_data as $item) {
            $total_items++;
            
            $ordered_qty = floatval($item['ordered_qty']);
            $received_qty = floatval($item['received_qty']);
            $cancel_qty = floatval($item['cancel_qty']);
            $is_cancelled = $item['is_cancelled'];
            $is_partially_cancelled = $item['is_partially_cancelled'];
            
            // Calculate total processed (received + cancelled)
            $total_processed = $received_qty + $cancel_qty;
            
            // Check if item is fully processed (received + cancelled = ordered)
            if ($total_processed >= $ordered_qty) {
                $fully_processed_items++;
            } else if ($received_qty > 0 || $cancel_qty > 0) {
                // Item has partial processing
                $any_partial_processing = true;
            }
        }
        
        // Determine new status
        $new_status = 'pending'; // Default
        
        // If all items have been fully processed (received + cancelled >= ordered)
        if ($fully_processed_items >= $total_items) {
            $new_status = 'completed';
        } elseif ($any_partial_processing || $fully_processed_items > 0) {
            // Some items have partial processing
            $new_status = 'partial';
        }
        
        error_log("PO Status Update: PO_ID=$po_id, Total Items=$total_items, Fully Processed=$fully_processed_items, New Status=$new_status");
        
        // Update PO status
        $update_sql = "UPDATE purchase_orders SET status = ? WHERE po_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_status, $po_id]);
    }
}
?>