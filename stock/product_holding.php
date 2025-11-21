<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// ====== ดึงข้อมูลสินค้ารอสร้างโปรโมชั่น ======
$sql_holding = "
    SELECT 
        ph.holding_id,
        ph.holding_code,
        ph.product_id,
        p.name,
        p.sku,
        ph.original_sku,
        ph.new_sku,
        ph.holding_qty,
        ph.cost_price,
        ph.sale_price,
        ph.promo_name,
        ph.promo_discount,
        ph.expiry_date,
        ph.days_to_expire,
        ph.holding_reason,
        ph.status,
        ph.created_at,
        u.name as created_by_name,
        ph.remark
    FROM product_holding ph
    LEFT JOIN products p ON ph.product_id = p.product_id
    LEFT JOIN users u ON ph.created_by = u.user_id
    WHERE ph.status = 'holding'
    ORDER BY ph.days_to_expire ASC, ph.created_at DESC
";
$stmt = $pdo->prepare($sql_holding);
$stmt->execute();
$holdingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// คำนวณสถิติ
$stats = [
    'total_holding' => count($holdingProducts),
    'total_qty' => array_sum(array_column($holdingProducts, 'holding_qty')),
    'total_value' => array_reduce($holdingProducts, fn($sum, $p) => $sum + ($p['holding_qty'] * $p['cost_price']), 0)
];

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้ารอสร้างโปรโมชั่น - IchoicePMS</title>
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
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #3b82f6;
        }
        
        .stats-card.blue { border-left-color: #3b82f6; }
        .stats-card.green { border-left-color: #10b981; }
        .stats-card.orange { border-left-color: #f59e0b; }
        
        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stats-label {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .badge-status {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-holding {
            background: #e0f2fe;
            color: #0277bd;
        }
        
        .action-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-edit {
            background: #3b82f6;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2563eb;
            color: white;
        }
        
        .btn-move-sale {
            background: #10b981;
            color: white;
        }
        
        .btn-move-sale:hover {
            background: #059669;
            color: white;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }
        
        .urgency-critical {
            color: #dc2626;
            font-weight: 700;
        }
        
        .urgency-warning {
            color: #f59e0b;
            font-weight: 600;
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
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #f59e0b;">pending_actions</span>
                    สินค้ารอสร้างโปรโมชั่น
                </h1>
                <p class="text-muted mb-0">สินค้าใกล้หมดอายุที่รอการแก้ไข SKU และสร้างขายต่อ</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card blue">
                    <div class="stats-value"><?= number_format($stats['total_holding']) ?></div>
                    <div class="stats-label">รายการรอสร้างโปรโมชั่น</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card green">
                    <div class="stats-value"><?= number_format($stats['total_qty']) ?></div>
                    <div class="stats-label">จำนวนสินค้าทั้งหมด</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card orange">
                    <div class="stats-value">฿<?= number_format($stats['total_value'], 2) ?></div>
                    <div class="stats-label">มูลค่าคงเหลือ (ต้นทุน)</div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการสินค้าพัก (<?= count($holdingProducts) ?> รายการ)
                    </h5>
                    <div class="table-actions">
                        <button class="btn-modern btn-modern-secondary btn-sm" onclick="location.reload()">
                            <span class="material-icons">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <table id="holding-products-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>รหัสอ้างอิง</th>
                            <th>ชื่อสินค้า</th>
                            <th>SKU</th>
                            <th>จำนวน</th>
                            <th>ราคาต้นทุน</th>
                            <th>ราคาขาย</th>
                            <th>โปรโมชั่น</th>
                            <th>วันหมดอายุ</th>
                            <th>เหลือ (วัน)</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($holdingProducts)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #10b981;">check_circle</span>
                                    <h5 class="text-success">ยอดเยี่ยม!</h5>
                                    <p class="text-muted mb-0">ไม่มีสินค้ารอสร้างโปรโมชั่น</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($holdingProducts as $product): ?>
                            <tr data-id="<?= $product['holding_id'] ?>">
                                <td>
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($product['holding_code']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <br>
                                    <small class="text-muted">โดย: <?= htmlspecialchars($product['created_by_name']) ?></small>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($product['original_sku']) ?></span>
                                    <?php if ($product['new_sku']): ?>
                                    <br><span class="badge bg-success">→ <?= htmlspecialchars($product['new_sku']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-primary"><?= number_format($product['holding_qty']) ?></span>
                                </td>
                                <td>
                                    ฿<?= number_format($product['cost_price'], 2) ?>
                                </td>
                                <td>
                                    <strong class="text-success">฿<?= number_format($product['sale_price'], 2) ?></strong>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong><?= htmlspecialchars($product['promo_name']) ?></strong>
                                        <br>
                                        <span class="text-danger">ลด <?= $product['promo_discount'] ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?= date("d/m/Y", strtotime($product['expiry_date'])) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($product['days_to_expire'] <= 7): ?>
                                    <span class="urgency-critical"><?= $product['days_to_expire'] ?> วัน</span>
                                    <?php elseif ($product['days_to_expire'] <= 30): ?>
                                    <span class="urgency-warning"><?= $product['days_to_expire'] ?> วัน</span>
                                    <?php else: ?>
                                    <span class="text-success"><?= $product['days_to_expire'] ?> วัน</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status badge-holding">พักไว้</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="action-btn btn-edit" 
                                                onclick="editHolding(<?= $product['holding_id'] ?>, '<?= htmlspecialchars($product['new_sku'] ?: $product['original_sku']) ?>', <?= $product['sale_price'] ?>, '<?= htmlspecialchars($product['holding_reason']) ?>', '<?= $product['expiry_date'] ?>')">
                                            <span class="material-icons" style="font-size: 1rem;">edit</span>
                                        </button>
                                        <button type="button" class="action-btn btn-move-sale"
                                                onclick="moveToSale(<?= $product['holding_id'] ?>, '<?= htmlspecialchars($product['new_sku'] ?: $product['original_sku']) ?>', this)">
                                            <span class="material-icons" style="font-size: 1rem;">shopping_cart</span>
                                        </button>
                                        <button type="button" class="action-btn btn-delete"
                                                onclick="deleteHolding(<?= $product['holding_id'] ?>, '<?= htmlspecialchars($product['holding_code']) ?>')">
                                            <span class="material-icons" style="font-size: 1rem;">delete</span>
                                        </button>
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
    // Initialize table
    new ModernTable('holding-products-table', {
        pageLength: 25,
        language: 'th',
        exportButtons: true,
        batchOperations: false,
        defaultOrder: [[8, 'asc']] // Sort by days to expire
    });
});

// แก้ไข SKU และราคา
function editHolding(holdingId, currentSku, currentPrice, currentReason, currentExpiry) {
    Swal.fire({
        title: '✏️ แก้ไขข้อมูลสินค้า',
        html: `
            <div style="text-align: left;">
                <div class="mb-3">
                    <label class="form-label fw-bold">SKU ใหม่ <span style="color: red;">*</span></label>
                    <input type="text" class="form-control" id="new_sku" placeholder="กรอก SKU ใหม่" value="${currentSku}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">ราคาขายใหม่ (บาท)</label>
                    <input type="number" class="form-control" id="new_price" step="0.01" placeholder="กรอกราคาขาย" value="${currentPrice}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">วันหมดอายุ</label>
                    <input type="date" class="form-control" id="edit_expiry" value="${currentExpiry}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">เงื่อนไข/หมายเหตุ</label>
                    <textarea class="form-control" id="edit_reason" rows="3" placeholder="เช่น สินค้าแบรนด์ใหม่, มีรสชาติใหม่, ฯลฯ">${currentReason}</textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '💾 บันทึกการแก้ไข',
        cancelButtonText: '❌ ยกเลิก',
        confirmButtonColor: '#3b82f6',
        width: '500px',
        preConfirm: () => {
            const newSku = document.getElementById('new_sku').value;
            const newPrice = parseFloat(document.getElementById('new_price').value);
            const newExpiry = document.getElementById('edit_expiry').value;
            const reason = document.getElementById('edit_reason').value;
            
            if (!newSku) {
                Swal.showValidationMessage('กรุณากรอก SKU ใหม่');
                return false;
            }
            
            if (!newPrice || newPrice <= 0) {
                Swal.showValidationMessage('กรุณากรอกราคาขายที่มากกว่า 0');
                return false;
            }
            
            if (!newExpiry) {
                Swal.showValidationMessage('กรุณาเลือกวันหมดอายุ');
                return false;
            }
            
            return { newSku, newPrice, reason, newExpiry };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveHoldingEdit(holdingId, result.value);
        }
    });
}

// บันทึกการแก้ไข
function saveHoldingEdit(holdingId, data) {
    $.ajax({
        url: '../api/update_product_holding.php',
        method: 'POST',
        dataType: 'json',
        data: JSON.stringify({
            holding_id: holdingId,
            new_sku: data.newSku,
            new_price: data.newPrice,
            reason: data.reason,
            new_expiry: data.newExpiry
        }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                Swal.fire('สำเร็จ!', response.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('ข้อผิดพลาด', response.message, 'error');
            }
        },
        error: function(xhr) {
            let errorMsg = 'เกิดข้อผิดพลาด';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.message;
            } catch (e) {}
            Swal.fire('ข้อผิดพลาด', errorMsg, 'error');
        }
    });
}

// ย้ายไปขาย
function moveToSale(holdingId, skuInfo, rowElement) {
    // ตรวจสอบว่า SKU เปลี่ยนแล้วหรือไม่
    const row = $(rowElement).closest('tr');
    const newSkuBadge = row.find('.badge.bg-success');
    
    if (newSkuBadge.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: '⚠️ ยังไม่มี SKU ใหม่',
            html: `
                <div style="text-align: left;">
                    <p>กรุณาแก้ไขข้อมูลสินค้าและเปลี่ยน SKU ก่อน</p>
                    <div class="alert alert-warning" role="alert">
                        <small>คลิกปุ่ม ✏️ เพื่อแก้ไข SKU ใหม่</small>
                    </div>
                </div>
            `,
            confirmButtonText: 'ตกลง'
        });
        return;
    }
    
    Swal.fire({
        title: '🛒 ย้ายสินค้าไปขาย',
        html: `
            <div style="text-align: left;">
                <p>คุณต้องการย้าย SKU: <strong>${skuInfo}</strong> ไปรายการความเคลื่อนไหวสินค้าหรือไม่?</p>
                <div class="alert alert-success" role="alert">
                    <small>
                        ✅ <strong>ระบบจะ:</strong><br>
                        • สร้างสินค้าใหม่ (ใช้ SKU ใหม่)<br>
                        • บันทึกลงตารางความเคลื่อนไหว receive_items<br>
                        • อัปเดต SKU ในตาราง products
                    </small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '✅ ย้ายไปขาย',
        cancelButtonText: '❌ ยกเลิก',
        confirmButtonColor: '#10b981'
    }).then((result) => {
        if (result.isConfirmed) {
            executeMoveSale(holdingId);
        }
    });
}

// ทำการย้ายไปขาย
function executeMoveSale(holdingId) {
    Swal.fire({
        title: 'กำลังประมวลผล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: '../api/move_holding_to_sale.php',
        method: 'POST',
        dataType: 'json',
        data: JSON.stringify({ holding_id: holdingId }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                Swal.fire('สำเร็จ!', response.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('ข้อผิดพลาด', response.message, 'error');
            }
        },
        error: function(xhr) {
            let errorMsg = 'เกิดข้อผิดพลาด';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.message;
            } catch (e) {}
            Swal.fire('ข้อผิดพลาด', errorMsg, 'error');
        }
    });
}

// ลบสินค้าพัก
function deleteHolding(holdingId, holdingCode) {
    Swal.fire({
        title: '🗑️ ลบสินค้าพัก',
        html: `
            <div style="text-align: left;">
                <p>คุณต้องการลบรหัส: <strong>${holdingCode}</strong> หรือไม่?</p>
                <div class="alert alert-warning" role="alert">
                    <small>⚠️ การดำเนินการนี้จะคืนสินค้าไปยังตารางรับเข้าต้นฉบับ</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '✅ ลบเลย',
        cancelButtonText: '❌ ยกเลิก',
        confirmButtonColor: '#ef4444'
    }).then((result) => {
        if (result.isConfirmed) {
            executeDelete(holdingId);
        }
    });
}

// ทำการลบ
function executeDelete(holdingId) {
    Swal.fire({
        title: 'กำลังประมวลผล...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: '../api/delete_product_holding.php',
        method: 'POST',
        dataType: 'json',
        data: JSON.stringify({ holding_id: holdingId }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                Swal.fire('สำเร็จ!', response.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('ข้อผิดพลาด', response.message, 'error');
            }
        },
        error: function(xhr) {
            let errorMsg = 'เกิดข้อผิดพลาด';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.message;
            } catch (e) {}
            Swal.fire('ข้อผิดพลาด', errorMsg, 'error');
        }
    });
}
</script>

</body>
</html>
