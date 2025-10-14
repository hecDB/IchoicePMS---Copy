<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// ====== สินค้าใกล้หมดอายุ (ใน 90 วัน) ======
$ninety_days_later = date('Y-m-d', strtotime('+90 days'));
$today = date('Y-m-d');

$sql_expiring_soon = "
    SELECT 
        p.product_id,
        p.name,
        p.sku,
        p.barcode,
        p.unit,
        p.image,
        ri.receive_qty AS stock_on_hand,
        ri.expiry_date,
        DATEDIFF(ri.expiry_date, CURDATE()) as days_to_expire,
        CASE 
            WHEN DATEDIFF(ri.expiry_date, CURDATE()) <= 7 THEN 'critical'
            WHEN DATEDIFF(ri.expiry_date, CURDATE()) <= 30 THEN 'warning'
            WHEN DATEDIFF(ri.expiry_date, CURDATE()) <= 60 THEN 'caution'
            ELSE 'normal'
        END as expiry_status
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    WHERE ri.expiry_date IS NOT NULL 
      AND ri.expiry_date BETWEEN ? AND ?
      AND ri.receive_qty > 0
    ORDER BY ri.expiry_date ASC, p.name ASC
";
$stmt = $pdo->prepare($sql_expiring_soon);
$stmt->execute([$today, $ninety_days_later]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total_expiring' => count($products),
    'critical' => count(array_filter($products, fn($p) => $p['expiry_status'] === 'critical')),
    'warning' => count(array_filter($products, fn($p) => $p['expiry_status'] === 'warning')),
    'caution' => count(array_filter($products, fn($p) => $p['expiry_status'] === 'caution')),
    'normal' => count(array_filter($products, fn($p) => $p['expiry_status'] === 'normal'))
];

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้าใกล้หมดอายุ - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
        }
        
        .expiry-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }
        
        .expiry-critical { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
            animation: pulse 2s infinite;
        }
        
        .expiry-warning { 
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #c53030;
        }
        
        .expiry-caution { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #d97706;
        }
        
        .expiry-normal { 
            background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%);
            color: #0277bd;
        }

        .days-remaining {
            font-weight: 700;
            font-size: 1rem;
        }

        .days-critical { color: #dc2626; }
        .days-warning { color: #c53030; }
        .days-caution { color: #d97706; }
        .days-normal { color: #0277bd; }

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

        .urgent-alert {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #f87171;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>

<div class="mainwrap">
    <div class="container-fluid py-4">
       

        <!-- Critical Alert -->
        <?php if ($stats['critical'] > 0): ?>
        <div class="urgent-alert">
            <div class="d-flex align-items-center">
                <span class="material-icons text-danger me-2" style="font-size: 1.5rem;">warning</span>
                <div>
                    <h6 class="mb-1 text-danger fw-bold">สินค้าวิกฤต!</h6>
                    <p class="mb-0 text-danger">
                        พบสินค้าที่หมดอายุใน 7 วันข้างหน้า จำนวน <?= $stats['critical'] ?> รายการ ต้องดำเนินการด่วน
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #f59e0b;">schedule</span>
                    สินค้าใกล้หมดอายุ
                </h1>
                <p class="text-muted mb-0">รายการสินค้าที่จะหมดอายุภายใน 90 วัน</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-warning">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">สินค้าใกล้หมดอายุ</div>
                                <div class="stats-value"><?= number_format($stats['total_expiring']) ?></div>
                                <div class="stats-subtitle">รายการทั้งหมด</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">schedule</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-danger">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">วิกฤต</div>
                                <div class="stats-value"><?= number_format($stats['critical']) ?></div>
                                <div class="stats-subtitle">≤ 7 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">priority_high</i>
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
                                <div class="stats-title">ใกล้หมดอายุ</div>
                                <div class="stats-value"><?= number_format($stats['warning']) ?></div>
                                <div class="stats-subtitle">8-30 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">warning</i>
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
                                <div class="stats-title">ต้องติดตาม</div>
                                <div class="stats-value"><?= number_format($stats['caution']) ?></div>
                                <div class="stats-subtitle">31-90 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">info</i>
                            </div>
                        </div>
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
                        รายการสินค้าใกล้หมดอายุ (<?= count($products) ?> รายการ)
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
                <table id="expiring-soon-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>จำนวนคงคลัง</th>
                            <th>หน่วย</th>
                            <th>วันหมดอายุ</th>
                            <th>เหลืออีก (วัน)</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #10b981;">check_circle</span>
                                    <h5 class="text-success">ยอดเยี่ยม! ไม่มีสินค้าใกล้หมดอายุ</h5>
                                    <p class="text-muted mb-0">สินค้าทั้งหมดยังไม่หมดอายุใน 90 วันข้างหน้า</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr data-id="<?= $product['product_id'] ?>">
                                <td>
                                    <?php 
                                    $image_path = '../images/noimg.png'; // Default
                                    if (!empty($product['image'])) {
                                        if (strpos($product['image'], 'images/') === 0) {
                                            $image_path = '../' . $product['image'];
                                        } else {
                                            $image_path = '../images/' . $product['image'];
                                        }
                                    }
                                    ?>
                                    <img src="<?= htmlspecialchars($image_path) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                         class="product-image" 
                                         onerror="this.src='../images/noimg.png'">
                                </td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><span class="fw-bold"><?= htmlspecialchars($product['sku']) ?></span></td>
                                <td><span class="fw-bold text-primary"><?= number_format($product['stock_on_hand']) ?></span></td>
                                <td><?= htmlspecialchars($product['unit']) ?></td>
                                <td>
                                    <?= date("d/m/Y", strtotime($product['expiry_date'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $days_class = '';
                                    switch ($product['expiry_status']) {
                                        case 'critical':
                                            $days_class = 'days-critical';
                                            break;
                                        case 'warning':
                                            $days_class = 'days-warning';
                                            break;
                                        case 'caution':
                                            $days_class = 'days-caution';
                                            break;
                                        case 'normal':
                                            $days_class = 'days-normal';
                                            break;
                                    }
                                    ?>
                                    <span class="days-remaining <?= $days_class ?>"><?= $product['days_to_expire'] ?> วัน</span>
                                </td>
                                <td>
                                    <?php
                                    $status_text = '';
                                    $status_class = '';
                                    switch ($product['expiry_status']) {
                                        case 'critical':
                                            $status_text = 'วิกฤต';
                                            $status_class = 'expiry-critical';
                                            break;
                                        case 'warning':
                                            $status_text = 'เตือน';
                                            $status_class = 'expiry-warning';
                                            break;
                                        case 'caution':
                                            $status_text = 'ระวัง';
                                            $status_class = 'expiry-caution';
                                            break;
                                        case 'normal':
                                            $status_text = 'ปกติ';
                                            $status_class = 'expiry-normal';
                                            break;
                                    }
                                    ?>
                                    <span class="badge expiry-badge <?= $status_class ?>"><?= $status_text ?></span>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="../assets/modern-table.js"></script>

<script>
$(document).ready(function() {
    // Initialize expiring products table with modern template
    const expiringTable = new ModernTable('expiring-soon-table', {
        pageLength: 25,
        language: 'th',
        exportButtons: true,
        defaultOrder: [[6, 'asc']] // Sort by days to expire
    });

    // Auto-filter to critical items if coming from dashboard notification
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filter') === 'critical') {
        expiringTable.search('วิกฤต');
    }

    // Auto-refresh every 60 minutes for expiry monitoring
    setInterval(function() {
        location.reload();
    }, 3600000);

    // Add color coding to rows based on expiry status
    $('#expiring-soon-table tbody tr').each(function() {
        const statusBadge = $(this).find('.expiry-badge');
        if (statusBadge.hasClass('expiry-critical')) {
            $(this).addClass('table-danger');
        } else if (statusBadge.hasClass('expiry-warning')) {
            $(this).addClass('table-warning');
        }
    });
});
</script>

</body>
</html>
