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
        CASE 
            WHEN l.row_code IS NOT NULL AND l.bin IS NOT NULL AND l.shelf IS NOT NULL 
            THEN CONCAT(l.row_code, '-', l.bin, '-', l.shelf)
            WHEN l.description IS NOT NULL 
            THEN l.description
            ELSE 'ไม่ระบุตำแหน่ง'
        END as location_display,
        l.description as location_description,
        CASE 
            WHEN COALESCE(SUM(ri.receive_qty), 0) > 10 THEN 'high'
            WHEN COALESCE(SUM(ri.receive_qty), 0) BETWEEN 2 AND 10 THEN 'medium' 
            WHEN COALESCE(SUM(ri.receive_qty), 0) <= 1 AND COALESCE(SUM(ri.receive_qty), 0) > 0 THEN 'low'
            ELSE 'out'
        END as stock_status
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    LEFT JOIN product_location pl ON pl.product_id = p.product_id
    LEFT JOIN locations l ON l.location_id = pl.location_id
    GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image, l.row_code, l.bin, l.shelf, l.description
    HAVING total_stock >= 0
    ORDER BY p.name
";
$stmt = $pdo->query($sql_stock);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics using subquery approach
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN total_stock > 10 THEN 1 ELSE 0 END) as high_stock,
    SUM(CASE WHEN total_stock BETWEEN 2 AND 10 THEN 1 ELSE 0 END) as medium_stock,  
    SUM(CASE WHEN total_stock <= 1 AND total_stock > 0 THEN 1 ELSE 0 END) as low_stock,
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
        
        .location-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .location-code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #1f2937;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .location-description {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.125rem;
        }
        
        .no-location {
            color: #9ca3af;
            font-style: italic;
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
                            <th>ตำแหน่งเก็บ</th>
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
                                        // Try different path variations
                                        $possible_paths = [];
                                        
                                        // Check if image already includes 'images/' prefix
                                        if (strpos($product['image'], 'images/') === 0) {
                                            $possible_paths[] = '../' . $product['image'];
                                        } else {
                                            $possible_paths[] = '../images/' . $product['image'];
                                        }
                                        
                                        // Also try direct path
                                        $possible_paths[] = '../' . $product['image'];
                                        
                                        // Check which file actually exists
                                        foreach ($possible_paths as $path) {
                                            if (file_exists($path)) {
                                                $image_path = $path;
                                                break;
                                            }
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
                                <td>
                                    <div class="location-info">
                                        <span class="material-icons text-info" style="font-size: 1.1rem;">place</span>
                                        <div>
                                            <?php if ($product['location_display'] === 'ไม่ระบุตำแหน่ง'): ?>
                                                <span class="no-location"><?= htmlspecialchars($product['location_display']) ?></span>
                                            <?php else: ?>
                                                <div class="location-code"><?= htmlspecialchars($product['location_display']) ?></div>
                                                <?php if (!empty($product['location_description']) && $product['location_description'] != $product['location_display']): ?>
                                                    <div class="location-description"><?= htmlspecialchars($product['location_description']) ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
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
