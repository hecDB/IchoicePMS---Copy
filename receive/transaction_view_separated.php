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
    tp.provisional_barcode
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
    tp.provisional_barcode
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

    @media (max-width: 768px) {
        .product-image { width: 28px; height: 28px; }
    }
    @media (max-width: 480px) {
        .product-image { width: 20px; height: 20px; }
    }

    /* Table responsive adjustments */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    #transaction-table {
        min-width: 2400px;
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
                            <th class="text-center no-sort">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4">
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
                                <button class="btn btn-sm btn-outline-primary edit-btn" data-temp-id="<?= $row['temp_product_id'] ?>" data-receive-id="<?= $row['transaction_id'] ?>" data-expiry="<?= $row['expiry_date'] ?? '' ?>" data-provisional-sku="<?= htmlspecialchars($row['provisional_sku'] ?? '') ?>" data-provisional-barcode="<?= htmlspecialchars($row['provisional_barcode'] ?? '') ?>">
                                    <span class="material-icons" style="font-size: 1rem;">edit</span>
                                </button>
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
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    });
    
    // Handle save button click
    $('#saveEditBtn').on('click', function() {
        const tempId = $('#tempProductId').val();
        const expiryDate = $('#expiryInput').val();
        const provisionalSku = $('#provisionalSkuInput').val();
        const provisionalBarcode = $('#provisionalBarcodeInput').val();
        
        if (!tempId) {
            alert('ข้อมูลไม่ถูกต้อง');
            return;
        }
        
        $.ajax({
            url: '../api/update_temp_product.php',
            type: 'POST',
            data: {
                temp_product_id: tempId,
                expiry_date: expiryDate,
                provisional_sku: provisionalSku,
                provisional_barcode: provisionalBarcode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('บันทึกสำเร็จ');
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (response.message || 'ไม่ทราบสาเหตุ'));
                }
            },
            error: function(xhr) {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                console.error(xhr);
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
