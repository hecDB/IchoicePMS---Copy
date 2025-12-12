<?php
/**
 * API: สินค้าตีกลับ
 * ดำเนินการ: สร้าง, ดูรายการ, ค้นหา, อนุมัติ, ปฏิเสธ
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// อ่าน action จาก GET, POST form-data, หรือ JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// ถ้าไม่พบ action ให้ลอง parse JSON body
if (!$action && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

// ========== GET RETURN REASONS ==========
if ($action === 'get_reasons') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM return_reasons 
            WHERE is_active = 1 
            ORDER BY category ASC, reason_name ASC
        ");
        $stmt->execute();
        $reasons = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $reasons]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== SEARCH PO BY PO_NUMBER OR TRACKING NUMBER ==========
if ($action === 'search_po') {
    try {
        $keyword = $_GET['keyword'] ?? '';
        
        $stmt = $pdo->prepare("
            SELECT 
                po.po_id,
                po.po_number,
                po.created_at,
                s.supplier_name,
                po.remark,
                COUNT(poi.item_id) as total_items
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
            WHERE po.po_number LIKE :keyword 
                OR po.remark LIKE :keyword
            GROUP BY po.po_id
            ORDER BY po.created_at DESC
            LIMIT 20
        ");
        
        $stmt->execute([':keyword' => "%{$keyword}%"]);
        $pos = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $pos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== SEARCH BY ISSUE TAG (เลขแท็ค) ==========
if ($action === 'search_by_issue_tag') {
    try {
        $keyword = $_GET['keyword'] ?? '';
        
        if (strlen($keyword) < 1) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                so.sale_order_id as so_id,
                so.issue_tag,
                so.created_at,
                u.name as customer_name,
                COALESCE(so.total_items, 0) as total_items
            FROM sales_orders so
            LEFT JOIN users u ON so.issued_by = u.user_id
            WHERE so.issue_tag LIKE :keyword 
            ORDER BY so.created_at DESC
            LIMIT 20
        ");
        
        $stmt->execute([':keyword' => "%{$keyword}%"]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $orders]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET PO ITEMS ==========
if ($action === 'get_po_items') {
    try {
        $po_id = $_GET['po_id'] ?? null;
        
        if (!$po_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'PO ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                poi.item_id,
                poi.po_id,
                poi.product_id,
                p.sku,
                p.barcode,
                p.name as product_name,
                p.image,
                poi.price_per_unit,
                ri.expiry_date,
                COALESCE(SUM(ret.return_qty), 0) as returned_qty,
                ri.receive_qty - COALESCE(SUM(ret.return_qty), 0) as available_qty,
                ri.receive_qty,
                l.location_id,
                l.row_code,
                l.bin,
                l.shelf
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.product_id
            LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
            LEFT JOIN product_location l ON p.product_id = l.product_id
            LEFT JOIN returned_items ret ON poi.item_id = ret.item_id AND ret.return_status != 'rejected'
            WHERE poi.po_id = :po_id
            GROUP BY poi.item_id
            ORDER BY poi.item_id ASC
        ");
        
        $stmt->execute([':po_id' => $po_id]);
        $items = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET SALES ORDER ITEMS ==========
if ($action === 'get_sales_order_items') {
    try {
        $so_id = $_GET['so_id'] ?? null;
        
        if (!$so_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Sales Order ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ii.issue_id as si_id,
                ii.sale_order_id as so_id,
                ii.product_id,
                p.sku,
                p.barcode,
                p.name as product_name,
                p.image,
                ii.issue_qty,
                0 as returned_qty,
                ii.issue_qty as available_qty
            FROM issue_items ii
            LEFT JOIN products p ON ii.product_id = p.product_id
            WHERE ii.sale_order_id = :so_id
            ORDER BY ii.issue_id ASC
        ");
        
        $stmt->execute([':so_id' => $so_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== CREATE RETURN ==========
if ($action === 'create_return') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $so_id = $data['so_id'] ?? null;
        $po_id = $data['po_id'] ?? null;
        $item_id = $data['item_id'] ?? null;
        $product_id = $data['product_id'] ?? null;
        $return_qty = $data['return_qty'] ?? 0;
        $reason_id = $data['reason_id'] ?? null;
        $notes = $data['notes'] ?? '';
        
        if (!$item_id || !$product_id || !$return_qty || !$reason_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }
        
        // Get product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :product_id");
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        // Determine if return is from sales or purchase
        $return_from_sales = !empty($so_id) ? 1 : 0;
        $po_number = null;
        $original_qty = 0;
        $issue_tag = null;
        
        if ($return_from_sales) {
            // Get sales order item details
            $stmt = $pdo->prepare("
                SELECT ii.*, so.issue_tag 
                FROM issue_items ii
                JOIN sales_orders so ON ii.sale_order_id = so.sale_order_id
                WHERE ii.issue_id = :item_id AND ii.sale_order_id = :so_id
            ");
            $stmt->execute([':item_id' => $item_id, ':so_id' => $so_id]);
            $order_item = $stmt->fetch();
            
            if (!$order_item) {
                throw new Exception('Sales order item not found');
            }
            
            $original_qty = $order_item['issue_qty'];
            $issue_tag = $order_item['issue_tag'];
            
            // ค้นหา po_id ที่มี product นี้ (สำหรับบันทึกวิบาก)
            if (!$po_id) {
                $stmt = $pdo->prepare("
                    SELECT MAX(po.po_id) as po_id, po.po_number
                    FROM purchase_orders po
                    JOIN purchase_order_items poi ON po.po_id = poi.po_id
                    WHERE poi.product_id = :product_id
                    ORDER BY po.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([':product_id' => $product_id]);
                $po_result = $stmt->fetch();
                $po_id = $po_result['po_id'] ?? null;
                $po_number = $po_result['po_number'] ?? null;
            }
        } else {
            // Get receive item details
            $stmt = $pdo->prepare("
                SELECT ri.*, poi.price_per_unit, po.po_number
                FROM receive_items ri
                JOIN purchase_order_items poi ON ri.item_id = poi.item_id
                JOIN purchase_orders po ON poi.po_id = po.po_id
                WHERE ri.item_id = :item_id AND ri.po_id = :po_id
            ");
            $stmt->execute([':item_id' => $item_id, ':po_id' => $po_id]);
            $order_item = $stmt->fetch();
            
            if (!$order_item) {
                throw new Exception('Purchase order item not found');
            }
            
            $original_qty = $order_item['receive_qty'];
            $po_number = $order_item['po_number'];
        }
        
        // Get reason details
        $stmt = $pdo->prepare("SELECT * FROM return_reasons WHERE reason_id = :reason_id");
        $stmt->execute([':reason_id' => $reason_id]);
        $reason = $stmt->fetch();
        
        if (!$reason) {
            throw new Exception('Return reason not found');
        }
        
        // Generate return code - ใช้ timestamp + random เพื่อหลีกเลี่ยงซ้ำ
        $timestamp = microtime(true) * 10000; // Convert to unique number
        $random = mt_rand(100, 999);
        $return_code = 'RET-' . date('Ymd') . '-' . str_pad(intval($timestamp) % 9999, 4, '0', STR_PAD_LEFT);
        
        // Insert return record
        $stmt = $pdo->prepare("
            INSERT INTO returned_items (
                return_code, po_id, po_number, so_id, issue_tag, item_id, product_id, 
                product_name, sku, barcode, original_qty, return_qty, reason_id, reason_name,
                is_returnable, return_status, return_from_sales, image_path, notes, expiry_date,
                location_id, created_by
            ) VALUES (
                :return_code, :po_id, :po_number, :so_id, :issue_tag, :item_id, :product_id,
                :product_name, :sku, :barcode, :original_qty, :return_qty, :reason_id, :reason_name,
                :is_returnable, 'pending', :return_from_sales, :image_path, :notes, :expiry_date,
                :location_id, :created_by
            )
        ");
        
        $stmt->execute([
            ':return_code' => $return_code,
            ':po_id' => $po_id,
            ':po_number' => $po_number,
            ':so_id' => $so_id,
            ':issue_tag' => $issue_tag,
            ':item_id' => $item_id,
            ':product_id' => $product_id,
            ':product_name' => $product['name'],
            ':sku' => $product['sku'],
            ':barcode' => $product['barcode'] ?? null,
            ':original_qty' => $original_qty,
            ':return_qty' => $return_qty,
            ':reason_id' => $reason_id,
            ':reason_name' => $reason['reason_name'],
            ':is_returnable' => $reason['is_returnable'],
            ':return_from_sales' => $return_from_sales,
            ':image_path' => $product['image'] ?? null,
            ':notes' => $notes,
            ':expiry_date' => $order_item['expiry_date'] ?? null,
            ':location_id' => null,
            ':created_by' => $user_id
        ]);
        
        $return_id = $pdo->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Return created successfully',
            'return_id' => $return_id,
            'return_code' => $return_code
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET RETURNS LIST ==========
if ($action === 'get_returns') {
    try {
        $status = $_GET['status'] ?? 'all';
        $is_returnable = $_GET['is_returnable'] ?? 'all';
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        $sql = "
            SELECT 
                ret.*,
                u.name as created_by_name,
                u2.name as approved_by_name
            FROM returned_items ret
            LEFT JOIN users u ON ret.created_by = u.user_id
            LEFT JOIN users u2 ON ret.approved_by = u2.user_id
            WHERE 1=1
        ";
        
        if ($status !== 'all') {
            $sql .= " AND ret.return_status = '" . $pdo->quote($status) . "'";
        }
        
        if ($is_returnable !== 'all') {
            $sql .= " AND ret.is_returnable = " . (int)$is_returnable;
        }
        
        $sql .= " ORDER BY ret.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->query($sql);
        $returns = $stmt->fetchAll();
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM returned_items WHERE 1=1";
        if ($status !== 'all') {
            $count_sql .= " AND return_status = '" . $pdo->quote($status) . "'";
        }
        if ($is_returnable !== 'all') {
            $count_sql .= " AND is_returnable = " . (int)$is_returnable;
        }
        
        $count_stmt = $pdo->query($count_sql);
        $total = $count_stmt->fetch()['total'];
        
        echo json_encode([
            'status' => 'success',
            'data' => $returns,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== APPROVE RETURN ==========
if ($action === 'approve_return') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $return_id = $data['return_id'] ?? null;
        
        if (!$return_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Return ID is required']);
            exit;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get return item details
        $stmt = $pdo->prepare("SELECT * FROM returned_items WHERE return_id = :return_id");
        $stmt->execute([':return_id' => $return_id]);
        $return_item = $stmt->fetch();
        
        if (!$return_item) {
            throw new Exception('Return item not found');
        }
        
        // Update return status
        $stmt = $pdo->prepare("
            UPDATE returned_items 
            SET return_status = 'approved', 
                approved_by = :approved_by,
                approved_at = NOW()
            WHERE return_id = :return_id
        ");
        
        $stmt->execute([
            ':return_id' => $return_id,
            ':approved_by' => $user_id
        ]);
        
        // ถ้าเป็นสินค้าที่สามารถคืนสต็อกได้ ให้เพิ่มลงใน receive_items
        if ($return_item['is_returnable'] == 1) {
            // แน่ใจว่า return_qty เป็นค่าบวก
            $return_qty = abs((float)$return_item['return_qty']);
            
            // หาข้อมูล PO จากสินค้าที่ตีกลับ
            $po_id = $return_item['po_id'];
            error_log("DEBUG: approve_return - return_id={$return_id}, po_id={$po_id}, product_id={$return_item['product_id']}, return_qty={$return_qty}");
            
            // ถ้าไม่มี po_id ให้ค้นหาจากสินค้า (สร้าง PO สำหรับการคืน หรือใช้ PO สุดท้าย)
            if (!$po_id) {
                // ค้นหา PO ล่าสุดที่มีสินค้านี้
                $stmt = $pdo->prepare("
                    SELECT MAX(po.po_id) as po_id 
                    FROM purchase_orders po
                    JOIN purchase_order_items poi ON po.po_id = poi.po_id
                    WHERE poi.product_id = :product_id
                    ORDER BY po.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([':product_id' => $return_item['product_id']]);
                $po_result = $stmt->fetch();
                $po_id = $po_result['po_id'] ?? null;
                error_log("DEBUG: Found po_id from search: {$po_id}");
            }
            
            // เพิ่มจำนวนลงใน receive_items
            if ($po_id) {
                // ค้นหา receive item สำหรับ product นี้จาก PO นี้
                // receive_items มี item_id (FK to purchase_order_items) ไม่ใช่ product_id
                $stmt = $pdo->prepare("
                    SELECT ri.* FROM receive_items ri
                    JOIN purchase_order_items poi ON ri.item_id = poi.item_id
                    WHERE ri.po_id = :po_id AND poi.product_id = :product_id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':po_id' => $po_id,
                    ':product_id' => $return_item['product_id']
                ]);
                $receive_item = $stmt->fetch();
                error_log("DEBUG: receive_item found: " . ($receive_item ? 'YES' : 'NO'));
                
                if ($receive_item) {
                    // Always record a new receive movement entry for returns
                    $base_remark = $receive_item['remark'] ?? '';
                    $clean_base = str_replace('ตีกลับ', '', $base_remark);
                    $clean_base = trim($clean_base);
                    $remark_lines = [];
                    if (!empty($clean_base)) {
                        $remark_lines[] = $clean_base;
                    }
                    $remark_lines[] = "รับคืนจากสินค้าตีกลับ: {$return_item['return_code']}";
                    $final_remark = implode("\n", $remark_lines);

                    $stmt = $pdo->prepare("
                        INSERT INTO receive_items (
                            po_id, item_id, receive_qty, expiry_date, remark, created_by, created_at
                        ) VALUES (
                            :po_id, :item_id, :receive_qty, :expiry_date, :remark, :created_by, NOW()
                        )
                    ");
                    $stmt->execute([
                        ':po_id' => $po_id,
                        ':item_id' => $receive_item['item_id'],
                        ':receive_qty' => $return_qty,
                        ':expiry_date' => $receive_item['expiry_date'] ?? null,
                        ':remark' => $final_remark,
                        ':created_by' => $user_id
                    ]);
                    error_log("DEBUG: INSERT receive_items (existing) - new ID: " . $pdo->lastInsertId());
                } else {
                    // ค้นหา item_id สำหรับ product นี้จาก PO นี้
                    $stmt = $pdo->prepare("
                        SELECT item_id FROM purchase_order_items 
                        WHERE po_id = :po_id AND product_id = :product_id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':po_id' => $po_id,
                        ':product_id' => $return_item['product_id']
                    ]);
                    $poi_result = $stmt->fetch();
                    error_log("DEBUG: poi_result found: " . ($poi_result ? 'YES (item_id=' . $poi_result['item_id'] . ')' : 'NO'));
                    
                    if ($poi_result) {
                        // Create new receive_items record
                        $stmt = $pdo->prepare("
                            INSERT INTO receive_items (
                                po_id, item_id, receive_qty, expiry_date, remark, created_by, created_at
                            ) VALUES (
                                :po_id, :item_id, :receive_qty, :expiry_date, :remark, :created_by, NOW()
                            )
                        ");
                        $stmt->execute([
                            ':po_id' => $po_id,
                            ':item_id' => $poi_result['item_id'],
                            ':receive_qty' => $return_qty,
                            ':expiry_date' => $return_item['expiry_date'] ?? null,
                            ':remark' => "รับคืนจากสินค้าตีกลับ: {$return_item['return_code']}",
                            ':created_by' => $user_id
                        ]);
                        error_log("DEBUG: INSERT receive_items (new) - new ID: " . $pdo->lastInsertId());
                    } else {
                        error_log("ERROR: ไม่พบ item_id สำหรับ product_id={$return_item['product_id']} ใน po_id={$po_id}");
                    }
                }
            } else {
                error_log("ERROR: ไม่พบ po_id สำหรับ return_id={$return_id}");
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Return approved successfully'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== REJECT RETURN ==========
if ($action === 'reject_return') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $return_id = $data['return_id'] ?? null;
        $reason = $data['reason'] ?? '';
        
        if (!$return_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Return ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE returned_items 
            SET return_status = 'rejected',
                notes = CONCAT(COALESCE(notes, ''), '\n[REJECTED] ', :reason, ' - by ', :approved_by, ' at ', NOW()),
                approved_by = :approved_by,
                approved_at = NOW()
            WHERE return_id = :return_id
        ");
        
        $stmt->execute([
            ':return_id' => $return_id,
            ':reason' => $reason,
            ':approved_by' => $user_id
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Return rejected successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== GET RETURN DETAILS ==========
if ($action === 'get_return') {
    try {
        $return_id = $_GET['return_id'] ?? null;
        
        if (!$return_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Return ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ret.*,
                u.name as created_by_name,
                u2.name as approved_by_name
            FROM returned_items ret
            LEFT JOIN users u ON ret.created_by = u.user_id
            LEFT JOIN users u2 ON ret.approved_by = u2.user_id
            WHERE ret.return_id = :return_id
        ");
        
        $stmt->execute([':return_id' => $return_id]);
        $return = $stmt->fetch();
        
        if (!$return) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Return not found']);
            exit;
        }
        
        echo json_encode(['status' => 'success', 'data' => $return]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
