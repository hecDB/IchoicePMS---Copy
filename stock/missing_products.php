<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

// ตรวจสอบว่า user ได้ login แล้ว
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกสินค้าสูญหาย - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
        }
        
        .card {\n            border: none;\n            border-radius: 8px;\n            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);\n            margin-bottom: 1.5rem;\n        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 15px;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header-custom .material-icons {
            font-size: 1.5rem;
        }
        
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title .material-icons {
            font-size: 2.5rem;
            color: #3b82f6;
        }
        
        .btn-custom {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .btn-custom:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
        }
        
        .selected-products-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .selected-product {
            position: relative;
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 4px;
        }

        .selected-product .remove-selected-product {
            position: absolute;
            top: 10px;
            right: 10px;
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
        }
        
        .search-results {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            display: none;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: absolute;
            width: 100%;
            z-index: 10;
        }
        
        .search-results.show {
            display: block;
        }
        
        .product-item {
            padding: 12px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .product-item:hover {
            background-color: #f0f9ff;
            padding-left: 16px;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .input-group {
            position: relative;
        }

        #missing-stats-cards {
            display: flex;
            flex-wrap: nowrap;
            gap: 12px;
            overflow-x: auto;
            padding: 4px 4px 8px;
            scrollbar-width: thin;
        }

        #missing-stats-cards.row {
            --bs-gutter-x: 0;
            --bs-gutter-y: 0;
            margin-left: 0;
            margin-right: 0;
        }

        #missing-stats-cards::-webkit-scrollbar {
            height: 6px;
        }

        #missing-stats-cards::-webkit-scrollbar-thumb {
            background: rgba(15, 23, 42, 0.18);
            border-radius: 999px;
        }

        .stat-card-item {
            flex: 0 0 auto;
            width: 280px;
        }

        .missing-stat-card {
            position: relative;
            border-radius: 14px;
            padding: 16px 18px;
            background: linear-gradient(135deg, var(--card-bg-start, #ffffff), var(--card-bg-end, #f8fafc));
            border: 1.2px solid var(--card-border, #cbd5f5);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            color: #0f172a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            user-select: none;
            min-height: 110px;
        }

        .missing-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
        }

        .missing-stat-card.active {
            border-color: var(--card-border, #3b82f6);
            box-shadow: 0 16px 32px rgba(59, 130, 246, 0.2);
        }

        .missing-stat-card .card-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .missing-stat-card .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: rgba(15, 23, 42, 0.74);
        }

        .missing-stat-card .card-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
            color: #0f172a;
        }

        .missing-stat-card .card-subtitle {
            font-size: 0.8rem;
            color: rgba(15, 23, 42, 0.58);
        }

        .missing-stat-card .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--card-icon-bg, rgba(59, 130, 246, 0.12));
            color: var(--card-icon-color, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
        }

        .missing-stat-card .card-icon .material-icons {
            font-size: 1.6rem;
        }

            /* Full width layout */
            .container-fluid {
                padding-left: 15px;
                padding-right: 15px;
            }

    </style>
</head>
<body>

<div class="mainwrap">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">inventory_2</span>
                    บันทึกสินค้าสูญหาย
                </h1>
                <p class="text-muted mb-0">บันทึกสินค้าที่สูญหายหรือหาไม่เจอในระบบ</p>
            </div>
        </div>

            <div class="row" id="missing-stats-row" style="display: none;">
                <div class="col-12">
                    <div class="row g-3" id="missing-stats-cards"></div>
                </div>
            </div>

        <div class="row">
            <div class="col-lg-12">

                <!-- Step 1: Search Product -->
                <div class="card mb-4">
                    <div class="card-header-custom">
                        <span class="badge bg-white text-primary me-2">ขั้นตอน 1</span>
                        แสกนบาร์โค้ด / พิมพ์ SKU
                    </div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <span class="input-group-text">
                                <span class="material-icons">barcode</span>
                            </span>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="barcode-search" 
                                   placeholder="แสกนบาร์โค้ด หรือพิมพ์ SKU / ชื่อสินค้า...">
                        </div>
                        <div class="search-results" id="search-results"></div>
                    </div>
                </div>

                <!-- Step 2: Selected Product -->
                <div id="selected-product-section" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header-custom">
                            <span class="badge bg-white text-primary me-2">ขั้นตอน 2</span>
                            สินค้าที่เลือก
                        </div>
                        <div class="card-body">
                                <p class="text-muted mb-3" id="selected-products-hint">เลือกสินค้าจากผลการค้นหาเพื่อเพิ่มในรายการด้านล่าง สามารถเพิ่มได้หลายรายการพร้อมกัน</p>
                                <div id="selected-products-container" class="selected-products-list"></div>
                        </div>
                    </div>

                    <!-- Step 3: Input Missing Quantity -->
                    <div class="card mb-4">
                        <div class="card-header-custom">
                            <span class="badge bg-white text-primary me-2">ขั้นตอน 3</span>
                            กรอกจำนวนที่สูญหาย
                        </div>
                        <div class="card-body">
                                <div class="mb-3">
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 60px;">ลำดับ</th>
                                                    <th>สินค้า</th>
                                                    <th style="width: 220px;">จำนวนที่สูญหาย</th>
                                                    <th style="width: 160px;">PO</th>
                                                    <th style="width: 160px;">วันหมดอายุ</th>
                                                </tr>
                                            </thead>
                                            <tbody id="selected-quantity-body">
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">
                                                        ยังไม่ได้เลือกสินค้า
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            <div class="mb-3">
                                <label for="remark-select" class="form-label">หมายเหตุ</label>
                                <select class="form-select form-select-lg mb-2" id="remark-select">
                                    <option value="">— เลือกหมายเหตุ —</option>
                                    <option value="damaged">1. ชำรุด/สูญหาย</option>
                                    <option value="substitute">2. ส่งแทนสินค้าอื่น</option>
                                    <option value="followup">3. ส่งตาม</option>
                                    <option value="borrow">4. ยืม</option>
                                    <option value="other">5. อื่นๆ (ระบุ)</option>
                                </select>
                                <textarea class="form-control" 
                                          id="remark" 
                                          rows="3" 
                                          placeholder="เพิ่มหมายเหตุเพิ่มเติม..." 
                                          style="display: none;"></textarea>
                            </div>

                            <button class="btn btn-custom btn-lg w-100" id="submit-btn">
                                <span class="material-icons" style="vertical-align: middle;">check_circle</span>
                                บันทึกสินค้าสูญหาย
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Missing Items List -->
                <div class="card">
                    <div class="card-header-custom">
                        <span class="material-icons">list</span>
                        รายการสินค้าสูญหายวันนี้
                    </div>
                    <div class="card-body p-0">
                        <div class="filter-bar p-3 border-bottom bg-light">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label for="filter-date" class="form-label mb-1">วันที่บันทึก</label>
                                    <input type="date" class="form-control" id="filter-date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-5">
                                    <label for="filter-keyword" class="form-label mb-1">ค้นหา (สินค้า, SKU, บาร์โค้ด, หมายเหตุ, ผู้บันทึก)</label>
                                    <input type="text" class="form-control" id="filter-keyword" placeholder="พิมพ์คำค้นหา...">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="button" class="btn btn-outline-primary" id="filter-apply-btn">ค้นหา</button>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="button" class="btn btn-outline-secondary" id="filter-clear-btn">ล้างตัวกรอง</button>
                                </div>
                            </div>
                        </div>
                        <div id="missing-items-list" style="min-height: 200px; overflow-x: auto;">
                            <table class="table table-hover mb-0" style="width: 100%; min-width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">ลำดับ</th>
                                        <th>สินค้า</th>
                                        <th style="width: 90px;">จำนวน</th>
                                        <th>หมายเหตุ</th>
                                        <th>บันทึกโดย</th>
                                        <th style="width: 150px;">วันที่บันทึก</th>
                                        <th style="width: 120px;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="missing-items-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <span class="material-icons">inbox</span><br>
                                            ยังไม่มีรายการสินค้าสูญหาย
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let selectedProducts = [];
        let tableFilterTimeout = null;
        let selectedRemarkFilter = null;

        const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

        function buildSelectedProductKey(product) {
            return [
                product.product_id || 0,
                product.receive_id || 0,
                product.item_id || 0,
                product.expiry_date || ''
            ].join('-');
        }

        function formatReceiveQty(value) {
            if (value === undefined || value === null || value === '') {
                return '-';
            }
            const numeric = parseFloat(value);
            if (Number.isNaN(numeric)) {
                return '-';
            }
            return numeric.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        const htmlEscapeMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '`': '&#96;',
            '=': '&#61;',
            '/': '&#47;'
        };

        function escapeHtml(value) {
            if (value === undefined || value === null) {
                return '';
            }
            return String(value).replace(/[&<>"'`=\/]/g, function(match) {
                return htmlEscapeMap[match] || match;
            });
        }

        const presetRemarkLabels = {
            'ชำรุด/สูญหาย': {
                title: 'ชำรุด/สูญหาย',
                icon: 'report_problem',
                bgStart: '#ffe4e6',
                bgEnd: '#fecdd3',
                borderColor: '#fb7185',
                iconColor: '#be123c',
                iconBg: 'rgba(251, 113, 133, 0.18)',
                subtitle: 'รวม {records} รายการ'
            },
            'ส่งแทนสินค้าอื่น': {
                title: 'ส่งแทนสินค้าอื่น',
                icon: 'swap_horiz',
                bgStart: '#fef9c3',
                bgEnd: '#fef08a',
                borderColor: '#facc15',
                iconColor: '#ca8a04',
                iconBg: 'rgba(250, 204, 21, 0.18)',
                subtitle: 'รวม {records} รายการ'
            },
            'ส่งตาม': {
                title: 'ส่งตาม',
                icon: 'local_shipping',
                bgStart: '#dbeafe',
                bgEnd: '#bfdbfe',
                borderColor: '#60a5fa',
                iconColor: '#1d4ed8',
                iconBg: 'rgba(96, 165, 250, 0.2)',
                subtitle: 'รวม {records} รายการ'
            },
            'ยืม': {
                title: 'ยืม',
                icon: 'shopping_bag',
                bgStart: '#ccfbf1',
                bgEnd: '#a7f3d0',
                borderColor: '#34d399',
                iconColor: '#047857',
                iconBg: 'rgba(52, 211, 153, 0.2)',
                subtitle: 'รวม {records} รายการ'
            },
            'อื่นๆ': {
                title: 'อื่นๆ',
                icon: 'notes',
                bgStart: '#ede9fe',
                bgEnd: '#ddd6fe',
                borderColor: '#a855f7',
                iconColor: '#7c3aed',
                iconBg: 'rgba(168, 85, 247, 0.2)',
                subtitle: 'รวม {records} รายการ'
            },
            'ไม่มีหมายเหตุ': {
                title: 'ไม่มีหมายเหตุ',
                icon: 'help_outline',
                bgStart: '#e2e8f0',
                bgEnd: '#cbd5e1',
                borderColor: '#94a3b8',
                iconColor: '#475569',
                iconBg: 'rgba(148, 163, 184, 0.22)',
                subtitle: 'รวม {records} รายการ'
            }
        };

        const remarkCardOrder = [
            'ชำรุด/สูญหาย',
            'ส่งแทนสินค้าอื่น',
            'ส่งตาม',
            'ยืม',
            'อื่นๆ',
            'ไม่มีหมายเหตุ'
        ];

        function renderSelectedProducts() {
            if (selectedProducts.length === 0) {
                $('#selected-products-container').empty();
                $('#selected-products-hint').show();
                $('#selected-quantity-body').html('<tr><td colspan="5" class="text-center text-muted py-3">ยังไม่ได้เลือกสินค้า</td></tr>');
                $('#selected-product-section').hide();
                return;
            }

            const productCards = selectedProducts.map(product => {
                const imageSrc = product.image_url || (product.image ? '../images/' + product.image : '../images/noimg.png');
                const poDisplay = product.po_label || product.po_number || (product.po_id ? 'PO-' + product.po_id : '-');
                const expiryDisplay = product.expiry_date_formatted || '-';

                return `
                    <div class="selected-product" data-key="${product.uniqueKey}">
                        <button type="button" class="btn-close remove-selected-product" data-key="${product.uniqueKey}" aria-label="Remove"></button>
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <img src="${imageSrc}" alt="Product" class="product-image" onerror="this.src='../images/noimg.png'">
                            </div>
                            <div class="col">
                                <div><strong>${product.product_name}</strong></div>
                                <small class="text-muted d-block">SKU: ${product.sku || '-'} | Barcode: ${product.barcode || '-'}</small>
                                <small class="text-muted d-block">PO: ${poDisplay} | วันหมดอายุ: ${expiryDisplay}</small>
                                <small class="text-muted d-block">รับเมื่อ: ${product.receive_created_at_formatted || '-'}</small>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            $('#selected-products-container').html(productCards);
            $('#selected-products-hint').hide();

            const quantityRows = selectedProducts.map((product, index) => {
                const poDisplay = product.po_label || product.po_number || (product.po_id ? 'PO-' + product.po_id : '-');
                const expiryDisplay = product.expiry_date_formatted || '-';
                const unitLabel = product.unit || 'ชิ้น';
                const currentQuantity = product.quantity !== undefined && product.quantity !== null ? product.quantity : '';

                return `
                    <tr data-key="${product.uniqueKey}">
                        <td>${index + 1}</td>
                        <td>
                            <strong>${product.product_name}</strong><br>
                            <small class="text-muted">SKU: ${product.sku || '-'} | Barcode: ${product.barcode || '-'}</small>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control missing-quantity-input" data-index="${index}" min="0.01" step="0.01" value="${currentQuantity !== '' ? currentQuantity : ''}" placeholder="0.00">
                                <span class="input-group-text">${unitLabel}</span>
                            </div>
                        </td>
                        <td>${poDisplay}</td>
                        <td>${expiryDisplay}</td>
                    </tr>
                `;
            }).join('');

            $('#selected-quantity-body').html(quantityRows);
            $('#selected-product-section').show();

            setTimeout(() => {
                const lastInput = $('.missing-quantity-input').last();
                if (lastInput.length) {
                    lastInput.focus().select();
                }
            }, 0);
        }

        const remarkOptionMap = {
            damaged: 'ชำรุด/สูญหาย',
            substitute: 'ส่งแทนสินค้าอื่น',
            followup: 'ส่งตาม',
            borrow: 'ยืม'
        };

        function getSelectedRemark() {
            const selectedValue = $('#remark-select').val();
            if (!selectedValue) {
                return { valid: false, message: 'กรุณาเลือกหมายเหตุ' };
            }

            if (selectedValue === 'other') {
                const customText = $('#remark').val().trim();
                if (!customText) {
                    return { valid: false, message: 'กรุณากรอกหมายเหตุเพิ่มเติม' };
                }
                return { valid: true, remark: customText };
            }

            return { valid: true, remark: remarkOptionMap[selectedValue] };
        }

        $('#remark-select').on('change', function() {
            const value = $(this).val();
            if (value === 'other') {
                $('#remark').show().focus();
            } else {
                $('#remark').hide().val('');
            }
        });

        // Search product by barcode/SKU
        let searchTimeout;
        $('#barcode-search').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length < 1) {
                $('#search-results').removeClass('show').empty();
                return;
            }

            searchTimeout = setTimeout(() => {
                $.get('../api/missing_product_search_api.php', { q: query }, function(resp) {
                    if (resp.success && resp.results.length > 0) {
                        let html = '';
                        resp.results.forEach(product => {
                            const imagePath = product.image_url || '../images/noimg.png';
                            const poLabel = product.po_label || product.po_number || (product.po_id ? 'PO-' + product.po_id : '-');
                            
                            // แสดงสินค้าแยกตามล็อต (receive_id)
                            const receiveBatches = product.receive_batches || [];
                            
                            if (receiveBatches.length > 0) {
                                // แสดงแยกตามแต่ละล็อต
                                receiveBatches.forEach(batch => {
                                    const batchExpiryLabel = batch.expiry_date 
                                        ? new Date(batch.expiry_date).toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
                                        : '-';
                                    const batchQtyLabel = (batch.available_qty !== undefined && batch.available_qty !== null)
                                        ? parseFloat(batch.available_qty).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                                        : '0';
                                    
                                    const batchStatusLabel = parseFloat(batch.available_qty) > 0 
                                        ? `ล็อต #${batch.receive_id}`
                                        : '<span class="text-danger">สินค้าหมด</span>';
                                    
                                    // สร้าง product object สำหรับแต่ละล็อต
                                    const batchProduct = {
                                        ...product,
                                        receive_id: batch.receive_id,
                                        receive_qty: batch.available_qty,
                                        expiry_date: batch.expiry_date,
                                        expiry_date_formatted: batchExpiryLabel,
                                        receive_created_at: batch.created_at,
                                        receive_created_at_formatted: batch.created_at 
                                            ? new Date(batch.created_at).toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
                                            : '-'
                                    };
                                    
                                    const dataset = encodeURIComponent(JSON.stringify(batchProduct));
                                    const isOutOfStock = parseFloat(batch.available_qty) <= 0;
                                    const clickableClass = isOutOfStock ? 'opacity-50' : '';
                                    const disabledAttr = isOutOfStock ? 'style="cursor: not-allowed; pointer-events: none;"' : '';
                                    
                                    html += `<div class="product-item ${clickableClass}" data-product="${dataset}" ${disabledAttr}>
                                        <div class="row align-items-center g-2">
                                            <div class="col-auto">
                                                <img src="${imagePath}" alt="Product" class="product-image" onerror="this.src='../images/noimg.png'">
                                            </div>
                                            <div class="col">
                                                <div><strong>${product.product_name}</strong></div>
                                                <small class="text-muted d-block">SKU: ${product.sku || '-'} | Barcode: ${product.barcode || '-'}</small>
                                                <small class="text-muted d-block">PO: ${poLabel} | วันหมดอายุ: ${batchExpiryLabel}</small>
                                                <small class="text-muted d-block">${batchStatusLabel}${parseFloat(batch.available_qty) > 0 ? ' | รับเมื่อ: ' + batchProduct.receive_created_at_formatted : ''}</small>
                                            </div>
                                        </div>
                                    </div>`;
                                });
                            } else {
                                // Fallback หากไม่มี receive_batches
                                const expiryLabel = product.expiry_date_formatted || '-';
                                const dataset = encodeURIComponent(JSON.stringify(product));
                                html += `<div class="product-item opacity-50" data-product="${dataset}" style="cursor: not-allowed; pointer-events: none;">
                                    <div class="row align-items-center g-2">
                                        <div class="col-auto">
                                            <img src="${imagePath}" alt="Product" class="product-image" onerror="this.src='../images/noimg.png'">
                                        </div>
                                        <div class="col">
                                            <div><strong>${product.product_name}</strong></div>
                                            <small class="text-muted d-block">SKU: ${product.sku || '-'} | Barcode: ${product.barcode || '-'}</small>
                                            <small class="text-muted d-block">PO: ${poLabel} | วันหมดอายุ: ${expiryLabel}</small>
                                            <small class="text-danger d-block">สินค้าหมด</small>
                                        </div>
                                    </div>
                                </div>`;
                            }
                        });
                        $('#search-results').html(html).addClass('show');
                    } else if (resp.success && resp.results.length === 0) {
                        $('#search-results').html('<div class="p-3 text-muted text-center"><span class="material-icons">search_off</span><br>ไม่พบสินค้า</div>').addClass('show');
                    } else {
                        $('#search-results').html('<div class="p-3 text-danger text-center">เกิดข้อผิดพลาด: ' + (resp.message || 'ไม่สามารถค้นหา') + '</div>').addClass('show');
                    }
                }).fail(function() {
                    $('#search-results').html('<div class="p-3 text-danger text-center">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>').addClass('show');
                });
            }, 300);
        });

        // Select product from search results
        $(document).on('click', '.product-item', function() {
            let productData;
            try {
                productData = JSON.parse(decodeURIComponent($(this).attr('data-product')));
            } catch (error) {
                console.error('Failed to parse product data', error);
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถอ่านข้อมูลสินค้าได้', 'error');
                return;
            }

            const uniqueKey = buildSelectedProductKey(productData);
            const existingIndex = selectedProducts.findIndex(item => item.uniqueKey === uniqueKey);

            if (existingIndex !== -1) {
                const currentQty = parseFloat(selectedProducts[existingIndex].quantity) || 0;
                selectedProducts[existingIndex].quantity = currentQty + 1;
            } else {
                selectedProducts.push({
                    ...productData,
                    uniqueKey: uniqueKey,
                    quantity: 1
                });
            }

            renderSelectedProducts();

            // Clear search and hide results
            $('#barcode-search').val('');
            $('#search-results').removeClass('show').empty();
        });

        $(document).on('input', '.missing-quantity-input', function() {
            const index = $(this).data('index');
            const value = parseFloat($(this).val());
            if (index >= 0 && index < selectedProducts.length) {
                selectedProducts[index].quantity = Number.isNaN(value) ? '' : value;
            }
        });

        $(document).on('click', '.remove-selected-product', function(e) {
            e.preventDefault();
            const key = $(this).data('key');
            selectedProducts = selectedProducts.filter(product => product.uniqueKey !== key);
            renderSelectedProducts();
        });

        // Submit missing product
        $('#submit-btn').click(async function() {
            if (selectedProducts.length === 0) {
                Swal.fire('ข้อผิดพลาด', 'กรุณาเลือกสินค้าอย่างน้อย 1 รายการ', 'warning');
                return;
            }

            $('.missing-quantity-input').each(function() {
                const index = $(this).data('index');
                const value = parseFloat($(this).val());
                if (!Number.isNaN(value)) {
                    selectedProducts[index].quantity = value;
                }
            });

            const invalidEntry = selectedProducts.find(item => {
                const value = parseFloat(item.quantity);
                return Number.isNaN(value) || value <= 0;
            });

            if (invalidEntry) {
                Swal.fire('ข้อผิดพลาด', `กรุณากรอกจำนวนที่มากกว่า 0 สำหรับสินค้า ${invalidEntry.product_name}`, 'warning');
                return;
            }

            const button = $(this);

            const remarkCheck = getSelectedRemark();
            if (!remarkCheck.valid) {
                Swal.fire('ข้อผิดพลาด', remarkCheck.message, 'warning');
                return;
            }
            const remark = remarkCheck.remark;

            const totalSelected = selectedProducts.length;
            const productsToSubmit = [...selectedProducts];
            const processedKeys = [];
            let hasChanges = false;

            button.prop('disabled', true);

            try {
                for (const product of productsToSubmit) {
                    const quantity = parseFloat(product.quantity);

                    await new Promise((resolve, reject) => {
                        $.post('../api/record_missing_product_api.php', {
                            product_id: product.product_id,
                            item_id: product.item_id || 0,
                            receive_id: product.receive_id || 0,
                            po_id: product.po_id || 0,
                            expiry_date: product.expiry_date || '',
                            quantity_missing: quantity,
                            remark: remark,
                            reported_by: userId
                        }, function(resp) {
                            if (resp.success) {
                                hasChanges = true;
                                processedKeys.push(product.uniqueKey);
                                resolve(resp);
                            } else {
                                reject(new Error((resp.message || 'ไม่สามารถบันทึกได้') + ' (' + product.product_name + ')'));
                            }
                        }, 'json').fail(function() {
                            reject(new Error('เกิดข้อผิดพลาดในการเชื่อมต่อ (' + product.product_name + ')'));
                        });
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกสำเร็จ',
                    text: 'บันทึกสินค้าสูญหาย ' + totalSelected + ' รายการเรียบร้อย',
                    timer: 2200
                });

                selectedProducts = [];
                $('#remark-select').val('');
                $('#remark').hide().val('');
                renderSelectedProducts();
                $('#barcode-search').val('').focus();
                if (hasChanges) {
                    loadMissingItemsList();
                    loadMissingStats();
                }
            } catch (error) {
                if (processedKeys.length > 0) {
                    selectedProducts = selectedProducts.filter(product => !processedKeys.includes(product.uniqueKey));
                    renderSelectedProducts();
                    if (hasChanges) {
                        loadMissingItemsList();
                        loadMissingStats();
                    }
                }
                const errorMessage = processedKeys.length > 0
                    ? error.message + '\n\nบันทึกแล้ว ' + processedKeys.length + ' รายการแรก'
                    : error.message;
                Swal.fire('ข้อผิดพลาด', errorMessage, 'error');
            } finally {
                button.prop('disabled', false);
            }
        });

        function getFilterValues() {
            const dateValue = $('#filter-date').val();
            const keywordValue = $('#filter-keyword').val().trim();
            return {
                date: dateValue,
                keyword: keywordValue
            };
        }

        function showLoadingRow() {
            $('#missing-items-tbody').html(`<tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;"></div>
                    <div class="mt-2">กำลังโหลดข้อมูล...</div>
                </td>
            </tr>`);
        }

        function renderStatsCards(stats) {
            const container = $('#missing-stats-cards');
            if (!Array.isArray(stats)) {
                $('#missing-stats-row').hide();
                container.empty();
                return;
            }

            const statsMap = new Map();
            stats.forEach(record => {
                const key = record.remark_group || 'อื่นๆ';
                statsMap.set(key, record);
            });

            let cardsHtml = '';
            remarkCardOrder.forEach(groupKey => {
                const config = presetRemarkLabels[groupKey] || presetRemarkLabels['อื่นๆ'];
                const record = statsMap.get(groupKey) || { total_quantity: 0, total_records: 0 };
                const totalQty = record.total_quantity !== null ? parseFloat(record.total_quantity) : 0;
                const totalRecords = record.total_records !== null ? parseInt(record.total_records, 10) : 0;

                const qtyLabel = Number.isFinite(totalQty)
                    ? totalQty.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                    : '0';
                const recordLabel = Number.isFinite(totalRecords)
                    ? totalRecords.toLocaleString('th-TH')
                    : '0';
                const subtitleText = config.subtitle
                    ? config.subtitle.replace('{records}', recordLabel)
                    : `${recordLabel} รายการ`;
                const isActive = selectedRemarkFilter === groupKey;

                cardsHtml += `
                    <div class="stat-card-item">
                        <div class="missing-stat-card${isActive ? ' active' : ''}"
                             data-remark="${groupKey}"
                             style="--card-bg-start:${config.bgStart}; --card-bg-end:${config.bgEnd}; --card-border:${config.borderColor}; --card-icon-bg:${config.iconBg}; --card-icon-color:${config.iconColor};">
                            <div class="card-content">
                                <div class="card-title">${config.title}</div>
                                <div class="card-value">${qtyLabel}</div>
                                <div class="card-subtitle">${subtitleText}</div>
                            </div>
                            <div class="card-icon">
                                <span class="material-icons">${config.icon}</span>
                            </div>
                        </div>
                    </div>`;
            });

            container.html(cardsHtml);

            if (!$('#missing-stats-row').is(':visible') && remarkCardOrder.length > 0) {
                $('#missing-stats-row').slideDown(150);
            }

            updateActiveStatCards();
        }

        function updateActiveStatCards() {
            $('.missing-stat-card').each(function() {
                const key = $(this).data('remark');
                if (selectedRemarkFilter && key === selectedRemarkFilter) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        }

        function loadMissingStats() {
            $.get('../api/get_missing_products_stats_api.php', function(resp) {
                if (resp.success) {
                    renderStatsCards(resp.data || []);
                } else {
                    $('#missing-stats-row').hide();
                    $('#missing-stats-cards').empty();
                }
            }).fail(function() {
                $('#missing-stats-row').hide();
                $('#missing-stats-cards').empty();
            });
        }

        // Load missing items list
        function loadMissingItemsList() {
            clearTimeout(tableFilterTimeout);
            const filters = getFilterValues();
            const params = { limit: 200 };
            if (filters.date) {
                params.date = filters.date;
            }
            if (filters.keyword) {
                params.search = filters.keyword;
            }
            if (selectedRemarkFilter) {
                params.remark_group = selectedRemarkFilter;
            }

            showLoadingRow();

            $.get('../api/get_missing_products_api.php', params, function(resp) {
                if (resp.success && resp.data.length > 0) {
                    let html = '';
                    resp.data.forEach((item, index) => {
                        const productName = item.product_name ? escapeHtml(item.product_name) : '-';
                        const sku = item.sku ? escapeHtml(item.sku) : '-';
                        const barcode = item.barcode ? escapeHtml(item.barcode) : '-';
                        let quantityDisplay = '-';
                        if (item.quantity_missing !== undefined && item.quantity_missing !== null && item.quantity_missing !== '') {
                            const quantityNumber = parseFloat(item.quantity_missing);
                            quantityDisplay = Number.isNaN(quantityNumber)
                                ? escapeHtml(item.quantity_missing)
                                : quantityNumber.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        }

                        let remarkHtml = item.remark && item.remark.trim() !== ''
                            ? escapeHtml(item.remark).replace(/\r?\n/g, '<br>')
                            : '<span class="text-muted">-</span>';

                        const isReturned = Number(item.is_returned) === 1;
                        if (isReturned) {
                            const returnAtRaw = item.return_created_at ? String(item.return_created_at).replace(' ', 'T') : '';
                            const returnDateObj = returnAtRaw ? new Date(returnAtRaw) : null;
                            const returnDateLabel = returnDateObj
                                ? returnDateObj.toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
                                : '';
                            const returnTimeLabel = returnDateObj
                                ? returnDateObj.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                                : '';
                            const returnByName = item.return_created_by_name ? escapeHtml(item.return_created_by_name) : '';
                            const infoParts = [];
                            infoParts.push('คืนแล้ว');
                            if (returnDateLabel) {
                                infoParts.push(returnDateLabel + (returnTimeLabel ? ' ' + returnTimeLabel : ''));
                            }
                            if (returnByName) {
                                infoParts.push('โดย ' + returnByName);
                            }
                            remarkHtml += `<br><small class="text-success">${infoParts.join(' ')}</small>`;
                        }

                        const createdAt = item.created_at ? new Date(item.created_at) : null;
                        const dateLabel = createdAt
                            ? createdAt.toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' })
                            : '-';
                        const timeLabel = createdAt
                            ? createdAt.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                            : '-';
                        const createdByName = item.created_by_name ? escapeHtml(item.created_by_name) : 'N/A';

                        const returnDisabledAttr = isReturned ? 'disabled aria-disabled="true"' : '';
                        const returnBtnTitle = isReturned ? 'คืนสินค้าแล้ว' : 'คืนสินค้า';

                        html += `<tr>
                            <td>${index + 1}</td>
                            <td>
                                <strong>${productName}</strong><br>
                                <small class="text-muted">SKU: ${sku} | Barcode: ${barcode}</small>
                            </td>
                            <td class="text-center">${quantityDisplay}</td>
                            <td class="remark-cell">${remarkHtml}</td>
                            <td>${createdByName}</td>
                            <td>${dateLabel}<br><small class="text-muted">${timeLabel}</small></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-success return-missing-btn" data-id="${item.missing_id}" ${returnDisabledAttr} title="${returnBtnTitle}">
                                        <span class="material-icons" style="font-size: 1rem;">undo</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-missing-btn" data-id="${item.missing_id}">
                                        <span class="material-icons" style="font-size: 1rem;">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                    });
                    $('#missing-items-tbody').html(html);
                    updateActiveStatCards();
                } else {
                    $('#missing-items-tbody').html(`<tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <span class="material-icons">inbox</span><br>
                            ยังไม่มีรายการสินค้าสูญหาย
                        </td>
                    </tr>`);
                    renderStatsCards([]);
                    loadMissingStats();
                    updateActiveStatCards();
                }
            }).fail(function() {
                $('#missing-items-tbody').html(`<tr>
                    <td colspan="7" class="text-center text-danger py-4">
                        <span class="material-icons">error</span><br>
                        ไม่สามารถโหลดข้อมูลได้ กรุณาลองอีกครั้ง
                    </td>
                </tr>`);
                renderStatsCards([]);
                updateActiveStatCards();
            });
        }

        // Load on page load
        $(document).ready(function() {
            renderSelectedProducts();
            const today = new Date().toISOString().split('T')[0];
            if (!$('#filter-date').val()) {
                $('#filter-date').val(today);
            }
            loadMissingItemsList();
            $('#barcode-search').focus();
            loadMissingStats();
        });

        $('#filter-date').on('change', function() {
            loadMissingItemsList();
        });

        function scheduleFilterReload() {
            clearTimeout(tableFilterTimeout);
            tableFilterTimeout = setTimeout(() => {
                loadMissingItemsList();
            }, 400);
        }

        $('#filter-keyword').on('input', function() {
            scheduleFilterReload();
        });

        $('#filter-keyword').on('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadMissingItemsList();
            }
        });

        $('#filter-apply-btn').on('click', function() {
            loadMissingItemsList();
        });

        $('#filter-clear-btn').on('click', function() {
            $('#filter-date').val('');
            $('#filter-keyword').val('');
            selectedRemarkFilter = null;
            updateActiveStatCards();
            loadMissingItemsList();
        });

        $('#missing-stats-cards').on('click', '.missing-stat-card', function() {
            const remarkKey = $(this).data('remark');
            if (!remarkKey) {
                return;
            }

            if (selectedRemarkFilter === remarkKey) {
                selectedRemarkFilter = null;
            } else {
                selectedRemarkFilter = remarkKey;
            }

            updateActiveStatCards();
            loadMissingItemsList();
        });

        // Return button handler
        $(document).on('click', '.return-missing-btn', function() {
            const button = $(this);
            if (button.prop('disabled')) {
                return;
            }

            const id = button.data('id');

            Swal.fire({
                title: 'ยืนยันการคืนสินค้า?',
                text: 'ระบบจะบันทึกการคืนสินค้าเข้าความเคลื่อนไหวสินค้า',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'คืนสินค้า',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (!result.isConfirmed) {
                    return;
                }

                button.prop('disabled', true);

                $.post('../api/return_missing_product_api.php', {
                    missing_id: id,
                    returned_by: userId
                }, function(resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'คืนสินค้าเรียบร้อย',
                            timer: 1600
                        });
                        loadMissingItemsList();
                        loadMissingStats();
                    } else {
                        button.prop('disabled', false);
                        Swal.fire('ข้อผิดพลาด', resp.message || 'ไม่สามารถคืนสินค้าได้', 'error');
                    }
                }).fail(function() {
                    button.prop('disabled', false);
                    Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                });
            });
        });

        // Delete button handler
        $(document).on('click', '.delete-missing-btn', function() {
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
                            Swal.fire('ลบสำเร็จ', '', 'success');
                            loadMissingItemsList();
                            loadMissingStats();
                        } else {
                            Swal.fire('ข้อผิดพลาด', resp.message, 'error');
                        }
                    });
                }
            });
        });

        // Auto-focus barcode input
        $(document).keydown(function(e) {
            if (e.key === '/') {
                e.preventDefault();
                $('#barcode-search').focus();
            }
        });
    </script>
            </div>
        </div>
    </div>
</body>
</html>
