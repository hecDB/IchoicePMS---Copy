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

try {
    include __DIR__ . '/../config/db_connect.php';
    
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('PDO connection not established');
    }
    
} catch (Exception $e) {
    error_log('Database connection failed in sales_orders_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// ฟังก์ชันตรวจสอบแพลตฟอร์มจากเลขแท็คด้วยระบบใหม่
function detectPlatformFromTag($tag) {
    if (empty($tag)) return '';
    
    // ใช้ระบบ tag validation ใหม่จากฐานข้อมูล
    try {
        global $pdo;
        
        // ดึงรูปแบบทั้งหมดที่เปิดใช้งาน
        $stmt = $pdo->query("SELECT platform, regex_pattern FROM tag_patterns WHERE is_active = 1 ORDER BY created_at DESC");
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern['regex_pattern'] . '/', $tag)) {
                return $pattern['platform'];
            }
        }
        
        // Fallback ถ้าไม่มีรูปแบบที่ตรงกัน
        return detectPlatformFallback($tag);
        
    } catch (Exception $e) {
        error_log('Platform detection error: ' . $e->getMessage());
        return detectPlatformFallback($tag);
    }
}

// ระบบ fallback สำหรับตรวจสอบแพลตฟอร์ม
function detectPlatformFallback($tag) {
    $tagLength = strlen($tag);
    
    // Shopee มาตรฐาน 2025: 5-6 ตัวอักษร + 8 ตัวเลข
    if (($tagLength == 13 || $tagLength == 14) && 
        preg_match('/^[A-Za-z]{5,6}[0-9]{8}$/', $tag)) {
        return 'Shopee';
    }
    
    // Shopee เก่า: 10-12 ตัวเลข
    if ($tagLength >= 10 && $tagLength <= 12 && ctype_digit($tag)) {
        return 'Shopee';
    }
    
    // Shopee SP format
    if (preg_match('/^SP[0-9]{8}$/', $tag)) {
        return 'Shopee';
    }
    
    // Lazada: 9 ตัวเลข
    if ($tagLength == 9 && ctype_digit($tag)) {
        return 'Lazada';
    }
    
    // Lazada LZ format
    if (preg_match('/^LZ[0-9]{7}$/', $tag)) {
        return 'Lazada';
    }
    
    return '';
}

// Get session user
session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Get parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $platform = $_GET['platform'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        // ปรับปรุงการค้นหาให้ค้นหาได้หลายฟิลด์
        $where_conditions[] = "(
            so.issue_tag LIKE ? OR 
            so.remark LIKE ? OR 
            u.name LIKE ? OR 
            EXISTS (
                SELECT 1 FROM issue_items ii 
                LEFT JOIN products p ON p.product_id = ii.product_id 
                WHERE ii.sale_order_id = so.sale_order_id 
                AND (p.name LIKE ? OR p.sku LIKE ?)
            )
        )";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // ปรับปรุงการกรองแพลตฟอร์มให้รองรับทั้งที่บันทึกไว้และตรวจสอบแบบเรียลไทม์
    if (!empty($platform)) {
        if ($platform === 'General') {
            // กรองรายการที่ไม่ใช่ Shopee หรือ Lazada
            $where_conditions[] = "(so.platform IS NULL OR so.platform = '' OR so.platform NOT IN ('Shopee', 'Lazada'))";
        } else {
            $where_conditions[] = "so.platform = ?";
            $params[] = $platform;
        }
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "DATE(so.sale_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "DATE(so.sale_date) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total records
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM sales_orders so 
        {$where_clause}
    ";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get sales orders with details
    $sql = "
        SELECT 
            so.sale_order_id,
            so.issue_tag,
            so.platform,
            so.total_amount,
            so.total_items,
            so.sale_date,
            so.remark,
            u.name as issued_by_name,
            COUNT(ii.issue_id) as actual_items,
            SUM(ii.issue_qty) as total_qty
        FROM sales_orders so
        LEFT JOIN users u ON u.user_id = so.issued_by
        LEFT JOIN issue_items ii ON ii.sale_order_id = so.sale_order_id
        {$where_clause}
        GROUP BY so.sale_order_id
        ORDER BY so.sale_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed items for each sale order
    if (!empty($sales_orders)) {
        $sale_order_ids = array_column($sales_orders, 'sale_order_id');
        $placeholders = str_repeat('?,', count($sale_order_ids) - 1) . '?';
        
        $items_sql = "
            SELECT 
                ii.sale_order_id,
                ii.issue_id,
                ii.issue_qty,
                ii.sale_price,
                ii.created_at as issue_date,
                p.name as product_name,
                p.sku,
                p.unit,
                p.image,
                ri.expiry_date,
                ri.remark as lot_info,
                (ii.issue_qty * COALESCE(ii.sale_price, 0)) as line_total
            FROM issue_items ii
            LEFT JOIN products p ON p.product_id = ii.product_id
            LEFT JOIN receive_items ri ON ri.receive_id = ii.receive_id
            WHERE ii.sale_order_id IN ({$placeholders})
            ORDER BY ii.created_at ASC
        ";
        
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute($sale_order_ids);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group items by sale_order_id
        $items_by_order = [];
        foreach ($items as $item) {
            $items_by_order[$item['sale_order_id']][] = $item;
        }
        
        // Add items to each sale order
        foreach ($sales_orders as &$order) {
            $order['items'] = $items_by_order[$order['sale_order_id']] ?? [];
            
            // Format dates
            $order['sale_date_formatted'] = date('d/m/Y H:i', strtotime($order['sale_date']));
            
            // Format amounts
            $order['total_amount_formatted'] = number_format($order['total_amount'], 2);
            
            // ตรวจสอบและอัพเดทแพลตฟอร์มด้วยระบบใหม่
            $detected_platform = detectPlatformFromTag($order['issue_tag']);
            
            // อัพเดทแพลตฟอร์มในฐานข้อมูลถ้ายังไม่มีหรือไม่ตรงกัน
            if (empty($order['platform']) || $order['platform'] !== $detected_platform) {
                if (!empty($detected_platform)) {
                    try {
                        $update_stmt = $pdo->prepare("UPDATE sales_orders SET platform = ? WHERE sale_order_id = ?");
                        $update_stmt->execute([$detected_platform, $order['sale_order_id']]);
                        $order['platform'] = $detected_platform;
                    } catch (Exception $e) {
                        error_log('Platform update error: ' . $e->getMessage());
                    }
                }
            }
            
            // Platform badge class with improved styling
            $order['platform_class'] = match($order['platform']) {
                'Shopee' => 'shopee-badge', // สีส้ม Shopee
                'Lazada' => 'lazada-badge', // สีฟ้าอมม่วง Lazada  
                default => 'badge bg-secondary' // สีเทาสำหรับทั่วไป
            };
            
            // เพิ่มข้อมูลสำหรับการแสดงผล
            $order['platform_display'] = match($order['platform']) {
                'Shopee' => '🛍️ Shopee',
                'Lazada' => '🛒 Lazada',
                default => '📦 ทั่วไป'
            };
        }
    }
    
    $total_pages = ceil($total_records / $limit);
    
    $response = [
        'success' => true,
        'data' => $sales_orders,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => intval($total_records),
            'limit' => $limit,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('PDO Error in sales_orders_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('General Error in sales_orders_api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Ensure output is flushed
if (ob_get_level()) {
    ob_end_flush();
} else {
    flush();
}
?>