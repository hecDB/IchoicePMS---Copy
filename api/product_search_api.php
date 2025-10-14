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

// Debug: Log incoming request
$query_param = $_GET['q'] ?? 'empty';
error_log('=== product_search_api.php START ===');
error_log('Request query: ' . $query_param);
error_log('Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
error_log('Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'CLI'));

try {
    include __DIR__ . '/../config/db_connect.php'; // เชื่อมฐานข้อมูล
    
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('PDO connection not established');
    }
    
} catch (Exception $e) {
    error_log('Database connection failed in product_search: ' . $e->getMessage());
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// รับ query จาก URL
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$type = isset($_GET['type']) ? $_GET['type'] : 'default';
$available_only = isset($_GET['available_only']) ? $_GET['available_only'] : false;

if($q === '') {
    echo json_encode([]);
    exit;
}

error_log("Searching for: '$q' with limit: $limit");

// เตรียมคำค้นแบบ %keyword%
$like = "%$q%";

try {
    // ถ้าเป็นการค้นหาสำหรับยิงสินค้า จะใช้ query ที่แตกต่าง
    if ($type === 'issue' && $available_only) {
        // Query สำหรับยิงสินค้าออก - แสดงเฉพาะสินค้าที่มีสต็อกและเรียงตาม FIFO
        $stmt = $pdo->prepare("
            SELECT 
                p.product_id,
                p.name,
                p.sku,
                p.barcode,
                p.unit,
                p.image,
                ri.receive_id,
                ri.receive_qty as available_qty,
                ri.expiry_date,
                ri.created_at as receive_date,
                ri.remark_color,
                ri.remark_split,
                poi.sale_price,
                CASE 
                    WHEN ri.expiry_date IS NOT NULL 
                    THEN CONCAT('ล็อตรับ: ', DATE_FORMAT(ri.created_at, '%d/%m/%Y'), ' | หมดอายุ: ', DATE_FORMAT(ri.expiry_date, '%d/%m/%Y'))
                    ELSE CONCAT('ล็อตรับ: ', DATE_FORMAT(ri.created_at, '%d/%m/%Y'))
                END as lot_info,
                poi.item_id
            FROM products p
            INNER JOIN purchase_order_items poi ON poi.product_id = p.product_id
            INNER JOIN receive_items ri ON ri.item_id = poi.item_id
            WHERE (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
              AND ri.receive_qty > 0
            ORDER BY 
                CASE 
                    WHEN p.name LIKE ? THEN 1 
                    WHEN p.sku LIKE ? THEN 2
                    WHEN p.barcode LIKE ? THEN 3
                    ELSE 4 
                END,
                p.name ASC,
                ri.expiry_date ASC,  -- หมดอายุเร็วก่อน
                ri.created_at ASC    -- รับเข้าเก่าก่อน (FIFO)
            LIMIT ?
        ");
        
        $stmt->execute([$like, $like, $like, $like, $like, $like, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Query เดิมสำหรับการค้นหาทั่วไป
        $stmt = $pdo->prepare("
        SELECT 
            product_id, 
            name, 
            sku, 
            barcode, 
            unit, 
            image,
            remark_color,
            remark_split,
            created_by,
            created_at
        FROM products
        WHERE (name LIKE ? 
           OR sku LIKE ?
           OR barcode LIKE ?)
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1 
                WHEN sku LIKE ? THEN 2
                WHEN barcode LIKE ? THEN 3
                ELSE 4 
            END,
            name ASC
        LIMIT ?
    ");
        
        // bind parameter ทั้ง 7 ช่อง (6 สำหรับ LIKE + 1 สำหรับ LIMIT)
        $stmt->execute([$like, $like, $like, $like, $like, $like, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // เพิ่มข้อมูลเพิ่มเติมสำหรับการแสดงผล
    foreach ($results as &$product) {
        if ($type === 'issue' && $available_only) {
            // สำหรับการยิงสินค้า - ข้อมูลมาจาก query ที่ join แล้ว
            $product['unit'] = $product['unit'] ?? 'ชิ้น';
            $product['display_name'] = $product['name'];
            $product['display_info'] = 'SKU: ' . ($product['sku'] ?: 'N/A') . ' | คงเหลือ: ' . $product['available_qty'] . ' ' . $product['unit'];
        } else {
            // สำหรับการค้นหาทั่วไป
            $product['price_per_unit'] = 0; 
            $product['stock_qty'] = 0; 
            $product['unit'] = $product['unit'] ?? 'ชิ้น';
            
            // จัดรูปแบบการแสดงผล
            $product['formatted_price'] = '0.00';
            $product['formatted_stock'] = '0';
            
            // เพิ่มข้อมูลสำหรับการแสดงผลใน autocomplete
            $product['display_name'] = $product['name'];
            $product['display_info'] = 'SKU: ' . ($product['sku'] ?: 'N/A');
        }
    }

    error_log('About to encode ' . count($results) . ' results for type: ' . $type);
    
    // ส่งผลลัพธ์ในรูปแบบที่เหมาะสม
    if ($type === 'issue' && $available_only) {
        $json_output = json_encode(['products' => $results]);
    } else {
        $json_output = json_encode($results);
    }
    
    if ($json_output === false) {
        error_log('JSON encoding failed: ' . json_last_error_msg());
        $error_response = json_encode(['error' => 'JSON encoding failed']);
        echo $error_response;
        error_log('Sent error response: ' . $error_response);
    } else {
        echo $json_output;
        error_log('Sent JSON response length: ' . strlen($json_output));
    }

} catch (PDOException $e) {
    error_log('PDO Error in product_search: ' . $e->getMessage());
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('General Error in product_search: ' . $e->getMessage());
    $error_response = json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
    echo $error_response;
    error_log('Sent error response: ' . $error_response);
}

error_log('=== product_search_api.php END ===');

// Ensure output is flushed
if (ob_get_level()) {
    ob_end_flush();
} else {
    flush();
}
