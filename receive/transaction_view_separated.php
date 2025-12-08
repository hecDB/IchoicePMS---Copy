<?php
session_start();
require '../config/db_connect.php';

// ตัวแปรสำหรับกรอง
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'new'; // 'all', 'existing', 'new'
$filter_transaction = isset($_GET['transaction']) ? $_GET['transaction'] : 'all'; // 'all', 'receive', 'issue'

// สร้าง Query สำหรับสินค้าซื้อใหม่ (temp_product_id > 0)
// ดึงข้อมูลทั้ง temp_products และ transaction details ที่แยกกัน
$sql = "
(SELECT 
    'receive' as transaction_type,
    r.receive_id as transaction_id, 
    tp.product_image as image,
    u.name AS created_by, 
    r.created_at, 
    r.receive_qty as quantity, 
    r.expiry_date,
    tp.product_name AS product_name,
    tp.product_category,
    tp.temp_product_id,
    tp.provisional_sku,
    tp.provisional_barcode,
    tp.status as temp_product_status
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
LEFT JOIN users u ON r.created_by = u.user_id
WHERE COALESCE(poi.temp_product_id, 0) > 0)

UNION ALL

(SELECT 
    'issue' as transaction_type,
    ii.issue_id as transaction_id,
    tp.product_image as image,
    u.name AS created_by,
    ii.created_at,
    ii.issue_qty as quantity,
    ri.expiry_date,
    tp.product_name AS product_name,
    tp.product_category,
    tp.temp_product_id,
    tp.provisional_sku,
    tp.provisional_barcode,
    tp.status as temp_product_status
FROM issue_items ii
LEFT JOIN receive_items ri ON ii.receive_id = ri.receive_id
LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
LEFT JOIN users u ON ii.issued_by = u.user_id
WHERE COALESCE(poi.temp_product_id, 0) > 0)
ORDER BY created_at DESC LIMIT 500";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("Transaction View Query Debug:");
    error_log("- SQL: " . substr($sql, 0, 200) . "...");
    error_log("- Rows returned: " . count($rows));
    
    // Debug: แสดงข้อมูลละเอียด
    error_log("DEBUG - Row Details:");
    foreach($rows as $idx => $row) {
        error_log($idx . ": temp_id=" . $row['temp_product_id'] . 
                  " | name=" . $row['product_name'] . 
                  " | sku=" . $row['provisional_sku'] . 
                  " | barcode=" . $row['provisional_barcode'] . 
                  " | type=" . $row['transaction_type'] . 
                  " | tid=" . $row['transaction_id']);
    }
    
} catch (Exception $e) {
    $rows = [];
    $error_msg = $e->getMessage();
    error_log("Query Error in transaction_view_separated.php: " . $error_msg);
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

        <!-- Main Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการความเคลื่อนไหว (<span id="row-count"><?= count($rows) ?></span> รายการ)
                    </h5>
                    <div class="table-actions d-flex align-items-center">
                        <div class="search-box me-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <span class="material-icons" style="font-size: 1.25rem; color: #6b7280;">search</span>
                                </span>
                                <input type="text" class="form-control" id="custom-search" placeholder="ค้นหา...">
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
                    <table id="transaction-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th class="text-center no-sort">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU สำรอง</th>
                            <th>บาร์โค้ดสำรอง</th>
                            <th>หมวดหมู่</th>
                            <th class="no-sort">ประเภท</th>
                            <th>ผู้ทำรายการ</th>
                            <th class="text-center">วันที่</th>
                            <th class="text-center no-sort">จำนวน</th>
                            <th class="text-center no-sort">วันหมดอายุ</th>
                            <th class="text-center no-sort">สถานะ</th>
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="12" class="text-center py-4">
                            <div class="text-muted mb-2">
                                <span class="material-icons" style="font-size: 3rem; opacity: 0.5;">inbox</span>
                            </div>
                            <p class="text-muted">ไม่มีข้อมูลรายการ</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($rows as $row): ?>
                        <tr data-id="<?= $row['transaction_id'] ?>" 
                            class="<?= $row['transaction_type'] === 'issue' ? 'table-danger' : '' ?>"
                            data-type="new">
                            <td class="text-center">
                                <?php
                                $img_path = !empty($row['image']) ? '../images/' . $row['image'] : '../images/noimg.png';
                                if (!file_exists($img_path)) $img_path = '../images/noimg.png';
                                ?>
                                <img src="<?= htmlspecialchars($img_path) ?>" class="product-image" alt="<?= htmlspecialchars($row['product_name']) ?>" title="<?= htmlspecialchars($row['product_name']) ?>">
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
                            <td class="text-center">
                                <?php if ($row['transaction_type'] === 'receive'): ?>
                                    <span class="badge bg-success">
                                        <span class="material-icons" style="font-size: 0.9rem; vertical-align: middle;">add_box</span>
                                        รับเข้า
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <span class="material-icons" style="font-size: 0.9rem; vertical-align: middle;">remove_circle</span>
                                        ออกไป
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['created_by'] ?? '-') ?></td>
                            <td class="text-center">
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
                                    <button class="btn btn-outline-primary edit-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-receive-id="<?= $row['transaction_id'] ?>" data-expiry="<?= $row['expiry_date'] ?? '' ?>" data-provisional-sku="<?= htmlspecialchars($row['provisional_sku'] ?? '') ?>" data-provisional-barcode="<?= htmlspecialchars($row['provisional_barcode'] ?? '') ?>" title="แก้ไข SKU/Barcode/รูปภาพ" <?php if ($status === 'converted'): ?>disabled<?php endif; ?>>
                                        <span class="material-icons" style="font-size: 1rem;">edit</span>
                                    </button>
                                    <button class="btn btn-outline-success approve-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-name="<?= htmlspecialchars($row['product_name']) ?>" title="อนุมัติและย้ายไปคลังปกติ" <?php if ($status === 'converted'): ?>disabled<?php endif; ?>>
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

$(document).ready(function() {
    // Debug Info
    console.log('Page loaded');
    console.log('Total rows from PHP:', <?= count($rows) ?>);
    console.log('Rows data:', <?= json_encode($rows) ?>);
    
    // Show debug info
    if (<?= count($rows) ?> === 0) {
        $('#debug-alert').show();
        $('#debug-content').html('<p>❌ No data returned from Query</p>');
    }
    
    // Initialize DataTable
    transactionTable = $('#transaction-table').DataTable({
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
                targets: [1, 2, 3, 4, 6, 7]  // Sortable columns
            },
            { className: "text-center", targets: [0, 8, 9, 10] }  // Center: Image, Qty, Expiry, Actions
        ],
        order: [[7, 'desc']], // Sort by date (column 7)
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

    // Custom search
    $('#custom-search').on('keyup', function() {
        transactionTable.search(this.value).draw();
    });

    // Hide default DataTable search
    setTimeout(function() {
        $('.dataTables_filter').hide();
    }, 100);
    
    // Handle edit button click
    $(document).on('click', '.edit-btn', function() {
        const tempId = $(this).data('temp-id');
        const receiveId = $(this).data('receive-id');
        const expiry = $(this).data('expiry');
        const provisionalSku = $(this).data('provisional-sku');
        const provisionalBarcode = $(this).data('provisional-barcode');
        
        $('#tempProductId').val(tempId);
        $('#receiveId').val(receiveId);
        $('#expiryInput').val(expiry);
        $('#provisionalSkuInput').val(provisionalSku);
        $('#provisionalBarcodeInput').val(provisionalBarcode);
        
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
        const rowCode = $('#editRowCodeInput').val().trim();
        const bin = $('#editBinInput').val().trim();
        const shelf = $('#editShelfInput').val().trim();
        const imageFile = $('#productImageInput')[0].files[0];
        
        if (!tempId) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'ข้อมูลไม่ถูกต้อง'
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
            compressAndSendImage(imageFile, tempId, expiryDate, provisionalSku, provisionalBarcode, rowCode, bin, shelf);
        } else {
            sendFormData(null, tempId, expiryDate, provisionalSku, provisionalBarcode, rowCode, bin, shelf);
        }
    });
    
    // Compress image and send
    function compressAndSendImage(file, tempId, expiryDate, provisionalSku, provisionalBarcode, rowCode, bin, shelf) {
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
                    
                    sendFormData(compressedFile, tempId, expiryDate, provisionalSku, provisionalBarcode, rowCode, bin, shelf);
                }, 'image/jpeg', 0.85);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
    // Send form data to server
    function sendFormData(imageFile, tempId, expiryDate, provisionalSku, provisionalBarcode, rowCode, bin, shelf) {
        const formData = new FormData();
        formData.append('temp_product_id', tempId);
        formData.append('expiry_date', expiryDate);
        formData.append('provisional_sku', provisionalSku);
        formData.append('provisional_barcode', provisionalBarcode);
        formData.append('row_code', rowCode);
        formData.append('bin', bin);
        formData.append('shelf', shelf);
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
                if (xhr.status === 0) {
                    errorMessage = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
                } else if (xhr.status === 400) {
                    errorMessage = 'ข้อมูลไม่ถูกต้อง (400)';
                } else if (xhr.status === 500) {
                    errorMessage = 'เซิร์ฟเวอร์เกิดข้อผิดพลาด (500)';
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage += '\n' + errorResponse.message;
                        }
                    } catch (e) {
                        // ถ้า parse ล้มเหลว ให้ใช้ response text แทน
                        if (xhr.responseText) {
                            errorMessage += '\n' + xhr.responseText.substring(0, 200);
                        }
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'เชื่อมต่อล้มเหลว',
                    text: errorMessage
                });
            }
        });
    }
    
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
                            Swal.fire({
                                icon: 'success',
                                title: 'อนุมัติสำเร็จ!',
                                text: 'สินค้าถูกย้ายไปยังคลังปกติแล้ว\nรหัสสินค้า: ' + response.product_id,
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
                        Swal.fire({
                            icon: 'error',
                            title: 'เชื่อมต่อล้มเหลว',
                            text: 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์'
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
    if (!transactionTable) return;
    
    const table = transactionTable.rows({ filter: 'applied' }).nodes();
    let newCount = 0;
    let receiveCount = 0;
    let issueCount = 0;
    
    $(table).each(function() {
        const type = $(this).data('type');
        // หาคอลัมน์ประเภท (column 5)
        const transactionBadge = $(this).find('td:eq(5) span.badge').first().text().trim();
        
        if (type === 'new') {
            newCount++;
            if (transactionBadge.includes('รับเข้า')) {
                receiveCount++;
            } else if (transactionBadge.includes('ออกไป')) {
                issueCount++;
            }
        }
    });
    
    $('#new-count').text(newCount);
    $('#receive-count').text('+' + receiveCount);
    $('#issue-count').text('-' + issueCount);
    $('#row-count').text(newCount);
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
                        <label for="expiryInput" class="form-label">วันหมดอายุ</label>
                        <input type="date" class="form-control" id="expiryInput" name="expiry_date">
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

</body>
</html>
