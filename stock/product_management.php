<?php
session_start();
require '../config/db_connect.php';

// ดึงข้อมูลสินค้าทั้งหมด
$sql = "
SELECT 
    p.product_id,
    p.image,
    p.barcode,
    p.sku,
    p.name,
    p.unit,
    p.remark_color,
    p.remark_split,
    pc.category_name,
    COALESCE(p.is_active, 1) as is_active,
    l.location_id,
    l.row_code,
    l.bin,
    l.shelf,
    l.description as location_description,
    pl.location_id as product_location_id
FROM products p
LEFT JOIN product_category pc ON p.product_category_id = pc.category_id
LEFT JOIN product_location pl ON p.product_id = pl.product_id
LEFT JOIN locations l ON pl.location_id = l.location_id
ORDER BY p.name ASC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สถิติ
$stats = [
    'total_products' => count($products),
    'active_products' => count(array_filter($products, fn($p) => $p['is_active'] == 1)),
    'inactive_products' => count(array_filter($products, fn($p) => $p['is_active'] == 0))
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสินค้า - IchoicePMS</title>
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
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .status-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }

        .status-active {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #059669;
        }

        .status-inactive {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-toggle {
            background: #f59e0b;
            color: white;
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .modal-content-box {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 2rem;
            z-index: 1000;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: none;
        }

        .modal-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
        }

        .modal-title-custom {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f2937;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }

        .close-btn:hover {
            color: #1f2937;
        }

        .form-group-custom {
            margin-bottom: 1rem;
        }

        .form-group-custom label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-group-custom input,
        .form-group-custom textarea,
        .form-group-custom select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: 'Prompt', sans-serif;
            font-size: 0.95rem;
        }

        .form-group-custom input:focus,
        .form-group-custom textarea:focus,
        .form-group-custom select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group-custom textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 2rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }

        .btn-submit {
            background: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-submit:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #374151;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .search-box {
            margin-bottom: 1.5rem;
        }

        .search-box input {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
        }

        .no-image {
            width: 50px;
            height: 50px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 0.8rem;
        }

        #imagePreviewContainer {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 150px;
            background: #f9fafb;
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
        }

        #imagePreview {
            max-width: 150px;
            max-height: 150px;
        }

        .btn-modern-info {
            background: #06b6d4;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .btn-modern-info:hover {
            background: #0891b2;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<?php include '../templates/sidebar.php'; ?>
<div class="mainwrap">
    <div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">inventory_2</span>
                    จัดการสินค้า
                </h1>
                <p class="text-muted mb-0">เพิ่ม แก้ไข ลบ และจัดการสถานะสินค้า</p>
            </div>
            <button class="btn-modern btn-modern-success" id="addProductBtn">
                <span class="material-icons" style="font-size: 1.25rem;">add_circle</span>
                เพิ่มสินค้าใหม่
            </button>
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
                                <div class="stats-subtitle">จำนวนรายการ</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">inventory_2</i>
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
                                <div class="stats-title">สินค้าที่ขาย</div>
                                <div class="stats-value"><?= number_format($stats['active_products']) ?></div>
                                <div class="stats-subtitle">กำลังขายอยู่</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">done_all</i>
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
                                <div class="stats-title">สินค้าหยุดขาย</div>
                                <div class="stats-value"><?= number_format($stats['inactive_products']) ?></div>
                                <div class="stats-subtitle">ไม่ขายแล้ว</div>
                            </div>
                            <div class="col-auto">
                                <i class="material-icons stats-icon">block</i>
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
                        รายการสินค้า (<?= count($products) ?> รายการ)
                    </h5>
                    <div class="table-actions">
                        <button class="btn-modern btn-modern-secondary btn-sm refresh-table me-2" onclick="location.reload()">
                            <span class="material-icons">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>
                <!-- Selection Actions -->
                <div id="selectionActions" style="display: none; margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 8px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span id="selectionCount" style="font-weight: 600; color: #374151;">เลือก 0 รายการ</span>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="btn-modern btn-modern-info btn-sm" onclick="exportToExcel()">
                                <span class="material-icons" style="font-size: 1rem;">table_chart</span>
                                ส่งออก Excel (รายงาน)
                            </button>
                            <button type="button" class="btn-modern btn-modern-info btn-sm" onclick="exportForImport()">
                                <span class="material-icons" style="font-size: 1rem;">file_download</span>
                                ส่งออก Excel (นำเข้า)
                            </button>
                            <button type="button" class="btn-modern btn-modern-info btn-sm" onclick="exportToPDF()">
                                <span class="material-icons" style="font-size: 1rem;">picture_as_pdf</span>
                                ส่งออก PDF
                            </button>
                            <button type="button" class="btn-modern btn-modern-secondary btn-sm" onclick="clearSelection()">
                                <span class="material-icons" style="font-size: 1rem;">clear</span>
                                ยกเลิกเลือก
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-body">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="ค้นหาสินค้า (ชื่อ, SKU, Barcode)...">
                </div>

                <table id="product-table" class="table modern-table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="cursor: pointer;">
                            </th>
                            <th style="width: 60px;">รูปภาพ</th>
                            <th>รายละเอียด</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th>หน่วย</th>
                            <th>หมวดหมู่</th>
                            <th>ตำแหน่งที่จัดเก็บ</th>
                            <th>สถานะ</th>
                            <th class="text-center" style="width: 140px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="material-icons mb-2" style="font-size: 3rem; color: #d1d5db;">inbox</span>
                                    <h5 class="text-muted">ไม่มีข้อมูลสินค้า</h5>
                                    <p class="text-muted mb-0">คลิกปุ่มเพิ่มสินค้าเพื่อเริ่มต้น</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($products as $product): ?>
                        <tr data-product-id="<?= $product['product_id'] ?>">
                            <td>
                                <input type="checkbox" class="product-checkbox" value="<?= $product['product_id'] ?>" onchange="updateSelectionCount()" style="cursor: pointer;">
                            </td>
                            <td>
                                <?php 
                                $image_path = '../images/noimg.png';
                                if (!empty($product['image'])) {
                                    if (strpos($product['image'], 'images/') === 0) {
                                        $image_path = '../' . $product['image'];
                                    } else {
                                        $image_path = '../images/' . $product['image'];
                                    }
                                }
                                ?>
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="no-image" style="display: none;">ไม่มีรูป</div>
                                <?php else: ?>
                                    <div class="no-image">ไม่มีรูป</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <?php if (!empty($product['remark_color'])): ?>
                                        <br><small class="text-muted">หมายเหตุสี: <?= htmlspecialchars($product['remark_color']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($product['remark_split'])): ?>
                                        <br><small class="text-muted">แบ่งขายสินค้า: <?= htmlspecialchars($product['remark_split']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($product['sku']) ?></td>
                            <td><?= htmlspecialchars($product['barcode']) ?></td>
                            <td><?= htmlspecialchars($product['unit']) ?></td>
                            <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($product['row_code']) && !empty($product['bin']) && !empty($product['shelf'])): ?>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <span class="badge bg-primary">แถว: <?= htmlspecialchars($product['row_code']) ?></span>
                                        <span class="badge bg-info">ล็อค: <?= htmlspecialchars($product['bin']) ?></span>
                                        <span class="badge bg-success">ชั้น: <?= htmlspecialchars($product['shelf']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $product['is_active'] == 1 ? 'status-active' : 'status-inactive' ?>">
                                    <?= $product['is_active'] == 1 ? '✓ ขายอยู่' : '✕ หยุดขาย' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="action-btn btn-edit" onclick="editProduct(<?= $product['product_id'] ?>)" title="แก้ไข">
                                    <span class="material-icons" style="font-size: 1rem;">edit</span>
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

<!-- Modal -->
<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal-content-box" id="productModal">
    <div class="modal-header-custom">
        <h2 class="modal-title-custom" id="modalTitle">เพิ่มสินค้าใหม่</h2>
        <button class="close-btn" onclick="closeModal()">×</button>
    </div>
    
    <form id="productForm">
        <div class="form-group-custom">
            <label>ชื่อสินค้า *</label>
            <input type="text" id="productName" name="name" required>
        </div>

        <div class="form-group-custom">
            <label>SKU *</label>
            <input type="text" id="productSku" name="sku" required>
        </div>

        <div class="form-group-custom">
            <label>Barcode *</label>
            <input type="text" id="productBarcode" name="barcode" required>
        </div>

        <div class="form-group-custom">
            <label>หน่วยนับ *</label>
            <input type="text" id="productUnit" name="unit" required>
        </div>

        <div class="form-group-custom">
            <label>หมวดหมู่</label>
            <select id="productCategory" name="product_category_id">
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php
                $cat_sql = "SELECT category_id, category_name FROM product_category ORDER BY category_name";
                $cat_stmt = $pdo->query($cat_sql);
                $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($categories as $cat):
                ?>
                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group-custom">
            <label>รูปภาพสินค้า</label>
            <div id="imagePreviewContainer" style="margin-bottom: 1rem; text-align: center;">
                <img id="imagePreview" src="" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 2px solid #e5e7eb; display: none;">
            </div>
            <input type="file" id="productImage" name="image" accept="image/*" onchange="previewImage(event)" style="padding: 0.5rem;">
            <small class="text-muted" style="display: block; margin-top: 0.5rem;">รองรับ JPG, PNG, GIF (ไม่บังคับ)</small>
        </div>

        <div class="form-group-custom">
            <label>หมายเหตุสี</label>
           
            <textarea id="productRemark" name="remark_color"></textarea>
        </div>

        <div class="form-group-custom">
            <label>แบ่งขายสินค้า

            </label>
            <textarea id="productRemarkSplit" name="remark_split"></textarea>
        </div>

        <div class="form-group-custom">
            <label>ตำแหน่งที่จัดเก็บสินค้า</label>
            <div style="margin-bottom: 1rem;">
                <input 
                    type="text" 
                    id="locationSearch" 
                    placeholder="ค้นหา หรือ พิมพ์ แถว/ล็อค/ชั้น (เช่น A 2 3)" 
                    style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: 'Prompt', sans-serif; font-size: 0.95rem;"
                >
                <div id="locationSuggestions" style="display: none; position: absolute; width: 100%; max-width: 550px; max-height: 250px; overflow-y: auto; background: white; border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 6px 6px; z-index: 100; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" class="location-suggestions"></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; background: #f9fafb; padding: 1rem; border-radius: 8px; border: 2px solid #e5e7eb;">
                <div>
                    <label style="font-size: 0.85rem; color: #6b7280; display: block; margin-bottom: 0.35rem;">แถว (Row)</label>
                    <input type="text" id="productRowCode" name="row_code" placeholder="เช่น A" readonly style="font-size: 0.9rem; background: #f3f4f6; cursor: not-allowed;">
                </div>
                <div>
                    <label style="font-size: 0.85rem; color: #6b7280; display: block; margin-bottom: 0.35rem;">ล็อค (Bin)</label>
                    <input type="number" id="productBin" name="bin" placeholder="1-10" readonly style="font-size: 0.9rem; background: #f3f4f6; cursor: not-allowed;">
                </div>
                <div>
                    <label style="font-size: 0.85rem; color: #6b7280; display: block; margin-bottom: 0.35rem;">ชั้น (Shelf)</label>
                    <input type="number" id="productShelf" name="shelf" placeholder="1-10" readonly style="font-size: 0.9rem; background: #f3f4f6; cursor: not-allowed;">
                </div>
            </div>
            <input type="hidden" id="productLocation" name="location_id">
            <small style="color: #6b7280; display: block; margin-top: 0.5rem;">🔍 พิมพ์เพื่อค้นหาตำแหน่ง หรือเลือกจากรายการแนะนำ</small>
        </div>

        <div class="form-group-custom">
            <label>สถานะ</label>
            <select id="productStatus" name="is_active">
                <option value="1">✓ ขายอยู่</option>
                <option value="0">✕ หยุดขาย</option>
            </select>
        </div>

        <div class="modal-buttons">
            <button type="button" class="btn-cancel" onclick="closeModal()">ยกเลิก</button>
            <button type="submit" class="btn-submit">บันทึก</button>
        </div>
    </form>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
let currentProductId = null;
let locationsData = [];

// Load locations from API
$.ajax({
    url: '../api/get_locations_list.php',
    type: 'GET',
    dataType: 'json',
    success: function(response) {
        if (response.success && response.data) {
            locationsData = response.data;
        }
    },
    error: function() {
        console.log('Could not load locations data');
    }
});

// Location search and autocomplete handler
const locationSearchInput = document.getElementById('locationSearch');
const locationSuggestions = document.getElementById('locationSuggestions');
const productRowCode = document.getElementById('productRowCode');
const productBin = document.getElementById('productBin');
const productShelf = document.getElementById('productShelf');
const productLocation = document.getElementById('productLocation');

if (locationSearchInput) {
    locationSearchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase().trim();
        
        if (searchText.length === 0) {
            locationSuggestions.style.display = 'none';
            return;
        }
        
        // Filter locations based on search text
        const filtered = locationsData.filter(loc => {
            const searchableText = `${loc.row_code} ${loc.bin} ${loc.shelf} ${loc.description || ''}`.toLowerCase();
            return searchableText.includes(searchText);
        }).slice(0, 15); // Limit to 15 suggestions
        
        if (filtered.length === 0) {
            locationSuggestions.style.display = 'none';
            return;
        }
        
        // Display suggestions
        locationSuggestions.innerHTML = filtered.map(loc => `
            <div style="padding: 10px 12px; border-bottom: 1px solid #e5e7eb; cursor: pointer; transition: background-color 0.2s;" 
                 onmouseover="this.style.backgroundColor='#f3f4f6'" 
                 onmouseout="this.style.backgroundColor='white'"
                 onclick="selectLocation(${loc.location_id}, '${loc.row_code}', ${loc.bin}, ${loc.shelf})">
                <div style="font-weight: 600; color: #1f2937;">
                    <span class="badge bg-primary" style="margin-right: 0.5rem;">แถว: ${loc.row_code}</span>
                    <span class="badge bg-info" style="margin-right: 0.5rem;">ล็อค: ${loc.bin}</span>
                    <span class="badge bg-success">ชั้น: ${loc.shelf}</span>
                </div>
                <small style="color: #6b7280; display: block; margin-top: 0.25rem;">${loc.description || ''}</small>
            </div>
        `).join('');
        locationSuggestions.style.display = 'block';
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== locationSearchInput && !locationSearchInput.contains(e.target)) {
            locationSuggestions.style.display = 'none';
        }
    });
}

// Select location from suggestions
function selectLocation(locationId, rowCode, bin, shelf) {
    productLocation.value = locationId;
    productRowCode.value = rowCode;
    productBin.value = bin;
    productShelf.value = shelf;
    locationSearchInput.value = `${rowCode} ${bin} ${shelf}`;
    locationSuggestions.style.display = 'none';
}
</script>
<script>

// Preview image before upload
function previewImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('imagePreview');
    const previewContainer = document.getElementById('imagePreviewContainer');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            previewContainer.style.justifyContent = 'center';
        }
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// เปิด Modal
function openModal(title = 'เพิ่มสินค้าใหม่') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('productModal').style.display = 'block';
    document.getElementById('modalBackdrop').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// ปิด Modal
function closeModal() {
    document.getElementById('productModal').style.display = 'none';
    document.getElementById('modalBackdrop').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('productForm').reset();
    document.getElementById('locationSearch').value = '';
    document.getElementById('locationSuggestions').style.display = 'none';
    document.getElementById('productRowCode').value = '';
    document.getElementById('productBin').value = '';
    document.getElementById('productShelf').value = '';
    document.getElementById('productLocation').value = '';
    currentProductId = null;
}

// ปิด Modal เมื่อคลิก Backdrop
document.getElementById('modalBackdrop').addEventListener('click', closeModal);

// เพิ่มสินค้า
document.getElementById('addProductBtn').addEventListener('click', function() {
    currentProductId = null;
    document.getElementById('productForm').reset();
    
    // Reset location fields
    document.getElementById('locationSearch').value = '';
    document.getElementById('locationSuggestions').style.display = 'none';
    document.getElementById('productRowCode').value = '';
    document.getElementById('productBin').value = '';
    document.getElementById('productShelf').value = '';
    document.getElementById('productLocation').value = '';
    
    // Reset image preview
    const imagePreview = document.getElementById('imagePreview');
    const productImage = document.getElementById('productImage');
    imagePreview.src = '';
    imagePreview.style.display = 'none';
    productImage.value = '';
    
    openModal('เพิ่มสินค้าใหม่');
});

// แก้ไขสินค้า
function editProduct(productId) {
    currentProductId = productId;
    
    // ดึงข้อมูลจากตาราง
    const row = document.querySelector(`tr[data-product-id="${productId}"]`);
    const cells = row.querySelectorAll('td');
    
    fetch(`../api/product_detail_api.php?product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const p = data.product;
                document.getElementById('productName').value = p.name || '';
                document.getElementById('productSku').value = p.sku || '';
                document.getElementById('productBarcode').value = p.barcode || '';
                document.getElementById('productUnit').value = p.unit || '';
                document.getElementById('productCategory').value = p.product_category_id || '';
                document.getElementById('productRemark').value = p.remark_color || '';
                document.getElementById('productRemarkSplit').value = p.remark_split || '';
                document.getElementById('productLocation').value = p.location_id || '';
                document.getElementById('productRowCode').value = p.row_code || '';
                document.getElementById('productBin').value = p.bin || '';
                document.getElementById('productShelf').value = p.shelf || '';
                document.getElementById('productStatus').value = p.is_active || 1;
                
                // แสดงรูปภาพปัจจุบัน
                const imagePreview = document.getElementById('imagePreview');
                const productImage = document.getElementById('productImage');
                if (p.image) {
                    let imagePath = p.image;
                    if (!imagePath.startsWith('../') && !imagePath.startsWith('http')) {
                        if (imagePath.startsWith('images/')) {
                            imagePath = '../' + imagePath;
                        } else {
                            imagePath = '../images/' + imagePath;
                        }
                    }
                    imagePreview.src = imagePath;
                    imagePreview.style.display = 'block';
                } else {
                    imagePreview.style.display = 'none';
                    imagePreview.src = '';
                }
                productImage.value = '';
                
                openModal('แก้ไขสินค้า');
            }
        })
        .catch(err => console.error(err));
}

// บันทึกสินค้า
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    if (currentProductId) {
        formData.append('product_id', currentProductId);
        formData.append('action', 'update');
    } else {
        formData.append('action', 'create');
    }
    
    try {
        console.log('Sending form data...');
        const response = await fetch('../api/product_management_api.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON:', e);
            throw new Error('ไม่สามารถแปลงข้อมูลจากเซิร์ฟเวอร์ได้: ' + responseText);
        }
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: result.message,
                confirmButtonText: 'ตกลง'
            }).then(() => {
                location.reload();
            });
            closeModal();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: result.message || 'ไม่สามารถบันทึกข้อมูลได้',
                confirmButtonText: 'ตกลง'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
            confirmButtonText: 'ตกลง'
        });
    }
});

// ลบสินค้า
function deleteProduct(productId) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'คุณแน่ใจว่าต้องการลบสินค้านี้?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../api/product_management_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&product_id=${productId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบสำเร็จ!',
                        text: data.message,
                        confirmButtonText: 'ตกลง'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonText: 'ตกลง'
                    });
                }
            });
        }
    });
}

// เปลี่ยนสถานะ
function toggleStatus(productId, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const statusText = newStatus == 1 ? 'ขายอยู่' : 'หยุดขาย';
    
    Swal.fire({
        title: 'เปลี่ยนสถานะ?',
        text: `เปลี่ยนสถานะเป็น "${statusText}"`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../api/product_management_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle_status&product_id=${productId}&is_active=${newStatus}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: data.message,
                        confirmButtonText: 'ตกลง'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonText: 'ตกลง'
                    });
                }
            });
        }
    });
}

// ค้นหา
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
});

// Initialize DataTable
$(document).ready(function() {
    $('#product-table').DataTable({
        pageLength: 50,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
        },
        columnDefs: [
            { orderable: false, targets: [0, 8] } // ปิดการจัดเรียงคอลัมน์เลือก และ จัดการ
        ]
    });
});

// ฟังก์ชั่น Checklist
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectionCount();
}

function updateSelectionCount() {
    const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;
    const selectionActions = document.getElementById('selectionActions');
    const selectionCount = document.getElementById('selectionCount');
    
    if (checkedCount > 0) {
        selectionActions.style.display = 'block';
        selectionCount.textContent = `\u0e40\u0e25\u0e37\u0e2d\u0e01 ${checkedCount} \u0e23\u0e32\u0e22\u0e01\u0e32\u0e23`;
    } else {
        selectionActions.style.display = 'none';
    }
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.product-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    selectAllCheckbox.checked = checkedCount === allCheckboxes.length && allCheckboxes.length > 0;
}

function clearSelection() {
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectionCount();
}

function getSelectedProducts() {
    const selected = [];
    document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
        const row = cb.closest('tr');
        const cells = row.querySelectorAll('td');
        
        // ดึงรูปภาพ
        const imageElement = cells[1].querySelector('img');
        const imageSrc = imageElement ? imageElement.src : '';
        
        // ดึงข้อมูลจากเซลล์ที่ 2 (ชื่อสินค้า) - แยก strong tag เพื่อได้แค่ชื่อ
        const nameCell = cells[2];
        const nameText = nameCell.querySelector('strong') ? nameCell.querySelector('strong').textContent.trim() : nameCell.textContent.trim();
        
        // ดึงหมายเหตุสี และแบ่งขายสินค้า แยกกัน
        const cellText = nameCell.innerHTML;
        const remarkColorMatch = cellText.match(/หมายเหตุสี:\s*([^<]*)/);
        const remarkSplitMatch = cellText.match(/แบ่งขายสินค้า:\s*([^<]*)/);
        
        const remarkColor = remarkColorMatch ? remarkColorMatch[1].trim() : '';
        const remarkSplit = remarkSplitMatch ? remarkSplitMatch[1].trim() : '';
        
        // ดึงตำแหน่งที่จัดเก็บแยกส่วน (เซลล์ที่ 8)
        const locationCell = cells[8];
        const badges = locationCell.querySelectorAll('.badge');
        let rowCode = '', bin = '', shelf = '';
        
        badges.forEach(badge => {
            const text = badge.textContent.trim();
            if (text.startsWith('แถว:')) rowCode = text.replace('แถว:', '').trim();
            if (text.startsWith('ล็อค:')) bin = text.replace('ล็อค:', '').trim();
            if (text.startsWith('ชั้น:')) shelf = text.replace('ชั้น:', '').trim();
        });
        
        selected.push({
            id: cb.value,
            image: imageSrc,
            name: nameText,
            sku: cells[3].textContent.trim(),
            barcode: cells[4].textContent.trim(),
            unit: cells[5].textContent.trim(),
            category: cells[6].textContent.trim(),
            row_code: rowCode,
            bin: bin,
            shelf: shelf,
            status: cells[9].textContent.trim(),
            remark_color: remarkColor,
            remark_split: remarkSplit
        });
    });
    return selected;
}

// Export to Excel
function exportToExcel() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('\u0e01\u0e23\u0e38\u0e13\u0e32\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e2d\u0e22\u0e48\u0e32\u0e07\u0e19\u0e49\u0e2d\u0e22');
        return;
    }
    
    // Create CSV data for Excel
    let csvData = '\ufeff'; // UTF-8 BOM
    csvData += '\u0e25\u0e33\u0e14\u0e31\u0e1a,\u0e0a\u0e37\u0e48\u0e2d\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32,SKU,Barcode,\u0e2b\u0e19\u0e48\u0e27\u0e22,\u0e2b\u0e21\u0e27\u0e14\u0e2b\u0e21\u0e39\u0e48,\u0e2b\u0e21\u0e32\u0e22\u0e40\u0e2b\u0e15\u0e38\u0e2a\u0e35,\u0e41\u0e1a\u0e48\u0e07\u0e02\u0e32\u0e22\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32,\u0e41\u0e16\u0e27,\u0e25\u0e47\u0e2d\u0e04,\u0e0a\u0e31\u0e49\u0e19,\u0e2a\u0e16\u0e32\u0e19\u0e30\n';
    
    selected.forEach((product, index) => {
        // Escape quotes and handle special characters
        const escapeCsv = (str) => {
            if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                return '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
        };
        
        csvData += (index + 1) + ',';
        csvData += escapeCsv(product.name) + ',';
        csvData += escapeCsv(product.sku) + ',';
        csvData += escapeCsv(product.barcode) + ',';
        csvData += escapeCsv(product.unit) + ',';
        csvData += escapeCsv(product.category) + ',';
        csvData += escapeCsv(product.remark_color) + ',';
        csvData += escapeCsv(product.remark_split) + ',';
        csvData += escapeCsv(product.row_code) + ',';
        csvData += escapeCsv(product.bin) + ',';
        csvData += escapeCsv(product.shelf) + ',';
        csvData += escapeCsv(product.status) + '\n';
    });
    
    // Create blob and download
    const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', '\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32_' + new Date().getTime() + '.csv');
    link.click();
    URL.revokeObjectURL(url);
}

// Export to PDF
function exportToPDF() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('\u0e01\u0e23\u0e38\u0e13\u0e32\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e2d\u0e22\u0e48\u0e32\u0e07\u0e19\u0e49\u0e2d\u0e22');
        return;
    }
    
    // Create HTML for PDF
    let pdfHTML = '<html><head><meta charset=\"utf-8\"><title>\u0e08\u0e31\u0e14\u0e01\u0e32\u0e23\u0e40\u0e27\u0e2b\u0e40\u0e0a\u0e37\u0e32</title>';
    pdfHTML += '<style>';
    pdfHTML += 'body { font-family: "AngsanaNew", "Tahoma", sans-serif; padding: 20px; font-size: 16px; }';
    pdfHTML += 'h1 { text-align: center; color: #333; margin-bottom: 5px; font-size: 20px; }';
    pdfHTML += '.date { text-align: center; color: #666; margin-bottom: 20px; font-size: 14px; }';
    pdfHTML += 'table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 16px; }';
    pdfHTML += 'th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; font-size: 16px; }';
    pdfHTML += 'th { background-color: #3b82f6; color: white; font-weight: bold; }';
    pdfHTML += 'tr:nth-child(even) { background-color: #f9fafb; }';
    pdfHTML += 'img { max-width: 80px; max-height: 80px; border: 1px solid #ddd; border-radius: 4px; }';
    pdfHTML += '.footer { margin-top: 20px; text-align: right; font-size: 14px; color: #666; }';
    pdfHTML += '</style></head><body>';
    
    pdfHTML += '<h1>\u0e23\u0e32\u0e22\u0e0a\u0e37\u0e48\u0e2d\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32</h1>';
    pdfHTML += '<div class=\"date\">\u0e27\u0e31\u0e19\u0e17\u0e35\u0e48: ' + new Date().toLocaleDateString('th-TH') + '</div>';
    
    pdfHTML += '<table>';
    pdfHTML += '<thead><tr>';
    pdfHTML += '<th>\u0e25\u0e33\u0e14\u0e31\u0e1a</th>';
    pdfHTML += '<th>\u0e23\u0e39\u0e1b\u0e20\u0e32\u0e1e</th>';
    pdfHTML += '<th>\u0e0a\u0e37\u0e48\u0e2d\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32</th>';
    pdfHTML += '<th>SKU</th>';
    pdfHTML += '<th>Barcode</th>';
    pdfHTML += '<th>\u0e2b\u0e19\u0e48\u0e27\u0e22</th>';
    pdfHTML += '<th>\u0e2b\u0e21\u0e27\u0e14\u0e2b\u0e21\u0e39\u0e48</th>';
    pdfHTML += '<th>\u0e2b\u0e21\u0e32\u0e22\u0e40\u0e2b\u0e15\u0e38\u0e2a\u0e35</th>';
    pdfHTML += '<th>\u0e41\u0e1a\u0e48\u0e07\u0e02\u0e32\u0e22\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32</th>';
    pdfHTML += '<th>\u0e41\u0e16\u0e27</th>';
    pdfHTML += '<th>\u0e25\u0e47\u0e2d\u0e04</th>';
    pdfHTML += '<th>\u0e0a\u0e31\u0e49\u0e19</th>';
    pdfHTML += '<th>\u0e2a\u0e16\u0e32\u0e19\u0e30</th>';
    pdfHTML += '</tr></thead>';
    pdfHTML += '<tbody>';
    
    selected.forEach((product, index) => {
        pdfHTML += '<tr>';
        pdfHTML += '<td style=\"text-align: center;\">' + (index + 1) + '</td>';
        if (product.image) {
            pdfHTML += '<td style=\"text-align: center;\"><img src=\"' + product.image + '\" alt=\"\u0e23\u0e39\u0e1b\"></td>';
        } else {
            pdfHTML += '<td style=\"text-align: center; color: #999;\">\u0e44\u0e21\u0e48\u0e21\u0e35\u0e23\u0e39\u0e1b</td>';
        }
        pdfHTML += '<td>' + product.name + '</td>';
        pdfHTML += '<td>' + product.sku + '</td>';
        pdfHTML += '<td>' + product.barcode + '</td>';
        pdfHTML += '<td>' + product.unit + '</td>';
        pdfHTML += '<td>' + product.category + '</td>';
        pdfHTML += '<td>' + product.remark_color + '</td>';
        pdfHTML += '<td>' + product.remark_split + '</td>';
        pdfHTML += '<td>' + product.row_code + '</td>';
        pdfHTML += '<td>' + product.bin + '</td>';
        pdfHTML += '<td>' + product.shelf + '</td>';
        pdfHTML += '<td>' + product.status + '</td>';
        pdfHTML += '</tr>';
    });
    
    pdfHTML += '</tbody></table>';
    pdfHTML += '<div class=\"footer\">\u0e23\u0e27\u0e21\u0e01\u0e32\u0e23\u0e28\u0e36\u0e01\u0e29\u0e22\u0e34\u0e2a: ' + new Date().toLocaleString('th-TH') + '</div>';
    pdfHTML += '</body></html>';
    
    // Open in new window and print
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write(pdfHTML);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
}

// Export for Import - ส่งออกในรูปแบบที่ตรงกับ import template
function exportForImport() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('\u0e01\u0e23\u0e38\u0e13\u0e32\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e2d\u0e22\u0e48\u0e32\u0e07\u0e19\u0e49\u0e2d\u0e22');
        return;
    }
    
    // Create CSV data in import template format
    // Columns: SKU, Barcode, Name, Image, Unit, Row, Bin, Shelf, Qty, Price, Sale Price, Currency, EXP, Remark Color, Remark Split, Remark
    let csvData = '\ufeff'; // UTF-8 BOM
    csvData += 'SKU,Barcode,Name,\u0e20\u0e32\u0e1e,\u0e2b\u0e19\u0e48\u0e27\u0e22,\u0e41\u0e16\u0e27,\u0e25\u0e47\u0e2d\u0e04,\u0e0a\u0e31\u0e49\u0e19,\u0e08\u0e33\u0e19\u0e27\u0e19,\u0e23\u0e32\u0e04\u0e32\u0e15\u0e49\u0e19\u0e17\u0e39\u0e19,\u0e23\u0e32\u0e04\u0e32\u0e02\u0e32\u0e22,\u0e2a\u0e01\u0e38\u0e25\u0e40\u0e07\u0e34\u0e19,EXP,\u0e2a\u0e35\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32,\u0e0a\u0e19\u0e34\u0e14\u0e01\u0e32\u0e23\u0e41\u0e1a\u0e48\u0e07\u0e02\u0e32\u0e22,\u0e2b\u0e21\u0e32\u0e22\u0e40\u0e2b\u0e15\u0e38\u0e2a\u0e35\n';
    
    selected.forEach((product) => {
        // Escape quotes and handle special characters
        const escapeCsv = (str) => {
            if (str == null || str === undefined) str = '';
            if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                return '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
        };
        
        csvData += escapeCsv(product.sku) + ',';
        csvData += escapeCsv(product.barcode) + ',';
        csvData += escapeCsv(product.name) + ',';
        csvData += escapeCsv(product.image || '') + ',';
        csvData += escapeCsv(product.unit) + ',';
        csvData += escapeCsv(product.row_code) + ',';
        csvData += escapeCsv(product.bin) + ',';
        csvData += escapeCsv(product.shelf) + ',';
        csvData += ','; // qty - leave blank for manual entry
        csvData += ','; // price - leave blank for manual entry
        csvData += ','; // sale_price - leave blank for manual entry
        csvData += 'THB,'; // currency default
        csvData += ','; // expiry_date - leave blank
        csvData += escapeCsv(product.remark_color) + ',';
        csvData += escapeCsv(product.remark_split) + ',';
        csvData += escapeCsv(product.status || '') + '\n'; // using status as remark
    });
    
    // Create blob and download
    const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', '\u0e23\u0e32\u0e22\u0e01\u0e32\u0e23\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32_\u0e19\u0e33\u0e40\u0e02\u0e49\u0e32_' + new Date().getTime() + '.csv');
    link.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>
