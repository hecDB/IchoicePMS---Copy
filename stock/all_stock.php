<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// ====== สินค้าคงคลังทั้งหมด ======
$sql_stock = "
    SELECT 
        p.product_id,
        p.name,
        p.sku,
        p.barcode,
        p.unit,
        p.image,
        COALESCE(SUM(ri.receive_qty), 0) AS total_stock,
        GROUP_CONCAT(DISTINCT ri.expiry_date ORDER BY ri.expiry_date SEPARATOR ', ') as expiry_dates,
        CASE 
            WHEN COALESCE(SUM(ri.receive_qty), 0) > 100 THEN 'high'
            WHEN COALESCE(SUM(ri.receive_qty), 0) >= 20 THEN 'medium' 
            WHEN COALESCE(SUM(ri.receive_qty), 0) > 0 THEN 'low'
            ELSE 'out'
        END as stock_status
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image
    HAVING total_stock >= 0
    ORDER BY p.name
";
$stmt = $pdo->query($sql_stock);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics using subquery approach
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN total_stock > 100 THEN 1 ELSE 0 END) as high_stock,
    SUM(CASE WHEN total_stock BETWEEN 20 AND 100 THEN 1 ELSE 0 END) as medium_stock,  
    SUM(CASE WHEN total_stock BETWEEN 1 AND 19 THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN total_stock = 0 THEN 1 ELSE 0 END) as out_stock
FROM (
    SELECT 
        p.product_id,
        COALESCE(SUM(ri.receive_qty), 0) AS total_stock
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    GROUP BY p.product_id
) stock_summary";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_products' => 0, 'high_stock' => 0, 'medium_stock' => 0, 'low_stock' => 0, 'out_stock' => 0];

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้าคงคลังทั้งหมด - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
        }
        
        .stock-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }
        
        .stock-low { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
        }
        
        .stock-medium { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #d97706;
        }
        
        .stock-high { 
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #16a34a;
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
    </style>
</head>
<body>

<div class="mainwrap">
    <div class="container-fluid py-4">
       

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #10b981;">inventory_2</span>
                    สินค้าคงคลังทั้งหมด
                </h1>
                <p class="text-muted mb-0">ภาพรวมสินค้าคงคลังทั้งหมดในระบบ</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-primary">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">สินค้าทั้งหมด</div>
                                <div class="stats-value"><?= number_format(count($products)) ?></div>
                                <div class="stats-subtitle">รายการในคลัง</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">inventory</i>
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
                                <div class="stats-title">สต็อกเพียงพอ</div>
                                <div class="stats-value"><?= number_format(array_sum(array_filter($products, fn($p) => $p['stock_status'] === 'high'))) ?></div>
                                <div class="stats-subtitle">มากกว่า 100 ชิ้น</div>
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
                                <div class="stats-title">สต็อกปานกลาง</div>
                                <div class="stats-value"><?= number_format(array_sum(array_filter($products, fn($p) => $p['stock_status'] === 'medium'))) ?></div>
                                <div class="stats-subtitle">20-100 ชิ้น</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">warning</i>
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
                                <div class="stats-title">สต็อกต่ำ</div>
                                <div class="stats-value"><?= number_format(array_sum(array_filter($products, fn($p) => $p['stock_status'] === 'low'))) ?></div>
                                <div class="stats-subtitle">น้อยกว่า 20 ชิ้น</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">remove_circle</i>
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
                        สินค้าคงคลัง (<?= count($products) ?> รายการ)
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
                <table id="all-stock-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>บาร์โค้ด</th>
                            <th>จำนวนคงคลัง</th>
                            <th>หน่วย</th>
                            <th>สถานะ</th>
                            <th>วันหมดอายุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #d1d5db;">inventory</span>
                                    <h5 class="text-muted">ไม่พบข้อมูลสินค้าคงคลัง</h5>
                                    <p class="text-muted mb-0">ยังไม่มีการรับสินค้าเข้าสต็อก</p>
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
                                <td><?= htmlspecialchars($product['barcode']) ?></td>
                                <td><span class="fw-bold text-primary"><?= number_format($product['total_stock']) ?></span></td>
                                <td><?= htmlspecialchars($product['unit']) ?></td>
                                <td>
                                    <?php
                                    $status_text = '';
                                    $status_class = '';
                                    switch ($product['stock_status']) {
                                        case 'high':
                                            $status_text = 'สต็อกเพียงพอ';
                                            $status_class = 'stock-high';
                                            break;
                                        case 'medium':
                                            $status_text = 'สต็อกปานกลาง';
                                            $status_class = 'stock-medium';
                                            break;
                                        case 'low':
                                            $status_text = 'สต็อกต่ำ';
                                            $status_class = 'stock-low';
                                            break;
                                    }
                                    ?>
                                    <span class="badge stock-badge <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td><?= htmlspecialchars($product['expiry_dates'] ?? 'ไม่ได้กำหนด') ?></td>
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
    // Initialize stock table with modern template
    const stockTable = new ModernTable('all-stock-table', {
        pageLength: 25,
        language: 'th',
        exportButtons: true,
        defaultOrder: [[1, 'asc']] // Sort by product name
    });

    // Auto-refresh every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});
</script>

</body>
</html>
