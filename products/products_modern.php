<?php
require_once '../auth/auth_check.php';
require_once '../config/db_connect.php';

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build SQL query with filters
$sql = "SELECT p.*, c.category_name, 
        CASE 
            WHEN p.quantity > 100 THEN 'high'
            WHEN p.quantity >= 20 THEN 'medium' 
            WHEN p.quantity > 0 THEN 'low'
            ELSE 'out'
        END as stock_status
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

$params = [];

if ($category_filter) {
    $sql .= " AND c.category_name LIKE ?";
    $params[] = "%$category_filter%";
}

if ($stock_filter) {
    switch ($stock_filter) {
        case 'high':
            $sql .= " AND p.quantity > 100";
            break;
        case 'medium':
            $sql .= " AND p.quantity BETWEEN 20 AND 100";
            break;
        case 'low':
            $sql .= " AND p.quantity BETWEEN 1 AND 19";
            break;
        case 'out':
            $sql .= " AND p.quantity = 0";
            break;
    }
}

if ($search) {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.updated_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN quantity > 100 THEN 1 ELSE 0 END) as high_stock,
        SUM(CASE WHEN quantity BETWEEN 20 AND 100 THEN 1 ELSE 0 END) as medium_stock,  
        SUM(CASE WHEN quantity BETWEEN 1 AND 19 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_stock
        FROM products";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $products = [];
    $stats = ['total_products' => 0, 'high_stock' => 0, 'medium_stock' => 0, 'low_stock' => 0, 'out_stock' => 0];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า - IchoicePMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="../assets/modern-table.css" rel="stylesheet">
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
        
        .stock-out {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #6b7280;
        }
        
        .price-display {
            font-weight: 600;
            color: #059669;
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

<?php include '../templates/sidebar.php'; ?>

<div class="main-content">
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
                    <span class="material-icons align-middle me-1" style="font-size: 1rem;">inventory</span>
                    สินค้า
                </li>
                <li class="breadcrumb-item active" aria-current="page">รายการสินค้า</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">inventory_2</span>
                    รายการสินค้า
                </h1>
                <p class="text-muted mb-0">จัดการข้อมูลสินค้าและสต็อกสินค้า</p>
            </div>
            <div>
                <button class="btn-modern btn-modern-primary me-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <span class="material-icons" style="font-size: 1.25rem;">add</span>
                    เพิ่มสินค้า
                </button>
                <button class="btn-modern btn-modern-success" onclick="window.location.href='import_product.php'">
                    <span class="material-icons" style="font-size: 1.25rem;">file_upload</span>
                    นำเข้า Excel
                </button>
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
                                <div class="stats-value"><?= number_format($stats['total_products']) ?></div>
                                <div class="stats-subtitle">รายการทั้งหมดในระบบ</div>
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
                                <div class="stats-value"><?= number_format($stats['high_stock']) ?></div>
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
                                <div class="stats-title">สต็อกต่ำ</div>
                                <div class="stats-value"><?= number_format($stats['low_stock']) ?></div>
                                <div class="stats-subtitle">น้อยกว่า 20 ชิ้น</div>
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
                                <div class="stats-title">สินค้าหมด</div>
                                <div class="stats-value"><?= number_format($stats['out_stock']) ?></div>
                                <div class="stats-subtitle">ต้องเติมสต็อกด่วน</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">remove_circle</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="table-card mb-4">
            <div class="table-header">
                <h5 class="table-title mb-0">
                    <span class="material-icons">filter_alt</span>
                    ตัวกรองข้อมูล
                </h5>
            </div>
            <div class="table-body">
                <form id="filterForm" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">หมวดหมู่</label>
                        <select name="category" class="form-select" id="categoryFilter">
                            <option value="">ทุกหมวดหมู่</option>
                            <option value="เครื่องเขียน" <?= $category_filter === 'เครื่องเขียน' ? 'selected' : '' ?>>เครื่องเขียน</option>
                            <option value="อิเล็กทรอนิกส์" <?= $category_filter === 'อิเล็กทรอนิกส์' ? 'selected' : '' ?>>อิเล็กทรอนิกส์</option>
                            <option value="เฟอร์นิเจอร์" <?= $category_filter === 'เฟอร์นิเจอร์' ? 'selected' : '' ?>>เฟอร์นิเจอร์</option>
                            <option value="ของใช้ทำความสะอาด" <?= $category_filter === 'ของใช้ทำความสะอาด' ? 'selected' : '' ?>>ของใช้ทำความสะอาด</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">สถานะสต็อก</label>
                        <select name="stock" class="form-select" id="stockFilter">
                            <option value="">ทุกสถานะ</option>
                            <option value="high" <?= $stock_filter === 'high' ? 'selected' : '' ?>>สต็อกเพียงพอ (>100)</option>
                            <option value="medium" <?= $stock_filter === 'medium' ? 'selected' : '' ?>>สต็อกปานกลาง (20-100)</option>
                            <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>สต็อกต่ำ (<20)</option>
                            <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>สินค้าหมด</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-control" placeholder="ค้นหารหัสสินค้า หรือ ชื่อสินค้า" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn-modern btn-modern-primary me-2">
                            <span class="material-icons">search</span>
                            ค้นหา
                        </button>
                        <a href="?" class="btn-modern btn-modern-secondary">
                            <span class="material-icons">clear</span>
                            ล้าง
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="table-title mb-0">
                        <span class="material-icons">table_view</span>
                        รายการสินค้า (<?= count($products) ?> รายการ)
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
                <table id="productsTable" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>รหัสสินค้า</th>
                            <th>ชื่อสินค้า</th>
                            <th>หมวดหมู่</th>
                            <th>ราคา</th>
                            <th>สต็อก</th>
                            <th>สถานะ</th>
                            <th>วันที่อัพเดท</th>
                            <th class="no-sort text-center" style="width: 120px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #d1d5db;">inventory</span>
                                    <h5 class="text-muted">ไม่พบข้อมูลสินค้า</h5>
                                    <p class="text-muted mb-0">ลองเปลี่ยนเงื่อนไขการค้นหาหรือเพิ่มสินค้าใหม่</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr data-id="<?= $product['id'] ?>">
                            <td>
                                <img src="../images/<?= htmlspecialchars($product['image'] ?? 'noimg.png') ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                     class="product-image" 
                                     onerror="this.src='../images/noimg.png'">
                            </td>
                            <td><span class="fw-bold"><?= htmlspecialchars($product['product_code']) ?></span></td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <?= htmlspecialchars($product['category_name'] ?? 'ไม่ระบุ') ?>
                                </span>
                            </td>
                            <td>
                                <span class="price-display">
                                    <?= number_format($product['price'], 2) ?> ฿
                                </span>
                            </td>
                            <td><span class="fw-bold"><?= number_format($product['quantity']) ?></span></td>
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
                                    case 'out':
                                        $status_text = 'สินค้าหมด';
                                        $status_class = 'stock-out';
                                        break;
                                }
                                ?>
                                <span class="badge stock-badge <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($product['updated_at'] ?? $product['created_at'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn action-btn-view" 
                                            onclick="viewProduct(<?= $product['id'] ?>)"
                                            title="ดูรายละเอียด">
                                        <span class="material-icons">visibility</span>
                                    </button>
                                    <button class="action-btn action-btn-edit" 
                                            onclick="editProduct(<?= $product['id'] ?>)"
                                            title="แก้ไข">
                                        <span class="material-icons">edit</span>
                                    </button>
                                    <button class="action-btn action-btn-delete" 
                                            onclick="deleteProduct(<?= $product['id'] ?>)"
                                            title="ลบ">
                                        <span class="material-icons">delete</span>
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
    // Initialize products table
    const productsTable = new ModernTable('productsTable', {
        pageLength: 25,
        language: 'th',
        exportButtons: true,
        defaultOrder: [[7, 'desc']] // Sort by update date
    });
});

// Product management functions
function viewProduct(id) {
    window.location.href = `product_view.php?id=${id}`;
}

function editProduct(id) {
    window.location.href = `product_edit.php?id=${id}`;
}

function deleteProduct(id) {
    Swal.fire({
        title: 'ยืนยันการลบสินค้า?',
        text: 'คุณไม่สามารถย้อนกลับการดำเนินการนี้ได้!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'กำลังลบ...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'product_delete.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire('สำเร็จ!', 'ลบสินค้าเรียบร้อยแล้ว', 'success')
                        .then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('ข้อผิดพลาด!', response.message || 'เกิดข้อผิดพลาดในการลบสินค้า', 'error');
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire('ข้อผิดพลาด!', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            });
        }
    });
}

// Auto-refresh every 10 minutes
setInterval(function() {
    location.reload();
}, 600000);
</script>

</body>
</html>