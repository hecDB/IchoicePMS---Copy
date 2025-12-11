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

    $sql = "SELECT 
                p.product_id,
                p.sku,
                p.barcode,
                p.name AS product_name,
                p.image,
                p.unit,
                ri.receive_id,
                ri.item_id,
                ri.po_id,
                ri.receive_qty,
                    ri.expiry_date,
                ri.created_at AS receive_created_at,
                po.po_number,
                po.order_date,
                poi.price_per_unit,
                poi.total
            FROM receive_items ri
            INNER JOIN purchase_order_items poi ON ri.item_id = poi.item_id
            INNER JOIN purchase_orders po ON ri.po_id = po.po_id
            INNER JOIN products p ON poi.product_id = p.product_id
                WHERE ri.receive_qty > 0
                  AND (
                        p.barcode LIKE :pattern_barcode
                     OR p.sku LIKE :pattern_sku
                     OR p.name LIKE :pattern_name
                  )
            ORDER BY 
                CASE 
                        WHEN p.barcode = :term_exact_barcode THEN 0
                        WHEN p.sku = :term_exact_sku THEN 1
                    WHEN p.name LIKE :term_prefix THEN 2
                    ELSE 3
                END,
                ri.created_at DESC
            LIMIT 30";

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

    $results = array_map(function ($row) {
        $hasExpiry = !empty($row['expiry_date']) && $row['expiry_date'] !== '0000-00-00';
        $expiryFormatted = $hasExpiry ? date('d/m/Y', strtotime($row['expiry_date'])) : null;
        $poLabel = $row['po_number'] ?: ($row['po_id'] ? 'PO-' . str_pad($row['po_id'], 5, '0', STR_PAD_LEFT) : null);
        $hasReceiveDate = !empty($row['receive_created_at']) && $row['receive_created_at'] !== '0000-00-00 00:00:00';
        $receiveDate = $hasReceiveDate ? date('d/m/Y H:i', strtotime($row['receive_created_at'])) : null;

        return [
            'product_id' => (int)$row['product_id'],
            'sku' => $row['sku'],
            'barcode' => $row['barcode'],
            'product_name' => $row['product_name'],
            'unit' => $row['unit'],
            'image' => $row['image'],
            'image_url' => resolveImageUrl($row['image']),
            'receive_id' => (int)$row['receive_id'],
            'item_id' => (int)$row['item_id'],
            'po_id' => (int)$row['po_id'],
            'po_number' => $row['po_number'],
            'po_label' => $poLabel,
            'receive_qty' => (float)$row['receive_qty'],
            'expiry_date' => $row['expiry_date'],
            'expiry_date_formatted' => $expiryFormatted,
            'receive_created_at' => $row['receive_created_at'],
            'receive_created_at_formatted' => $receiveDate,
            'price_per_unit' => $row['price_per_unit'] !== null ? (float)$row['price_per_unit'] : null,
            'po_total' => $row['total'] !== null ? (float)$row['total'] : null
        ];
    }, $rows);

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
