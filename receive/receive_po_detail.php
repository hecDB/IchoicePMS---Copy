<?php
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

$po_id = $_GET['po_id'] ?? null;
if (!$po_id) {
    header('Location: receive_po_items.php');
    exit;
}

// Get Purchase Order details
$sql_po = "
    SELECT 
        po.po_id,
        po.po_number,
        s.name as supplier_name,
        po.order_date as po_date,
        NULL as expected_delivery_date,
        po.total_amount,
        c.code as currency_code,
        po.remark,
        po.status
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN currencies c ON po.currency_id = c.currency_id
    WHERE po.po_id = ? AND po.status IN ('pending', 'partial')
";

$stmt = $pdo->prepare($sql_po);
$stmt->execute([$po_id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    header('Location: receive_po_items.php');
    exit;
}

// Get Purchase Order Items with receive status
$sql_items = "
    SELECT 
        poi.item_id,
        p.product_id,
        p.name,
        p.sku,
        p.barcode,
        p.unit,
        p.image,
        poi.quantity,
        poi.price_per_unit,
        poi.sale_price,
        COALESCE(SUM(ri.receive_qty), 0) as received_qty,
        poi.remark
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.product_id
    LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
    WHERE poi.po_id = ?
    GROUP BY poi.item_id, p.product_id, p.name, p.sku, p.barcode, p.unit, p.image, poi.quantity, poi.price_per_unit, poi.sale_price, poi.remark
    ORDER BY p.name ASC
";

$stmt = $pdo->prepare($sql_items);
$stmt->execute([$po_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_items = count($items);
$completed_items = 0;
$pending_items = 0;

foreach ($items as $item) {
    if ($item['received_qty'] >= $item['quantity']) {
        $completed_items++;
    } else {
        $pending_items++;
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รับสินค้า - <?= htmlspecialchars($po['po_number']) ?> - IchoicePMS</title>
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

        .po-header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .product-image {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }

        .quantity-input {
            width: 100px;
            text-align: center;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.5rem;
            font-weight: 600;
        }

        .quantity-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }

        .status-pending {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
        }

        .status-partial {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #d97706;
        }

        .status-complete {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
        }

        .receive-btn {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .receive-btn:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            color: white;
            transform: translateY(-1px);
        }

        .receive-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
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

        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #059669 0%, #10b981 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
    </style>
</head>

<body>
<?php include '../templates/sidebar.php'; ?>

<div class="mainwrap">
    <div class="container-fluid py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb breadcrumb-modern">
                <li class="breadcrumb-item">
                    <a href="../dashboard.php" class="text-decoration-none">
                        <span class="material-icons align-middle me-1" style="font-size: 1rem;">home</span>
                        หน้าแรก
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="receive_po_items.php" class="text-decoration-none">
                        <span class="material-icons align-middle me-1" style="font-size: 1rem;">input</span>
                        รับเข้าสินค้า
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($po['po_number']) ?></li>
            </ol>
        </nav>

        <!-- PO Header -->
        <div class="po-header-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <span class="material-icons me-2" style="font-size: 2rem;">receipt_long</span>
                        <div>
                            <h2 class="mb-0 fw-bold"><?= htmlspecialchars($po['po_number']) ?></h2>
                            <p class="mb-0 opacity-90"><?= htmlspecialchars($po['supplier_name']) ?></p>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="small opacity-75">วันที่สั่งซื้อ</div>
                            <div class="fw-semibold"><?= date('d/m/Y', strtotime($po['po_date'])) ?></div>
                        </div>
                        <?php if ($po['expected_delivery_date']): ?>
                        <div class="col-sm-6">
                            <div class="small opacity-75">กำหนดส่ง</div>
                            <div class="fw-semibold"><?= date('d/m/Y', strtotime($po['expected_delivery_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4 text-md-end">
                    <div class="mb-2">
                        <div class="small opacity-75">ความคืบหน้า</div>
                        <div class="h4 mb-2"><?= $completed_items ?> / <?= $total_items ?> รายการ</div>
                    </div>
                    <div class="progress-bar-custom mb-2">
                        <?php $progress = $total_items > 0 ? ($completed_items / $total_items) * 100 : 0; ?>
                        <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                    </div>
                    <div class="small opacity-75"><?= round($progress) ?>% เสร็จสิ้น</div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <div class="stats-card stats-primary">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รายการทั้งหมด</div>
                                <div class="stats-value"><?= number_format($total_items) ?></div>
                                <div class="stats-subtitle">สินค้าใน PO</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">inventory_2</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="stats-card stats-success">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">รับครบแล้ว</div>
                                <div class="stats-value"><?= number_format($completed_items) ?></div>
                                <div class="stats-subtitle">สินค้าที่รับครบ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">check_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-3">
                <div class="stats-card stats-warning">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">ยังไม่ครบ</div>
                                <div class="stats-value"><?= number_format($pending_items) ?></div>
                                <div class="stats-subtitle">ต้องรับเพิ่ม</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">pending</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการสินค้าในใบสั่งซื้อ
                    </h5>
                    <div class="table-actions">
                        <button id="receive-all-btn" class="btn-modern btn-modern-success btn-sm me-2">
                            <span class="material-icons">done_all</span>
                            รับทั้งหมด
                        </button>
                        <button class="btn-modern btn-modern-secondary btn-sm" onclick="location.reload()">
                            <span class="material-icons">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <table id="po-items-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>หน่วย</th>
                            <th>สั่งซื้อ</th>
                            <th>รับแล้ว</th>
                            <th>คงเหลือ</th>
                            <th>รับครั้งนี้</th>
                            <th>สถานะ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <?php
                        $remaining = $item['quantity'] - $item['received_qty'];
                        $status_class = '';
                        $status_text = '';
                        
                        if ($item['received_qty'] == 0) {
                            $status_class = 'status-pending';
                            $status_text = 'ยังไม่ได้รับ';
                        } elseif ($item['received_qty'] < $item['quantity']) {
                            $status_class = 'status-partial';
                            $status_text = 'รับบางส่วน';
                        } else {
                            $status_class = 'status-complete';
                            $status_text = 'รับครบแล้ว';
                        }
                        ?>
                        <tr data-item-id="<?= $item['item_id'] ?>">
                            <td>
                                <?php 
                                $image_path = '../images/noimg.png';
                                if (!empty($item['image'])) {
                                    if (strpos($item['image'], 'images/') === 0) {
                                        $image_path = '../' . $item['image'];
                                    } else {
                                        $image_path = '../images/' . $item['image'];
                                    }
                                }
                                ?>
                                <img src="<?= htmlspecialchars($image_path) ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>" 
                                     class="product-image" 
                                     onerror="this.src='../images/noimg.png'">
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($item['barcode']) ?></div>
                            </td>
                            <td><span class="fw-bold"><?= htmlspecialchars($item['sku']) ?></span></td>
                            <td><?= htmlspecialchars($item['unit']) ?></td>
                            <td><span class="fw-bold text-primary"><?= number_format($item['quantity']) ?></span></td>
                            <td><span class="fw-bold text-success"><?= number_format($item['received_qty']) ?></span></td>
                            <td>
                                <?php if ($remaining > 0): ?>
                                <span class="fw-bold text-warning"><?= number_format($remaining) ?></span>
                                <?php else: ?>
                                <span class="fw-bold text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" 
                                       class="quantity-input receive-qty-input" 
                                       min="0" 
                                       max="<?= $remaining ?>" 
                                       value="<?= $remaining > 0 ? $remaining : 0 ?>"
                                       data-item-id="<?= $item['item_id'] ?>"
                                       data-max="<?= $remaining ?>"
                                       <?= $remaining <= 0 ? 'disabled' : '' ?>>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                            <td class="text-center">
                                <button class="receive-btn receive-item-btn" 
                                        data-item-id="<?= $item['item_id'] ?>"
                                        <?= $remaining <= 0 ? 'disabled' : '' ?>>
                                    <span class="material-icons" style="font-size: 1rem;">input</span>
                                    รับ
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
    // Initialize DataTable
    const table = new ModernTable('po-items-table', {
        pageLength: 25,
        language: 'th',
        exportButtons: true,
        defaultOrder: [[1, 'asc']]
    });

    // Handle individual receive
    $('.receive-item-btn').on('click', function() {
        const itemId = $(this).data('item-id');
        const qtyInput = $(`.receive-qty-input[data-item-id="${itemId}"]`);
        const qty = parseInt(qtyInput.val()) || 0;
        
        if (qty <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาระบุจำนวน',
                text: 'กรุณาระบุจำนวนที่ต้องการรับ'
            });
            return;
        }
        
        receiveItem(itemId, qty);
    });

    // Handle receive all
    $('#receive-all-btn').on('click', function() {
        const items = [];
        $('.receive-qty-input:not(:disabled)').each(function() {
            const qty = parseInt($(this).val()) || 0;
            if (qty > 0) {
                items.push({
                    item_id: $(this).data('item-id'),
                    quantity: qty
                });
            }
        });

        if (items.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่มีรายการที่ต้องรับ',
                text: 'กรุณาตรวจสอบจำนวนสินค้าที่ต้องการรับ'
            });
            return;
        }

        Swal.fire({
            title: 'ยืนยันการรับสินค้า?',
            text: `คุณต้องการรับสินค้า ${items.length} รายการหรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'รับสินค้า',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                receiveMultipleItems(items);
            }
        });
    });

    // Validate quantity input
    $('.receive-qty-input').on('input', function() {
        const max = parseInt($(this).data('max'));
        const val = parseInt($(this).val());
        
        if (val > max) {
            $(this).val(max);
        }
        if (val < 0) {
            $(this).val(0);
        }
    });
});

function receiveItem(itemId, quantity) {
    Swal.fire({
        title: 'กำลังบันทึก...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'process_receive_po.php',
        method: 'POST',
        data: {
            action: 'receive_single',
            item_id: itemId,
            quantity: quantity,
            po_id: <?= $po_id ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'รับสินค้าสำเร็จ',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
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
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'
            });
        }
    });
}

function receiveMultipleItems(items) {
    Swal.fire({
        title: 'กำลังบันทึก...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'process_receive_po.php',
        method: 'POST',
        data: {
            action: 'receive_multiple',
            items: JSON.stringify(items),
            po_id: <?= $po_id ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'รับสินค้าสำเร็จ',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
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
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'
            });
        }
    });
}
</script>

</body>
</html>