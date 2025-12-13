<?php
// Clear any existing output buffer
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);
ini_set('error_log', '../logs/api_errors.log');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../config/db_connect.php';
    require_once __DIR__ . '/../includes/tag_validator.php';

    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('PDO connection not established');
    }
    
} catch (Exception $e) {
    error_log('Database connection failed in issue_product_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get session user
session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
$issue_tag = $data['issue_tag'] ?? '';
$products = $data['products'] ?? [];

if (empty($issue_tag)) {
    echo json_encode(['success' => false, 'message' => 'แท็คส่งออกจำเป็นต้องระบุ']);
    exit;
}

if (empty($products) || !is_array($products)) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสินค้าที่จะยิงออก']);
    exit;
}

error_log('Issue request: Tag=' . $issue_tag . ', Products=' . count($products) . ', User=' . $user_id);

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // ตรวจสอบรูปแบบแท็คส่งออกผ่านระบบ pattern กลาง หากไม่พบให้ fallback ไปตรรกะเดิม
    $platform = '';
    $patternName = '';
    try {
        $validation = validateTagNumber($issue_tag);
        if (!empty($validation['valid'])) {
            $platform = $validation['platform'] ?? '';
            $patternName = $validation['pattern_name'] ?? '';
        }
    } catch (Throwable $t) {
        error_log('Tag validator unavailable: ' . $t->getMessage());
    }

    if ($platform === '') {
        if (strlen($issue_tag) == 14) {
            $first_six = substr($issue_tag, 0, 6);
            $seventh_char = substr($issue_tag, 6, 1);

            if (ctype_digit($first_six) && ctype_alpha($seventh_char)) {
                $platform = 'Shopee';
            }
            if ($platform === '' && ctype_digit($issue_tag)) {
                $platform = 'Lazada';
            }
        } elseif (strlen($issue_tag) == 16 && ctype_digit($issue_tag)) {
            $platform = 'Lazada';
        }
    }

    if ($platform === '') {
        $platform = 'Internal';
    }
    
    // สร้าง Sales Order ก่อน
    $insert_sale_order = $pdo->prepare("
        INSERT INTO sales_orders (
            issue_tag, 
            platform, 
            total_amount, 
            total_items, 
            issued_by, 
            remark,
            sale_date
        ) VALUES (?, ?, 0.00, ?, ?, ?, NOW())
    ");
    
    $total_items = count($products);
    $remarkParts = ["แท็คส่งออก: {$issue_tag}"];
    if (!empty($platform)) {
        $remarkParts[] = "แพลตฟอร์ม: {$platform}";
    }
    if (!empty($patternName)) {
        $remarkParts[] = "รูปแบบ: {$patternName}";
    }
    $remark = implode(' | ', $remarkParts);
    
    $sale_order_result = $insert_sale_order->execute([
        $issue_tag,
        $platform,
        $total_items,
        $user_id,
        $remark
    ]);
    
    if (!$sale_order_result) {
        throw new Exception('ไม่สามารถสร้างรายการขายได้');
    }
    
    $sale_order_id = $pdo->lastInsertId();
    error_log("Created sale_order_id: {$sale_order_id}");
    
    $success_count = 0;
    $error_messages = [];
    $total_amount = 0;
    
    foreach ($products as $product) {
        // Validate product data
        $product_id = $product['product_id'] ?? 0;
        $receive_id = $product['receive_id'] ?? 0;
        $issue_qty = $product['issue_qty'] ?? 0;
        
        if (!$product_id || !$receive_id || !$issue_qty) {
            $error_messages[] = "ข้อมูลสินค้า {$product['name']} ไม่ครบถ้วน";
            continue;
        }
        
        // Check available quantity
        $check_stmt = $pdo->prepare("
            SELECT 
                ri.receive_qty,
                p.name,
                p.sku,
                poi.price_per_unit AS cost_price
            FROM receive_items ri
            INNER JOIN purchase_order_items poi ON poi.item_id = ri.item_id
            INNER JOIN products p ON p.product_id = poi.product_id
            WHERE ri.receive_id = ? AND poi.product_id = ?
        ");
        $check_stmt->execute([$receive_id, $product_id]);
        $available = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$available) {
            $error_messages[] = "ไม่พบข้อมูลสต็อกของสินค้า {$product['name']}";
            continue;
        }
        
        if ($available['receive_qty'] < $issue_qty) {
            $error_messages[] = "สินค้า {$available['name']} มีสต็อกไม่เพียงพอ (คงเหลือ: {$available['receive_qty']}, ต้องการ: {$issue_qty})";
            continue;
        }
        
        // Insert into issue_items with sale_order_id
        $insert_issue = $pdo->prepare("
            INSERT INTO issue_items (
                product_id, 
                receive_id,
                sale_order_id,
                issue_qty, 
                sale_price,
                cost_price,
                issued_by, 
                remark, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $sale_price = isset($product['sale_price']) && $product['sale_price'] !== '' ? (float) $product['sale_price'] : 0.0;
        $cost_price = isset($available['cost_price']) && $available['cost_price'] !== '' ? (float) $available['cost_price'] : 0.0;
        $item_remark = "ยิงสินค้าจากแท็ค: {$issue_tag}";
        
        $insert_result = $insert_issue->execute([
            $product_id,
            $receive_id,
            $sale_order_id,
            $issue_qty,
            $sale_price,
            $cost_price,
            $user_id,
            $item_remark
        ]);
        
        if (!$insert_result) {
            $error_messages[] = "ไม่สามารถบันทึกการยิงสินค้า {$available['name']} ได้";
            continue;
        }
        
        // Update receive_items quantity
        $update_receive = $pdo->prepare("
            UPDATE receive_items 
            SET receive_qty = receive_qty - ? 
            WHERE receive_id = ?
        ");
        
        $update_result = $update_receive->execute([$issue_qty, $receive_id]);
        
        if (!$update_result) {
            $error_messages[] = "ไม่สามารถอัปเดตสต็อกของสินค้า {$available['name']} ได้";
            continue;
        }

        $success_count++;
        $total_amount += ($sale_price * $issue_qty);
        error_log("Successfully issued: Product ID {$product_id}, Qty {$issue_qty}, Receive ID {$receive_id}, Sale Order ID {$sale_order_id}");
    }
    
    // อัพเดท total_amount และ total_items ใน sales_orders
    if ($success_count > 0) {
        $update_sale_order = $pdo->prepare("
            UPDATE sales_orders 
            SET total_amount = ?, total_items = ? 
            WHERE sale_order_id = ?
        ");
        $update_sale_order->execute([$total_amount, $success_count, $sale_order_id]);
    }
    
    // Check if any products were successfully processed
    if ($success_count === 0) {
        $pdo->rollBack();
        $message = 'ไม่สามารถยิงสินค้าออกได้: ' . implode(', ', $error_messages);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    // If there were some errors but some successes, still commit but warn
    if (!empty($error_messages) && $success_count > 0) {
        $pdo->commit();
        $message = "ยิงสินค้าออกสำเร็จ {$success_count} รายการ แต่มีปัญหา: " . implode(', ', $error_messages);
        echo json_encode(['success' => true, 'message' => $message, 'warnings' => $error_messages]);
    } else {
        // All successful
        $pdo->commit();
        $message = "ยิงสินค้าออกสำเร็จทั้งหมด {$success_count} รายการ (แท็ค: {$issue_tag})";
        echo json_encode(['success' => true, 'message' => $message, 'count' => $success_count]);
    }
    
    error_log("Issue completed: {$success_count} successful, " . count($error_messages) . " errors");

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('PDO Error in issue_product_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('General Error in issue_product_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}

// Ensure output is flushed
if (ob_get_level()) {
    ob_end_flush();
} else {
    flush();
}
?>