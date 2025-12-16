<?php
/**
 * API for searching products (per receive batch) by barcode, SKU, or name
 * Used by the missing products feature to allow selecting specific PO/expiry entries
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($search_term === '') {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกบาร์โค้ดหรือ SKU']);
    exit;
}

try {
    $search_pattern = '%' . $search_term . '%';
    $search_prefix = $search_term . '%';

    $sql = "
        SELECT 
            p.product_id,
            p.sku,
            p.barcode,
            p.name AS product_name,
            p.image,
            p.unit,
            poi.item_id,
            poi.po_id,
            poi.price_per_unit,
            poi.total,
            po.po_number,
            po.order_date,
            SUM(ri.receive_qty) AS total_qty
        FROM purchase_order_items poi
        INNER JOIN products p ON poi.product_id = p.product_id
        INNER JOIN receive_items ri ON ri.item_id = poi.item_id AND ri.po_id = poi.po_id
        LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
        WHERE
            (p.barcode LIKE :pattern_barcode
             OR p.sku LIKE :pattern_sku
             OR p.name LIKE :pattern_name)
        GROUP BY
            p.product_id,
            p.sku,
            p.barcode,
            p.name,
            p.image,
            p.unit,
            poi.item_id,
            poi.po_id,
            poi.price_per_unit,
            poi.total,
            po.po_number,
            po.order_date
        HAVING SUM(ri.receive_qty) > 0
        ORDER BY
            CASE
                WHEN p.barcode = :term_exact_barcode THEN 0
                WHEN p.sku = :term_exact_sku THEN 1
                WHEN p.name LIKE :term_prefix THEN 2
                ELSE 3
            END,
            p.name ASC
        LIMIT 30
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pattern_barcode' => $search_pattern,
        ':pattern_sku' => $search_pattern,
        ':pattern_name' => $search_pattern,
        ':term_exact_barcode' => $search_term,
        ':term_exact_sku' => $search_term,
        ':term_prefix' => $search_prefix
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $results = [];
    foreach ($rows as $row) {
        $movementStmt->execute([$row['item_id'], $row['po_id']]);
        $movements = $movementStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($movements)) {
            continue;
        }

        $fifoQueue = [];
        foreach ($movements as $movement) {
            $qty = isset($movement['receive_qty']) ? (float)$movement['receive_qty'] : 0.0;
            if ($qty > 0) {
                $fifoQueue[] = [
                    'receive_id' => isset($movement['receive_id']) ? (int)$movement['receive_id'] : null,
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
            $available = isset($batch['available_qty']) ? (float)$batch['available_qty'] : 0.0;
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

        $latestBatch = !empty($remainingBatches) ? $remainingBatches[count($remainingBatches) - 1] : null;
        $latestCreatedAt = $latestBatch['created_at'] ?? null;
        $latestExpiry = $latestBatch['expiry_date'] ?? null;
        $receiveTimestamp = $latestCreatedAt ? strtotime($latestCreatedAt) : false;
        $expiryTimestamp = $latestExpiry ? strtotime($latestExpiry) : false;
        $receiveFormatted = $receiveTimestamp !== false ? date('d/m/Y H:i', $receiveTimestamp) : null;
        $expiryFormatted = $expiryTimestamp !== false ? date('d/m/Y', $expiryTimestamp) : null;

        $poLabel = $row['po_number'] ?: ($row['po_id'] ? 'PO-' . str_pad($row['po_id'], 5, '0', STR_PAD_LEFT) : null);

        $results[] = [
            'product_id' => (int)$row['product_id'],
            'sku' => $row['sku'],
            'barcode' => $row['barcode'],
            'product_name' => $row['product_name'],
            'unit' => $row['unit'],
            'image' => $row['image'],
            'image_url' => resolveImageUrl($row['image']),
            'receive_id' => $latestBatch['receive_id'] ?? null,
            'item_id' => (int)$row['item_id'],
            'po_id' => (int)$row['po_id'],
            'po_number' => $row['po_number'],
            'po_label' => $poLabel,
            'receive_qty' => $totalAvailable,
            'expiry_date' => $latestExpiry,
            'expiry_date_formatted' => $expiryFormatted,
            'receive_created_at' => $latestCreatedAt,
            'receive_created_at_formatted' => $receiveFormatted,
            'price_per_unit' => $row['price_per_unit'] !== null ? (float)$row['price_per_unit'] : null,
            'po_total' => $row['total'] !== null ? (float)$row['total'] : null,
            'batch_count' => count($remainingBatches),
            'receive_batches' => $remainingBatches
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => empty($results) ? 'ไม่พบสินค้า' : 'พบสินค้า ' . count($results) . ' รายการ',
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Missing product search error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
