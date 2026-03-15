<?php
session_start();
require '../config/db_connect.php';

// Ensure temp_products has expiry_date column (if not already exists)
try {
    $columnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temp_products' AND COLUMN_NAME = 'expiry_date'");
    $columnsStmt->execute();
    
    if ($columnsStmt->rowCount() === 0) {
        // Add expiry_date column if it doesn't exist
        $pdo->exec("ALTER TABLE temp_products ADD COLUMN expiry_date DATE NULL COMMENT 'วันหมดอายุ' AFTER po_id");
    }
} catch (Exception $e) {
    error_log('Failed to ensure temp_products expiry_date column: ' . $e->getMessage());
}

try {
    $columnsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temp_products' AND COLUMN_NAME = 'sale_price'");
    $columnsStmt->execute();

    if ($columnsStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE temp_products ADD COLUMN sale_price DECIMAL(12,2) NULL COMMENT 'ราคาขาย' AFTER expiry_date");
    }
} catch (Exception $e) {
    error_log('Failed to ensure temp_products sale_price column: ' . $e->getMessage());
}

// ตัวแปรสำหรับกรอง
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'new'; // 'all', 'existing', 'new'
$filter_transaction = isset($_GET['transaction']) ? $_GET['transaction'] : 'all'; // 'all', 'receive', 'issue'

// Query 1: สินค้าใหม่รับเข้าปกติ (receive items)
$sql_receive = "
SELECT 
    'receive' as transaction_type,
    r.receive_id as transaction_id, 
    tp.product_image as image,
    u.name AS created_by, 
    r.created_at, 
    r.receive_qty as quantity, 
    r.expiry_date,
    tp.product_name AS product_name,
    tp.product_category,
    tp.unit,
    tp.temp_product_id,
    tp.provisional_sku,
    tp.provisional_barcode,
    tp.status as temp_product_status,
    tp.source_type,
    COALESCE(tp.sale_price, poi.sale_price) as sale_price,
    poi.price_per_unit as po_price_per_unit,
    poi.unit_price as purchase_price,
    tp.remark as temp_product_remark,
    tp.remark_weight as remark_weight
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
LEFT JOIN users u ON r.created_by = u.user_id
WHERE COALESCE(poi.temp_product_id, 0) > 0
AND tp.source_type = 'NewProduct'
ORDER BY r.created_at DESC LIMIT 500";

// Query 2: สินค้าใหม่รับเข้าจากการตรวจสอบชำรุด (damaged inspection)
$sql_damaged = "
SELECT 
    'damaged_inspection' as transaction_type,
    poi.temp_product_id as transaction_id,
    tp.product_image as image,
    u.name AS created_by,
    tp.created_at,
    COALESCE(ri.return_qty, 0) as quantity,
    tp.expiry_date,
    tp.product_name AS product_name,
    tp.product_category,
    tp.unit,
    tp.temp_product_id,
    tp.provisional_sku,
    tp.provisional_barcode,
    tp.status as temp_product_status,
    tp.source_type,
    COALESCE(tp.sale_price, poi.sale_price) as sale_price,
    poi.price_per_unit as po_price_per_unit,
    poi.unit_price as purchase_price,
    tp.remark as temp_product_remark,
    tp.remark_weight as remark_weight
FROM temp_products tp
LEFT JOIN returned_items ri ON ri.temp_product_id = tp.temp_product_id
LEFT JOIN purchase_order_items poi ON poi.item_id = ri.item_id
LEFT JOIN users u ON tp.created_by = u.user_id
WHERE tp.source_type = 'Damaged'
AND COALESCE(tp.temp_product_id, 0) > 0
AND ri.temp_product_id IS NOT NULL
ORDER BY tp.created_at DESC LIMIT 500";

$receive_rows = [];
$damaged_rows = [];
$rows = []; // สำหรับการ debug รวม

try {
    // Fetch receive items
    $stmt_receive = $pdo->query($sql_receive);
    $receive_rows = $stmt_receive->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch damaged items
    $stmt_damaged = $pdo->query($sql_damaged);
    $damaged_rows = $stmt_damaged->fetchAll(PDO::FETCH_ASSOC);
    
    // รวมข้อมูลสำหรับตัวแปร debug
    $rows = array_merge($receive_rows, $damaged_rows);
    
    // Debug log
    error_log("Transaction View Query Debug:");
    error_log("- Receive rows: " . count($receive_rows));
    error_log("- Damaged rows: " . count($damaged_rows));
    error_log("- Total rows: " . count($rows));
    
} catch (Exception $e) {
    $receive_rows = [];
    $damaged_rows = [];
    $rows = [];
    $error_msg = $e->getMessage();
    error_log("Query Error in transaction_view_separated.php: " . $error_msg);
}

function resolveTransactionImageSrc($imageValue) {
    if ($imageValue === null || $imageValue === '') {
        return '../images/noimg.png';
    }

    if (is_resource($imageValue)) {
        $imageValue = stream_get_contents($imageValue);
    }

    if (!is_string($imageValue)) {
        return '../images/noimg.png';
    }

    $trimmed = trim($imageValue);
    if ($trimmed === '') {
        return '../images/noimg.png';
    }

    if (stripos($trimmed, 'data:') === 0) {
        return $trimmed;
    }

    if (preg_match('/^[A-Za-z0-9+\/\s]+=*$/', $trimmed)) {
        $cleaned = preg_replace('/\s+/', '', $trimmed);
        if (strlen($cleaned) >= 60 && strlen($cleaned) % 4 === 0) {
            return 'data:image/jpeg;base64,' . $cleaned;
        }
    }

    if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $trimmed)) {
        return 'data:image/jpeg;base64,' . base64_encode($trimmed);
    }

    if (preg_match('/^https?:\/\//i', $trimmed)) {
        return $trimmed;
    }

    $relativePath = $trimmed;
    if ($trimmed[0] !== '/' && strpos($trimmed, '../') !== 0 && strpos($trimmed, './') !== 0) {
        if (strpos($trimmed, 'images/') === 0) {
            $relativePath = '../' . ltrim($trimmed, '/');
        } elseif (strpos($trimmed, '/') === false) {
            $relativePath = '../images/' . $trimmed;
        } else {
            $relativePath = '../' . ltrim($trimmed, '/');
        }
    }

    $absolutePath = realpath(__DIR__ . '/' . $relativePath);
    if ($absolutePath && is_file($absolutePath)) {
        return $relativePath;
    }

    $altRelative = '../' . ltrim($trimmed, '/');
    $altAbsolute = realpath(__DIR__ . '/' . $altRelative);
    if ($altAbsolute && is_file($altAbsolute)) {
        return $altRelative;
    }

    return '../images/noimg.png';
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ความเคลื่อนไหวสินค้า (แยกตามประเภท) - IchoicePMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../images/favicon.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/base.css">
<link rel="stylesheet" href="../assets/sidebar.css">
<link rel="stylesheet" href="../assets/components.css">
<link href="../assets/modern-table.css" rel="stylesheet">
<link href="../assets/mainwrap-modern.css" rel="stylesheet">

<style>
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f8fafc;
    }

    /* Full-width table styling */
    .mainwrap .table-card {
        width: 100%;
        max-width: 100%;
        margin: 0;
    }

    .mainwrap .table-header,
    .mainwrap .table-body {
        width: 100%;
        margin: 0;
    }


    .product-image {
        width: 36px; 
        height: 36px;
        max-width: 48px; 
        max-height: 48px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid #e5e7eb;
    }
    
    .qty-plus {
        color: #059669;
        font-weight: 700;
    }
    
    .qty-minus {
        color: #dc2626;
        font-weight: 700;
    }
    
    .badge-existing {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .badge-new {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-tabs {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 0.75rem 1.5rem;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 0.5rem;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        font-family: 'Prompt', sans-serif;
    }
    
    .filter-btn:hover {
        border-color: #3b82f6;
        color: #3b82f6;
    }
    
    .filter-btn.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    .breadcrumb-modern {
        background: none;
        padding: 0;
    }
    
    .breadcrumb-modern .breadcrumb-item {
        color: #6b7280;
    }
    
    .breadcrumb-modern .breadcrumb-item.active {
        color: #111827;
        font-weight: 500;
    }
 
    /* Table responsive adjustments */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    #transaction-table {
        width: 100%;
        min-width: 100%;
        white-space: nowrap;
    }
    
    #transaction-table th,
    #transaction-table td {
        padding: 0.85rem 0.65rem;
        font-size: 0.95rem;
        vertical-align: middle;
    }
    
    #transaction-table th:nth-child(1) { width: 90px; min-width: 90px; }
    #transaction-table th:nth-child(2) { width: 200px; min-width: 200px; }
    #transaction-table th:nth-child(3) { width: 120px; min-width: 120px; }
    #transaction-table th:nth-child(4) { width: 120px; min-width: 120px; }
    #transaction-table th:nth-child(5) { width: 140px; min-width: 140px; }
    #transaction-table th:nth-child(6) { width: 120px; min-width: 120px; }
    #transaction-table th:nth-child(7) { width: 140px; min-width: 140px; }
    #transaction-table th:nth-child(8) { width: 120px; min-width: 120px; }
    #transaction-table th:nth-child(9) { width: 120px; min-width: 120px; }
    #transaction-table th:nth-child(10) { width: 140px; min-width: 140px; }
    #transaction-table th:nth-child(11) { width: 100px; min-width: 100px; }
    
    #transaction-table td:nth-child(3) {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .table-danger {
        --bs-table-bg: rgba(220, 53, 69, 0.05);
    }
    
   
    .action-btn {
        padding: 0.375rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1;
        border-radius: 0.375rem;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.25rem;
        height: 2.25rem;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .stats-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.5rem;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    
    .stats-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border-color: #3b82f6;
    }
    
    .stats-card-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
    }
    
    .stats-icon {
        font-size: 3rem;
        opacity: 0.2;
    }
    
    /* Location Section Styling */
    .location-section {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 0.75rem;
        padding: 1.5rem;
        border-left: 4px solid #3b82f6;
        margin-top: 1.5rem;
    }
    
    .location-section h6 {
        color: #1e40af;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .location-section .form-label {
        color: #374151;
        font-weight: 600;
        font-size: 0.95rem;
    }
    
    .location-section .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 0.75rem;
        transition: all 0.3s ease;
    }
    
    .location-section .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .location-inputs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .location-input-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6b7280;
    }


    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        #receive-table {
            width: 100%;
        }
        
        #receive-table th,
        #receive-table td {
            padding: 0.5rem 0.25rem;
            font-size: 0.85rem;
        }
        
        .product-image {
            width: 32px !important; 
            height: 32px !important;
            max-width: 32px !important; 
            max-height: 32px !important;
        }
        
        .badge {
            font-size: 0.7rem !important;
            padding: 0.25rem 0.4rem !important;
        }
    }
    
    @media (max-width: 480px) {
        #receive-table {
            width: 100%;
        }
        
        #receive-table th,
        #receive-table td {
            padding: 0.4rem 0.15rem;
            font-size: 0.8rem;
        }
        
        .product-image {
            width: 24px !important; 
            height: 24px !important;
            max-width: 24px !important; 
            max-height: 24px !important;
        }
    }
    
    
</style>

</head>
<body>

<?php include '../templates/sidebar.php'; ?>
<div class="mainwrap">
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">swap_horiz</span>
                    ความเคลื่อนไหวสินค้า (แยกตามประเภท)
                </h1>
                <p class="text-muted mb-0">ดูความเคลื่อนไหวของสินค้าเดิมและสินค้าซื้อใหม่แยกกัน</p>
            </div>
            <div>
                <a href="receive_items_view.php" class="btn-modern btn-modern-secondary">
                    <span class="material-icons" style="font-size: 1.25rem;">arrow_back</span>
                    กลับไปหน้าเต็ม
                </a>
            </div>
        </div>

        <!-- Filter Tabs (Hidden) -->
        <!-- เฉพาะแสดงสินค้าซื้อใหม่เท่านั้น -->
        
        <!-- Debug Info -->
        <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger">
            <strong>Database Error:</strong> <?= htmlspecialchars($error_msg) ?>
        </div>
        <?php endif; ?>
        
        <!-- Debug Console -->
        <div class="alert alert-warning" id="debug-alert" style="display: none;">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <strong>Debug Info:</strong>
            <div id="debug-content"></div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-card-body">
                        <div>
                            <h6 class="text-muted mb-2">จำนวนรายการสินค้าซื้อใหม่</h6>
                            <div class="stats-number" id="new-count">0</div>
                        </div>
                        <span class="material-icons stats-icon">new_releases</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-card-body">
                        <div>
                            <h6 class="text-muted mb-2">รับเข้า / ออกไป</h6>
                            <div>
                                <span class="qty-plus" id="receive-count" style="margin-right: 1rem;">+0</span>
                                <span class="qty-minus" id="issue-count">-0</span>
                            </div>
                        </div>
                        <span class="material-icons stats-icon">swap_horiz</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receive Items Table -->
        <div class="table-card mb-5">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons" style="color: #10b981;">add_box</span>
                        1. สินค้าใหม่รับเข้าปกติ (<span id="receive-count"><?= count($receive_rows) ?></span> รายการ)
                    </h5>
                    <div class="table-actions d-flex align-items-center">
                        <div class="search-box me-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <span class="material-icons" style="font-size: 1.25rem; color: #6b7280;">search</span>
                                </span>
                                <input type="text" class="form-control" id="custom-search-receive" placeholder="ค้นหา...">
                            </div>
                        </div>
                        <button class="btn-modern btn-modern-secondary btn-sm" onclick="location.reload()">
                            <span class="material-icons" style="font-size: 1.25rem;">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <div class="table-responsive">
                    <table id="receive-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th class="text-center no-sort">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU สำรอง</th>
                            <th>บาร์โค้ดสำรอง</th>
                            <th>หมวดหมู่</th>
                            <th>ผู้ทำรายการ</th>
                            <th class="text-center">วันที่</th>
                            <th class="text-center no-sort">จำนวน</th>
                            <th class="text-center no-sort">วันหมดอายุ</th>
                            <th class="text-center no-sort">สถานะ</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($receive_rows)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4">
                            <div class="text-muted mb-2">
                                <span class="material-icons" style="font-size: 3rem; opacity: 0.5;">inbox</span>
                            </div>
                            <p class="text-muted">ไม่มีข้อมูลรายการรับเข้าปกติ</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($receive_rows as $row): ?>
                        <tr data-id="<?= $row['transaction_id'] ?>" data-type="receive">
                            <td class="text-center">
                                <?php $imageSrc = resolveTransactionImageSrc($row['image'] ?? null); ?>
                                <img src="<?= htmlspecialchars($imageSrc) ?>" class="product-image" alt="<?= htmlspecialchars($row['product_name']) ?>" title="<?= htmlspecialchars($row['product_name']) ?>">
                            </td>
                            <td><strong><?= htmlspecialchars($row['product_name'] ?? '-') ?></strong></td>
                            <td>
                                <?php if (!empty($row['provisional_sku'])): ?>
                                    <code><?= htmlspecialchars($row['provisional_sku']) ?></code>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">ยังไม่มีข้อมูล</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['provisional_barcode'])): ?>
                                    <code><?= htmlspecialchars($row['provisional_barcode']) ?></code>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">ยังไม่มีข้อมูล</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['product_category']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['product_category']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['created_by'] ?? '-') ?></td>
                            <td class="text-center" data-order="<?= htmlspecialchars($row['created_at']) ?>">
                                <small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td class="text-center">
                                <?php
                                $qty = $row['quantity'] ?? 0;
                                if($qty > 0) {
                                    echo '<span class="qty-plus">+'.$qty.'</span>';
                                } else if($qty < 0) {
                                    echo '<span class="qty-minus">'.$qty.'</span>';
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php if ($row['expiry_date']): ?>
                                    <?php
                                    $expiry = new DateTime($row['expiry_date']);
                                    $today = new DateTime();
                                    $is_expired = $expiry < $today;
                                    $badge_class = $is_expired ? 'bg-danger' : 'bg-info';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= $expiry->format('d/m/Y') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $status = $row['temp_product_status'] ?? 'pending';
                                if ($status === 'converted'):
                                ?>
                                    <span class="badge bg-success">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">check_circle</span>
                                        ย้ายไปคลังแล้ว
                                    </span>
                                <?php elseif ($status === 'approved'): ?>
                                    <span class="badge bg-info">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">pending_actions</span>
                                        อนุมัติแล้ว
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">schedule</span>
                                        รอดำเนิน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary edit-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-receive-id="<?= $row['transaction_id'] ?>" data-expiry="<?= $row['expiry_date'] ?? '' ?>" data-provisional-sku="<?= htmlspecialchars($row['provisional_sku'] ?? '') ?>" data-provisional-barcode="<?= htmlspecialchars($row['provisional_barcode'] ?? '') ?>" data-category="<?= htmlspecialchars($row['product_category'] ?? '') ?>" data-quantity="<?= htmlspecialchars($row['quantity'] ?? 0) ?>" data-purchase-price="<?= htmlspecialchars($row['purchase_price'] ?? '') ?>" data-sale-price="<?= htmlspecialchars($row['sale_price'] ?? '') ?>" data-po-sale-price="<?= htmlspecialchars($row['po_price_per_unit'] ?? '') ?>" data-remark="<?= htmlspecialchars($row['temp_product_remark'] ?? '') ?>" data-weight="<?= htmlspecialchars($row['remark_weight'] ?? '') ?>" data-unit="<?= htmlspecialchars($row['unit'] ?? '') ?>" data-product-name="<?= htmlspecialchars($row['product_name'] ?? '') ?>" title="แก้ไข SKU/Barcode/รูปภาพ" <?php if ($status === 'converted'): ?>disabled<?php endif; ?>>
                                        <span class="material-icons" style="font-size: 1rem;">edit</span>
                                    </button>
                                    <button class="btn btn-outline-success approve-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-name="<?= htmlspecialchars($row['product_name']) ?>" title="อนุมัติและย้ายไปคลังปกติ" <?php if ($status === 'converted'): ?>disabled<?php endif; ?>>
                                        <span class="material-icons" style="font-size: 1rem;">check_circle</span>
                                    </button>
                                    <?php if ($status === 'converted'): ?>
                                    <button class="btn btn-outline-info view-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-product-name="<?= htmlspecialchars($row['product_name'] ?? '') ?>" data-provisional-sku="<?= htmlspecialchars($row['provisional_sku'] ?? '') ?>" data-provisional-barcode="<?= htmlspecialchars($row['provisional_barcode'] ?? '') ?>" data-unit="<?= htmlspecialchars($row['unit'] ?? '') ?>" data-category="<?= htmlspecialchars($row['product_category'] ?? '') ?>" data-expiry="<?= htmlspecialchars($row['expiry_date'] ?? '') ?>" data-quantity="<?= htmlspecialchars($row['quantity'] ?? 0) ?>" data-purchase-price="<?= htmlspecialchars($row['purchase_price'] ?? '') ?>" data-sale-price="<?= htmlspecialchars($row['sale_price'] ?? '') ?>" data-po-sale-price="<?= htmlspecialchars($row['po_price_per_unit'] ?? '') ?>" data-weight="<?= htmlspecialchars($row['remark_weight'] ?? '') ?>" data-remark="<?= htmlspecialchars($row['temp_product_remark'] ?? '') ?>" data-image="<?= htmlspecialchars(resolveTransactionImageSrc($row['image'] ?? null)) ?>" title="ดูรายละเอียดสินค้า">
                                        <span class="material-icons" style="font-size: 1rem;">visibility</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Damaged Inspection Items Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons" style="color: #f59e0b;">warning</span>
                        2. สินค้าใหม่รับเข้าจากการตรวจสอบชำรุด (<span id="damaged-count"><?= count($damaged_rows) ?></span> รายการ)
                    </h5>
                    <div class="table-actions d-flex align-items-center">
                        <div class="search-box me-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <span class="material-icons" style="font-size: 1.25rem; color: #6b7280;">search</span>
                                </span>
                                <input type="text" class="form-control" id="custom-search-damaged" placeholder="ค้นหา...">
                            </div>
                        </div>
                        <button class="btn-modern btn-modern-secondary btn-sm" onclick="location.reload()">
                            <span class="material-icons" style="font-size: 1.25rem;">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <div class="table-responsive">
                    <table id="damaged-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th class="text-center no-sort">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU สำรอง</th>
                            <th>บาร์โค้ดสำรอง</th>
                            <th>หมวดหมู่</th>
                            <th>ผู้บันทึก</th>
                            <th class="text-center">วันที่</th>
                            <th class="text-center no-sort">จำนวน</th>
                            <th class="text-center no-sort">วันหมดอายุ</th>
                            <th class="text-center no-sort">สถานะ</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($damaged_rows)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4">
                            <div class="text-muted mb-2">
                                <span class="material-icons" style="font-size: 3rem; opacity: 0.5;">inbox</span>
                            </div>
                            <p class="text-muted">ไม่มีข้อมูลรายการตรวจสอบชำรุด</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($damaged_rows as $row): ?>
                        <tr data-id="<?= $row['transaction_id'] ?>" data-type="damaged">
                            <td class="text-center">
                                <?php $imageSrc = resolveTransactionImageSrc($row['image'] ?? null); ?>
                                <img src="<?= htmlspecialchars($imageSrc) ?>" class="product-image" alt="<?= htmlspecialchars($row['product_name']) ?>" title="<?= htmlspecialchars($row['product_name']) ?>">
                            </td>
                            <td><strong><?= htmlspecialchars($row['product_name'] ?? '-') ?></strong></td>
                            <td>
                                <?php if (!empty($row['provisional_sku'])): ?>
                                    <code><?= htmlspecialchars($row['provisional_sku']) ?></code>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">ยังไม่มีข้อมูล</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['provisional_barcode'])): ?>
                                    <code><?= htmlspecialchars($row['provisional_barcode']) ?></code>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">ยังไม่มีข้อมูล</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['product_category']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['product_category']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['created_by'] ?? '-') ?></td>
                            <td class="text-center" data-order="<?= htmlspecialchars($row['created_at']) ?>">
                                <small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td class="text-center">
                                <span class="text-muted" title="จำนวนรอการอนุมัติ"><?= htmlspecialchars($row['quantity'] ?? 0) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($row['expiry_date']): ?>
                                    <?php
                                    $expiry = new DateTime($row['expiry_date']);
                                    $today = new DateTime();
                                    $is_expired = $expiry < $today;
                                    $badge_class = $is_expired ? 'bg-danger' : 'bg-info';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= $expiry->format('d/m/Y') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $status = $row['temp_product_status'] ?? 'pending';
                                if ($status === 'converted'):
                                ?>
                                    <span class="badge bg-success">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">check_circle</span>
                                        ย้ายไปคลังแล้ว
                                    </span>
                                <?php elseif ($status === 'approved'): ?>
                                    <span class="badge bg-info">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">pending_actions</span>
                                        อนุมัติแล้ว
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <span class="material-icons" style="font-size: 0.85rem; vertical-align: middle;">schedule</span>
                                        รอดำเนิน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-warning view-damaged-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-product-name="<?= htmlspecialchars($row['product_name'] ?? '') ?>" data-provisional-sku="<?= htmlspecialchars($row['provisional_sku'] ?? '') ?>" data-provisional-barcode="<?= htmlspecialchars($row['provisional_barcode'] ?? '') ?>" data-unit="<?= htmlspecialchars($row['unit'] ?? '') ?>" data-category="<?= htmlspecialchars($row['product_category'] ?? '') ?>" data-expiry="<?= htmlspecialchars($row['expiry_date'] ?? '') ?>" data-quantity="<?= htmlspecialchars($row['quantity'] ?? 0) ?>" data-purchase-price="<?= htmlspecialchars($row['purchase_price'] ?? '') ?>" data-sale-price="<?= htmlspecialchars($row['sale_price'] ?? '') ?>" data-po-sale-price="<?= htmlspecialchars($row['po_price_per_unit'] ?? '') ?>" data-weight="<?= htmlspecialchars($row['remark_weight'] ?? '') ?>" data-remark="<?= htmlspecialchars($row['temp_product_remark'] ?? '') ?>" data-image="<?= htmlspecialchars(resolveTransactionImageSrc($row['image'] ?? null)) ?>" title="ดูรายละเอียดตรวจสอบชำรุด">
                                        <span class="material-icons" style="font-size: 1rem;">visibility</span>
                                    </button>
                                    <button class="btn btn-outline-info edit-damaged-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-product-name="<?= htmlspecialchars($row['product_name'] ?? '') ?>" data-provisional-sku="<?= htmlspecialchars($row['provisional_sku'] ?? '') ?>" data-provisional-barcode="<?= htmlspecialchars($row['provisional_barcode'] ?? '') ?>" data-unit="<?= htmlspecialchars($row['unit'] ?? '') ?>" data-category="<?= htmlspecialchars($row['product_category'] ?? '') ?>" data-expiry="<?= htmlspecialchars($row['expiry_date'] ?? '') ?>" data-quantity="<?= htmlspecialchars($row['quantity'] ?? 0) ?>" data-purchase-price="<?= htmlspecialchars($row['purchase_price'] ?? '') ?>" data-sale-price="<?= htmlspecialchars($row['sale_price'] ?? '') ?>" data-po-sale-price="<?= htmlspecialchars($row['po_price_per_unit'] ?? '') ?>" data-weight="<?= htmlspecialchars($row['remark_weight'] ?? '') ?>" data-remark="<?= htmlspecialchars($row['temp_product_remark'] ?? '') ?>" data-image="<?= htmlspecialchars(resolveTransactionImageSrc($row['image'] ?? null)) ?>" title="แก้ไข SKU/Barcode">
                                        <span class="material-icons" style="font-size: 1rem;">edit</span>
                                    </button>
                                    <button class="btn btn-outline-success approve-damaged-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-name="<?= htmlspecialchars($row['product_name']) ?>" title="อนุมัติและนำเข้าคลังเก็บ">
                                        <span class="material-icons" style="font-size: 1rem;">check_circle</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

<script>
let transactionTable;
let currentBasePurchasePrice = 0;
let currentSavedSalePrice = 0;  // เก็บราคาขายที่บันทึกไว้

function formatPrice(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num.toFixed(2) : '-';
}

function decodeHtml(value) {
    if (value === undefined || value === null) {
        return '';
    }
    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;
    return textarea.value;
}

function updatePricingFromWeight() {
    const weightVal = Number($('#weightInput').val());
    const hasWeight = Number.isFinite(weightVal) && weightVal >= 0;
    const weightCost = hasWeight ? weightVal * 850 : 0;
    const baseCost = Number.isFinite(currentBasePurchasePrice) ? currentBasePurchasePrice : 0;
    const trueCost = baseCost + weightCost;

    if (baseCost > 0 || hasWeight) {
        $('#trueCostInput').val(trueCost.toFixed(2));
        // เพียงแนะนำราคาต้นทุน เมื่อยังไม่มีการตั้งราคาขาย หรือราคาขายเป็นศูนย์
        if (hasWeight) {
            const currentSalePrice = Number($('#salePriceInput').val());
            // ถ้าไม่มีราคาขายหรือเป็น 0 จึงตั้งจากต้นทุน
            if (!Number.isFinite(currentSalePrice) || currentSalePrice === 0) {
                $('#salePriceInput').val(trueCost.toFixed(2));
            }
            // ถ้ามีราคาขายบันทึกไว้แล้ว ให้ใช้ค่านั้น (ไม่ overwrite)
        }
    } else {
        $('#trueCostInput').val('');
    }

    updateNetProfitLabel();
}

function calculateDetailedProfit(salePrice, cost) {
    if (!Number.isFinite(salePrice) || salePrice < 0) {
        return null;
    }
    
    // 1. ค่าธรรมเนียม 15%
    const fee15 = salePrice * 0.15;
    
    // 2. ค่าใช้จ่าย 17%
    const expense17 = salePrice * 0.17;
    
    // 3. ค่าคอมมิชชั่น 5%
    const commission5 = (salePrice - fee15) * 0.05;
    
    // 4. กำไรขั้นต้น
    const grossProfit = salePrice - cost - fee15 - expense17 - commission5;
    
    // 5. ภาษีนิติบุคคล 20%
    const corporateTax20 = grossProfit * 0.20;
    
    // 6. กำไรสุทธิ
    const netProfit = grossProfit - corporateTax20;
    
    // 7. %กำไรสุทธิ
    const netProfitPercent = (netProfit * 100) / salePrice;
    
    return {
        netProfit: netProfit,
        netProfitPercent: netProfitPercent
    };
}

function updateNetProfitLabel() {
    const saleVal = Number($('#salePriceInput').val());
    const trueCostVal = Number($('#trueCostInput').val());
    
    if (!Number.isFinite(saleVal) || saleVal < 0) {
        $('#netProfitDisplay').text('-');
        return;
    }
    
    const cost = Number.isFinite(trueCostVal) && trueCostVal > 0 ? trueCostVal : 0;
    const profitData = calculateDetailedProfit(saleVal, cost);
    
    if (profitData) {
        const profitDisplay = profitData.netProfit.toFixed(2) + ' บาท (' + profitData.netProfitPercent.toFixed(2) + '%)';
        $('#netProfitDisplay').text(profitDisplay);
    } else {
        $('#netProfitDisplay').text('-');
    }
}

// Global variables for categories
let categoriesData = [];

// Load product categories on page load
function loadProductCategories() {
    $.ajax({
        url: '../api/get_product_categories.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                categoriesData = response.data;
                console.log('✅ Loaded', categoriesData.length, 'categories');
            } else {
                console.error('Failed to load categories:', response.message);
            }
        },
        error: function(xhr) {
            console.error('Error loading categories:', xhr);
        }
    });
}

// Populate category dropdown (call this when opening modal)
function populateCategoryDropdown() {
    const categorySelect = $('#categorySelect');
    if (categorySelect.length === 0) {
        console.warn('⚠️ Category select not found in DOM');
        return;
    }
    
    categorySelect.empty();
    categorySelect.append('<option value="">-- เลือกหมวดหมู่ --</option>');
    
    if (categoriesData.length === 0) {
        console.warn('⚠️ No categories data loaded yet');
        return;
    }
    
    categoriesData.forEach(function(category) {
        categorySelect.append(
            $('<option></option>')
                .attr('value', category.category_id)
                .text(category.category_name)
        );
    });
    
    console.log('✅ Populated', categoriesData.length, 'categories into dropdown');
}

$(document).ready(function() {
    // Load categories first (just data, not populating dropdown yet)
    loadProductCategories();
    
    // Debug Info
    console.log('Page loaded');
    console.log('Receive rows from PHP:', <?= count($receive_rows) ?>);
    console.log('Damaged rows from PHP:', <?= count($damaged_rows) ?>);
    console.log('Receive data:', <?= json_encode($receive_rows) ?>);
    console.log('Damaged data:', <?= json_encode($damaged_rows) ?>);
    
    // Initialize DataTable for Receive Items
    let receiveTable = null;
    try {
        // ตรวจสอบว่าตารางมีข้อมูลหรือไม่
        const receiveTableRows = $('#receive-table tbody tr');
        const hasReceiveData = receiveTableRows.length > 0 && !receiveTableRows.first().find('td[colspan]').length;
        
        console.log('Receive table has data:', hasReceiveData, 'rows:', receiveTableRows.length);
        
        // เฉพาะตารางที่มีข้อมูลจริงๆ เท่านั้นถึงจะ initialize DataTable
        if (hasReceiveData) {
            receiveTable = $('#receive-table').DataTable({
                pageLength: 25,
                language: {
                    "decimal": "",
                    "emptyTable": "ไม่มีข้อมูลในตาราง",
                    "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                    "infoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
                    "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
                    "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                    "loadingRecords": "กำลังโหลด...",
                    "processing": "กำลังประมวลผล...",
                    "search": "ค้นหา:",
                    "zeroRecords": "ไม่พบรายการที่ตรงกัน",
                    "paginate": {
                        "first": "หน้าแรก",
                        "last": "หน้าสุดท้าย",
                        "next": "ถัดไป",
                        "previous": "ก่อนหน้า"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: 'no-sort' },
                { 
                    render: function(data, type, row) {
                        if(type === 'display') return data;
                        return data;
                    },
                    targets: [1, 2, 3, 4, 5, 6]  // Sortable columns
                },
                { className: "text-center", targets: [0, 7, 8, 9, 10] }  // Center: Image, Qty, Expiry, Actions
            ],
            order: [[6, 'desc']], // Sort by date (column 6)
            scrollX: true,
            scrollCollapse: true,
            searching: true,
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
            responsive: true,
            processing: true,
            drawCallback: function(settings) {
                updateStats();
                $('[title]').tooltip();
            },
            initComplete: function() {
                updateStats();
            }
            });
            
            console.log('✅ Receive DataTable initialized successfully');
        } else {
            console.log('⚠️ Receive table is empty, skipping DataTable initialization');
        }
    } catch (error) {
        console.error('❌ Error initializing Receive DataTable:', error);
        // ถ้า initialize ล้มเหลว ให้แสดงตารางปกติ
        $('#receive-table').show();
    }

    // Initialize DataTable for Damaged Items
    let damagedTable = null;
    try {
        // ตรวจสอบว่าตารางมีข้อมูลหรือไม่
        const damagedTableRows = $('#damaged-table tbody tr');
        const hasDamagedData = damagedTableRows.length > 0 && !damagedTableRows.first().find('td[colspan]').length;
        
        console.log('Damaged table has data:', hasDamagedData, 'rows:', damagedTableRows.length);
        
        // เฉพาะตารางที่มีข้อมูลจริงๆ เท่านั้นถึงจะ initialize DataTable
        if (hasDamagedData) {
            damagedTable = $('#damaged-table').DataTable({
                pageLength: 25,
                language: {
                    "decimal": "",
                    "emptyTable": "ไม่มีข้อมูลในตาราง",
                    "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                    "infoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
                    "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
                    "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                    "loadingRecords": "กำลังโหลด...",
                    "processing": "กำลังประมวลผล...",
                "search": "ค้นหา:",
                "zeroRecords": "ไม่พบรายการที่ตรงกัน",
                "paginate": {
                    "first": "หน้าแรก",
                    "last": "หน้าสุดท้าย",
                    "next": "ถัดไป",
                    "previous": "ก่อนหน้า"
                }
            },
            columnDefs: [
                { orderable: false, targets: 'no-sort' },
                { 
                    render: function(data, type, row) {
                        if(type === 'display') return data;
                        return data;
                    },
                    targets: [1, 2, 3, 4, 5, 6]  // Sortable columns
                },
                { className: "text-center", targets: [0, 7, 8, 9, 10] }  // Center: Image, Qty, Expiry, Actions
            ],
            order: [[6, 'desc']], // Sort by date (column 6)
            scrollX: true,
            scrollCollapse: true,
            searching: true,
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
            responsive: true,
            processing: true,
            drawCallback: function(settings) {
                updateStats();
                $('[title]').tooltip();
            },
            initComplete: function() {
                updateStats();
            }
            });
            
            console.log('✅ Damaged DataTable initialized successfully');
        } else {
            console.log('⚠️ Damaged table is empty, skipping DataTable initialization');
        }
    } catch (error) {
        console.error('❌ Error initializing Damaged DataTable:', error);
        // ถ้า initialize ล้มเหลว ให้แสดงตารางปกติ
        $('#damaged-table').show();
    }

    // Custom search for Receive Table
    $('#custom-search-receive').on('keyup', function() {
        if (receiveTable) {
            receiveTable.search(this.value).draw();
        }
    });

    // Custom search for Damaged Table
    $('#custom-search-damaged').on('keyup', function() {
        if (damagedTable) {
            damagedTable.search(this.value).draw();
        }
    });

    // Check buttons after initialization
    setTimeout(function() {
        const receiveButtons = $('#receive-table .view-btn, #receive-table .edit-btn').length;
        const damagedButtons = $('#damaged-table .view-btn, #damaged-table .edit-btn').length;
        
        console.log('🔘 Buttons found - Receive:', receiveButtons, 'Damaged:', damagedButtons);
        console.log('📊 DataTable status - Receive:', receiveTable !== null, 'Damaged:', damagedTable !== null);
    }, 500);

    $('#weightInput').on('input change', updatePricingFromWeight);
    $('#salePriceInput').on('input change', updateNetProfitLabel);

    // Hide default DataTable search
    setTimeout(function() {
        $('.dataTables_filter').hide();
    }, 100);
    
    // Handle edit button click
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        console.log('🔧 Edit button clicked (receive)');
        
        const tempId = $(this).data('temp-id');
        const receiveId = $(this).data('receive-id');
        const expiry = $(this).data('expiry');
        const provisionalSku = $(this).data('provisional-sku');
        const provisionalBarcode = $(this).data('provisional-barcode');
        const categoryValue = $(this).data('category');
        const quantityValue = $(this).data('quantity');
        const purchasePrice = $(this).attr('data-purchase-price');
        const salePrice = $(this).attr('data-sale-price');
        const poSalePrice = $(this).attr('data-po-sale-price');
        const remarkRaw = $(this).attr('data-remark');
        const weightValue = $(this).data('weight');
        const unitValue = $(this).data('unit');
        const productName = $(this).attr('data-product-name') || '';
        const rowImageSrc = $(this).closest('tr').find('img.product-image').attr('src');
        const resolvedPurchasePrice = [purchasePrice, poSalePrice, salePrice].find(v => v !== undefined && v !== null && v !== '');
        const resolvedSalePrice = (salePrice !== undefined && salePrice !== null && salePrice !== '') ? salePrice : '';
        const numericPurchaseBase = Number(resolvedPurchasePrice);
        
        // Populate category dropdown first
        populateCategoryDropdown();
        
        $('#tempProductId').val(tempId);
        $('#receiveId').val(receiveId);
        $('#expiryInput').val(expiry);
        $('#provisionalSkuInput').val(provisionalSku);
        $('#provisionalBarcodeInput').val(provisionalBarcode);
        $('#unitInput').val(unitValue || '');
        $('#productNameDisplay').text(productName || '');
        
        // Set category - find matching category ID from categoryValue (category name)
        if (categoryValue) {
            const matchingCategory = categoriesData.find(c => c.category_name === categoryValue);
            if (matchingCategory) {
                $('#categorySelect').val(matchingCategory.category_id);
            } else {
                $('#categorySelect').val('');
            }
        } else {
            $('#categorySelect').val('');
        }
        
        $('#quantityDisplay').text(quantityValue ? parseFloat(quantityValue) : '-');
        $('#weightInput').val(weightValue ?? '');
        $('#purchasePriceInput').val('');
        $('#salePriceInput').val('');
        $('#remarkInput').val('');
        $('#salePriceDisplay').text('ราคาขายจาก PO: ' + formatPrice(resolvedSalePrice) + ' บาท');
        currentBasePurchasePrice = Number.isFinite(numericPurchaseBase) ? numericPurchaseBase : 0;

        if (resolvedPurchasePrice !== undefined && resolvedPurchasePrice !== null && resolvedPurchasePrice !== '') {
            const numericPurchasePrice = Number(resolvedPurchasePrice);
            $('#purchasePriceInput').val(!Number.isNaN(numericPurchasePrice) ? numericPurchasePrice.toFixed(2) : resolvedPurchasePrice).addClass('text-danger fw-semibold');
        } else {
            $('#purchasePriceInput').val('');
        }
        
        // โหลดราคาขายที่บันทึกไว้ (priority: สำถามจากฐานข้อมูลก่อน)
        if (resolvedSalePrice !== '') {
            const numericSalePrice = Number(resolvedSalePrice);
            currentSavedSalePrice = !Number.isNaN(numericSalePrice) ? numericSalePrice : 0;
            $('#salePriceInput').val(!Number.isNaN(numericSalePrice) ? numericSalePrice.toFixed(2) : resolvedSalePrice);
        } else {
            currentSavedSalePrice = 0;
        }
        
        // อัปเดตต้นทุนจากน้ำหนัก แต่ไม่ overwrite ราคาขายที่มีในข้อมูล
        updatePricingFromWeight();
        updateNetProfitLabel();
        if (remarkRaw) {
            $('#remarkInput').val(decodeHtml(remarkRaw));
        }
        $('#productImageInput').val('');
        
        if (rowImageSrc) {
            $('#previewImg').attr('src', rowImageSrc);
            $('#imagePreview').show();
        } else {
            $('#previewImg').attr('src', '');
            $('#imagePreview').hide();
        }
        
        // ล้างฟิลด์ตำแหน่ง
        $('#locationSearchEdit').val('');
        $('#editRowCodeInput').val('');
        $('#editBinInput').val('');
        $('#editShelfInput').val('');
        $('#editProductLocation').val('');
        $('#locationSuggestionsEdit').hide();
        
        // ดึงข้อมูลตำแหน่งจาก API ถ้ามี receive_id
        if (receiveId) {
            $.get('../api/receive_position_api.php', { receive_id: receiveId }, function(resp){
                if(resp && resp.success) {
                    $('#editRowCodeInput').val(resp.row_code || '');
                    $('#editBinInput').val(resp.bin || '');
                    $('#editShelfInput').val(resp.shelf || '');
                    if (resp.location_id) {
                        $('#editProductLocation').val(resp.location_id);
                    }
                }
            }, 'json').fail(function(){
                console.log('Could not load location data');
            });
        }
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    });
    
    // Handle image preview with compression
    $('#productImageInput').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // Create canvas for compression
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Calculate new dimensions (max 1200x1200)
                    let width = img.width;
                    let height = img.height;
                    const maxSize = 1200;
                    
                    if (width > height) {
                        if (width > maxSize) {
                            height = Math.round(height * (maxSize / width));
                            width = maxSize;
                        }
                    } else {
                        if (height > maxSize) {
                            width = Math.round(width * (maxSize / height));
                            height = maxSize;
                        }
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Convert to WebP with compression
                    canvas.toBlob(function(blob) {
                        // Preview original size
                        $('#previewImg').attr('src', e.target.result);
                        $('#imagePreview').show();
                        
                        // Show compression info
                        const originalSize = (file.size / 1024).toFixed(2);
                        const compressedSize = (blob.size / 1024).toFixed(2);
                        console.log(`Image Compression: ${originalSize}KB → ${compressedSize}KB`);
                    }, 'image/webp', 0.8);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            $('#imagePreview').hide();
        }
    });
    
    // Handle save button click
    $('#saveEditBtn').on('click', function() {
        const tempId = $('#tempProductId').val();
        const expiryDate = $('#expiryInput').val();
        const provisionalSku = $('#provisionalSkuInput').val();
        const provisionalBarcode = $('#provisionalBarcodeInput').val();
        const unitValue = $('#unitInput').val().trim();
        const categoryId = $('#categorySelect').val();
        const rowCode = $('#editRowCodeInput').val().trim();
        const bin = $('#editBinInput').val().trim();
        const shelf = $('#editShelfInput').val().trim();
        const weightVal = $('#weightInput').val();
        const purchasePrice = $('#purchasePriceInput').val();
        const salePrice = $('#salePriceInput').val();
        const remark = $('#remarkInput').val().trim();
        const locationId = $('#editProductLocation').val();
        const imageFile = $('#productImageInput')[0].files[0];
        
        if (!tempId) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'ข้อมูลไม่ถูกต้อง'
            });
            return;
        }

        if (purchasePrice !== '' && !isNaN(purchasePrice) && Number(purchasePrice) < 0) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อมูลราคาซื้อไม่ถูกต้อง',
                text: 'กรุณากรอกราคาซื้อที่เป็นจำนวนไม่ติดลบ'
            });
            return;
        }

        if (salePrice !== '' && !isNaN(salePrice) && Number(salePrice) < 0) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อมูลราคาขายไม่ถูกต้อง',
                text: 'กรุณากรอกราคาขายที่เป็นจำนวนไม่ติดลบ'
            });
            return;
        }
        
        // Show loading state
        Swal.fire({
            title: 'กำลังบันทึก',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Process image compression if exists
        if (imageFile) {
            compressAndSendImage(imageFile, tempId, expiryDate, provisionalSku, provisionalBarcode, unitValue, categoryId, rowCode, bin, shelf, weightVal, purchasePrice, salePrice, remark, locationId);
        } else {
            sendFormData(null, tempId, expiryDate, provisionalSku, provisionalBarcode, unitValue, categoryId, rowCode, bin, shelf, weightVal, purchasePrice, salePrice, remark, locationId);
        }
    });
    
    // Compress image and send
    function compressAndSendImage(file, tempId, expiryDate, provisionalSku, provisionalBarcode, unitValue, categoryId, rowCode, bin, shelf, weightVal, purchasePrice, salePrice, remark, locationId) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                // Create canvas for compression
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Calculate new dimensions (max 1200x1200)
                let width = img.width;
                let height = img.height;
                const maxSize = 1200;
                
                if (width > height) {
                    if (width > maxSize) {
                        height = Math.round(height * (maxSize / width));
                        width = maxSize;
                    }
                } else {
                    if (height > maxSize) {
                        width = Math.round(width * (maxSize / height));
                        height = maxSize;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);
                
                // Convert to Blob (canvas.toBlob will auto-convert to JPEG in this context)
                canvas.toBlob(function(blob) {
                    // Create new File object from compressed blob as JPEG
                    const compressedFile = new File([blob], 'image.jpg', { type: 'image/jpeg' });
                    const originalSize = (file.size / 1024).toFixed(2);
                    const compressedSize = (compressedFile.size / 1024).toFixed(2);
                    console.log(`Image Compressed: ${originalSize}KB → ${compressedSize}KB`);
                    console.log('Sending file:', compressedFile.name, compressedFile.type, compressedFile.size);
                    
                    sendFormData(compressedFile, tempId, expiryDate, provisionalSku, provisionalBarcode, unitValue, categoryId, rowCode, bin, shelf, weightVal, purchasePrice, salePrice, remark, locationId);
                }, 'image/jpeg', 0.85);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
    // Send form data to server
    function sendFormData(imageFile, tempId, expiryDate, provisionalSku, provisionalBarcode, unitValue, categoryId, rowCode, bin, shelf, weightVal, purchasePrice, salePrice, remark, locationId) {
        const formData = new FormData();
        formData.append('temp_product_id', tempId);
        formData.append('expiry_date', expiryDate);
        formData.append('provisional_sku', provisionalSku);
        formData.append('provisional_barcode', provisionalBarcode);
        formData.append('unit', unitValue);
        formData.append('product_category_id', categoryId ?? '');
        formData.append('row_code', rowCode);
        formData.append('bin', bin);
        formData.append('shelf', shelf);
        formData.append('weight', weightVal ?? '');
        formData.append('purchase_price', purchasePrice ?? '');
        formData.append('sale_price', salePrice ?? '');
        formData.append('remark', remark ?? '');
        formData.append('location_id', locationId ?? '');
        if (imageFile) {
            formData.append('product_image', imageFile);
            console.log('FormData entries:');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(`  ${key}: File(${value.name}, ${value.size} bytes, ${value.type})`);
                } else {
                    console.log(`  ${key}: ${value}`);
                }
            }
        }
        
        $.ajax({
            url: '../api/update_temp_product.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Success:', response);
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลสินค้าถูกบันทึก' + (response.compression_info ? '\n' + response.compression_info : ''),
                        confirmButtonText: 'ตกลง'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: response.message || 'ไม่ทราบสาเหตุ'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:');
                console.error('Status:', xhr.status);
                console.error('Status Text:', xhr.statusText);
                console.error('Response Text:', xhr.responseText);
                console.error('Error:', error);
                
                let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                let errorTitle = 'เชื่อมต่อล้มเหลว';
                
                // Try to parse JSON error response from server
                try {
                    if (xhr.responseText) {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                            errorTitle = 'เกิดข้อผิดพลาด';
                        }
                    }
                } catch (e) {
                    // If not JSON, try to get responseText
                    if (xhr.responseText) {
                        errorMessage = xhr.responseText.substring(0, 500);
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: errorTitle,
                    text: errorMessage,
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    }
    
    // Handle view button click
    $(document).on('click', '.view-btn', function(e) {
        e.preventDefault();
        console.log('👁️ View button clicked');
        
        const tempId = $(this).data('temp-id');
        const productName = $(this).data('product-name');
        const provisionalSku = $(this).data('provisional-sku');
        const provisionalBarcode = $(this).data('provisional-barcode');
        const unitValue = $(this).data('unit');
        const category = $(this).data('category');
        const expiryDate = $(this).data('expiry');
        const quantityValue = $(this).data('quantity');
        const purchasePrice = $(this).data('purchase-price');
        const salePrice = $(this).data('sale-price');
        const poSalePrice = $(this).data('po-sale-price');
        const weightValue = $(this).data('weight');
        const remarkValue = $(this).data('remark');
        const imageSrc = $(this).data('image');
        
        // Resolve purchase price from multiple sources (same logic as edit button)
        const resolvedPurchasePrice = [purchasePrice, poSalePrice, salePrice].find(v => v !== undefined && v !== null && v !== '');
        const resolvedSalePrice = (salePrice !== undefined && salePrice !== null && salePrice !== '') ? salePrice : '';
        
        // Set modal content
        $('#viewProductNameDisplay').text(productName || '-');
        $('#viewProductImagePreview').attr('src', imageSrc);
        $('#viewProvisionalSkuDisplay').text(provisionalSku || '-');
        $('#viewProvisionalBarcodeDisplay').text(provisionalBarcode || '-');
        $('#viewUnitDisplay').text(unitValue || '-');
        $('#viewCategoryDisplay').text(category || '-');
        $('#viewExpiryDisplay').text(expiryDate ? new Date(expiryDate).toLocaleDateString('th-TH') : '-');
        $('#viewQuantityDisplay').text(quantityValue ? parseFloat(quantityValue) : '-');
        
        // Display purchase price (resolved from multiple sources)
        if (resolvedPurchasePrice !== undefined && resolvedPurchasePrice !== null && resolvedPurchasePrice !== '') {
            const numericPurchasePrice = Number(resolvedPurchasePrice);
            $('#viewPurchasePriceDisplay').text(!Number.isNaN(numericPurchasePrice) ? numericPurchasePrice.toFixed(2) : '-');
        } else {
            $('#viewPurchasePriceDisplay').text('-');
        }
        
        $('#viewSalePriceDisplay').text(resolvedSalePrice ? parseFloat(resolvedSalePrice).toFixed(2) : '-');
        $('#viewWeightDisplay').text(weightValue ? parseFloat(weightValue).toFixed(2) : '-');
        
        // Calculate true cost (purchase price + weight cost)
        let trueCost = 0;
        let hasCost = false;
        
        if (resolvedPurchasePrice !== undefined && resolvedPurchasePrice !== null && resolvedPurchasePrice !== '') {
            const baseCost = parseFloat(resolvedPurchasePrice);
            if (!Number.isNaN(baseCost)) {
                trueCost = baseCost;
                hasCost = true;
                
                // Add weight cost if exists
                if (weightValue) {
                    const weightCost = parseFloat(weightValue) * 850;
                    if (!Number.isNaN(weightCost)) {
                        trueCost += weightCost;
                    }
                }
            }
        }
        
        $('#viewTrueCostDisplay').text(hasCost ? trueCost.toFixed(2) : '-');
        
        // Calculate net profit with detailed formula
        if (resolvedSalePrice && resolvedSalePrice !== '') {
            const salePriceNum = parseFloat(resolvedSalePrice);
            if (!Number.isNaN(salePriceNum) && salePriceNum > 0) {
                const profitData = calculateDetailedProfit(salePriceNum, trueCost);
                if (profitData) {
                    const profitDisplay = profitData.netProfit.toFixed(2) + ' บาท (' + profitData.netProfitPercent.toFixed(2) + '%)';
                    $('#viewNetProfitDisplay').text(profitDisplay);
                } else {
                    $('#viewNetProfitDisplay').text('-');
                }
            } else {
                $('#viewNetProfitDisplay').text('-');
            }
        } else {
            $('#viewNetProfitDisplay').text('-');
        }
        
        $('#viewRemarkDisplay').text(remarkValue || '-');
        
        const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        viewModal.show();
    });
    
    // Handle approve button click
    $(document).on('click', '.approve-btn', function() {
        const tempId = $(this).data('temp-id');
        const productName = $(this).data('name');
        
        // Get the row element to check SKU and Barcode values
        const $row = $(this).closest('tr');
        const $skuCell = $row.find('td:eq(2)'); // SKU column (3rd column)
        const $barcodeCell = $row.find('td:eq(3)'); // Barcode column (4th column)
        
        // Check if SKU and Barcode have the "ยังไม่มีข้อมูล" badge
        const hasNoSku = $skuCell.find('.badge.bg-warning').length > 0;
        const hasNoBarcode = $barcodeCell.find('.badge.bg-warning').length > 0;
        
        // If SKU or Barcode is missing, show warning
        if (hasNoSku || hasNoBarcode) {
            let missingFields = [];
            if (hasNoSku) missingFields.push('SKU');
            if (hasNoBarcode) missingFields.push('บาร์โค้ด');
            
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลยังไม่ครบถ้วน',
                html: 'กรุณากรอกข้อมูลต่อไปนี้ก่อนอนุมัติ:<br><br>' +
                      '<strong style="color: #dc2626;">' + missingFields.join(' และ ') + '</strong><br><br>' +
                      'คลิก "แก้ไข" เพื่ออัปเดตข้อมูล',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#f97316'
            });
            return;
        }
        
        Swal.fire({
            title: 'ยืนยันการอนุมัติ',
            text: 'ต้องการอนุมัติสินค้า "' + productName + '" และย้ายไปคลังสินค้าปกติใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่ อนุมัติ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'กำลังประมวลผล',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '../api/approve_temp_product.php',
                    type: 'POST',
                    data: {
                        temp_product_id: tempId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const productId = response.product_id || response.new_product_id || '-';
                            Swal.fire({
                                icon: 'success',
                                title: 'อนุมัติสำเร็จ!',
                                text: 'สินค้าถูกย้ายไปยังคลังปกติแล้ว\nรหัสสินค้า: ' + productId,
                                confirmButtonText: 'ตกลง'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    location.reload();
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: response.message || 'ไม่สามารถอนุมัติสินค้าได้'
                            });
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์';
                        let errorTitle = 'เชื่อมต่อล้มเหลว';
                        
                        // Try to parse JSON error response from server
                        try {
                            if (xhr.responseText) {
                                const errorResponse = JSON.parse(xhr.responseText);
                                if (errorResponse.message) {
                                    errorMessage = errorResponse.message;
                                    errorTitle = 'เกิดข้อผิดพลาด';
                                }
                            }
                        } catch (e) {
                            // If not JSON, try to get responseText
                            if (xhr.responseText) {
                                errorMessage = xhr.responseText.substring(0, 500);
                            }
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: errorTitle,
                            text: errorMessage,
                            confirmButtonText: 'ตกลง'
                        });
                        console.error(xhr);
                    }
                });
            }
        });
    });
    
    // Handle view damaged button click
    $(document).on('click', '.view-damaged-btn', function() {
        const tempId = $(this).data('temp-id');
        const productName = $(this).data('product-name');
        const provisionalSku = $(this).data('provisional-sku');
        const provisionalBarcode = $(this).data('provisional-barcode');
        const unitValue = $(this).data('unit');
        const category = $(this).data('category');
        const expiryDate = $(this).data('expiry');
        const quantityValue = $(this).data('quantity');
        const purchasePrice = $(this).data('purchase-price');
        const salePrice = $(this).data('sale-price');
        const poSalePrice = $(this).data('po-sale-price');
        const weightValue = $(this).data('weight');
        const remarkValue = $(this).data('remark');
        const imageSrc = $(this).data('image');
        
        // Resolve purchase price from multiple sources (same logic as edit button)
        const resolvedPurchasePrice = [purchasePrice, poSalePrice, salePrice].find(v => v !== undefined && v !== null && v !== '');
        const resolvedSalePrice = (salePrice !== undefined && salePrice !== null && salePrice !== '') ? salePrice : '';
        
        // Set modal content for damaged items
        $('#viewProductNameDisplay').text(productName || '-');
        $('#viewProductImagePreview').attr('src', imageSrc);
        $('#viewProvisionalSkuDisplay').text(provisionalSku || '-');
        $('#viewProvisionalBarcodeDisplay').text(provisionalBarcode || '-');
        $('#viewUnitDisplay').text(unitValue || '-');
        $('#viewCategoryDisplay').text(category || '-');
        $('#viewExpiryDisplay').text(expiryDate ? new Date(expiryDate).toLocaleDateString('th-TH') : '-');
        $('#viewQuantityDisplay').text(quantityValue ? parseFloat(quantityValue) : '-');
        
        // Display purchase price (resolved from multiple sources)
        if (resolvedPurchasePrice !== undefined && resolvedPurchasePrice !== null && resolvedPurchasePrice !== '') {
            const numericPurchasePrice = Number(resolvedPurchasePrice);
            $('#viewPurchasePriceDisplay').text(!Number.isNaN(numericPurchasePrice) ? numericPurchasePrice.toFixed(2) : '-');
        } else {
            $('#viewPurchasePriceDisplay').text('-');
        }
        
        $('#viewSalePriceDisplay').text(resolvedSalePrice ? parseFloat(resolvedSalePrice).toFixed(2) : '-');
        $('#viewWeightDisplay').text(weightValue ? parseFloat(weightValue).toFixed(2) : '-');
        
        // Calculate true cost (purchase price + weight cost)
        let trueCost = 0;
        let hasCost = false;
        
        if (resolvedPurchasePrice !== undefined && resolvedPurchasePrice !== null && resolvedPurchasePrice !== '') {
            const baseCost = parseFloat(resolvedPurchasePrice);
            if (!Number.isNaN(baseCost)) {
                trueCost = baseCost;
                hasCost = true;
                
                // Add weight cost if exists
                if (weightValue) {
                    const weightCost = parseFloat(weightValue) * 850;
                    if (!Number.isNaN(weightCost)) {
                        trueCost += weightCost;
                    }
                }
            }
        }
        
        $('#viewTrueCostDisplay').text(hasCost ? trueCost.toFixed(2) : '-');
        
        // Calculate net profit with detailed formula
        if (resolvedSalePrice && resolvedSalePrice !== '') {
            const salePriceNum = parseFloat(resolvedSalePrice);
            if (!Number.isNaN(salePriceNum) && salePriceNum > 0) {
                const profitData = calculateDetailedProfit(salePriceNum, trueCost);
                if (profitData) {
                    const profitDisplay = profitData.netProfit.toFixed(2) + ' บาท (' + profitData.netProfitPercent.toFixed(2) + '%)';
                    $('#viewNetProfitDisplay').text(profitDisplay);
                } else {
                    $('#viewNetProfitDisplay').text('-');
                }
            } else {
                $('#viewNetProfitDisplay').text('-');
            }
        } else {
            $('#viewNetProfitDisplay').text('-');
        }
        
        $('#viewRemarkDisplay').text(remarkValue || '-');
        
        const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        viewModal.show();
    });
    
    // Handle edit damaged button click
    $(document).on('click', '.edit-damaged-btn', function() {
        const tempId = $(this).data('temp-id');
        const productName = $(this).data('product-name');
        const provisionalSku = $(this).data('provisional-sku');
        const provisionalBarcode = $(this).data('provisional-barcode');
        const unitValue = $(this).data('unit');
        const category = $(this).data('category');
        const expiryDate = $(this).data('expiry');
        const quantityValue = $(this).data('quantity');
        const purchasePrice = $(this).attr('data-purchase-price');
        const salePrice = $(this).attr('data-sale-price');
        const poSalePrice = $(this).attr('data-po-sale-price');
        const weightValue = $(this).data('weight');
        const remarkRaw = $(this).attr('data-remark');
        const imageSrc = $(this).data('image');
        const rowImageSrc = $(this).closest('tr').find('img.product-image').attr('src');
        const resolvedPurchasePrice = [purchasePrice, poSalePrice, salePrice].find(v => v !== undefined && v !== null && v !== '');
        const resolvedSalePrice = (salePrice !== undefined && salePrice !== null && salePrice !== '') ? salePrice : '';
        const numericPurchaseBase = Number(resolvedPurchasePrice);
        
        // Populate category dropdown first
        populateCategoryDropdown();
        
        // Show modal to edit damaged item SKU/Barcode
        $('#tempProductId').val(tempId);
        $('#receiveId').val(''); // No receive ID for damaged items
        $('#expiryInput').val(expiryDate);
        $('#provisionalSkuInput').val(provisionalSku);
        $('#provisionalBarcodeInput').val(provisionalBarcode);
        $('#unitInput').val(unitValue || '');
        $('#productNameDisplay').text(productName || '');
        
        // Set category - find matching category ID from category (category name)
        if (category) {
            const matchingCategory = categoriesData.find(c => c.category_name === category);
            if (matchingCategory) {
                $('#categorySelect').val(matchingCategory.category_id);
            } else {
                $('#categorySelect').val('');
            }
        } else {
            $('#categorySelect').val('');
        }
        
        $('#quantityDisplay').text(quantityValue ? parseFloat(quantityValue) : '-');
        $('#weightInput').val(weightValue ?? '');
        $('#remarkInput').val(remarkRaw ? decodeHtml(remarkRaw) : '');
        $('#productImageInput').val('');
        $('#purchasePriceInput').removeClass('text-danger fw-semibold');
        
        // Debug logging
        console.log('=== Edit Damaged Debug ===');
        console.log('purchasePrice (raw):', purchasePrice);
        console.log('salePrice (raw):', salePrice);
        console.log('poSalePrice (raw):', poSalePrice);
        console.log('resolvedPurchasePrice:', resolvedPurchasePrice);
        console.log('resolvedSalePrice:', resolvedSalePrice);
        console.log('numericPurchaseBase:', numericPurchaseBase);
        
        // Set purchase price from PO
        if (resolvedPurchasePrice !== undefined && resolvedPurchasePrice !== null && resolvedPurchasePrice !== '') {
            const numericPurchasePrice = Number(resolvedPurchasePrice);
            if (!Number.isNaN(numericPurchasePrice) && numericPurchasePrice !== 0) {
                $('#purchasePriceInput').val(numericPurchasePrice.toFixed(2)).addClass('text-danger fw-semibold');
                currentBasePurchasePrice = numericPurchasePrice;
            } else {
                $('#purchasePriceInput').val('');
                currentBasePurchasePrice = 0;
            }
        } else {
            $('#purchasePriceInput').val('');
            currentBasePurchasePrice = 0;
        }
        
        // Load saved sale price
        if (resolvedSalePrice !== '' && resolvedSalePrice !== undefined && resolvedSalePrice !== null) {
            const numericSalePrice = Number(resolvedSalePrice);
            if (!Number.isNaN(numericSalePrice) && numericSalePrice !== 0) {
                currentSavedSalePrice = numericSalePrice;
                $('#salePriceInput').val(numericSalePrice.toFixed(2));
            } else {
                currentSavedSalePrice = 0;
                $('#salePriceInput').val('');
            }
        } else {
            currentSavedSalePrice = 0;
            $('#salePriceInput').val('');
        }
        
        $('#salePriceDisplay').text('ราคาขายจาก PO: ' + formatPrice(resolvedSalePrice) + ' บาท');
        
        // Update pricing from weight
        updatePricingFromWeight();
        updateNetProfitLabel();
        
        if (rowImageSrc) {
            $('#previewImg').attr('src', rowImageSrc);
            $('#imagePreview').show();
        } else {
            $('#previewImg').attr('src', '');
            $('#imagePreview').hide();
        }
        
        // Clear location fields
        $('#locationSearchEdit').val('');
        $('#editRowCodeInput').val('');
        $('#editBinInput').val('');
        $('#editShelfInput').val('');
        $('#editProductLocation').val('');
        $('#locationSuggestionsEdit').hide();

        if (tempId) {
            $.get('../api/receive_position_api.php', { temp_product_id: tempId }, function(resp){
                if (resp && resp.success) {
                    $('#editRowCodeInput').val(resp.row_code || '');
                    $('#editBinInput').val(resp.bin || '');
                    $('#editShelfInput').val(resp.shelf || '');
                    if (resp.location_id) {
                        $('#editProductLocation').val(resp.location_id);
                    }
                }
            }, 'json').fail(function(){
                console.log('Could not load temp product location data');
            });
        }
        
        // Change button text to indicate this is damaged item
        $('#editModalLabel').text('แก้ไขสินค้าชำรุด');
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    });
    
    // Handle approve damaged button click
    $(document).on('click', '.approve-damaged-btn', function() {
        const tempId = $(this).data('temp-id');
        const productName = $(this).data('name');
        
        // Get the row element to check SKU and Barcode values
        const $row = $(this).closest('tr');
        const $skuCell = $row.find('td:eq(2)'); // SKU column (3rd column)
        const $barcodeCell = $row.find('td:eq(3)'); // Barcode column (4th column)
        
        // Check if SKU and Barcode have the "ยังไม่มีข้อมูล" badge
        const hasNoSku = $skuCell.find('.badge.bg-warning').length > 0;
        const hasNoBarcode = $barcodeCell.find('.badge.bg-warning').length > 0;
        
        // If SKU or Barcode is missing, show warning
        if (hasNoSku || hasNoBarcode) {
            let missingFields = [];
            if (hasNoSku) missingFields.push('SKU');
            if (hasNoBarcode) missingFields.push('บาร์โค้ด');
            
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลยังไม่ครบถ้วน',
                html: 'กรุณากรอกข้อมูลของสินค้าชำรุดต่อไปนี้ก่อนอนุมัติ:<br><br>' +
                      '<strong style="color: #dc2626;">' + missingFields.join(' และ ') + '</strong><br><br>' +
                      'คลิก "แก้ไข" เพื่ออัปเดตข้อมูล',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#f97316'
            });
            return;
        }
        
        Swal.fire({
            title: 'ยืนยันการอนุมัติสินค้าชำรุด',
            text: 'ต้องการอนุมัติสินค้าชำรุด "' + productName + '" และนำเข้าคลังเก็บใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่ อนุมัติ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'กำลังประมวลผล',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '../api/approve_temp_product.php',
                    type: 'POST',
                    data: {
                        temp_product_id: tempId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const productId = response.product_id || response.new_product_id || '-';
                            Swal.fire({
                                icon: 'success',
                                title: 'อนุมัติสำเร็จ!',
                                text: 'สินค้าชำรุดถูกอนุมัติและนำเข้าคลังแล้ว\nรหัสสินค้า: ' + productId,
                                confirmButtonText: 'ตกลง'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    location.reload();
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: response.message || 'ไม่สามารถอนุมัติสินค้าได้'
                            });
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์';
                        let errorTitle = 'เชื่อมต่อล้มเหลว';
                        
                        try {
                            if (xhr.responseText) {
                                const errorResponse = JSON.parse(xhr.responseText);
                                if (errorResponse.message) {
                                    errorMessage = errorResponse.message;
                                    errorTitle = 'เกิดข้อผิดพลาด';
                                }
                            }
                        } catch (e) {
                            if (xhr.responseText) {
                                errorMessage = xhr.responseText.substring(0, 500);
                            }
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: errorTitle,
                            text: errorMessage,
                            confirmButtonText: 'ตกลง'
                        });
                        console.error(xhr);
                    }
                });
            }
        });
    });
});

function filterByType(type) {
    const params = new URLSearchParams();
    params.append('type', type);
    window.location.href = '?' + params.toString();
}

function updateStats() {
    // Count rows from receive table (ไม่นับแถว "ไม่มีข้อมูล")
    let receiveCount = 0;
    let damagedCount = 0;
    
    // Count receive items (ไม่นับแถวที่มี colspan)
    const receiveTableRows = $('#receive-table tbody tr');
    receiveTableRows.each(function() {
        if (!$(this).find('td[colspan]').length) {
            receiveCount++;
        }
    });
    
    // Count damaged items (ไม่นับแถวที่มี colspan)
    const damagedTableRows = $('#damaged-table tbody tr');
    damagedTableRows.each(function() {
        if (!$(this).find('td[colspan]').length) {
            damagedCount++;
        }
    });
    
    const totalCount = receiveCount + damagedCount;
    
    // Update stat displays
    $('#receive-count').text(receiveCount);
    $('#damaged-count').text(damagedCount);
    
    console.log('📊 Stats updated - Receive:', receiveCount, 'Damaged:', damagedCount);
}

// Location search data from AJAX (load on page load)
let locationsEditData = [];
$.ajax({
    url: '../api/get_locations_list.php',
    type: 'GET',
    dataType: 'json',
    success: function(response) {
        if (response.success && response.data) {
            locationsEditData = response.data;
            // Initialize location handlers after data is loaded
            initializeLocationHandlers();
        }
    },
    error: function() {
        console.log('Could not load locations data');
        // Still initialize handlers even if data load fails
        initializeLocationHandlers();
    }
});

// Initialize location handlers (called after AJAX or as fallback)
function initializeLocationHandlers() {
    const locationSearchEditInput = document.getElementById('locationSearchEdit');
    const locationSuggestionsEdit = document.getElementById('locationSuggestionsEdit');
    const editRowCodeInput = document.getElementById('editRowCodeInput');
    const editBinInput = document.getElementById('editBinInput');
    const editShelfInput = document.getElementById('editShelfInput');
    const editProductLocation = document.getElementById('editProductLocation');

    if (locationSearchEditInput && locationSuggestionsEdit) {
        locationSearchEditInput.addEventListener('input', function() {
            const searchText = this.value.toLowerCase().trim();
            
            if (searchText.length === 0) {
                locationSuggestionsEdit.style.display = 'none';
                return;
            }
            
            // Filter locations based on search text
            const filtered = locationsEditData.filter(loc => {
                const searchableText = `${loc.row_code} ${loc.bin} ${loc.shelf} ${loc.description || ''}`.toLowerCase();
                return searchableText.includes(searchText);
            }).slice(0, 15); // Limit to 15 suggestions
            
            if (filtered.length === 0) {
                locationSuggestionsEdit.style.display = 'none';
                return;
            }
            
            // Display suggestions
            locationSuggestionsEdit.innerHTML = filtered.map(loc => `
                <div style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; cursor: pointer; transition: background-color 0.2s;" 
                     onmouseover="this.style.backgroundColor='#f3f4f6'" 
                     onmouseout="this.style.backgroundColor='white'"
                     onclick="selectLocationEdit(${loc.location_id}, '${loc.row_code}', ${loc.bin}, ${loc.shelf})">
                    <div style="font-weight: 600; color: #1f2937;">
                        <span class="badge bg-primary" style="margin-right: 0.5rem;">แถว: ${loc.row_code}</span>
                        <span class="badge bg-info" style="margin-right: 0.5rem;">ล็อค: ${loc.bin}</span>
                        <span class="badge bg-success">ชั้น: ${loc.shelf}</span>
                    </div>
                    <small style="color: #6b7280; display: block; margin-top: 0.25rem;">${loc.description || ''}</small>
                </div>
            `).join('');
            locationSuggestionsEdit.style.display = 'block';
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== locationSearchEditInput && !locationSearchEditInput.contains(e.target) && !locationSuggestionsEdit.contains(e.target)) {
                locationSuggestionsEdit.style.display = 'none';
            }
        });
    }
}

// Select location from suggestions for edit modal
function selectLocationEdit(locationId, rowCode, bin, shelf) {
    const editProductLocation = document.getElementById('editProductLocation');
    const editRowCodeInput = document.getElementById('editRowCodeInput');
    const editBinInput = document.getElementById('editBinInput');
    const editShelfInput = document.getElementById('editShelfInput');
    const locationSearchEditInput = document.getElementById('locationSearchEdit');
    const locationSuggestionsEdit = document.getElementById('locationSuggestionsEdit');
    
    if (editProductLocation && editRowCodeInput && editBinInput && editShelfInput && locationSearchEditInput) {
        editProductLocation.value = locationId;
        editRowCodeInput.value = rowCode;
        editBinInput.value = bin;
        editShelfInput.value = shelf;
        locationSearchEditInput.value = `${rowCode} ${bin} ${shelf}`;
        locationSuggestionsEdit.style.display = 'none';
    }
}
</script>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">แก้ไขข้อมูลสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="tempProductId" name="temp_product_id">
                    <input type="hidden" id="receiveId" name="receive_id">

                    <div class="mb-3">
                        <div id="productNameDisplay" class="fw-bold" style="font-size: 1.05rem;"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="productImageInput" class="form-label">รูปภาพสินค้า</label>
                        <input type="file" class="form-control" id="productImageInput" name="product_image" accept="image/*">
                        <div id="imagePreview" class="mt-2" style="display: none;">
                            <img id="previewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border: 2px solid #e5e7eb; border-radius: 8px;">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="provisionalSkuInput" class="form-label">SKU สำรอง</label>
                        <input type="text" class="form-control" id="provisionalSkuInput" name="provisional_sku" placeholder="ใส่ SKU สำรอง">
                    </div>
                    
                    <div class="mb-3">
                        <label for="provisionalBarcodeInput" class="form-label">บาร์โค้ดสำรอง</label>
                        <input type="text" class="form-control" id="provisionalBarcodeInput" name="provisional_barcode" placeholder="ใส่บาร์โค้ดสำรอง">
                    </div>

                    <div class="mb-3">
                        <label for="unitInput" class="form-label">หน่วยนับ</label>
                        <input type="text" class="form-control" id="unitInput" name="unit" placeholder="เช่น ชิ้น, กล่อง, แพ็ค">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="categorySelect" class="form-label">หมวดหมู่สินค้า</label>
                            <select class="form-select" id="categorySelect" name="product_category">
                                <option value="">-- เลือกหมวดหมู่ --</option>
                            </select>
                            <small class="text-muted">เลือกหมวดหมู่สินค้าที่ต้องการ</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จำนวน</label>
                            <div class="form-control-plaintext" id="quantityDisplay">-</div>
                        </div>
                    </div>
                    
                    <div class="mb-3 p-3" style="background: #dae9f8; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="purchasePriceInput" class="form-label">ราคาซื้อ</label>
                            <input type="number" class="form-control text-danger fw-semibold" id="purchasePriceInput" name="purchase_price" placeholder="0.00" min="0" step="0.01" inputmode="decimal" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="weightInput" class="form-label">น้ำหนัก (กิโลกรัม)</label>
                            <input type="number" class="form-control" id="weightInput" name="weight" placeholder="เช่น 1.00" min="0" step="0.01" inputmode="decimal">
                            <!-- <small class="text-muted">ระบบจะคิด 850 บาท/กก. (85 บาท/ขีด) แล้วบวกราคาซื้อจากใบ PO เข้ากับต้นทุนเพื่อตั้งราคาขายอัตโนมัติ</small> -->
                        </div>
                        </div>

                        <div class="mb-3">
                            <label for="trueCostInput" class="form-label">ราคาต้นทุน (PO + น้ำหนัก)</label>
                            <input type="text" class="form-control" id="trueCostInput" placeholder="0.00" readonly>
                            <small class="text-muted">คำนวณจาก ราคาซื้อ PO + (น้ำหนัก × 850 บาท/กก. หรือ 85 บาท/ขีด)</small>
                        </div>

                        <div class="mb-3">
                            <label for="salePriceInput" class="form-label">ราคาขาย (บาท)</label>
                            <input type="number" class="form-control" id="salePriceInput" name="sale_price" placeholder="0.00" min="0" step="0.01" inputmode="decimal">
                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <span class="badge bg-danger-subtle text-danger fw-semibold" style="letter-spacing: 0.2px;">กำไรสุทธิ & %กำไรสุทธิ</span>
                                <span id="netProfitDisplay" class="fw-bold text-danger">-</span>
                            </div>
                            <small class="text-muted">สูตร: ค่าธรรมเนียม15% + ค่าใช้จ่าย17% + ค่าคอมมิชชั่น5% + ภาษีนิติบุคคล20%</small>
                        </div>
                       
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiryInput" class="form-label">วันหมดอายุ</label>
                        <input type="date" class="form-control" id="expiryInput" name="expiry_date">
                    </div>

                    <div class="mb-3">
                        <label for="remarkInput" class="form-label">หมายเหตุการบันทึก</label>
                        <textarea class="form-control" id="remarkInput" name="remark" rows="3" placeholder="ระบุรายละเอียดน้ำหนักและขนาด (กว้าง x ยาว x สูง)"></textarea>
                        <small class="text-muted">กรุณาบันทึกน้ำหนักสินค้า และขนาด (กว้าง x ยาว x สูง) เพื่อใช้ในการขึ้นระบบหลัก</small>
                    </div>
                    
                    <!-- ส่วนตำแหน่งเก็บสินค้า -->
                    <div class="form-group-custom">
                        <label>ตำแหน่งที่จัดเก็บสินค้า</label>
                        <div style="margin-bottom: 1rem; position: relative;">
                            <input 
                                type="text" 
                                id="locationSearchEdit" 
                                placeholder="ค้นหา หรือ พิมพ์ แถว/ล็อค/ชั้น (เช่น A 2 3)" 
                                style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: 'Prompt', sans-serif; font-size: 0.95rem;"
                                autocomplete="off"
                            >
                            <div id="locationSuggestionsEdit" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 250px; overflow-y: auto; background: white; border: 1px solid #d1d5db; border-top: 1px solid #d1d5db; border-radius: 0 0 6px 6px; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" class="location-suggestions"></div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; background: linear-gradient(135deg, #f0f9ff 0%, #f0fdf4 100%); padding: 1.25rem; border-radius: 10px; border: 2px solid #3b82f6; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);">
                            <div style="background: white; padding: 0.875rem; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s ease;">
                                <label style="font-size: 0.8rem; color: #1e40af; display: block; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <span class="material-icons" style="font-size: 0.95rem; vertical-align: middle; margin-right: 0.25rem;">straight</span>
                                    แถว
                                </label>
                                <input type="text" id="editRowCodeInput" name="row_code" placeholder="—" readonly style="font-size: 1.1rem; font-weight: 700; background: transparent; border: none; color: #1f2937; cursor: default; text-align: center; width: 100%;">
                            </div>
                            <div style="background: white; padding: 0.875rem; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s ease;">
                                <label style="font-size: 0.8rem; color: #0891b2; display: block; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <span class="material-icons" style="font-size: 0.95rem; vertical-align: middle; margin-right: 0.25rem;">inbox</span>
                                    ล็อค
                                </label>
                                <input type="text" id="editBinInput" name="bin" placeholder="—" readonly style="font-size: 1.1rem; font-weight: 700; background: transparent; border: none; color: #1f2937; cursor: default; text-align: center; width: 100%;">
                            </div>
                            <div style="background: white; padding: 0.875rem; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s ease;">
                                <label style="font-size: 0.8rem; color: #059669; display: block; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <span class="material-icons" style="font-size: 0.95rem; vertical-align: middle; margin-right: 0.25rem;">layers</span>
                                    ชั้น
                                </label>
                                <input type="text" id="editShelfInput" name="shelf" placeholder="—" readonly style="font-size: 1.1rem; font-weight: 700; background: transparent; border: none; color: #1f2937; cursor: default; text-align: center; width: 100%;">
                            </div>
                        </div>
                        <input type="hidden" id="editProductLocation" name="location_id">
                        <small style="color: #6b7280; display: block; margin-top: 0.5rem;">🔍 พิมพ์เพื่อค้นหาตำแหน่ง หรือเลือกจากรายการแนะนำ</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="saveEditBtn">บันทึก</button>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">ดูรายละเอียดสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div id="viewProductNameDisplay" class="fw-bold" style="font-size: 1.05rem;"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">รูปภาพสินค้า</label>
                    <div>
                        <img id="viewProductImagePreview" src="" alt="Product" style="max-width: 100%; max-height: 250px; border: 2px solid #e5e7eb; border-radius: 8px;">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">SKU สำรอง</label>
                    <div class="form-control-plaintext"><code id="viewProvisionalSkuDisplay">-</code></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">บาร์โค้ดสำรอง</label>
                    <div class="form-control-plaintext"><code id="viewProvisionalBarcodeDisplay">-</code></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">หน่วยนับ</label>
                    <div class="form-control-plaintext" id="viewUnitDisplay">-</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">จำนวน</label>
                    <div class="form-control-plaintext" id="viewQuantityDisplay">-</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">หมวดหมู่</label>
                    <div class="form-control-plaintext" id="viewCategoryDisplay">-</div>
                </div>
                
                <div class="mb-3 p-3" style="background: #dae9f8; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">ราคาซื้อ</label>
                            <div class="form-control-plaintext text-danger fw-semibold" id="viewPurchasePriceDisplay">-</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">น้ำหนัก (กิโลกรัม)</label>
                            <div class="form-control-plaintext" id="viewWeightDisplay">-</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ราคาต้นทุน (PO + น้ำหนัก)</label>
                        <div class="form-control-plaintext" id="viewTrueCostDisplay">-</div>
                        <small class="text-muted">คำนวณจาก ราคาซื้อ PO + (น้ำหนัก × 850 บาท/กก. หรือ 85 บาท/ขีด)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ราคาขาย (บาท)</label>
                        <div class="form-control-plaintext" id="viewSalePriceDisplay">-</div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <span class="badge bg-danger-subtle text-danger fw-semibold">กำไรสุทธิ (หัก 20% × 3)</span>
                            <span id="viewNetProfitDisplay" class="fw-bold text-danger">-</span>
                        </div>
                        <small class="text-muted">สูตร: ราคาขาย × 0.8 × 0.8 × 0.8</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">วันหมดอายุ</label>
                    <div class="form-control-plaintext" id="viewExpiryDisplay">-</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">หมายเหตุการบันทึก</label>
                    <div class="form-control-plaintext" id="viewRemarkDisplay" style="white-space: pre-wrap;">-</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
