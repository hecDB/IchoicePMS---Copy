<?php
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

// Get Purchase Orders that are ready for receiving (including both regular and new product POs)
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
        COALESCE(SUM(
            CASE WHEN (COALESCE(received_summary.total_received, 0) + COALESCE(poi.cancel_qty, 0)) >= poi.qty THEN 1 ELSE 0 END
        ), 0) as fully_received_items,
        COALESCE(SUM(
            CASE WHEN (COALESCE(received_summary.total_received, 0) + COALESCE(poi.cancel_qty, 0)) > 0 AND (COALESCE(received_summary.total_received, 0) + COALESCE(poi.cancel_qty, 0)) < poi.qty THEN 1 ELSE 0 END
        ), 0) as partially_received_items,
        COALESCE(SUM(poi.qty), 0) as total_ordered_qty,
        COALESCE(SUM(COALESCE(received_summary.total_received, 0)), 0) as total_received_qty,
        COALESCE(SUM(COALESCE(poi.cancel_qty, 0)), 0) as total_cancelled_qty
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN currencies c ON po.currency_id = c.currency_id
    LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
    LEFT JOIN (
        SELECT item_id, SUM(receive_qty) as total_received 
        FROM receive_items 
        GROUP BY item_id
    ) received_summary ON poi.item_id = received_summary.item_id
    WHERE po.status IN ('pending', 'partial')
    GROUP BY po.po_id, po.po_number, s.name, po.order_date, po.total_amount, c.code, po.remark, po.status
    ORDER BY po.order_date DESC, po.po_number DESC
";

$stmt = $pdo->query($sql_pos);
$all_purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate regular and new product POs
$purchase_orders = array_filter($all_purchase_orders, function($po) {
    return !$po['remark'] || stripos($po['remark'], 'New Product Purchase') === false;
});

$new_product_orders = array_filter($all_purchase_orders, function($po) {
    return $po['remark'] && stripos($po['remark'], 'New Product Purchase') !== false;
});

// Calculate statistics directly from purchase_orders.status so the cards match table data
$sql_status_counts = "
    SELECT status, COUNT(*) AS cnt
    FROM purchase_orders
    GROUP BY status
";

$status_counts = [];
$stmt_status = $pdo->query($sql_status_counts);
foreach ($stmt_status->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $status = $row['status'] ?? '';
    $count = (int)($row['cnt'] ?? 0);
    if ($status !== '') {
        $status_counts[$status] = $count;
    }
}

$total_all_pos = array_sum($status_counts); // ทุกสถานะรวม
$ready_to_receive = $status_counts['pending'] ?? 0; // พร้อมรับสินค้า
$partially_received = $status_counts['partial'] ?? 0; // รับบางส่วน
$fully_received = $status_counts['completed'] ?? 0; // รับครบแล้ว

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



        .po-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            background: white;
            transition: all 0.3s ease;
        }

        .po-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .po-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px 10px 0 0;
        }

        .po-card .card-body {
            padding: 0.75rem 1rem;
        }

        .po-card h6 {
            font-size: 0.95rem;
        }

        .po-card .small {
            font-size: 0.75rem;
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

        .po-item-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .po-item-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
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
            min-width: 120px;
            max-width: 150px;
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

        /* Filter Buttons */
        .filter-btn {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-color: #1d4ed8;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        /* Filter Button Cards */
        .filter-btn-card {
            border: 2px solid #e5e7eb !important;
            border-radius: 12px !important;
            transition: all 0.3s ease !important;
            color: inherit !important;
            text-decoration: none !important;
        }

        .filter-btn-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12) !important;
            border-color: #3b82f6 !important;
        }

        .filter-btn-card.active {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
            color: white !important;
            border-color: #1d4ed8 !important;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4) !important;
        }

        .filter-btn-card .stats-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .filter-btn-card.active .stats-title {
            color: rgba(255, 255, 255, 0.9);
        }

        .filter-btn-card .stats-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
        }

        .filter-btn-card.active .stats-value {
            color: white;
        }

        .filter-btn-card .stats-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        .filter-btn-card.active .stats-subtitle {
            color: rgba(255, 255, 255, 0.8);
        }

        .filter-btn-card .stats-icon {
            font-size: 2.5rem;
            color: #d1d5db;
        }

        .filter-btn-card.active .stats-icon {
            color: rgba(255, 255, 255, 0.9);
        }


        /* PO Card Container for filtering */
        .po-card-container {
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                <p class="text-muted mb-0">จัดการรับเข้าสินค้าจากสินค้าปกติและสินค้าใหม่</p>
            </div>
            <div class="d-flex gap-2">
                <a href="quick_receive.php" class="btn btn-outline-primary">
                    <span class="material-icons me-1">qr_code_scanner</span>
                    รับสินค้าด่วน (Scan)
                </a>
                <button class="btn btn-outline-success" onclick="toggleCompletedPOs()">
                    <span class="material-icons me-1">visibility</span>
                    <span id="toggleText">ดูที่รับครบแล้ว</span>
                </button>
                <button class="btn btn-outline-secondary" onclick="location.reload()">
                    <span class="material-icons me-1">refresh</span>
                    รีเฟรช
                </button>
            </div>
        </div>

        <!-- Stats Cards with Filter Buttons -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <button class="filter-btn-card btn btn-lg w-100 filter-btn active" data-filter="all" style="padding: 1.5rem; border: 2px solid #e5e7eb; background: white; text-align: left;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-title">ทั้งหมด</div>
                            <div class="stats-value"><?= number_format($total_all_pos) ?></div>
                            <div class="stats-subtitle">ทุกสถานะ</div>
                        </div>
                        <div class="col-auto">
                            <i class="material-icons stats-icon">apps</i>
                        </div>
                    </div>
                </button>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <button class="filter-btn-card btn btn-lg w-100 filter-btn" data-filter="ready" style="padding: 1.5rem; border: 2px solid #e5e7eb; background: white; text-align: left;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-title">พร้อมรับสินค้า</div>
                            <div class="stats-value"><?= number_format($ready_to_receive) ?></div>
                            <div class="stats-subtitle">ยังไม่ได้รับ</div>
                        </div>
                        <div class="col-auto">
                            <i class="material-icons stats-icon">check_circle</i>
                        </div>
                    </div>
                </button>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <button class="filter-btn-card btn btn-lg w-100 filter-btn" data-filter="partial" style="padding: 1.5rem; border: 2px solid #e5e7eb; background: white; text-align: left;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-title">รับบางส่วน</div>
                            <div class="stats-value"><?= number_format($partially_received) ?></div>
                            <div class="stats-subtitle">ยังไม่ครบ</div>
                        </div>
                        <div class="col-auto">
                            <i class="material-icons stats-icon">pending</i>
                        </div>
                    </div>
                </button>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <button class="filter-btn-card btn btn-lg w-100 filter-btn" data-filter="complete" style="padding: 1.5rem; border: 2px solid #e5e7eb; background: white; text-align: left;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-title">รับครบแล้ว</div>
                            <div class="stats-value"><?= number_format($fully_received) ?></div>
                            <div class="stats-subtitle">เสร็จสิ้น</div>
                        </div>
                        <div class="col-auto">
                            <i class="material-icons stats-icon">done_all</i>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Purchase Orders List -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">inventory_2</span>
                        ใบสั่งซื้อปกติ (<?= count($purchase_orders) ?> ใบ)
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
                    <span class="material-icons mb-3" style="font-size: 4rem; color: #34d399;">done_all</span>
                    <h5 class="text-success">รับสินค้าครบถ้วนแล้ว</h5>
                    <p class="text-muted mb-0">ไม่มีใบสั่งซื้อปกติที่ต้องรับสินค้า</p>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($purchase_orders as $po): ?>
                    <?php
                    // คำนวณเปอร์เซ็นต์จากจำนวนที่รับจริง เทียบกับจำนวนที่สั่ง
                    $completion_rate = $po['total_ordered_qty'] > 0 ? ($po['total_received_qty'] / $po['total_ordered_qty']) * 100 : 0;
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
                    <div class="col-sm-6 col-lg-4 col-xl-3 mb-3 po-card-container" data-filter-status="<?php 
                        if ($completion_rate == 0) echo 'ready';
                        elseif ($completion_rate < 100) echo 'partial';
                        else echo 'complete';
                    ?>">
                        <div class="po-card">
                            <div class="po-card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($po['po_number']) ?></h6>
                                        <div class="small text-muted"><?= date('d/m/Y', strtotime($po['po_date'])) ?></div>
                                          <div class="small text-muted">ซื้อจาก : <?= htmlspecialchars($po['supplier_name']) ?></div>
                                    </div>
                                    <span class="po-status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="d-flex gap-2">
                                        <button type="button" 
                                            class="btn btn-outline-primary btn-sm w-100 view-po-btn"
                                            data-po-id="<?= $po['po_id'] ?>"
                                            data-po-number="<?= htmlspecialchars($po['po_number']) ?>"
                                            data-supplier="<?= htmlspecialchars($po['supplier_name']) ?>"
                                            data-remark=""
                                            data-mode="receive">
                                        <span class="material-icons" style="font-size: 1rem;">input</span>
                                        รับเข้า
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

        <!-- New Product Purchase Orders List -->
        <?php if (!empty($new_product_orders)): ?>
        <div class="table-card mt-5">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">new_releases</span>
                        ใบสั่งซื้อสินค้าใหม่ (<?= count($new_product_orders) ?> ใบ)
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
                <div class="row g-3">
                    <?php foreach ($new_product_orders as $po): ?>
                    <?php
                    // คำนวณเปอร์เซ็นต์จากจำนวนที่รับจริง เทียบกับจำนวนที่สั่ง
                    $completion_rate = $po['total_ordered_qty'] > 0 ? ($po['total_received_qty'] / $po['total_ordered_qty']) * 100 : 0;
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
                    <div class="col-sm-6 col-lg-4 col-xl-3 mb-3 po-card-container" data-filter-status="<?php 
                        if ($completion_rate == 0) echo 'ready';
                        elseif ($completion_rate < 100) echo 'partial';
                        else echo 'complete';
                    ?>">
                        <div class="po-card" style="border-left: 4px solid #f59e0b;">
                            <div class="po-card-header" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($po['po_number']) ?></h6>
                                        <div class="small text-muted"><?= date('d/m/Y', strtotime($po['po_date'])) ?></div>
                                       <div class="small text-muted">ซื้อจาก : <?= htmlspecialchars($po['supplier_name']) ?></div>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.7rem; margin-top: 3px;">สินค้าใหม่</span>
                                    </div>
                                    <span class="po-status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="d-flex gap-2">
                                        <button type="button" 
                                            class="btn btn-outline-success btn-sm w-100 view-po-btn"
                                            data-po-id="<?= $po['po_id'] ?>"
                                            data-po-number="<?= htmlspecialchars($po['po_number']) ?>"
                                            data-supplier="<?= htmlspecialchars($po['supplier_name']) ?>"
                                            data-remark="<?= htmlspecialchars($po['remark']) ?>"
                                            data-mode="receive">
                                        <span class="material-icons" style="font-size: 1rem;">input</span>
                                        รับเข้า
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                                <th width="3%">#</th>
                                <th width="8%">รูปภาพ</th>
                                <th width="10%">สินค้า</th>
                                <th width="10%">SKU</th>
                                <th width="5%">หน่วย</th>
                                <th width="5%">จำนวนสั่ง</th>
                                <th width="5%">ราคา/หน่วย</th>
                                <th width="5%">รับแล้ว</th>
                                <th width="5%">ยกเลิก</th>
                                <th width="5%">ชำรุด</th>
                                <th width="5%">คงเหลือ</th>
                                <th width="11%">วันหมดอายุ</th>
                                <th width="11%">รับเข้า</th>
                            </tr>
                        </thead>
                        <tbody id="poItemsTableBody">
                            <!-- Items will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Damaged Unsellable Section -->
                <div id="damagedUnsellableSection" style="display: none;">
                    <div class="mt-4 pt-4 border-top">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <span class="material-icons align-middle me-2" style="font-size: 1.2rem;">error</span>
                            <strong>สินค้าชำรุดขายไม่ได้</strong> - ของรายการสั่งซื้อนี้
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 10%;">รูปภาพ</th>
                                        <th>ชื่อสินค้า</th>
                                        <th style="width: 12%; text-align: center;">SKU</th>
                                        <th style="width: 10%; text-align: right;">จำนวน</th>
                                        <th style="width: 15%; text-align: center;">วันหมดอายุ</th>
                                        <th style="width: 15%; text-align: center;">บันทึกเมื่อ</th>
                                    </tr>
                                </thead>
                                <tbody id="damagedunsellablePoTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                    
                    <div class="mb-3">
                        <label for="quickExpiryDate" class="form-label">วันหมดอายุ (ถ้ามี)</label>
                        <input type="date" class="form-control" id="quickExpiryDate" name="expiry_date">
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

<!-- Damaged Item Modal -->
<div class="modal fade" id="damagedItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">construction</span>
                    สินค้าชำรุดบางส่วน
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <form id="damagedItemForm">
                    <input type="hidden" id="damagedItemId">
                    <input type="hidden" id="damagedProductId">
                    <input type="hidden" id="damagedPoId">
                    <div class="mb-2">
                        <label class="form-label fw-bold">สินค้า</label>
                        <div id="damagedProductName" class="form-control-plaintext fw-bold text-warning"></div>
                        <small class="text-muted">SKU: <span id="damagedSku"></span></small>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">คงเหลือรับได้</label>
                            <div id="damagedRemaining" class="form-control-plaintext text-info fw-bold"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">จำนวนสั่ง</label>
                            <div id="damagedOrdered" class="form-control-plaintext text-muted"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="damagedQty" class="form-label fw-bold">จำนวนที่ชำรุด *</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="damagedQty" min="0.01" step="0.01" required>
                            <span class="input-group-text" id="damagedUnit"></span>
                        </div>
                        <small class="text-muted">ระบุจำนวนไม่เกินคงเหลือรับได้</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">สถานะสินค้า</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="damagedDisposition" id="damagedSellable" value="sellable" checked>
                                <label class="form-check-label" for="damagedSellable">ขายได้</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="damagedDisposition" id="damagedDiscard" value="discard">
                                <label class="form-check-label" for="damagedDiscard">ทิ้ง/ใช้ไม่ได้</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="damagedNotes" class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="damagedNotes" rows="2" placeholder="รายละเอียดสภาพสินค้า..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-warning" id="confirmDamagedItem">
                    <span class="material-icons align-middle me-1">check</span>
                    ส่งเข้าตรวจสอบ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Item Modal -->
<div class="modal fade" id="cancelItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">cancel</span>
                    ยกเลิกสินค้า
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <form id="cancelItemForm">
                    <input type="hidden" id="cancelItemId" name="item_id">
                    <input type="hidden" id="cancelPoId" name="po_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">สินค้า</label>
                        <div id="cancelProductName" class="form-control-plaintext fw-bold text-danger"></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">รับได้อีก</label>
                            <div id="cancelRemainingQty" class="form-control-plaintext fw-bold text-warning"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">หน่วย</label>
                            <div id="cancelUnit" class="form-control-plaintext"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cancelQuantity" class="form-label fw-bold">จำนวนที่จะยกเลิก *</label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   id="cancelQuantity" 
                                   name="cancel_qty"
                                   min="0.01" 
                                   step="0.01"
                                   placeholder="0"
                                   required>
                            <span class="input-group-text" id="cancelQtyUnit"></span>
                        </div>
                        <small class="text-muted mt-1 d-block">
                            <span id="cancelQtyValidation" style="display:none;">
                                ✓ จำนวนถูกต้อง
                            </span>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label fw-bold">เหตุผลในการยกเลิก *</label>
                        <select class="form-select" id="cancelReason" name="reason" required>
                            <option value="">-- เลือกเหตุผล --</option>
                            <option value="stock_unavailable">สินค้าไม่มีสต็อก</option>
                            <option value="out_of_stock">สินค้าหมด</option>
                            <option value="damaged">สินค้าเสียหาย</option>
                            <option value="supplier_cancel">ผู้จำหน่ายยกเลิก</option>
                            <option value="other">อื่นๆ</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cancelNotes" class="form-label">หมายเหตุเพิ่มเติม</label>
                        <textarea class="form-control" id="cancelNotes" name="notes" rows="3" placeholder="อธิบายรายละเอียดเพิ่มเติม..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning" role="alert">
                        <small>
                            <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">info</span>
                            กรุณากรอกจำนวนที่แท้จริงในการยกเลิก ซึ่งต้องไม่เกินจำนวนที่รับได้อีก
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-danger" id="confirmCancelItem">
                    <span class="material-icons me-1">done</span>
                    ยืนยันการยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Completed PO Items Modal - Simplified View -->
<div class="modal fade" id="completedPoItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                <h5 class="modal-title" style="color: white;">
                    <span class="material-icons align-middle me-2">done_all</span>
                    รายการที่รับครบแล้ว - <span id="completedPoNumber"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 pb-3 border-bottom">
                    <small class="text-muted d-block">ผู้จัดจำหน่าย</small>
                    <strong id="completedPoSupplier" class="fs-6"></strong>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 6%; text-align: center;">#</th>
                                <th style="width: 12%; text-align: center;">รูปภาพ</th>
                                <th>ชื่อสินค้า</th>
                                <th style="width: 16%; text-align: center;">SKU</th>
                                <th style="width: 15%; text-align: right;">จำนวนสั่ง</th>
                                <th style="width: 15%; text-align: right;">รับจริง</th>
                                <th style="width: 15%; text-align: right;">ยกเลิก</th>
                            </tr>
                        </thead>
                        <tbody id="completedPoItemsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
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
// Utility functions (outside document.ready)
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

// Fetch damaged reason id (สินค้าชำรุดบางส่วน)
function fetchDamagedReasonId() {
    $.ajax({
        url: '../api/returned_items_api.php?action=get_reasons',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('🔍 All return reasons fetched:', response);
            if (!response || response.status !== 'success' || !Array.isArray(response.data)) {
                console.warn('❌ Cannot load return reasons - Invalid response');
                return;
            }
            
            console.log('📋 Total reasons:', response.data.length);
            response.data.forEach(r => {
                console.log(`  - ${r.reason_id}: ${r.reason_name} (is_returnable: ${r.is_returnable})`);
            });
            
            const match = response.data.find(r => (r.reason_name || '').trim() === 'สินค้าชำรุดบางส่วน');
            
            if (match) {
                damagedReasonId = match.reason_id;
                console.log('✅ Found damaged reason: reason_id =', damagedReasonId);
            } else {
                console.warn('⚠️ Cannot find "สินค้าชำรุดบางส่วน" reason');
                console.log('Available non-zero returnable reasons:');
                response.data.forEach(r => {
                    if (r.is_returnable === '0' || r.is_returnable === 0) {
                        console.log(`  - ${r.reason_id}: ${r.reason_name}`);
                    }
                });
                damagedReasonId = null;
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Failed to fetch reasons', error, xhr.responseText);
        }
    });
}

function formatThaiDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString + 'T00:00:00');
    const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear() + 543;
    return `${day} ${month} ${year}`;
}

// Load damaged unsellable items for specific PO
function loadDamagedUnsellableByPo(poId) {
    console.log('🔍 loadDamagedUnsellableByPo called with poId:', poId);
    
    $.ajax({
        url: '../api/get_damaged_unsellable_by_po.php?po_id=' + encodeURIComponent(poId),
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('📥 API Response from get_damaged_unsellable_by_po:', response);
            if (response.status === 'success') {
                console.log('✅ Data received:', response.data);
                displayDamagedUnsellableByPo(response.data || []);
            } else {
                console.warn('⚠️ API returned unsuccessful status:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error loading damaged items by PO:', error, xhr.responseText);
        }
    });
}

function displayDamagedUnsellableByPo(items) {
    console.log('📋 displayDamagedUnsellableByPo called with items:', items);
    
    if (!items || items.length === 0) {
        console.log('ℹ️ No damaged unsellable items found, hiding section');
        $('#damagedUnsellableSection').hide();
        return;
    }
    
    console.log('✅ Found ' + items.length + ' damaged unsellable item(s)');
    
    let html = '';
    items.forEach((item) => {
        console.log('Processing item:', item);
        const imageSrc = resolveProductImage(item);
        const productName = escapeHtml(item.product_name || '-');
        const sku = escapeHtml(item.sku || '-');
        const returnCode = escapeHtml(item.return_code || '-');
        const expiryDisplay = item.expiry_date ? formatThaiDate(item.expiry_date) : '-';
        const createdDate = item.created_at ? formatThaiDateTime(item.created_at) : '-';
        
        html += `
            <tr>
                <td class="text-center">
                    <img src="${imageSrc}" alt="${productName}" class="po-item-image" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover;">
                </td>
                <td>
                    <div>
                        <strong>${productName}</strong>
                        <div class="text-muted small">${returnCode}</div>
                    </div>
                </td>
                <td class="text-center">
                    <small class="badge bg-secondary">${sku}</small>
                </td>
                <td class="text-center">
                    <strong>${Number(item.return_qty || 0).toLocaleString()}</strong>
                </td>
                <td class="text-center">
                    <small class="badge bg-danger">${expiryDisplay}</small>
                </td>
                <td class="text-center">
                    <small class="text-muted">${createdDate}</small>
                </td>
            </tr>
        `;
    });
    
    $('#damagedunsellablePoTableBody').html(html);
    $('#damagedUnsellableSection').show();
    console.log('✅ Damaged unsellable section displayed');
}

function formatThaiDateTime(dateTimeString) {
    if (!dateTimeString) return '-';
    const parts = dateTimeString.split(' ');
    const datePart = parts[0] || '';
    const timePart = parts[1] || '';
    const thaiDate = formatThaiDate(datePart);
    if (thaiDate === '-') return '-';
    const timeDisplay = timePart ? timePart.slice(0, 5) : '';
    return timeDisplay ? `${thaiDate} ${timeDisplay}` : thaiDate;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function isExpired(dateString) {
    if (!dateString) return false;
    const expiryDate = new Date(dateString + 'T23:59:59');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return expiryDate < today;
}

function resolveProductImage(item) {
    const fallback = '../images/noimg.png';
    if (!item) {
        return fallback;
    }

    const sources = [item.image_path, item.image_url, item.image, item.product_image];

    for (const src of sources) {
        if (!src || typeof src !== 'string') {
            continue;
        }

        const trimmed = src.trim();
        if (!trimmed) {
            continue;
        }

        if (trimmed.startsWith('data:image')) {
            return trimmed;
        }

        const base64Candidate = trimmed.replace(/\s+/g, '');
        if (/^[A-Za-z0-9+/=]+$/.test(base64Candidate) && base64Candidate.length > 100) {
            return `data:image/*;base64,${base64Candidate}`;
        }

        if (/^https?:\/\//i.test(trimmed)) {
            return trimmed;
        }

        if (trimmed.startsWith('../')) {
            return trimmed;
        }

        if (trimmed.startsWith('./')) {
            return `../${trimmed.slice(2)}`;
        }

        if (trimmed.startsWith('/')) {
            return `..${trimmed}`;
        }

        if (trimmed.startsWith('images/')) {
            return `../${trimmed}`;
        }

        return `../images/${trimmed}`;
    }

    return fallback;
}

function showDamagedItemModal({ itemId, productId, productName, orderedQty, remainingQty, unit, sku }) {
    $('#damagedItemId').val(itemId);
    $('#damagedProductId').val(productId);
    $('#damagedPoId').val(currentPoData.poId || '');
    $('#damagedProductName').text(productName || '-');
    $('#damagedSku').text(sku || '-');
    $('#damagedOrdered').text(orderedQty.toLocaleString() + ' ' + (unit || ''));
    $('#damagedRemaining').text(remainingQty.toLocaleString() + ' ' + (unit || ''));
    $('#damagedUnit').text(unit || '');
    $('#damagedQty').attr('max', remainingQty > 0 ? remainingQty : 0).val(remainingQty > 0 ? remainingQty : '');
    $('#damagedNotes').val('');
    $('#damagedSellable').prop('checked', true);

    $('#damagedItemModal').modal('show');

    setTimeout(() => {
        $('#damagedQty').focus().select();
    }, 300);
}

function submitDamagedItem() {
    const itemId = $('#damagedItemId').val();
    const productId = $('#damagedProductId').val();
    const poId = $('#damagedPoId').val();
    const qty = parseFloat($('#damagedQty').val());
    const maxQty = parseFloat($('#damagedQty').attr('max'));
    const notes = $('#damagedNotes').val() || '';
    const disposition = $('input[name="damagedDisposition"]:checked').val();

    if (!damagedReasonId) {
        Swal.fire({ icon: 'warning', title: 'ไม่พบเหตุผลชำรุดบางส่วน', text: 'กรุณาเพิ่มเหตุผลก่อน' });
        return;
    }

    if (!itemId || !productId || !poId) {
        Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบ', text: 'ไม่พบข้อมูลสินค้า/PO' });
        return;
    }

    if (!qty || qty <= 0) {
        Swal.fire({ icon: 'warning', title: 'จำนวนไม่ถูกต้อง', text: 'กรุณากรอกจำนวนที่มากกว่า 0' });
        return;
    }

    if (Number.isFinite(maxQty) && qty > maxQty + 0.00001) {
        Swal.fire({ icon: 'warning', title: 'จำนวนเกินกำหนด', text: 'จำนวนชำรุดต้องไม่เกินคงเหลือรับได้' });
        return;
    }

    const finalNotes = disposition === 'discard'
        ? `[ทิ้ง/ใช้ไม่ได้] ${notes}`.trim()
        : `[ขายได้] ${notes}`.trim();

    Swal.fire({
        title: 'ยืนยันส่งตรวจสอบสินค้าชำรุด',
        text: `จำนวน ${qty.toLocaleString()}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
            url: '../api/returned_items_api.php?action=create_return',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'create_return',
                po_id: poId,
                item_id: itemId,
                product_id: productId,
                return_qty: qty,
                reason_id: damagedReasonId,
                notes: finalNotes
            }),
            dataType: 'json',
            success: function(response) {
                if (response && response.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'ส่งเข้าตรวจสอบแล้ว', text: 'ไปที่เมนูสินค้าชำรุดเพื่อดำเนินการต่อ' });
                    $('#damagedItemModal').modal('hide');
                } else {
                    Swal.fire({ icon: 'error', title: 'ไม่สามารถส่งตรวจสอบได้', text: (response && response.message) || 'กรุณาลองใหม่' });
                }
            },
            error: function(xhr, status, error) {
                console.error('Damaged submit error:', error);
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถส่งตรวจสอบได้ กรุณาลองใหม่' });
            }
        });
    });
}

// Toggle completed POs function
function toggleCompletedPOs() {
    const button = $('#toggleText');
    const tableBody = $('.table-body').first();
    
    if (!window.showingCompleted) {
        // Load completed POs
        button.text('กำลังโหลด...');
        
        $.ajax({
            url: 'get_completed_pos.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displayCompletedPOs(response.data);
                    button.text('ซ่อนที่รับครบแล้ว');
                    window.showingCompleted = true;
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'ไม่มีรายการ',
                        text: 'ไม่พบใบสั่งซื้อที่รับครบแล้ว',
                        timer: 2000
                    });
                    button.text('ดูที่รับครบแล้ว');
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถโหลดข้อมูลได้'
                });
                button.text('ดูที่รับครบแล้ว');
            }
        });
    } else {
        // Hide completed POs - reload page
        location.reload();
    }
}

// Global variables for modal and items
let currentPoData = {};
let receiveItems = {};
let damagedReasonId = null;

// Load PO items - GLOBAL FUNCTION
function loadPoItems(poId, poNumber, supplier, mode, remark) {
    console.log('loadPoItems called with:', { poId, poNumber, supplier, mode, remark });
    currentPoData = { poId, poNumber, supplier, mode, remark };
    
    $('#modalPoNumber').text(poNumber);
    $('#modalSupplier').text(supplier);
    
    // Show loading
    $('#poItemsTableBody').html(`
        <tr>
            <td colspan="12" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">กำลังโหลด...</span>
                </div>
                <div class="mt-2">กำลังโหลดข้อมูล...</div>
            </td>
        </tr>
    `);
    
    $('#poItemsModal').modal('show');
    
    // Determine which API to use based on remark
    const isNewProduct = remark && remark.toLowerCase().includes('new product');
    const apiUrl = isNewProduct ? '../api/get_po_items_new_product.php' : '../api/get_po_items.php';
    
    // Load data via AJAX
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { po_id: poId },
        dataType: 'json',
        success: function(response) {
            console.log('API Response:', response);
            if (response.success) {
                console.log('Items:', response.items);
                displayPoItems(response.items, mode);
            } else {
                console.error('API Error:', response.error);
                $('#poItemsTableBody').html(`
                    <tr>
                        <td colspan="12" class="text-center py-4 text-danger">
                            <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                            <div>${response.error}</div>
                        </td>
                    </tr>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading PO items:', error);
            $('#poItemsTableBody').html(`
                <tr>
                    <td colspan="12" class="text-center py-4 text-danger">
                        <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                        <div>เกิดข้อผิดพลาดในการโหลดข้อมูล</div>
                    </td>
                </tr>
            `);
        }
    });
}

// Load completed PO items - GLOBAL FUNCTION
function loadCompletedPoItems(poId, poNumber, supplier, remark) {
    console.log('loadCompletedPoItems called with:', { poId, poNumber, supplier, remark });
    
    $('#completedPoNumber').text(poNumber);
    $('#completedPoSupplier').text(supplier);
    
    // Show loading
    $('#completedPoItemsTableBody').html(`
        <tr>
            <td colspan="7" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">กำลังโหลด...</span>
                </div>
                <div class="mt-2">กำลังโหลดข้อมูล...</div>
            </td>
        </tr>
    `);
    
    $('#completedPoItemsModal').modal('show');
    
    // Determine which API to use based on remark
    const isNewProduct = remark && remark.toLowerCase().includes('new product');
    const apiUrl = isNewProduct ? '../api/get_po_items_new_product.php' : '../api/get_po_items.php';
    
    // Load data via AJAX
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { po_id: poId },
        dataType: 'json',
        success: function(response) {
            console.log('Completed PO API Response:', response);
            if (response.success) {
                console.log('Items:', response.items);
                displayCompletedPoItems(response.items);
            } else {
                console.error('API Error:', response.error);
                $('#completedPoItemsTableBody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                            <div>${response.error}</div>
                        </td>
                    </tr>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading completed PO items:', error);
            $('#completedPoItemsTableBody').html(`
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                        <div>เกิดข้อผิดพลาดในการโหลดข้อมูล</div>
                    </td>
                </tr>
            `);
        }
    });
}

// Display completed PO items in simple table - GLOBAL FUNCTION
function displayCompletedPoItems(items) {
    console.log('displayCompletedPoItems called with:', items);
    let html = '';
    
    if (!items || !Array.isArray(items) || items.length === 0) {
        html = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <span class="material-icons mb-2" style="font-size: 2rem;">inbox</span>
                    <div>ไม่พบรายการสินค้าในใบสั่งซื้อนี้</div>
                </td>
            </tr>
        `;
    } else {
        items.forEach(function(item, index) {
            const orderedQty = parseFloat(item.order_qty || item.ordered_qty || 0);
            const receivedQty = parseFloat(item.received_qty || 0);
            const cancelledQty = parseFloat(item.cancel_qty || 0);
            const productName = escapeHtml(item.product_name);
            const imageSrc = resolveProductImage(item);
            const cancelReason = escapeHtml(item.cancel_reason || '-');
            const rawCancelNotes = item.cancel_notes ? item.cancel_notes.toString().trim() : '';
            const cancelNotes = rawCancelNotes ? escapeHtml(rawCancelNotes) : '';
            const cancelDateDisplay = item.cancelled_at ? formatThaiDateTime(item.cancelled_at) : '-';
            const cancelTooltipLines = [`เหตุผล: ${cancelReason}`];
            if (cancelNotes) {
                cancelTooltipLines.push(`หมายเหตุ: ${cancelNotes}`);
            }
            if (cancelDateDisplay !== '-') {
                cancelTooltipLines.push(`ยกเลิกเมื่อ: ${cancelDateDisplay}`);
            }
            const cancelTooltip = cancelTooltipLines.join('\n');
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td class="text-center">
                        <img src="${imageSrc}" alt="${productName}" class="po-item-image" onerror="this.onerror=null;this.src='../images/noimg.png';">
                    </td>
                    <td>
                        <div class="fw-bold">${productName}</div>
                    </td>
                    <td>
                        <span class="badge bg-secondary">${escapeHtml(item.sku)}</span>
                    </td>
                    <td class="text-end">
                        <div class="fw-bold text-info">${orderedQty.toLocaleString()}</div>
                    </td>
                    <td class="text-end">
                        <div class="fw-bold text-success">${receivedQty.toLocaleString()}</div>
                    </td>
                    <td class="text-end">
                        <div class="fw-bold ${cancelledQty > 0 ? 'text-danger' : 'text-muted'}">
                            ${cancelledQty > 0 ? `<span title="${cancelTooltip}">${cancelledQty.toLocaleString()}</span>` : cancelledQty.toLocaleString()}
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#completedPoItemsTableBody').html(html);
}

// Display PO items - GLOBAL FUNCTION
function displayPoItems(items, mode) {
    console.log('displayPoItems called with:', items, mode);
    let html = '';
    receiveItems = {};
    
    if (!items || !Array.isArray(items) || items.length === 0) {
        html = `
            <tr>
                <td colspan="12" class="text-center py-4 text-muted">
                    <span class="material-icons mb-2" style="font-size: 2rem;">inbox</span>
                    <div>ไม่พบรายการสินค้าในใบสั่งซื้อนี้</div>
                </td>
            </tr>
        `;
    } else {
        items.forEach(function(item, index) {
            const remainingQty = parseFloat(item.remaining_qty);
            const cancelledQty = parseFloat(item.cancel_qty || 0);
            const damagedQty = parseFloat(item.damaged_qty || 0);
            const canReceive = remainingQty > 0;
            const allowDamaged = !!item.product_id;
            const isCancelled = item.is_cancelled === true || item.is_cancelled === 1;
            const productName = escapeHtml(item.product_name);
            const imageSrc = resolveProductImage(item);
            const cancelReason = escapeHtml(item.cancel_reason || '-');
            const rawCancelNotes = item.cancel_notes ? item.cancel_notes.toString().trim() : '';
            const cancelNotes = rawCancelNotes ? escapeHtml(rawCancelNotes) : '';
            const cancelDateDisplay = item.cancelled_at ? formatThaiDateTime(item.cancelled_at) : '-';
            const cancelTooltipLines = [`เหตุผล: ${cancelReason}`];
            if (cancelNotes) {
                cancelTooltipLines.push(`หมายเหตุ: ${cancelNotes}`);
            }
            if (cancelDateDisplay !== '-') {
                cancelTooltipLines.push(`ยกเลิกเมื่อ: ${cancelDateDisplay}`);
            }
            const cancelTooltip = cancelTooltipLines.join('\n');
            
            html += `
                <tr ${isCancelled ? 'style="background-color: #fee2e2; opacity: 0.8;"' : ''}>
                    <td>${index + 1}</td>
                    <td class="text-center">
                        <img src="${imageSrc}" alt="${productName}" class="po-item-image" onerror="this.onerror=null;this.src='../images/noimg.png';">
                    </td>
                    <td>
                        <div class="fw-bold">${productName}</div>
                        ${item.barcode ? `<small class="text-muted">Barcode: ${escapeHtml(item.barcode)}</small>` : ''}
                    </td>
                    <td><span class="badge bg-secondary">${escapeHtml(item.sku)}</span></td>
                    <td>${escapeHtml(item.unit)}</td>
                    <td class="fw-bold text-info">${parseFloat(item.order_qty).toLocaleString()}</td>
                    <td>${parseFloat(item.unit_price).toLocaleString()} ${escapeHtml(item.currency_code || '')}</td>
                    <td class="fw-bold text-success">${parseFloat(item.received_qty || 0).toLocaleString()}</td>
                    <td class="fw-bold ${cancelledQty > 0 ? 'text-danger' : 'text-muted'}">
                        ${cancelledQty > 0 ? `<span title="${cancelTooltip}">${cancelledQty.toLocaleString()}</span>` : '-'}
                    </td>
                    <td class="fw-bold ${damagedQty > 0 ? 'text-warning' : 'text-muted'}">
                        ${damagedQty > 0 ? damagedQty.toLocaleString() : '-'}
                    </td>
                    <td class="fw-bold ${canReceive ? 'text-warning' : 'text-muted'}">${remainingQty.toLocaleString()}</td>
                    <td>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="expiry-display" data-item-id="${item.item_id}">
                                <span class="text-muted">-</span>
                            </span>
                            <input type="date" 
                               class="form-control expiry-date-input" 
                               data-item-id="${item.item_id}"
                               value=""
                               style="width: 120px; height: 32px; font-size: 0.75rem;">
                        </div>
                    </td>
                    <td>`;
            
            if (mode === 'receive' && canReceive) {
                html += `
                    <div class="input-group input-group-sm">
                        <input type="number" 
                               class="form-control receive-qty-input" 
                               data-item-id="${item.item_id}"
                               data-product-id="${item.product_id}"
                               data-max="${remainingQty}"
                               min="0" 
                               max="${remainingQty}" 
                               step="0.01" 
                               placeholder="จำนวน">
                        <button type="button" 
                                class="btn btn-outline-primary quick-receive-btn"
                                data-item-id="${item.item_id}"
                                data-product-name="${escapeHtml(item.product_name)}"
                                data-ordered-qty="${item.order_qty}"
                                data-remaining-qty="${remainingQty}"
                                data-unit="${escapeHtml(item.unit)}"
                                title="รับเข้าด่วน">
                            <span class="material-icons" style="font-size: 1rem;">speed</span>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-warning damaged-item-btn"
                                data-item-id="${item.item_id}"
                                data-product-id="${item.product_id}"
                                data-product-name="${escapeHtml(item.product_name)}"
                                data-ordered-qty="${item.order_qty}"
                                data-remaining-qty="${remainingQty}"
                                data-unit="${escapeHtml(item.unit)}"
                                data-sku="${escapeHtml(item.sku)}"
                                title="สินค้าชำรุดบางส่วน"
                                ${allowDamaged ? '' : 'disabled'}>
                            <span class="material-icons" style="font-size: 1rem;">construction</span>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-danger cancel-item-btn"
                                data-item-id="${item.item_id}"
                                data-product-name="${escapeHtml(item.product_name)}"
                                data-remaining-qty="${remainingQty}"
                                data-unit="${escapeHtml(item.unit)}"
                                title="ยกเลิกสินค้า">
                            <span class="material-icons" style="font-size: 1rem;">cancel</span>
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
    
    // Load damaged unsellable items for this PO
    console.log('📍 Current PO Data:', currentPoData);
    console.log('🔄 Attempting to load damaged items for PO ID:', currentPoData.poId);
    loadDamagedUnsellableByPo(currentPoData.poId);
    
    // Show/hide save button
    if (mode === 'receive') {
        $('#saveReceiveBtn').show();
        setupReceiveInputs();
    } else {
        $('#saveReceiveBtn').hide();
    }
    
}

$(document).ready(function() {
    let showingCompleted = false;
    let currentFilter = 'all';

    // Load damaged reason id once
    fetchDamagedReasonId();
    
    // Filter buttons click event
    $('.filter-btn, .filter-btn-card').on('click', function() {
        const filterValue = $(this).data('filter');
        currentFilter = filterValue;
        
        // Update active button
        $('.filter-btn, .filter-btn-card').removeClass('active');
        $(this).addClass('active');
        
        // Filter cards with animation
        filterPoCards(filterValue);
    });
    
    // Filter PO cards function
    function filterPoCards(filterValue) {
        const allCards = $('.po-card-container');
        
        allCards.each(function() {
            const cardStatus = $(this).data('filter-status');
            
            if (filterValue === 'all' || cardStatus === filterValue) {
                // Show card with animation
                $(this).fadeIn(300);
                $(this).css('display', '');
            } else {
                // Hide card
                $(this).fadeOut(300);
            }
        });
    }
    
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
        const remark = $(this).data('remark') || '';
        
        console.log('Receive button clicked:', { poId, poNumber, supplier, remark });
        loadPoItems(poId, poNumber, supplier, 'receive', remark);
    });
    
    // View PO button click
    $('.view-po-btn').on('click', function() {
        const poId = $(this).data('po-id');
        const poNumber = $(this).data('po-number');
        const supplier = $(this).data('supplier');
        const remark = $(this).data('remark') || '';
        const mode = $(this).data('mode') || 'view';
        
        loadPoItems(poId, poNumber, supplier, mode, remark);
    });
    
    // Load PO items
    function loadPoItems(poId, poNumber, supplier, mode, remark) {
        console.log('loadPoItems called with:', { poId, poNumber, supplier, mode, remark });
        currentPoData = { poId, poNumber, supplier, mode, remark };
        
        $('#modalPoNumber').text(poNumber);
        $('#modalSupplier').text(supplier);
        
        // Show loading
        $('#poItemsTableBody').html(`
            <tr>
                <td colspan="12" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <div class="mt-2">กำลังโหลดข้อมูล...</div>
                </td>
            </tr>
        `);
        
        $('#poItemsModal').modal('show');
        
        // Determine which API to use based on remark
        const isNewProduct = remark && remark.toLowerCase().includes('new product');
        const apiUrl = isNewProduct ? '../api/get_po_items_new_product.php' : '../api/get_po_items.php';
        
        // Load data via AJAX
        $.ajax({
            url: apiUrl,
            method: 'GET',
            data: { po_id: poId },
            dataType: 'json',
            success: function(response) {
                console.log('API Response:', response);
                if (response.success) {
                    console.log('Items:', response.items);
                    displayPoItems(response.items, mode);
                } else {
                    console.error('API Error:', response.error);
                    $('#poItemsTableBody').html(`
                        <tr>
                            <td colspan="12" class="text-center py-4 text-danger">
                                <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                                <div>${response.error}</div>
                            </td>
                        </tr>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading PO items:', error);
                $('#poItemsTableBody').html(`
                    <tr>
                        <td colspan="12" class="text-center py-4 text-danger">
                            <span class="material-icons mb-2" style="font-size: 2rem;">error</span>
                            <div>เกิดข้อผิดพลาดในการโหลดข้อมูล</div>
                        </td>
                    </tr>
                `);
            }
        });
    }
    
    // Parse date from Thai display format back to ISO format
    function parseDateFromDisplay(dateString) {
        // Expected format: "19 พ.ค. 2567" -> "2024-11-19"
        const months = {
            'ม.ค.': '01', 'ก.พ.': '02', 'มี.ค.': '03', 'เม.ย.': '04',
            'พ.ค.': '05', 'มิ.ย.': '06', 'ก.ค.': '07', 'ส.ค.': '08',
            'ก.ย.': '09', 'ต.ค.': '10', 'พ.ย.': '11', 'ธ.ค.': '12'
        };
        
        const parts = dateString.split(' ');
        if (parts.length === 3) {
            const day = parts[0].padStart(2, '0');
            const monthTh = parts[1];
            const yearTh = parseInt(parts[2]);
            const yearAd = yearTh - 543;
            const month = months[monthTh] || '01';
            return `${yearAd}-${month}-${day}`;
        }
        return '';
    }
    
    // Update expiry date and restore display
    function updateExpiryDate(itemId, newDate, tdElement) {
        const display = tdElement.find('.expiry-display');
        
        if (newDate) {
            // Validate date
            const selectedDateObj = new Date(newDate + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDateObj < today) {
                Swal.fire({
                    icon: 'warning',
                    title: 'วันที่ผ่านมาแล้ว',
                    text: 'กรุณาเลือกวันที่ไม่เป็นอดีต',
                    timer: 2000
                });
                restoreEditButton(itemId, tdElement);
                return;
            }
            
            // Update display
            const expired = new Date(newDate + 'T23:59:59') < new Date();
            display.html(`<span class="badge ${expired ? 'bg-danger' : 'bg-info'}">${formatThaiDate(newDate)}</span>`);
        } else {
            display.html('<span class="text-muted">-</span>');
        }
        
        // Restore edit button
        restoreEditButton(itemId, tdElement);
        
        // Update receiveItems - Always save, even if no quantity
        const row = tdElement.closest('tr');
        const qtyInput = row.find('.receive-qty-input');
        const qty = parseFloat(qtyInput.val()) || 0;
        
        // Initialize if not exists
        if (!receiveItems[itemId]) {
            receiveItems[itemId] = {
                quantity: qty > 0 ? qty : 0,
                expiry_date: newDate || null
            };
        } else {
            // Update existing
            if (qty > 0) receiveItems[itemId].quantity = qty;
            receiveItems[itemId].expiry_date = newDate || null;
        }
        
        // Save expiry date immediately to database (even if no quantity)
        if (newDate !== undefined && newDate !== null) {
            saveExpiryDateOnly(itemId, newDate);
        }
    }
    
    // Save expiry date only (without quantity)
    function saveExpiryDateOnly(itemId, expiryDate) {
        $.ajax({
            url: 'process_receive_po.php',
            method: 'POST',
            data: {
                action: 'update_expiry_only',
                item_id: itemId,
                expiry_date: expiryDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกวันหมดอายุสำเร็จ',
                        text: 'อัปเดตข้อมูลวันหมดอายุแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: response.message || 'ไม่สามารถบันทึกข้อมูลได้'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Save expiry error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
                });
            }
        });
    }
    
    // Restore edit button
    function restoreEditButton(itemId, tdElement) {
        const editBtn = tdElement.find('.expiry-date-input-edit');
        if (editBtn.length > 0) {
            editBtn.replaceWith(`
                <button type="button" class="btn btn-sm btn-outline-warning edit-expiry-btn" data-item-id="${itemId}" title="แก้ไขวันหมดอายุ">
                    <span class="material-icons" style="font-size: 0.9rem;">edit</span>
                </button>
            `);
            
            // Rebind click event
            tdElement.find('.edit-expiry-btn').on('click', function(e) {
                e.stopPropagation();
                const itemId = $(this).data('item-id');
                const currentDate = $(this).closest('td').find('.expiry-display .badge').text();
                
                // Show inline date picker
                const btn = $(this);
                btn.replaceWith(`
                    <input type="date" 
                           class="form-control expiry-date-input-edit" 
                           data-item-id="${itemId}"
                           style="width: 120px; height: 32px; font-size: 0.75rem;">
                `);
                
                // Set current date in ISO format
                const display = $(this).closest('td').find('.expiry-display');
                const badge = display.find('.badge');
                if (badge.length > 0) {
                    const badgeText = badge.text().trim();
                    const isoDate = parseDateFromDisplay(badgeText);
                    $(this).closest('td').find('.expiry-date-input-edit').val(isoDate).focus();
                }
                
                // Setup save on change
                $(this).closest('td').find('.expiry-date-input-edit').on('change', function() {
                    updateExpiryDate(itemId, $(this).val(), $(this).closest('td'));
                }).on('blur', function() {
                    if (!$(this).val()) {
                        restoreEditButton(itemId, $(this).closest('td'));
                    }
                });
            });
        }
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
                // Get current expiry date if exists
                const expiryInput = $(this).closest('tr').find('.expiry-date-input');
                const expiryDate = expiryInput.val() || null;
                
                receiveItems[itemId] = {
                    quantity: qty,
                    expiry_date: expiryDate
                };
            } else {
                delete receiveItems[itemId];
            }
        });
        
        // Setup expiry date inputs
        $('.expiry-date-input').on('change', function() {
            const itemId = $(this).data('item-id');
            const selectedDate = $(this).val();
            const display = $(this).closest('td').find('.expiry-display');
            
            // Validate date
            if (selectedDate) {
                const selectedDateObj = new Date(selectedDate + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDateObj < today) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'วันที่ผ่านมาแล้ว',
                        text: 'กรุณาเลือกวันที่ไม่เป็นอดีต',
                        timer: 2000
                    });
                    $(this).val('');
                    return;
                }
                
                // Update display
                const expired = new Date(selectedDate + 'T23:59:59') < new Date();
                display.html(`<span class="badge ${expired ? 'bg-danger' : 'bg-info'}">${formatThaiDate(selectedDate)}</span>`);
            } else {
                display.html('<span class="text-muted">-</span>');
            }
            
            // Update receiveItems with expiry date
            const row = $(this).closest('tr');
            const qtyInput = row.find('.receive-qty-input');
            const qty = parseFloat(qtyInput.val()) || 0;
            
            if (qty > 0) {
                if (!receiveItems[itemId]) {
                    receiveItems[itemId] = {};
                }
                receiveItems[itemId].quantity = qty;
                receiveItems[itemId].expiry_date = selectedDate || null;
            } else {
                // Even if no quantity yet, save expiry date for later
                if (selectedDate) {
                    if (!receiveItems[itemId]) {
                        receiveItems[itemId] = {};
                    }
                    receiveItems[itemId].expiry_date = selectedDate;
                }
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
        $('#quickExpiryDate').val('');
        
        $('#quickReceiveModal').modal('show');
        
        setTimeout(() => {
            $('#quickReceiveQty').focus().select();
        }, 500);
    }
    
    // Show cancel item modal
    function showCancelItemModal(itemId, productName, remainingQty, unit) {
        const maxCancelQty = parseFloat(remainingQty);
        
        $('#cancelItemId').val(itemId);
        $('#cancelPoId').val(currentPoData.poId);
        $('#cancelProductName').text(productName);
        $('#cancelRemainingQty').html(`<strong class="text-warning">${maxCancelQty.toLocaleString()}</strong>`);
        $('#cancelUnit').text(unit || '');
        $('#cancelQtyUnit').text(unit || '');
        
        // Setup input validation
        const cancelQtyInput = $('#cancelQuantity');
        cancelQtyInput.attr('max', maxCancelQty).val('').off('input').on('input', function() {
            const value = parseFloat($(this).val()) || 0;
            const validationMsg = $('#cancelQtyValidation');
            
            if (value > maxCancelQty) {
                $(this).val(maxCancelQty);
                Swal.fire({
                    icon: 'warning',
                    title: 'จำนวนเกินกำหนด',
                    text: `จำนวนสูงสุดที่สามารถยกเลิกได้คือ ${maxCancelQty.toLocaleString()}`,
                    timer: 2000
                });
            }
            
            if (value > 0 && value <= maxCancelQty) {
                validationMsg.show();
            } else {
                validationMsg.hide();
            }
        }).focus();
        
        $('#cancelReason').val('');
        $('#cancelNotes').val('');
        
        $('#cancelItemModal').modal('show');
    }

    // Delegated handlers for dynamic action buttons
    $(document).on('click', '.quick-receive-btn', function() {
        const itemId = $(this).data('item-id');
        const productName = $(this).data('product-name');
        const orderedQty = $(this).data('ordered-qty');
        const remainingQty = $(this).data('remaining-qty');
        const unit = $(this).data('unit');

        showQuickReceiveModal(itemId, productName, orderedQty, remainingQty, unit);
    });

    $(document).on('click', '.cancel-item-btn', function() {
        const itemId = $(this).data('item-id');
        const productName = $(this).data('product-name');
        const remainingQty = $(this).data('remaining-qty');
        const unit = $(this).data('unit');

        showCancelItemModal(itemId, productName, remainingQty, unit);
    });

    $(document).on('click', '.damaged-item-btn', function() {
        const itemId = $(this).data('item-id');
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        const orderedQty = parseFloat($(this).data('ordered-qty')) || 0;
        const remainingQty = parseFloat($(this).data('remaining-qty')) || 0;
        const unit = $(this).data('unit');
        const sku = $(this).data('sku');

        if (!damagedReasonId) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบเหตุผล "สินค้าชำรุดบางส่วน"',
                text: 'กรุณาเพิ่มเหตุผลการคืนสินค้าหรือแจ้งผู้ดูแลระบบ'
            });
            return;
        }

        showDamagedItemModal({ itemId, productId, productName, orderedQty, remainingQty, unit, sku });
    });
    
    // Confirm cancel item - Reset event binding
    $(document).off('click', '#confirmCancelItem').on('click', '#confirmCancelItem', function() {
        const form = $('#cancelItemForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const itemId = $('#cancelItemId').val();
        const cancelQty = parseFloat($('#cancelQuantity').val());
        const reason = $('#cancelReason').val();
        const notes = $('#cancelNotes').val();
        
        // Validate cancel quantity
        if (cancelQty <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'จำนวนไม่ถูกต้อง',
                text: 'กรุณากรอกจำนวนที่มากกว่า 0'
            });
            return;
        }
        
        if (!reason) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาเลือกเหตุผล',
                text: 'โปรดเลือกเหตุผลในการยกเลิก'
            });
            return;
        }
        
        cancelItem(itemId, cancelQty, reason, notes);
    });
    
    // Cancel item function
    function cancelItem(itemId, cancelQty, reason, notes) {
        Swal.fire({
            title: 'ยืนยันการยกเลิกสินค้า',
            html: `คุณแน่ใจหรือว่าต้องการยกเลิก <strong>${cancelQty.toLocaleString()}</strong> หน่วย?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยันการยกเลิก',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'process_receive_po.php',
                    method: 'POST',
                    data: {
                        action: 'cancel_item',
                        po_id: currentPoData.poId,
                        item_id: itemId,
                        cancel_type: 'cancel_partial',
                        cancel_qty: cancelQty,
                        cancel_reason: reason,
                        cancel_notes: notes,
                        po_number: currentPoData.poNumber
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ยกเลิกสินค้าสำเร็จ',
                                text: response.message || 'สินค้าถูกยกเลิกแล้ว',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                $('#cancelItemModal').modal('hide');
                                // Reload PO items
                                loadPoItems(currentPoData.poId, currentPoData.poNumber, currentPoData.supplier, currentPoData.mode, currentPoData.remark);
                                // Refresh page to update statistics
                                setTimeout(() => location.reload(), 1000);
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: response.message || 'ไม่สามารถยกเลิกสินค้าได้'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Cancel item error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถยกเลิกสินค้าได้ กรุณาลองใหม่อีกครั้ง'
                        });
                    }
                });
            }
        });
    }
    
    // Save receive items (batch)
    $('#saveReceiveBtn').on('click', function() {
        const itemsToReceive = [];
        let invalidQuantity = null;
        let invalidExpiry = false;

        $('#poItemsTableBody tr').each(function() {
            const qtyInput = $(this).find('.receive-qty-input');
            if (!qtyInput.length) {
                return;
            }

            const itemId = qtyInput.data('item-id');
            const rawQty = qtyInput.val();
            const quantity = parseFloat(rawQty);
            const maxQty = parseFloat(qtyInput.data('max'));
            const expiryInput = $(this).find('.expiry-date-input');
            const expiryValue = expiryInput.length ? (expiryInput.val() || null) : null;

            if (!rawQty || quantity <= 0 || Number.isNaN(quantity)) {
                return;
            }

            if (!Number.isFinite(quantity) || (Number.isFinite(maxQty) && quantity > maxQty + 0.00001)) {
                invalidQuantity = maxQty;
                return;
            }

            if (expiryValue) {
                const selectedDateObj = new Date(expiryValue + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDateObj < today) {
                    invalidExpiry = true;
                    return;
                }
            }

            itemsToReceive.push({
                item_id: itemId,
                quantity: quantity,
                expiry_date: expiryValue
            });
        });

        if (invalidQuantity !== null) {
            const limitText = Number.isFinite(invalidQuantity) ? invalidQuantity.toLocaleString() : '';
            Swal.fire({
                icon: 'warning',
                title: 'จำนวนเกินกำหนด',
                text: limitText ? `กรุณาตรวจสอบจำนวนรับเข้าที่ระบุ (สูงสุด ${limitText})` : 'กรุณาตรวจสอบจำนวนรับเข้าที่ระบุ'
            });
            return;
        }

        if (invalidExpiry) {
            Swal.fire({
                icon: 'warning',
                title: 'วันที่หมดอายุไม่ถูกต้อง',
                text: 'กรุณาเลือกวันหมดอายุที่ไม่เป็นอดีต'
            });
            return;
        }

        if (itemsToReceive.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่พบรายการรับเข้า',
                text: 'กรุณากรอกจำนวนสินค้าที่ต้องการรับเข้า'
            });
            return;
        }

        // Sync latest values to receiveItems cache
        itemsToReceive.forEach(item => {
            receiveItems[item.item_id] = {
                quantity: item.quantity,
                expiry_date: item.expiry_date
            };
        });

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

    // Confirm damaged item
    $('#confirmDamagedItem').on('click', function() {
        submitDamagedItem();
    });
    
    // Save single receive
    function saveSingleReceive(itemId, quantity, notes) {
        const expiryDate = $('#quickExpiryDate').val();
        
        $.ajax({
            url: 'process_receive_po.php',
            method: 'POST',
            data: {
                action: 'receive_single',
                po_id: currentPoData.poId,
                item_id: itemId,
                quantity: quantity,
                notes: notes,
                expiry_date: expiryDate,
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
                        loadPoItems(currentPoData.poId, currentPoData.poNumber, currentPoData.supplier, currentPoData.mode, currentPoData.remark);
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
        // Convert items array to proper format with expiry_date
        const formattedItems = items.map(item => ({
            item_id: item.item_id,
            quantity: item.quantity || 0,
            expiry_date: item.expiry_date || null
        }));
        
        $.ajax({
            url: 'process_receive_po.php',
            method: 'POST',
            data: {
                action: 'receive_multiple',
                po_id: currentPoData.poId,
                items: JSON.stringify(formattedItems),
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
    
    // Format date to Thai format
    function formatThaiDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString + 'T00:00:00');
        const months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        const day = date.getDate();
        const month = months[date.getMonth()];
        const year = date.getFullYear() + 543;
        return `${day} ${month} ${year}`;
    }
    
    // Check if date is expired
    function isExpired(dateString) {
        if (!dateString) return false;
        const expiryDate = new Date(dateString + 'T23:59:59');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return expiryDate < today;
    }
    
    // Auto refresh every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});

// Toggle completed POs function
function toggleCompletedPOs() {
    const button = $('#toggleText');
    const tableBody = $('.table-body .row').first();
    
    if (!window.showingCompleted) {
        // Load completed POs
        button.text('กำลังโหลด...');
        
        $.ajax({
            url: 'get_completed_pos.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displayCompletedPOs(response.data);
                    button.text('ซ่อนที่รับครบแล้ว');
                    window.showingCompleted = true;
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'ไม่มีรายการ',
                        text: 'ไม่พบใบสั่งซื้อที่รับครบแล้ว',
                        timer: 2000
                    });
                    button.text('ดูที่รับครบแล้ว');
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถโหลดข้อมูลได้'
                });
                button.text('ดูที่รับครบแล้ว');
            }
        });
    } else {
        // Hide completed POs - reload page
        location.reload();
    }
}

function displayCompletedPOs(completedPOs) {
    let html = '';
    
    if (!completedPOs || completedPOs.length === 0) {
        html = `
            <div class="col-12">
                <div class="text-center py-5">
                    <span class="material-icons mb-3" style="font-size: 4rem; color: #9ca3af;">done_all</span>
                    <h5 class="text-muted">ไม่มีใบสั่งซื้อที่รับครบแล้ว</h5>
                    <p class="text-muted mb-0">กรุณากลับไปตรวจสอบสถานะอื่นๆ</p>
                </div>
            </div>
        `;
    } else {
        completedPOs.forEach(function(po) {
            const hasCancelled = parseFloat(po.total_cancelled_qty || 0) > 0;
            
            html += `
                <div class="col-sm-6 col-lg-4 col-xl-3 mb-3">
                    <div class="po-card" style="opacity: 0.85; border-left: 4px solid #10b981;">
                        <div class="po-card-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="flex: 1; min-width: 0;">
                                    <h6 class="mb-1 fw-bold" style="font-size: 0.95rem;">${escapeHtml(po.po_number)}</h6>
                                    <p class="text-muted mb-1 small" style="font-size: 0.75rem;">${escapeHtml(po.supplier_name)}</p>
                                    <div class="small text-muted">${formatDate(po.po_date)}</div>
                                </div>
                                <div class="text-end" style="margin-left: 10px;">
                                    <span class="po-status-badge status-received d-block mb-2" style="font-size: 0.7rem;">รับครบแล้ว</span>
                                    ${hasCancelled ? `<span class="material-icons" style="color: #f59e0b; font-size: 1.4rem;" title="มีรายการที่ถูกยกเลิก">warning</span>` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <button type="button" 
                                    class="btn btn-primary btn-sm w-100 view-po-btn"
                                    data-po-id="${po.po_id}"
                                    data-po-number="${escapeHtml(po.po_number)}"
                                    data-supplier="${escapeHtml(po.supplier_name)}"
                                    data-remark="${escapeHtml(po.remark || '')}"
                                    data-mode="view">
                                <span class="material-icons" style="font-size: 1rem;">visibility</span>
                                ดูรายละเอียด
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Create a new wrapper for completed POs with unique id
    const completedContainer = `<div class="row g-3" id="completedPOsRow">${html}</div>`;
    
    // Get the table-body element
    const tableBody = $('.table-body').first();
    
    // Clear existing content and add completed POs
    tableBody.html(completedContainer);
    
    // Re-bind click events
    $('.view-po-btn').off('click').on('click', function() {
        const poId = $(this).data('po-id');
        const poNumber = $(this).data('po-number');
        const supplier = $(this).data('supplier');
        const remark = $(this).data('remark') || '';
        
        console.log('Completed PO clicked:', { poId, poNumber, supplier, remark });
        loadCompletedPoItems(poId, poNumber, supplier, remark);
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}
</script>

</body>
</html>