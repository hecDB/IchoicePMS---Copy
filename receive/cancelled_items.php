<?php
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

// Get all cancelled items from all POs
$sql_cancelled = "
    SELECT 
        poi.item_id,
        poi.po_id,
        po.po_number,
        s.name as supplier_name,
        p.product_id,
        p.name as product_name,
        COALESCE(p.sku, '-') as product_code,
        poi.qty as ordered_qty,
        COALESCE(SUM(ri.receive_qty), 0) as received_qty,
        poi.cancel_qty,
        poi.is_cancelled,
        poi.is_partially_cancelled,
        poi.cancel_reason,
        poi.cancel_notes,
        poi.cancelled_at,
        COALESCE(u.name, '-') as cancelled_by_name,
        COALESCE(poi.unit, 'หน่วย') as unit_name,
        poi.unit_price,
        'THB' as currency_code
    FROM purchase_order_items poi
    LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN products p ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
    LEFT JOIN users u ON poi.cancelled_by = u.user_id
    WHERE (poi.is_cancelled = 1 OR poi.is_partially_cancelled = 1)
    GROUP BY poi.item_id
    ORDER BY poi.cancelled_at DESC
";

$stmt = $pdo->query($sql_cancelled);
$cancelled_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by PO
$grouped_by_po = [];
foreach ($cancelled_items as $item) {
    $po_id = $item['po_id'];
    if (!isset($grouped_by_po[$po_id])) {
        $grouped_by_po[$po_id] = [
            'po_number' => $item['po_number'],
            'supplier_name' => $item['supplier_name'],
            'items' => []
        ];
    }
    $grouped_by_po[$po_id]['items'][] = $item;
}

// Stats
$total_cancelled_items = count($cancelled_items);
$total_cancelled_pos = count($grouped_by_po);
$fully_cancelled_count = 0;
$partially_cancelled_count = 0;

foreach ($cancelled_items as $item) {
    if ($item['is_cancelled']) {
        $fully_cancelled_count++;
    } elseif ($item['is_partially_cancelled']) {
        $partially_cancelled_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้าที่ถูกยกเลิก - IchoicePMS</title>
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

        .page-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 12px 12px;
        }

        .cancelled-item-card {
            border: 1px solid #fee2e2;
            border-left: 4px solid #dc3545;
            border-radius: 8px;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .cancelled-item-card:hover {
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15);
        }

        .cancelled-item-header {
            padding: 1rem;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-bottom: 1px solid #fee2e2;
        }

        .cancelled-item-body {
            padding: 1rem;
        }

        .badge-cancelled {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .badge-partial {
            background: linear-gradient(135deg, #fd7e14 0%, #f76707 100%);
            color: white;
        }

        .po-group-header {
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 8px 8px 0 0;
            margin-top: 2rem;
        }

        .po-group-header:first-child {
            margin-top: 0;
        }

        .po-items-section {
            border: 1px solid #e5e7eb;
            border-radius: 0 0 8px 8px;
            padding: 1rem;
            background: white;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item-detail {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .item-detail:last-child {
            border-bottom: none;
        }

        .item-detail-label {
            color: #6b7280;
            font-weight: 600;
        }

        .item-detail-value {
            color: #1f2937;
            font-weight: 600;
        }

        .cancel-reason-box {
            background: #fef2f2;
            border-left: 4px solid #dc3545;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .item-detail {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
<?php include '../templates/sidebar.php'; ?>

<div class="mainwrap">
    <div class="page-header">
        <div class="container-fluid">
            <a href="receive_po_items.php" class="btn-back text-white mb-3">
                <span class="material-icons align-middle">arrow_back</span>
                กลับไปรับสินค้า
            </a>
            <h1 class="h3 mb-0 fw-bold">
                <span class="material-icons align-middle me-2" style="font-size: 2rem;">cancel</span>
                สินค้าที่ถูกยกเลิก
            </h1>
            <p class="text-white-50 mb-0 mt-2">รายละเอียดสินค้าที่ได้รับการยกเลิกจากใบ PO</p>
        </div>
    </div>

    <div class="container-fluid py-4">
        <?php if (empty($cancelled_items)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <span class="material-icons" style="font-size: 5rem; color: #10b981;">check_circle</span>
            </div>
            <h5 class="text-success">ไม่มีสินค้าที่ถูกยกเลิก</h5>
            <p class="text-muted">ระบบทั้งหมดปราศจากรายการสินค้าที่ยกเลิก</p>
        </div>
        <?php else: ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_cancelled_items; ?></div>
                <div class="stat-label">รวมรายการที่ยกเลิก</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $fully_cancelled_count; ?></div>
                <div class="stat-label">ยกเลิกทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $partially_cancelled_count; ?></div>
                <div class="stat-label">ยกเลิกบางจำนวน</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_cancelled_pos; ?></div>
                <div class="stat-label">ใบ PO ที่มีการยกเลิก</div>
            </div>
        </div>

        <!-- Cancelled Items by PO -->
        <?php foreach ($grouped_by_po as $po_id => $po_data): ?>
        <div class="po-group-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">
                        <span class="material-icons align-middle me-2" style="font-size: 1.25rem;">description</span>
                        <?php echo htmlspecialchars($po_data['po_number']); ?>
                    </h5>
                    <p class="text-white-50 mb-0">
                        <span class="material-icons align-middle" style="font-size: 1rem;">business</span>
                        <?php echo htmlspecialchars($po_data['supplier_name']); ?>
                    </p>
                </div>
                <span class="badge badge-light"><?php echo count($po_data['items']); ?> รายการ</span>
            </div>
        </div>

        <div class="po-items-section">
            <?php foreach ($po_data['items'] as $item): ?>
            <div class="cancelled-item-card">
                <div class="cancelled-item-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-2 fw-bold">
                                <span class="material-icons align-middle me-2" style="font-size: 1rem;">inventory_2</span>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </h6>
                            <p class="text-muted mb-0 small">รหัสสินค้า: <?php echo htmlspecialchars($item['product_code']); ?></p>
                        </div>
                        <span class="badge <?php echo $item['is_cancelled'] ? 'badge-cancelled' : 'badge-partial'; ?>">
                            <?php echo $item['is_cancelled'] ? 'ยกเลิกทั้งหมด' : 'ยกเลิกบางจำนวน'; ?>
                        </span>
                    </div>
                </div>

                <div class="cancelled-item-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="item-detail">
                                <span class="item-detail-label">จำนวนสั่ง:</span>
                                <span class="item-detail-value"><?php echo number_format((float)$item['ordered_qty'], 2); ?> <?php echo htmlspecialchars($item['unit_name']); ?></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">จำนวนรับได้:</span>
                                <span class="item-detail-value"><?php echo number_format((float)$item['received_qty'], 2); ?> <?php echo htmlspecialchars($item['unit_name']); ?></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">จำนวนยกเลิก:</span>
                                <span class="item-detail-value text-danger fw-bold"><?php echo number_format((float)$item['cancel_qty'], 2); ?> <?php echo htmlspecialchars($item['unit_name']); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="item-detail">
                                <span class="item-detail-label">ราคาต่อหน่วย:</span>
                                <span class="item-detail-value"><?php echo number_format((float)$item['unit_price'], 2); ?> <?php echo htmlspecialchars($item['currency_code']); ?></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">ยกเลิกโดย:</span>
                                <span class="item-detail-value"><?php echo htmlspecialchars($item['cancelled_by_name'] ?? '-'); ?></span>
                            </div>
                            <div class="item-detail">
                                <span class="item-detail-label">วันที่ยกเลิก:</span>
                                <span class="item-detail-value"><?php echo $item['cancelled_at'] ? date('d/m/Y H:i', strtotime($item['cancelled_at'])) : '-'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="cancel-reason-box">
                        <div class="mb-2">
                            <strong class="text-danger">เหตุผลการยกเลิก:</strong>
                            <p class="mb-1"><?php echo htmlspecialchars($item['cancel_reason'] ?? '-'); ?></p>
                        </div>
                        <?php if ($item['cancel_notes']): ?>
                        <div>
                            <strong class="text-muted">หมายเหตุ:</strong>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($item['cancel_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
