<?php
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

// Get Purchase Orders that are ready for receiving
$sql_pos = "
    SELECT 
        po.po_id,
        po.po_number,
        s.name as supplier_name,
        po.order_date as po_date,
        NULL as expected_delivery_date,
        po.total_amount,
        c.code as currency_code,
        po.remark,
        po.status,
        COUNT(poi.item_id) as total_items,
        COALESCE(SUM(CASE WHEN ri.item_id IS NOT NULL THEN 1 ELSE 0 END), 0) as received_items
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN currencies c ON po.currency_id = c.currency_id
    LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
    LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
    WHERE po.status IN ('pending', 'partial')
    GROUP BY po.po_id, po.po_number, s.name, po.order_date, po.total_amount, c.code, po.remark, po.status
    ORDER BY po.order_date DESC, po.po_number DESC
";

$stmt = $pdo->query($sql_pos);
$purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_pos = count($purchase_orders);
$ready_to_receive = count(array_filter($purchase_orders, function($po) {
    return $po['received_items'] == 0;
}));
$partially_received = count(array_filter($purchase_orders, function($po) {
    return $po['received_items'] > 0 && $po['received_items'] < $po['total_items'];
}));
$fully_received = count(array_filter($purchase_orders, function($po) {
    return $po['received_items'] > 0 && $po['received_items'] >= $po['total_items'];
}));

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รับเข้าสินค้า - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link href="../assets/modern-table.css" rel="stylesheet">
    <link href="../assets/mainwrap-modern.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8fafc;
        }

        .po-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .po-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .po-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px 12px 0 0;
        }

        .po-status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
        }

        .status-partially-received {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #a16207;
        }

        .status-received {
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%);
            color: #0c4a6e;
        }

        .progress-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .progress-0 {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
        }

        .progress-partial {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }

        .progress-complete {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: white;
        }

        .receive-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .receive-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .receive-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Modal Styles */
        .modal-xl {
            max-width: 95%;
        }

        #poItemsTable {
            font-size: 0.875rem;
        }

        #poItemsTable th {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 0.75rem 0.5rem;
            vertical-align: middle;
        }

        #poItemsTable td {
            padding: 0.75rem 0.5rem;
            vertical-align: middle;
        }

        .receive-qty-input {
            width: 80px;
        }

        .quick-receive-btn {
            padding: 0.25rem 0.5rem;
        }

        .badge {
            font-size: 0.75rem;
        }

        .table-responsive {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Quick Receive Modal */
        #quickReceiveModal .modal-content {
            border-radius: 12px;
        }

        #quickReceiveModal .modal-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        #quickReceiveModal .btn-close {
            filter: invert(1);
        }

        /* Loading and Empty States */
        .spinner-border {
            width: 2rem;
            height: 2rem;
        }

        .empty-state {
            color: #6b7280;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-xl {
                max-width: 95%;
                margin: 1rem auto;
            }
            
            #poItemsTable {
                font-size: 0.75rem;
            }
            
            #poItemsTable th,
            #poItemsTable td {
                padding: 0.5rem 0.25rem;
            }
            
            .receive-qty-input {
                width: 60px;
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
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">input</span>
                    รับเข้าสินค้า
                </h1>
                <p class="text-muted mb-0">รับสินค้าเข้าคลังจากใบสั่งซื้อ (Purchase Order)</p>
            </div>
            <div class="d-flex gap-2">
                <a href="quick_receive.php" class="btn btn-outline-primary">
                    <span class="material-icons me-1">qr_code_scanner</span>
                    รับสินค้าด่วน (Scan)
                </a>
                <button class="btn btn-outline-secondary" onclick="location.reload()">
                    <span class="material-icons me-1">refresh</span>
                    รีเฟรช
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-primary">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">ใบสั่งซื้อทั้งหมด</div>
                                <div class="stats-value"><?= number_format($total_pos) ?></div>
                                <div class="stats-subtitle">รอดำเนินการ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">receipt</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-success">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">พร้อมรับสินค้า</div>
                                <div class="stats-value"><?= number_format($ready_to_receive) ?></div>
                                <div class="stats-subtitle">ยังไม่ได้รับ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">check_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-warning">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รับบางส่วน</div>
                                <div class="stats-value"><?= number_format($partially_received) ?></div>
                                <div class="stats-subtitle">ยังไม่ครบ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">pending</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-info">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รับครบแล้ว</div>
                                <div class="stats-value"><?= number_format($fully_received) ?></div>
                                <div class="stats-subtitle">เสร็จสิ้น</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">done_all</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการใบสั่งซื้อ (<?= $total_pos ?> ใบ)
                    </h5>
                    <div class="table-actions">
                        <button class="btn-modern btn-modern-secondary btn-sm refresh-table me-2" onclick="location.reload()">
                            <span class="material-icons">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <?php if (empty($purchase_orders)): ?>
                <div class="text-center py-5">
                    <span class="material-icons mb-3" style="font-size: 4rem; color: #d1d5db;">receipt_long</span>
                    <h5 class="text-muted">ไม่พบใบสั่งซื้อที่พร้อมรับสินค้า</h5>
                    <p class="text-muted mb-0">กรุณาสร้างใบสั่งซื้อใหม่หรือตรวจสอบสถานะใบสั่งซื้อ</p>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($purchase_orders as $po): ?>
                    <?php
                    $completion_rate = $po['total_items'] > 0 ? ($po['received_items'] / $po['total_items']) * 100 : 0;
                    $status_class = '';
                    $status_text = '';
                    $progress_class = '';
                    
                    if ($completion_rate == 0) {
                        $status_class = 'status-approved';
                        $status_text = 'พร้อมรับสินค้า';
                        $progress_class = 'progress-0';
                    } elseif ($completion_rate < 100) {
                        $status_class = 'status-partially-received';
                        $status_text = 'รับบางส่วน';
                        $progress_class = 'progress-partial';
                    } else {
                        $status_class = 'status-received';
                        $status_text = 'รับครบแล้ว';
                        $progress_class = 'progress-complete';
                    }
                    ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="po-card">
                            <div class="po-card-header">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($po['po_number']) ?></h6>
                                        <p class="text-muted mb-0 small"><?= htmlspecialchars($po['supplier_name']) ?></p>
                                    </div>
                                    <span class="po-status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small text-muted">วันที่สั่งซื้อ</div>
                                        <div class="fw-semibold"><?= date('d/m/Y', strtotime($po['po_date'])) ?></div>
                                    </div>
                                    <div class="progress-circle <?= $progress_class ?>">
                                        <?= round($completion_rate) ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="small text-muted">จำนวนรายการ</div>
                                        <div class="fw-bold"><?= $po['total_items'] ?> รายการ</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted">รับแล้ว</div>
                                        <div class="fw-bold text-success"><?= $po['received_items'] ?> รายการ</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="small text-muted">มูลค่ารวม</div>
                                    <div class="fw-bold text-primary"><?= number_format($po['total_amount'], 2) ?> <?= htmlspecialchars($po['currency_code']) ?></div>
                                </div>
                                
                                <?php if ($po['expected_delivery_date']): ?>
                                <div class="mb-3">
                                    <div class="small text-muted">กำหนดส่ง</div>
                                    <div class="fw-semibold"><?= date('d/m/Y', strtotime($po['expected_delivery_date'])) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" 
                                            class="receive-btn flex-fill receive-po-btn"
                                            data-po-id="<?= $po['po_id'] ?>"
                                            data-po-number="<?= htmlspecialchars($po['po_number']) ?>"
                                            data-supplier="<?= htmlspecialchars($po['supplier_name']) ?>">
                                        <span class="material-icons" style="font-size: 1.1rem;">input</span>
                                        รับสินค้า
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-sm view-po-btn"
                                            data-po-id="<?= $po['po_id'] ?>"
                                            data-po-number="<?= htmlspecialchars($po['po_number']) ?>"
                                            data-supplier="<?= htmlspecialchars($po['supplier_name']) ?>">
                                        <span class="material-icons" style="font-size: 1rem;">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Order Items Modal -->
<div class="modal fade" id="poItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">inventory_2</span>
                    รายการสินค้า - <span id="modalPoNumber"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>ผู้จำหน่าย:</strong> <span id="modalSupplier"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>สถานะ:</strong> <span id="modalStatus" class="badge"></span>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped" id="poItemsTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">#</th>
                                <th width="30%">สินค้า</th>
                                <th width="10%">SKU</th>
                                <th width="8%">หน่วย</th>
                                <th width="10%">จำนวนสั่ง</th>
                                <th width="10%">ราคา/หน่วย</th>
                                <th width="10%">รับแล้ว</th>
                                <th width="10%">คงเหลือ</th>
                                <th width="12%">รับเข้า</th>
                            </tr>
                        </thead>
                        <tbody id="poItemsTableBody">
                            <!-- Items will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button type="button" class="btn btn-success" id="saveReceiveBtn" style="display: none;">
                    <span class="material-icons me-1">save</span>
                    บันทึกการรับเข้า
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Receive Modal -->
<div class="modal fade" id="quickReceiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">speed</span>
                    รับเข้าสินค้าด่วน
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickReceiveForm">
                    <input type="hidden" id="quickItemId" name="item_id">
                    <input type="hidden" id="quickPoId" name="po_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">สินค้า</label>
                        <div id="quickProductName" class="form-control-plaintext fw-bold text-primary"></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">จำนวนสั่ง</label>
                            <div id="quickOrderedQty" class="form-control-plaintext"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">คงเหลือรับได้</label>
                            <div id="quickRemainingQty" class="form-control-plaintext text-warning fw-bold"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quickReceiveQty" class="form-label fw-bold">จำนวนที่รับเข้า *</label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   id="quickReceiveQty" 
                                   name="quantity"
                                   min="0.01" 
                                   step="0.01" 
                                   required>
                            <span class="input-group-text" id="quickUnit"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quickNotes" class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="quickNotes" name="notes" rows="2" placeholder="หมายเหตุการรับเข้าสินค้า..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success" id="confirmQuickReceive">
                    <span class="material-icons me-1">check</span>
                    ยืนยันรับเข้า
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/modern-table.js"></script>

<script>
$(document).ready(function() {
    let currentPoData = {};
    let receiveItems = {};
    
    // Add hover effects and animations
    $('.po-card').hover(
        function() {
            $(this).find('.receive-btn').addClass('shadow-lg');
        },
        function() {
            $(this).find('.receive-btn').removeClass('shadow-lg');
        }
    );
    
    // Receive PO button click
    $('.receive-po-btn').on('click', function() {
        const poId = $(this).data('po-id');
        const poNumber = $(this).data('po-number');
        const supplier = $(this).data('supplier');
        
        loadPoItems(poId, poNumber, supplier, 'receive');
    });
    
    // View PO button click
    $('.view-po-btn').on('click', function() {
        const poId = $(this).data('po-id');
        const poNumber = $(this).data('po-number');
        const supplier = $(this).data('supplier');
        
        loadPoItems(poId, poNumber, supplier, 'view');
    });
    
    // Load PO items
    function loadPoItems(poId, poNumber, supplier, mode) {
        currentPoData = { poId, poNumber, supplier, mode };
        
        $('#modalPoNumber').text(poNumber);
        $('#modalSupplier').text(supplier);
        
        // Show loading
        $('#poItemsTableBody').html(`
            <tr>
                <td colspan="9" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <div class="mt-2">กำลังโหลดข้อมูล...</div>
                </td>
            </tr>
        `);
        
        $('#poItemsModal').modal('show');
        
        // Load data via AJAX
        $.ajax({
            url: 'get_po_items.php',
            method: 'GET',
            data: { po_id: poId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayPoItems(response.data, mode);
                } else {
                    $('#poItemsTableBody').html(`
                        <tr>
                            <td colspan="9" class="text-center py-4 text-danger">
                                <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                                <div>${response.message}</div>
                            </td>
                        </tr>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading PO items:', error);
                $('#poItemsTableBody').html(`
                    <tr>
                        <td colspan="9" class="text-center py-4 text-danger">
                            <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                            <div>เกิดข้อผิดพลาดในการโหลดข้อมูล</div>
                        </td>
                    </tr>
                `);
            }
        });
    }
    
    // Display PO items in table
    function displayPoItems(items, mode) {
        let html = '';
        receiveItems = {};
        
        if (items.length === 0) {
            html = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <span class="material-icons mb-2" style="font-size: 2rem;">inbox</span>
                        <div>ไม่พบรายการสินค้าในใบสั่งซื้อนี้</div>
                    </td>
                </tr>
            `;
        } else {
            items.forEach(function(item, index) {
                const remainingQty = parseFloat(item.ordered_qty) - parseFloat(item.received_qty || 0);
                const canReceive = remainingQty > 0;
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <div class="fw-bold">${escapeHtml(item.product_name)}</div>
                            ${item.barcode ? `<small class="text-muted">Barcode: ${escapeHtml(item.barcode)}</small>` : ''}
                        </td>
                        <td><span class="badge bg-secondary">${escapeHtml(item.sku)}</span></td>
                        <td>${escapeHtml(item.unit)}</td>
                        <td class="fw-bold text-info">${parseFloat(item.ordered_qty).toLocaleString()}</td>
                        <td>${parseFloat(item.unit_cost).toLocaleString()} ${escapeHtml(item.currency_code)}</td>
                        <td class="fw-bold text-success">${parseFloat(item.received_qty || 0).toLocaleString()}</td>
                        <td class="fw-bold ${canReceive ? 'text-warning' : 'text-muted'}">${remainingQty.toLocaleString()}</td>
                        <td>
                `;
                
                if (mode === 'receive' && canReceive) {
                    html += `
                        <div class="input-group input-group-sm">
                            <input type="number" 
                                   class="form-control receive-qty-input" 
                                   data-item-id="${item.item_id}"
                                   data-max="${remainingQty}"
                                   min="0" 
                                   max="${remainingQty}" 
                                   step="0.01" 
                                   placeholder="จำนวน">
                            <button type="button" 
                                    class="btn btn-outline-primary quick-receive-btn"
                                    data-item-id="${item.item_id}"
                                    data-product-name="${escapeHtml(item.product_name)}"
                                    data-ordered-qty="${item.ordered_qty}"
                                    data-remaining-qty="${remainingQty}"
                                    data-unit="${escapeHtml(item.unit)}"
                                    title="รับเข้าด่วน">
                                <span class="material-icons" style="font-size: 1rem;">speed</span>
                            </button>
                        </div>
                    `;
                } else if (mode === 'view') {
                    html += `<span class="text-muted">-</span>`;
                } else {
                    html += `<span class="text-muted">รับครบแล้ว</span>`;
                }
                
                html += `
                        </td>
                    </tr>
                `;
            });
        }
        
        $('#poItemsTableBody').html(html);
        
        // Show/hide save button
        if (mode === 'receive') {
            $('#saveReceiveBtn').show();
            setupReceiveInputs();
        } else {
            $('#saveReceiveBtn').hide();
        }
        
        // Setup quick receive buttons
        $('.quick-receive-btn').on('click', function() {
            const itemId = $(this).data('item-id');
            const productName = $(this).data('product-name');
            const orderedQty = $(this).data('ordered-qty');
            const remainingQty = $(this).data('remaining-qty');
            const unit = $(this).data('unit');
            
            showQuickReceiveModal(itemId, productName, orderedQty, remainingQty, unit);
        });
    }
    
    // Setup receive inputs
    function setupReceiveInputs() {
        $('.receive-qty-input').on('input', function() {
            const itemId = $(this).data('item-id');
            const qty = parseFloat($(this).val()) || 0;
            const maxQty = parseFloat($(this).data('max'));
            
            if (qty > maxQty) {
                $(this).val(maxQty);
                Swal.fire({
                    icon: 'warning',
                    title: 'จำนวนเกินกำหนด',
                    text: `จำนวนสูงสุดที่สามารถรับได้คือ ${maxQty}`,
                    timer: 2000
                });
            }
            
            if (qty > 0) {
                receiveItems[itemId] = qty;
            } else {
                delete receiveItems[itemId];
            }
        });
    }
    
    // Show quick receive modal
    function showQuickReceiveModal(itemId, productName, orderedQty, remainingQty, unit) {
        $('#quickItemId').val(itemId);
        $('#quickPoId').val(currentPoData.poId);
        $('#quickProductName').text(productName);
        $('#quickOrderedQty').text(parseFloat(orderedQty).toLocaleString() + ' ' + unit);
        $('#quickRemainingQty').text(parseFloat(remainingQty).toLocaleString() + ' ' + unit);
        $('#quickUnit').text(unit);
        $('#quickReceiveQty').attr('max', remainingQty).val(remainingQty);
        $('#quickNotes').val('');
        
        $('#quickReceiveModal').modal('show');
        
        setTimeout(() => {
            $('#quickReceiveQty').focus().select();
        }, 500);
    }
    
    // Save receive items (batch)
    $('#saveReceiveBtn').on('click', function() {
        if (Object.keys(receiveItems).length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบรายการรับเข้า',
                text: 'กรุณากรอกจำนวนสินค้าที่ต้องการรับเข้า'
            });
            return;
        }
        
        const itemsToReceive = Object.keys(receiveItems).map(itemId => ({
            item_id: itemId,
            quantity: receiveItems[itemId]
        }));
        
        Swal.fire({
            title: 'ยืนยันการรับเข้าสินค้า',
            text: `จำนวน ${itemsToReceive.length} รายการ`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                saveBatchReceive(itemsToReceive);
            }
        });
    });
    
    // Confirm quick receive
    $('#confirmQuickReceive').on('click', function() {
        const form = $('#quickReceiveForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const itemId = $('#quickItemId').val();
        const quantity = parseFloat($('#quickReceiveQty').val());
        const notes = $('#quickNotes').val();
        
        if (quantity <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'จำนวนไม่ถูกต้อง',
                text: 'กรุณากรอกจำนวนที่มากกว่า 0'
            });
            return;
        }
        
        saveSingleReceive(itemId, quantity, notes);
    });
    
    // Save single receive
    function saveSingleReceive(itemId, quantity, notes) {
        $.ajax({
            url: 'process_receive_po.php',
            method: 'POST',
            data: {
                action: 'receive_single',
                po_id: currentPoData.poId,
                item_id: itemId,
                quantity: quantity,
                notes: notes,
                po_number: currentPoData.poNumber
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'รับเข้าสินค้าสำเร็จ',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#quickReceiveModal').modal('hide');
                        // Reload PO items
                        loadPoItems(currentPoData.poId, currentPoData.poNumber, currentPoData.supplier, currentPoData.mode);
                        // Refresh page to update statistics
                        setTimeout(() => location.reload(), 1000);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Receive error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
                });
            }
        });
    }
    
    // Save batch receive
    function saveBatchReceive(items) {
        $.ajax({
            url: 'process_receive_po.php',
            method: 'POST',
            data: {
                action: 'receive_multiple',
                po_id: currentPoData.poId,
                items: JSON.stringify(items),
                po_number: currentPoData.poNumber
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'รับเข้าสินค้าสำเร็จ',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#poItemsModal').modal('hide');
                        // Refresh page
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Batch receive error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
                });
            }
        });
    }
    
    // Utility function
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Auto refresh every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});
</script>

</body>
</html>