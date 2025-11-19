<?php
// API for Item Borrow Management
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../config/db_connect.php';
    
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    
    switch ($action) {
        // Get all borrow records
        case 'list':
            $status = $_GET['status'] ?? null;
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            
            $sql = "SELECT b.*, u.name as created_by_name, bc.category_name
                    FROM item_borrows b
                    LEFT JOIN users u ON b.created_by = u.user_id
                    LEFT JOIN borrow_categories bc ON b.category_id = bc.category_id
                    WHERE 1=1";
            
            if ($status) {
                $sql .= " AND b.status = '" . $pdo->quote($status) . "'";
            }
            
            $sql .= " ORDER BY b.borrow_date DESC LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->query($sql);
            $borrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $borrows,
                'count' => count($borrows)
            ]);
            break;
            
        // Get borrow detail with items
        case 'get':
            $borrow_id = intval($_GET['id'] ?? 0);
            
            $sql = "SELECT b.*, u.name as created_by_name, bc.category_name
                    FROM item_borrows b
                    LEFT JOIN users u ON b.created_by = u.user_id
                    LEFT JOIN borrow_categories bc ON b.category_id = bc.category_id
                    WHERE b.borrow_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$borrow_id]);
            $borrow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$borrow) {
                throw new Exception("ไม่พบรายการยืม");
            }
            
            // Get items
            $sql = "SELECT * FROM borrow_items WHERE borrow_id = ? ORDER BY borrow_item_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$borrow_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'borrow' => $borrow,
                'items' => $items
            ]);
            break;
            
        // Create new borrow record
        case 'create':
            $pdo->beginTransaction();
            
            $category_id = intval($_POST['category_id'] ?? 1);
            $borrower_name = trim($_POST['borrower_name'] ?? '');
            $borrower_phone = trim($_POST['borrower_phone'] ?? '');
            $borrower_email = trim($_POST['borrower_email'] ?? '');
            $purpose = trim($_POST['purpose'] ?? '');
            $expected_return_date = $_POST['expected_return_date'] ?? null;
            $notes = trim($_POST['notes'] ?? '');
            $created_by = intval($_POST['created_by'] ?? 0);
            
            if (!$borrower_name) {
                throw new Exception("กรุณาระบุชื่อผู้ยืม");
            }
            
            // Generate borrow number
            $today = date('Y');
            $sql = "SELECT COUNT(*) FROM item_borrows WHERE YEAR(borrow_date) = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$today]);
            $count = $stmt->fetchColumn() + 1;
            $borrow_number = 'BRW-' . $today . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO item_borrows (borrow_number, category_id, borrower_name, borrower_phone, borrower_email, purpose, expected_return_date, notes, created_by, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$borrow_number, $category_id, $borrower_name, $borrower_phone, $borrower_email, $purpose, $expected_return_date, $notes, $created_by]);
            
            $borrow_id = $pdo->lastInsertId();
            
            // Add items if provided
            $items = json_decode($_POST['items'] ?? '[]', true);
            
            foreach ($items as $item) {
                $product_id = intval($item['product_id'] ?? 0);
                $product_name = trim($item['product_name'] ?? '');
                $sku = trim($item['sku'] ?? '');
                $qty = intval($item['qty'] ?? 1);
                $unit = trim($item['unit'] ?? 'ชิ้น');
                $image = $item['image'] ?? '';
                $item_notes = trim($item['notes'] ?? '');
                
                if (!$product_name || $qty < 1) continue;
                
                $sql = "INSERT INTO borrow_items (borrow_id, product_id, product_name, sku, qty, unit, image, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$borrow_id, $product_id > 0 ? $product_id : null, $product_name, $sku, $qty, $unit, $image, $item_notes]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'สร้างรายการยืมสำเร็จ',
                'borrow_id' => $borrow_id,
                'borrow_number' => $borrow_number
            ]);
            break;
            
        // Update return date
        case 'return':
            $borrow_id = intval($_POST['borrow_id'] ?? 0);
            $actual_return_date = $_POST['actual_return_date'] ?? date('Y-m-d H:i:s');
            $return_notes = trim($_POST['return_notes'] ?? '');
            
            if (!$borrow_id) {
                throw new Exception("ไม่ระบุรหัสการยืม");
            }
            
            $sql = "UPDATE item_borrows 
                    SET actual_return_date = ?, status = 'returned', notes = CONCAT(IFNULL(notes, ''), '\n[ผลการคืน] ', ?)
                    WHERE borrow_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$actual_return_date, $return_notes, $borrow_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'บันทึกการคืนสินค้าสำเร็จ'
            ]);
            break;
            
        // Get categories
        case 'categories':
            $sql = "SELECT * FROM borrow_categories ORDER BY category_name";
            $stmt = $pdo->query($sql);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            break;
            
        default:
            throw new Exception("ไม่พบ action ที่ขอ");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
