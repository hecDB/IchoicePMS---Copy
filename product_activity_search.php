<?php
session_start();
require 'db_connect.php';

$draw = intval($_GET['draw'] ?? 1);
$start = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 50);
$search = trim($_GET['search']['value'] ?? '');

// Columns for ordering
$columns = [
    null, // checkbox
    null, // product_info
    'u.name',
    'pa.activity_date',
    'pa.activity_type',
    'pa.location_from',
    'pa.location_to',
    'pa.quantity',
    null // reference
];

$orderColumnIndex = intval($_GET['order'][0]['column'] ?? 3);
$orderDir = $_GET['order'][0]['dir'] ?? 'desc';
$orderBy = $columns[$orderColumnIndex] ?? 'pa.activity_date';

// Base query
$sqlBase = "
FROM product_activity pa
LEFT JOIN products p ON pa.product_id = p.product_id
LEFT JOIN users u ON pa.user_id = u.user_id
WHERE 1=1
";

// Total records
$totalRecords = $pdo->query("SELECT COUNT(*) FROM product_activity")->fetchColumn();

// Search condition
$searchSql = "";
$params = [];
if (!empty($search)) {
    $searchSql = " AND (p.name LIKE :s OR p.sku LIKE :s OR u.name LIKE :s OR pa.activity_type LIKE :s OR pa.reference LIKE :s)";
    $params[':s'] = "%$search%";
}

// Filtered records count
$countQuery = "SELECT COUNT(*) " . $sqlBase . $searchSql;
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalFiltered = $stmt->fetchColumn();

// Data query
$dataQuery = "
SELECT
    pa.activity_id,
    p.name as product_name,
    p.sku,
    p.image,
    u.name as user_name,
    pa.activity_date,
    pa.activity_type,
    pa.location_from,
    pa.location_to,
    pa.quantity,
    pa.reference
" . $sqlBase . $searchSql . " ORDER BY $orderBy $orderDir LIMIT :start, :length";

$stmt = $pdo->prepare($dataQuery);
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
if (!empty($search)) {
    $stmt->bindValue(':s', $params[':s']);
}
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($activities as $a) {
    $img = !empty($a['image']) ? $a['image'] : 'assets/no-image.png';
    $product_name = $a['product_name'] ?? '[สินค้าถูกลบ]';
    $sku = $a['sku'] ?? 'N/A';
    $user_name = $a['user_name'] ?? 'System';
    $activity_date = $a['activity_date'] ? date("d M Y, H:i", strtotime($a['activity_date'])) : '-';
    $activity_type = $a['activity_type'] ?? '';
    $location_from = $a['location_from'] ?? '';
    $location_to = $a['location_to'] ?? '';
    $reference = $a['reference'] ?? '';
    $quantity = $a['quantity'] ?? 0;

    $productInfo = '
        <div class="d-flex align-items-center">
            <img src="'.$img.'" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-right:10px;">
            <div>
                <div style="font-weight:500;">'.htmlspecialchars($product_name).'</div>
                <div class="text-muted" style="font-size:12px;">SKU: '.htmlspecialchars($sku).'</div>
            </div>
        </div>';

    $quantity_display = '';
    if (in_array($activity_type, ['Stock-In', 'เพิ่ม', 'รับสินค้า'])) {
        $quantity_display = '<span class="text-success fw-bold">+' . number_format($quantity) . '</span>';
    } elseif (in_array($activity_type, ['Stock-Out', 'การขาย', 'ตัดจ่าย'])) {
        $quantity_display = '<span class="text-danger fw-bold">-' . number_format($quantity) . '</span>';
    } else {
        $quantity_display = number_format($quantity);
    }

    $data[] = [
        "activity_id" => $a['activity_id'],
        "product_info" => $productInfo,
        "user_name" => htmlspecialchars($user_name),
        "activity_date" => $activity_date,
        "activity_type" => htmlspecialchars($activity_type),
        "location_from" => htmlspecialchars($location_from),
        "location_to" => htmlspecialchars($location_to),
        "quantity" => $quantity_display,
        "reference" => htmlspecialchars($reference),
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);

