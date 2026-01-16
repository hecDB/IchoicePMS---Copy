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

function resolveImageUrl($value) {
    if ($value === null || $value === '') {
        return '../images/noimg.png';
    }

    if (is_resource($value)) {
        $value = stream_get_contents($value);
    }

    if (!is_string($value)) {
        return '../images/noimg.png';
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return '../images/noimg.png';
    }

    if (stripos($trimmed, 'data:') === 0) {
        return $trimmed;
    }

    $sanitized = preg_replace('/\s+/', '', $trimmed);
    $isBase64 = preg_match('/^[A-Za-z0-9+\/]+=*$/', $sanitized) && strlen($sanitized) >= 60 && strlen($sanitized) % 4 === 0;
    if ($isBase64) {
        return 'data:image/jpeg;base64,' . $sanitized;
    }

    if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $trimmed)) {
        return 'data:image/jpeg;base64,' . base64_encode($trimmed);
    }

    if (preg_match('/^https?:\/\//i', $trimmed)) {
        return $trimmed;
    }

    if (strpos($trimmed, '../') === 0 || strpos($trimmed, './') === 0 || $trimmed[0] === '/') {
        return $trimmed;
    }

    if (strpos($trimmed, 'images/') === 0) {
        return '../' . ltrim($trimmed, '/');
    }

    if (strpos($trimmed, '/') === false) {
        return '../images/' . $trimmed;
    }

    return '../' . ltrim($trimmed, '/');
}

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
        $stmt = $pdo->prepare("
            SELECT 
                p.product_id,
                p.name,
                p.sku,
                p.barcode,
                p.unit,
                p.image,
                poi.item_id AS receive_item_id,
                poi.po_id,
                SUM(ri.receive_qty) AS total_qty,
                poi.sale_price
            FROM purchase_order_items poi
            INNER JOIN products p ON p.product_id = poi.product_id
            INNER JOIN receive_items ri ON ri.item_id = poi.item_id AND ri.po_id = poi.po_id
            WHERE (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
            GROUP BY 
                p.product_id,
                p.name,
                p.sku,
                p.barcode,
                p.unit,
                p.image,
                poi.item_id,
                poi.po_id,
                poi.sale_price
            HAVING SUM(ri.receive_qty) > 0
            ORDER BY 
                CASE 
                    WHEN p.name LIKE ? THEN 1 
                    WHEN p.sku LIKE ? THEN 2
                    WHEN p.barcode LIKE ? THEN 3
                    ELSE 4 
                END,
                p.name ASC
            LIMIT ?
        ");

        $stmt->execute([$like, $like, $like, $like, $like, $like, $limit]);
        $rawResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $movementStmt = $pdo->prepare("
            SELECT 
                receive_id,
                receive_qty,
                expiry_date,
                created_at,
                remark_color,
                remark_split
            FROM receive_items
            WHERE item_id = ? AND po_id = ?
            ORDER BY created_at ASC, receive_id ASC
        ");

        foreach ($rawResults as $row) {
            $movementStmt->execute([$row['receive_item_id'], $row['po_id']]);
            $movements = $movementStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($movements)) {
                continue;
            }

            $fifoQueue = [];
            foreach ($movements as $movement) {
                $qty = isset($movement['receive_qty']) ? (float) $movement['receive_qty'] : 0.0;
                if ($qty > 0) {
                    $fifoQueue[] = [
                        'receive_id' => isset($movement['receive_id']) ? (int) $movement['receive_id'] : null,
                        'available_qty' => $qty,
                        'expiry_date' => $movement['expiry_date'] ?? null,
                        'created_at' => $movement['created_at'] ?? null,
                        'remark_color' => $movement['remark_color'] ?? null,
                        'remark_split' => $movement['remark_split'] ?? null
                    ];
                } elseif ($qty < 0) {
                    $need = abs($qty);
                    $queueCount = count($fifoQueue);
                    for ($i = 0; $i < $queueCount && $need > 0; $i++) {
                        $available = isset($fifoQueue[$i]['available_qty']) ? (float)$fifoQueue[$i]['available_qty'] : 0.0;
                        if ($available <= 0) {
                            continue;
                        }
                        $deduct = min($available, $need);
                        $fifoQueue[$i]['available_qty'] = $available - $deduct;
                        $need -= $deduct;
                    }
                }
            }

            $remainingBatches = [];
            $totalAvailable = 0.0;
            foreach ($fifoQueue as $batch) {
                $available = isset($batch['available_qty']) ? (float) $batch['available_qty'] : 0.0;
                if ($available <= 0) {
                    continue;
                }
                $batch['available_qty'] = $available;
                $remainingBatches[] = $batch;
                $totalAvailable += $available;
            }

            if ($totalAvailable <= 0) {
                continue;
            }

            $row['available_qty'] = $totalAvailable;
            $row['batch_count'] = count($remainingBatches);
            $row['receive_batches'] = array_values($remainingBatches);

            $row['receive_id'] = null;
            $row['receive_date'] = null;
            $row['expiry_date'] = null;
            $row['remark_color'] = null;
            $row['remark_split'] = null;
            $row['lot_info'] = null;

            if (!empty($remainingBatches)) {
                $latestBatch = $remainingBatches[count($remainingBatches) - 1];
                $row['receive_id'] = $latestBatch['receive_id'];
                $row['receive_date'] = $latestBatch['created_at'];
                $row['expiry_date'] = $latestBatch['expiry_date'];
                $row['remark_color'] = $latestBatch['remark_color'];
                $row['remark_split'] = $latestBatch['remark_split'];
                // Copy sale_price from latest batch
                if (isset($latestBatch['sale_price'])) {
                    $row['sale_price'] = $latestBatch['sale_price'];
                }

                $lotParts = [];
                $lotTimestamp = !empty($latestBatch['created_at']) ? strtotime($latestBatch['created_at']) : false;
                if ($lotTimestamp !== false) {
                    $lotParts[] = 'ล็อตรับ: ' . date('d/m/Y', $lotTimestamp);
                }
                $expiryTimestamp = !empty($latestBatch['expiry_date']) ? strtotime($latestBatch['expiry_date']) : false;
                if ($expiryTimestamp !== false) {
                    $lotParts[] = 'หมดอายุ: ' . date('d/m/Y', $expiryTimestamp);
                }
                if (!empty($lotParts)) {
                    $row['lot_info'] = implode(' | ', $lotParts);
                }
            }

            $results[] = $row;
        }

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
        $product['image_url'] = resolveImageUrl($product['image'] ?? '');
        if ($type === 'issue' && $available_only) {
            // สำหรับการยิงสินค้า - ข้อมูลมาจาก query ที่ join แล้ว
            $product['unit'] = $product['unit'] ?? 'ชิ้น';
            $product['display_name'] = $product['name'];
            $product['display_info'] = 'SKU: ' . ($product['sku'] ?: 'N/A') . ' | คงเหลือ: ' . $product['available_qty'] . ' ' . $product['unit'];
            $product['receive_id'] = isset($product['receive_id']) ? (int) $product['receive_id'] : null;
            $product['receive_item_id'] = isset($product['receive_item_id']) ? (int) $product['receive_item_id'] : null;
            $product['po_id'] = isset($product['po_id']) ? (int) $product['po_id'] : null;
            $product['available_qty'] = isset($product['available_qty']) ? (float) $product['available_qty'] : 0.0;
            $product['sale_price'] = isset($product['sale_price']) ? (float) $product['sale_price'] : 0.0;
            $product['batch_count'] = isset($product['batch_count']) ? (int) $product['batch_count'] : 0;
            if (!isset($product['receive_batches']) || !is_array($product['receive_batches'])) {
                $product['receive_batches'] = [];
            }
            if ($product['batch_count'] === 0 && !empty($product['receive_batches'])) {
                $product['batch_count'] = count($product['receive_batches']);
            }
            if (empty($product['receive_batches'])) {
                $product['receive_id'] = $product['receive_id'] ?? null;
            } else {
                $lastBatch = end($product['receive_batches']);
                if (!isset($product['receive_id']) || $product['receive_id'] === null) {
                    $product['receive_id'] = $lastBatch['receive_id'] ?? $product['receive_id'];
                }
                reset($product['receive_batches']);
            }
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
