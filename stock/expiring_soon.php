<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// ====== สินค้าใกล้หมดอายุ (ใน 300 วัน) ======
$three_hundred_days_later = date('Y-m-d', strtotime('+300 days'));
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
        po.po_number,
        DATEDIFF(ri.expiry_date, CURDATE()) as days_to_expire,
        CASE 
            WHEN DATEDIFF(ri.expiry_date, CURDATE()) < 240 THEN 'critical'
            WHEN DATEDIFF(ri.expiry_date, CURDATE()) < 270 THEN 'warning'
            WHEN DATEDIFF(ri.expiry_date, CURDATE()) <= 300 THEN 'caution'
            ELSE 'normal'
        END as expiry_status
    FROM products p
    LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
    LEFT JOIN product_holding ph ON ph.receive_id = ri.receive_id AND ph.status = 'holding'
    LEFT JOIN product_holding ph_moved ON ph_moved.product_id = p.product_id AND ph_moved.status = 'moved_to_sale'
    LEFT JOIN purchase_orders po ON ri.po_id = po.po_id
    WHERE ri.expiry_date IS NOT NULL 
        AND ri.expiry_date BETWEEN ? AND ?
        AND ri.receive_qty > 0
        AND ph.receive_id IS NULL
        AND ph_moved.holding_id IS NULL
    ORDER BY ri.expiry_date ASC, p.name ASC
";
$stmt = $pdo->prepare($sql_expiring_soon);
$stmt->execute([$today, $three_hundred_days_later]);
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

        .stats-card-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        @media (min-width: 1200px) {
            .stats-card-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        .stats-card-item {
            display: flex;
        }

        .stats-card.filter-card {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            flex: 1 1 auto;
        }

        .stats-card.filter-card .stats-card-body {
            padding: 1rem 1.25rem;
        }

        .stats-card.filter-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }

        .stats-card.filter-card.active {
            outline: 3px solid rgba(59, 130, 246, 0.35);
            box-shadow: 0 16px 32px rgba(37, 99, 235, 0.2);
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

         .mainwrap .table-body {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        }

        .mainwrap .table-body table {
            min-width: 960px;
        }

        @media (max-width: 768px) {
            .mainwrap .table-body table {
                min-width: 720px;
            }
        }

        @media (max-width: 576px) {
            .mainwrap .table-body table {
                min-width: 680px;
            }
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
                        พบสินค้าที่จะหมดอายุภายใน 240 วัน จำนวน <?= $stats['critical'] ?> รายการ ต้องดำเนินการด่วน
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
                <p class="text-muted mb-0">รายการสินค้าที่จะหมดอายุภายใน 300 วัน (ประมาณ 10 เดือน)</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-card-grid mb-4">
            <div class="stats-card-item">
                <div class="stats-card stats-warning filter-card active" data-filter="all" data-search="" role="button" tabindex="0" aria-pressed="true">
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

            <div class="stats-card-item">
                <div class="stats-card stats-danger filter-card" data-filter="critical" data-search="วิกฤต" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">วิกฤต</div>
                                <div class="stats-value"><?= number_format($stats['critical']) ?></div>
                                <div class="stats-subtitle">น้อยกว่า 240 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">priority_high</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-card-item">
                <div class="stats-card stats-warning filter-card" data-filter="warning" data-search="เตือน" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">ใกล้หมดอายุ</div>
                                <div class="stats-value"><?= number_format($stats['warning']) ?></div>
                                <div class="stats-subtitle">240-269 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">warning</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-card-item">
                <div class="stats-card stats-info filter-card" data-filter="caution" data-search="ระวัง" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">ต้องติดตาม</div>
                                <div class="stats-value"><?= number_format($stats['caution']) ?></div>
                                <div class="stats-subtitle">270-300 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">info</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-card-item">
                <div class="stats-card stats-success filter-card" data-filter="normal" data-search="ปกติ" role="button" tabindex="0" aria-pressed="false">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">ปลอดภัย</div>
                                <div class="stats-value"><?= number_format($stats['normal']) ?></div>
                                <div class="stats-subtitle">มากกว่า 300 วัน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">check_circle</i>
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
                        <?php if (!empty($products)): ?>
                        <button class="btn-modern btn-modern-danger btn-sm" id="bulk-promotion-btn" style="display: none;">
                            <span class="material-icons">local_offer</span>
                            สร้างโปรโมชั่นขาย
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <table id="expiring-soon-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all-products" class="form-check-input">
                            </th>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>จำนวนคงคลัง</th>
                            <th>หน่วย</th>
                            <th>เลขที่ PO</th>
                            <th>วันหมดอายุ</th>
                            <th>เหลืออีก (วัน)</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #10b981;">check_circle</span>
                                    <h5 class="text-success">ยอดเยี่ยม! ไม่มีสินค้าใกล้หมดอายุ</h5>
                                    <p class="text-muted mb-0">สินค้าทั้งหมดยังไม่หมดอายุใน 300 วันข้างหน้า</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr data-id="<?= $product['product_id'] ?>" data-sku="<?= htmlspecialchars($product['sku']) ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-stock="<?= $product['stock_on_hand'] ?>" data-expiry="<?= $product['expiry_date'] ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input product-checkbox" value="<?= $product['product_id'] ?>">
                                </td>
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
                                <td><?= $product['po_number'] ? htmlspecialchars($product['po_number']) : '-' ?></td>
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
                                            $status_text = 'วิกฤต (<240 วัน)';
                                            $status_class = 'expiry-critical';
                                            break;
                                        case 'warning':
                                            $status_text = 'เตือน (240-269 วัน)';
                                            $status_class = 'expiry-warning';
                                            break;
                                        case 'caution':
                                            $status_text = 'ระวัง (270-300 วัน)';
                                            $status_class = 'expiry-caution';
                                            break;
                                        case 'normal':
                                            $status_text = 'ปกติ (>300 วัน)';
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
    // ========== Checkbox Selection Functionality ==========
    
    // Prevent ModernTable from initializing if we have checkboxes
    // Initialize expiring products table with modern template
    const expiringTable = new ModernTable('expiring-soon-table', {
        pageLength: 25,
        language: 'th',
        exportButtons: true,
        batchOperations: false, // Disable auto checkboxes (using manual checkboxes)
        defaultOrder: [[8, 'asc']], // Sort by days to expire (column index 8 - days remaining)
        columnDefs: [
            { orderable: false, targets: 0 } // Disable sorting on checkbox column
        ]
    });

    // Select all checkbox - bind AFTER DataTable initialization
    $(document).off('change', '#select-all-products').on('change', '#select-all-products', function() {
        const isChecked = $(this).is(':checked');
        $('.product-checkbox').prop('checked', isChecked);
        updateBulkActionButton();
    });
    
    // Individual product checkbox - use event delegation
    $(document).off('change', '.product-checkbox').on('change', '.product-checkbox', function() {
        updateBulkActionButton();
        
        // Update select all checkbox state
        const totalCheckboxes = $('.product-checkbox').length;
        const checkedCheckboxes = $('.product-checkbox:checked').length;
        $('#select-all-products').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
    });
    
    // Update bulk action button visibility
    function updateBulkActionButton() {
        const selectedCount = $('.product-checkbox:checked').length;
        if (selectedCount > 0) {
            $('#bulk-promotion-btn').show();
        } else {
            $('#bulk-promotion-btn').hide();
        }
    }
    
    // Bulk promotion button click
    $(document).off('click', '#bulk-promotion-btn').on('click', '#bulk-promotion-btn', function() {
        const selectedProducts = [];
        $('.product-checkbox:checked').each(function() {
            const row = $(this).closest('tr');
            selectedProducts.push({
                product_id: row.data('id'),
                sku: row.data('sku'),
                name: row.data('name'),
                stock: row.data('stock'),
                expiry_date: row.data('expiry')
            });
        });
        
        if (selectedProducts.length === 0) {
            Swal.fire('ข้อผิดพลาด', 'กรุณาเลือกสินค้าอย่างน้อย 1 รายการ', 'error');
            return;
        }
        
        showPromotionModal(selectedProducts);
    });
    
    // Show promotion modal
    function showPromotionModal(products) {
        let productsList = '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem;">';
        
        let totalStock = 0;
        products.forEach(product => {
            totalStock += parseInt(product.stock) || 0;
            productsList += `
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom: 1px solid #f0f0f0;">
                    <div>
                        <strong>${product.name}</strong><br>
                        <small class="text-muted">SKU: ${product.sku} | จำนวน: ${product.stock} | หมดอายุ: ${new Date(product.expiry_date).toLocaleDateString('th-TH')}</small>
                    </div>
                </div>
            `;
        });
        productsList += '</div>';
        
        Swal.fire({
            title: '📦 สร้างโปรโมชั่นขายสินค้าใกล้หมดอายุ',
            html: `
                <div style="text-align: left;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">สินค้าที่เลือก (${products.length} รายการ)</label>
                        ${productsList}
                    </div>
                    
                    <div class="mb-3">
                        <label for="promotion_name" class="form-label">ชื่อโปรโมชั่น</label>
                        <input type="text" class="form-control" id="promotion_name" placeholder="เช่น โปรขาย clearance" value="โปรโมชั่นสินค้าใกล้หมดอายุ">
                    </div>
                    
                    <div class="mb-3">
                        <label for="promo_discount" class="form-label">ส่วนลด (%)</label>
                        <input type="number" class="form-control" id="promo_discount" min="0" max="100" value="20" placeholder="กรอกเปอร์เซ็นต์ส่วนลด">
                    </div>
                    
                    <div class="mb-3">
                        <label for="promo_reason" class="form-label">เหตุผลโปรโมชั่น</label>
                        <textarea class="form-control" id="promo_reason" rows="2" placeholder="เช่น สินค้าใกล้หมดอายุต้องรีบขาย">ใกล้หมดอายุ - ต้องรีบขาย</textarea>
                    </div>
                    
                    <div class="alert alert-info" role="alert">
                        <small><strong>📌 หมายเหตุ:</strong> สินค้าจะถูกพักไว้ในตารางรอคอยการแก้ไข SKU เพื่อเตรียมขายใหม่</small>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '✅ สร้างโปรโมชั่นและพักสินค้า',
            cancelButtonText: '❌ ยกเลิก',
            confirmButtonColor: '#dc3545',
            width: '600px',
            preConfirm: () => {
                const promoName = document.getElementById('promotion_name').value;
                const promoDiscount = parseFloat(document.getElementById('promo_discount').value) || 0;
                const promoReason = document.getElementById('promo_reason').value;
                
                if (!promoName) {
                    Swal.showValidationMessage('กรุณากรอกชื่อโปรโมชั่น');
                    return false;
                }
                
                if (promoDiscount < 0 || promoDiscount > 100) {
                    Swal.showValidationMessage('ส่วนลดต้องอยู่ระหว่าง 0-100%');
                    return false;
                }
                
                return { promoName, promoDiscount, promoReason };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                createPromotionAndIssue(products, result.value);
            }
        });
    }
    
    // Create promotion and issue products
    function createPromotionAndIssue(products, promoData) {
        Swal.fire({
            title: 'กำลังสร้างโปรโมชั่น...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        // Prepare data
        const promotionData = {
            promo_name: promoData.promoName,
            promo_discount: promoData.promoDiscount,
            promo_reason: promoData.promoReason,
            products: products
        };
        
        // Send to backend to create promotion and issue items
        $.ajax({
            url: '../api/create_promotion_clearance.php',
            method: 'POST',
            dataType: 'json',
            data: JSON.stringify(promotionData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สร้างโปรโมชั่นสำเร็จ!',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>🏷️ ชื่อโปรโมชั่น:</strong> ${response.promo_name}</p>
                                <p><strong>📦 จำนวนสินค้า:</strong> ${response.item_count} รายการ</p>
                                <p><strong>💰 ส่วนลด:</strong> ${response.promo_discount}%</p>
                                <p class="text-muted mb-0"><small>✅ สินค้าได้ถูกพักไว้ในตารางรอการแก้ไข SKU และนำกลับขายใหม่</small></p>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'ปิด'
                    }).then(() => {
                        // Refresh page to update table
                        location.reload();
                    });
                } else {
                    Swal.fire('ข้อผิดพลาด', response.message || 'ไม่สามารถสร้างโปรโมชั่นได้', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
                let errorMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch (e) {
                    // Use default message
                }
                Swal.fire('ข้อผิดพลาด', errorMsg, 'error');
            }
        });
    }

    const statusColumnIndex = 9;

    function setActiveCard($card) {
        $('.filter-card').removeClass('active').attr('aria-pressed', 'false');
        if ($card && $card.length) {
            $card.addClass('active').attr('aria-pressed', 'true');
        }
    }

    function applyStatusFilter(filterKey) {
        const $targetCard = $(`.filter-card[data-filter="${filterKey}"]`);
        const searchTerm = ($targetCard.data('search') || '').toString();

        if (searchTerm) {
            expiringTable.table.column(statusColumnIndex).search(searchTerm, false, true).draw();
        } else {
            expiringTable.table.column(statusColumnIndex).search('', false, true).draw();
        }

        setActiveCard($targetCard.length ? $targetCard : $('.filter-card[data-filter="all"]'));
    }

    $(document).on('click', '.filter-card', function() {
        const filterKey = $(this).data('filter') || 'all';
        applyStatusFilter(filterKey);
    });

    $(document).on('keydown', '.filter-card', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const filterKey = $(this).data('filter') || 'all';
            applyStatusFilter(filterKey);
        }
    });

    // Auto-filter to critical items if coming from dashboard notification
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('filter')) {
        applyStatusFilter(urlParams.get('filter'));
    } else {
        applyStatusFilter('all');
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
