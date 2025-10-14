<?php
session_start();
require '../config/db_connect.php';

// Query สินค้าที่มีสต็อกต่ำ (น้อยกว่าหรือเท่ากับ 20) พร้อมจำนวนคงเหลือและวันหมดอายุล่าสุด
$sql = "
SELECT 
	p.product_id,
	p.name,
	p.sku,
	p.barcode,
	p.unit,
	p.image,
	MAX(ri.expiry_date) AS expiry_date,
	SUM(ri.receive_qty) AS total_qty,
	CASE 
        WHEN SUM(ri.receive_qty) = 0 THEN 'out'
        WHEN SUM(ri.receive_qty) <= 5 THEN 'critical'
        WHEN SUM(ri.receive_qty) <= 20 THEN 'low'
        ELSE 'normal'
    END as stock_status
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image  
HAVING total_qty <= 20
ORDER BY total_qty ASC, p.name ASC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'total_low_stock' => count($products),
    'critical_stock' => count(array_filter($products, fn($p) => $p['stock_status'] === 'critical')),
    'out_of_stock' => count(array_filter($products, fn($p) => $p['stock_status'] === 'out')),
    'low_stock' => count(array_filter($products, fn($p) => $p['stock_status'] === 'low'))
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<title>สินค้าสต็อกต่ำ - IchoicePMS</title>
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
        
        .stock-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }
        
        .stock-out { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
        }
        
        .stock-critical { 
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #c53030;
        }
        
        .stock-low { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #d97706;
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

        .urgent-alert {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #f87171;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
	</style>
</head>
<body>
<?php include '../templates/sidebar.php'; ?>
<div class="mainwrap">
    <div class="container-fluid py-4">


        <!-- Urgent Alert -->
        <?php if ($stats['critical_stock'] > 0 || $stats['out_of_stock'] > 0): ?>
        <div class="urgent-alert">
            <div class="d-flex align-items-center">
                <span class="material-icons text-danger me-2" style="font-size: 1.5rem;">warning</span>
                <div>
                    <h6 class="mb-1 text-danger fw-bold">แจ้งเตือนเร่งด่วน!</h6>
                    <p class="mb-0 text-danger">
                        พบสินค้าหมดสต็อก <?= $stats['out_of_stock'] ?> รายการ และสินค้าวิกฤต <?= $stats['critical_stock'] ?> รายการ ต้องเติมสต็อกด่วน
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #f59e0b;">warning</span>
                    สินค้าสต็อกต่ำ
                </h1>
                <p class="text-muted mb-0">รายการสินค้าที่ต้องเติมสต็อกด่วน (น้อยกว่าหรือเท่ากับ 20 ชิ้น)</p>
            </div>
            <div>
                <button class="btn-modern btn-modern-primary" onclick="window.location.href='../orders/purchase_orders.php'">
                    <span class="material-icons" style="font-size: 1.25rem;">shopping_cart</span>
                    สร้าง PO
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card stats-warning">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">สินค้าสต็อกต่ำ</div>
                                <div class="stats-value"><?= number_format($stats['total_low_stock']) ?></div>
                                <div class="stats-subtitle">รายการทั้งหมด</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">inventory</i>
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
                                <div class="stats-title">สินค้าหมด</div>
                                <div class="stats-value"><?= number_format($stats['out_of_stock']) ?></div>
                                <div class="stats-subtitle">0 ชิ้น</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">remove_circle</i>
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
                                <div class="stats-title">สต็อกวิกฤต</div>
                                <div class="stats-value"><?= number_format($stats['critical_stock']) ?></div>
                                <div class="stats-subtitle">1-5 ชิ้น</div>
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
                                <div class="stats-title">สต็อกต่ำ</div>
                                <div class="stats-value"><?= number_format($stats['low_stock']) ?></div>
                                <div class="stats-subtitle">6-20 ชิ้น</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">warning</i>
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
                        รายการสินค้าสต็อกต่ำ (<?= count($products) ?> รายการ)
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
                <table id="product-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>รายการสินค้า</th>
                            <th>SKU</th>
                            <th>บาร์โค้ด</th>
                            <th>จำนวนคงเหลือ</th>
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
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #10b981;">check_circle</span>
                                    <h5 class="text-success">ยอดเยี่ยม! ไม่มีสินค้าสต็อกต่ำ</h5>
                                    <p class="text-muted mb-0">สินค้าทุกรายการมีสต็อกเพียงพอ</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($products as $row): ?>
                        <tr data-id="<?= $row['product_id'] ?>">
                            <td>
                                <?php 
                                $image_path = '../images/noimg.png';
                                if (!empty($row['image'])) {
                                    if (strpos($row['image'], 'images/') === 0) {
                                        $image_path = '../' . $row['image'];
                                    } else {
                                        $image_path = '../images/' . $row['image'];
                                    }
                                }
                                ?>
                                <img src="<?= htmlspecialchars($image_path) ?>" 
                                     alt="<?= htmlspecialchars($row['name']) ?>" 
                                     class="product-image" 
                                     onerror="this.src='../images/noimg.png'">
                            </td>
                            <td>
                                <div title="<?= htmlspecialchars($row['name']) ?>">
                                    <?= htmlspecialchars($row['name']) ?>
                                </div>
                            </td>
                            <td><span class="fw-bold"><?= htmlspecialchars($row['sku']) ?></span></td>
                            <td><?= htmlspecialchars($row['barcode']) ?></td>
                            <td>
                                <span class="fw-bold text-primary"><?= number_format($row['total_qty']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['unit']) ?></td>
                            <td>
                                <?php
                                $status_text = '';
                                $status_class = '';
                                switch ($row['stock_status']) {
                                    case 'out':
                                        $status_text = 'หมดสต็อก';
                                        $status_class = 'stock-out';
                                        break;
                                    case 'critical':
                                        $status_text = 'วิกฤต';
                                        $status_class = 'stock-critical';
                                        break;
                                    case 'low':
                                        $status_text = 'สต็อกต่ำ';
                                        $status_class = 'stock-low';
                                        break;
                                }
                                ?>
                                <span class="badge stock-badge <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <?php if($row['expiry_date']): ?>
                                    <?= date('d/m/Y', strtotime($row['expiry_date'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
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
    // Initialize low stock table with modern template
    const lowStockTable = new ModernTable('product-table', {
        pageLength: 50,
        language: 'th',
        exportButtons: true,
        defaultOrder: [[4, 'asc']] // Sort by quantity
    });

    // Show alerts for critical items
    <?php if ($stats['out_of_stock'] > 0): ?>
    setTimeout(function() {
        Swal.fire({
            title: 'แจ้งเตือนสต็อกหมด!',
            text: 'พบสินค้าหมดสต็อก <?= $stats['out_of_stock'] ?> รายการ ต้องสั่งซื้อด่วน',
            icon: 'error',
            confirmButtonText: 'รับทราบ'
        });
    }, 1000);
    <?php endif; ?>

    // Auto-refresh every 3 minutes for low stock monitoring
    setInterval(function() {
        location.reload();
    }, 180000);
});
</script>
</body>
</html>
