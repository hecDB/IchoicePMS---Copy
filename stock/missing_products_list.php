<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// Get all missing products
$sql = "SELECT 
            mp.missing_id,
            mp.product_id,
            mp.sku,
            mp.barcode,
            mp.product_name,
            mp.quantity_missing,
            mp.remark,
            mp.created_at,
            u.name as created_by_name,
            mp.reported_by
        FROM missing_products mp
        LEFT JOIN users u ON mp.reported_by = u.user_id
        ORDER BY mp.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$missing_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการสินค้าสูญหาย - IchoicePMS</title>
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
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-left: 4px solid #3b82f6;
        }
        
        .stats-card.stats-danger {
            border-left-color: #ef4444;
        }
        
        .stats-card.stats-warning {
            border-left-color: #f59e0b;
        }
        
        .stats-card.stats-info {
            border-left-color: #06b6d4;
        }
        
        .stats-card-body {
            padding: 1.5rem;
        }
        
        .stats-title {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0.5rem 0;
        }
        
        .stats-subtitle {
            font-size: 0.875rem;
            color: #9ca3af;
        }
        
        .stats-icon {
            font-size: 3rem;
            color: #d1d5db;
        }
        
        .table-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-title .material-icons {
            font-size: 1.5rem;
        }
        
        .breadcrumb-modern {
            background: none;
            padding: 0;
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
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">inventory_2</span>
                    รายการสินค้าสูญหาย
                </h1>
                <p class="text-muted mb-0">รายการสินค้าที่หายหรือหาไม่เจอในระบบ</p>
            </div>
            <a href="missing_products.php" class="btn btn-primary">
                <span class="material-icons align-middle" style="font-size: 1.25rem;">add</span>
                บันทึกสินค้าสูญหายใหม่
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card">
                    <div class="stats-card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="stats-title">สินค้าสูญหาย</div>
                                <div class="stats-value"><?= count($missing_items) ?></div>
                                <div class="stats-subtitle">ทั้งหมด</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">inventory_2</i>
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
                                <div class="stats-title">วันนี้</div>
                                <div class="stats-value">
                                    <?php 
                                    $today_count = 0;
                                    foreach ($missing_items as $item) {
                                        if (date('Y-m-d', strtotime($item['created_at'])) === date('Y-m-d')) {
                                            $today_count++;
                                        }
                                    }
                                    echo $today_count;
                                    ?>
                                </div>
                                <div class="stats-subtitle">รายการ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">today</i>
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
                                <div class="stats-title">สัปดาห์นี้</div>
                                <div class="stats-value">
                                    <?php 
                                    $this_week = 0;
                                    $week_ago = date('Y-m-d', strtotime('-7 days'));
                                    foreach ($missing_items as $item) {
                                        if ($item['created_at'] >= $week_ago) {
                                            $this_week++;
                                        }
                                    }
                                    echo $this_week;
                                    ?>
                                </div>
                                <div class="stats-subtitle">รายการ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">date_range</i>
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
                                <div class="stats-title">จำนวนรวม</div>
                                <div class="stats-value">
                                    <?php 
                                    $total_qty = 0;
                                    foreach ($missing_items as $item) {
                                        $total_qty += $item['quantity_missing'];
                                    }
                                    echo number_format($total_qty, 2);
                                    ?>
                                </div>
                                <div class="stats-subtitle">ชิ้น</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">numbers</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการสินค้าสูญหายทั้งหมด (<?= count($missing_items) ?> รายการ)
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
                <table id="missing-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ลำดับ</th>
                            <th>สินค้า</th>
                            <th style="width: 80px;">SKU</th>
                            <th style="width: 100px;">บาร์โค้ด</th>
                            <th style="width: 80px;">จำนวน</th>
                            <th>หมายเหตุ</th>
                            <th style="width: 100px;">บันทึกโดย</th>
                            <th style="width: 150px;">วันที่/เวลา</th>
                            <th style="width: 100px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($missing_items)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #10b981;">check_circle</span>
                                    <h5 class="text-success">ยอดเยี่ยม! ไม่มีสินค้าสูญหาย</h5>
                                    <p class="text-muted mb-0">ยังไม่มีรายการสินค้าสูญหาย</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($missing_items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($item['sku']) ?></td>
                                <td><?= htmlspecialchars($item['barcode']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= number_format($item['quantity_missing'], 2) ?></span>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars(substr($item['remark'] ?? '', 0, 50)) ?></small>
                                </td>
                                <td><?= htmlspecialchars($item['created_by_name'] ?? 'N/A') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $item['missing_id'] ?>">
                                        <span class="material-icons" style="font-size: 1rem;">delete</span>
                                    </button>
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#missing-table').DataTable({
                order: [[7, 'desc']],
                pageLength: 25,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
                }
            });

            // Delete handler
            $(document).on('click', '.delete-btn', function() {
                const id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: 'คุณต้องการลบรายการนี้หรือไม่?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ลบ',
                    cancelButtonText: 'ยกเลิก'
                }).then(result => {
                    if (result.isConfirmed) {
                        $.post('../api/delete_missing_product_api.php', { missing_id: id }, function(resp) {
                            if (resp.success) {
                                Swal.fire('ลบสำเร็จ', '', 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('ข้อผิดพลาด', resp.message, 'error');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
