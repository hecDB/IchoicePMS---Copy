<?php
session_start();
require '../config/db_connect.php';
require '../templates/sidebar.php';

// ตรวจสอบสิทธิ์การเข้าถึง
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สินค้าตีกลับ - IchoicePMS</title>
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
        
        .mainwrap {
            display: flex;
            margin-left: 280px;
            padding: 2rem;
            gap: 1rem;
        }
        
        .card {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .search-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
        }
        
        .search-input {
            font-size: 1.1rem;
            padding: 0.75rem;
            border: none;
            border-radius: 10px;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }
        
        .po-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #3b82f6;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .po-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateX(4px);
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #10b981;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            background: #e5e7eb;
        }
        
        .return-form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-btn {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="mainwrap">
        <div style="flex: 1;">
            <div class="container-fluid">
                <!-- Header -->
                <div style="margin-bottom: 2rem;">
                    <h1 style="font-size: 2rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem;">
                        📦 สินค้าตีกลับ
                    </h1>
                    <p style="color: #6b7280; font-size: 1rem;">
                        บันทึกและจัดการสินค้าที่ลูกค่าตีกลับมา
                    </p>
                </div>

                <!-- Tabs -->
                <div class="card mb-4">
                    <div style="display: flex; border-bottom: 1px solid #e5e7eb;">
                        <button class="tab-btn active" onclick="switchTab('form')">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">add_circle</span>
                            บันทึกสินค้าตีกลับ
                        </button>
                        <button class="tab-btn" onclick="switchTab('list')">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">list</span>
                            รายการตีกลับ
                        </button>
                    </div>
                </div>

                <!-- TAB 1: FORM -->
                <div id="form-tab" class="tab-content active">
                    <!-- Search Issue Tag -->
                    <div class="search-box">
                        <h5 class="mb-3">🔍 ค้นหาเลขแท็ค</h5>
                        <input type="text" id="issue-tag-search" class="search-input" placeholder="ใส่เลขแท็ค (issue_tag) ที่ออกสินค้า...">
                        <small style="color: rgba(255,255,255,0.8);">ผลลัพธ์จะแสดงที่ด้านล่าง</small>
                    </div>

                    <!-- Issue Tag Search Results -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="card-title">📋 ผลการค้นหาเลขแท็ค</h6>
                            <div id="issue-tag-results" style="max-height: 300px; overflow-y: auto;">
                                <p class="text-muted">ค้นหาเลขแท็คเพื่อดูรายการสินค้าที่อออก</p>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Sales Order Details -->
                    <div id="sales-order-details-section" style="display: none;">
                        <div class="return-form-section">
                            <h6 class="mb-3">📄 รายละเอียดการออกสินค้า</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>เลขแท็ค:</strong> <span id="selected-issue-tag"></span></p>
                                    <p><strong>วันที่ออก:</strong> <span id="selected-issue-date"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>ลูกค้า:</strong> <span id="selected-customer-name"></span></p>
                                    <p><strong>จำนวนรายการ:</strong> <span id="selected-sales-items"></span> รายการ</p>
                                </div>
                            </div>
                            <button class="btn btn-secondary btn-sm" onclick="resetSalesOrderSelection()">ค้นหาเลขแท็คใหม่</button>
                        </div>

                        <!-- Sales Order Items -->
                        <div class="return-form-section">
                            <h6 class="mb-3">📦 สินค้าที่ออก</h6>
                            <div id="sales-items-list"></div>
                        </div>
                    </div>

                    <!-- Return Form -->
                    <div id="return-form-section" style="display: none;">
                        <div class="return-form-section">
                            <h6 class="mb-3">⬅️ กรอกข้อมูลสินค้าตีกลับ</h6>
                            <form id="return-form">
                                <input type="hidden" id="form-so-id">
                                <input type="hidden" id="form-po-id">
                                <input type="hidden" id="form-item-id">
                                <input type="hidden" id="form-product-id">

                                <!-- Product Info -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อสินค้า</label>
                                        <input type="text" id="form-product-name" class="form-control" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SKU / Barcode</label>
                                        <input type="text" id="form-sku-barcode" class="form-control" disabled>
                                    </div>
                                </div>

                                <!-- Qty -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">จำนวนออกเดิม</label>
                                        <input type="number" id="form-original-qty" class="form-control" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">จำนวนตีกลับ <span style="color: red;">*</span></label>
                                        <input type="number" id="form-return-qty" class="form-control" min="0.01" step="0.01" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">คงเหลือสามารถตีกลับ</label>
                                        <input type="text" id="form-available-qty" class="form-control" disabled>
                                    </div>
                                </div>

                                <!-- Return Reason -->
                                <div class="mb-3">
                                    <label class="form-label">เหตุผลการตีกลับ <span style="color: red;">*</span></label>
                                    <select id="form-reason-id" class="form-control" required onchange="updateReasonInfo()">
                                        <option value="">-- เลือกเหตุผล --</option>
                                    </select>
                                    <small id="reason-description" class="text-muted d-block mt-2"></small>
                                    <span id="reason-badge" class="badge mt-2" style="display: none;"></span>
                                </div>

                                <!-- Notes -->
                                <div class="mb-3">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea id="form-notes" class="form-control" rows="4" placeholder="เพิ่มหมายเหตุเกี่ยวกับสินค้า เช่น สภาพการบรรจุ หรือปัญหา..."></textarea>
                                </div>

                                <div style="display: flex; gap: 1rem;">
                                    <button type="submit" class="btn btn-primary">
                                        <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">save</span>
                                        บันทึกสินค้าตีกลับ
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetReturnForm()">
                                        ยกเลิก
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: LIST -->
                <div id="list-tab" class="tab-content">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">สถานะ</label>
                                    <select id="filter-status" class="form-control" onchange="loadReturnsList()">
                                        <option value="all">ทั้งหมด</option>
                                        <option value="pending">รอการอนุมัติ</option>
                                        <option value="approved">อนุมัติแล้ว</option>
                                        <option value="completed">เสร็จสิ้น</option>
                                        <option value="rejected">ปฏิเสธ</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ประเภท</label>
                                    <select id="filter-returnable" class="form-control" onchange="loadReturnsList()">
                                        <option value="all">ทั้งหมด</option>
                                        <option value="1">สามารถคืนสต็อก</option>
                                        <option value="0">ไม่สามารถคืนสต็อก</option>
                                    </select>
                                </div>
                                <div class="col-md-4" style="display: flex; align-items: flex-end;">
                                    <button class="btn btn-primary w-100" onclick="loadReturnsList()">
                                        <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">refresh</span>
                                        รีเฟรช
                                    </button>
                                </div>
                            </div>

                            <div id="returns-list" style="max-height: 600px; overflow-y: auto;">
                                <p class="text-muted text-center">กำลังโหลด...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div id="alertModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" id="alertModalContent">
                <div class="modal-body text-center p-4">
                    <div id="alertIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
                    <h5 id="alertTitle" class="mb-3" style="font-weight: 600;"></h5>
                    <p id="alertMessage" class="text-muted" style="margin: 0;"></p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const API_URL = '../api/returned_items_api.php';
        let selectedSalesOrder = null;
        let returnReasons = [];

        function escapeAttr(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/'/g, '&#39;');
        }

        // Show Alert Modal
        function showAlert(type, title, message) {
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));
            const content = document.getElementById('alertModalContent');
            const icon = document.getElementById('alertIcon');
            const titleEl = document.getElementById('alertTitle');
            const msgEl = document.getElementById('alertMessage');
            
            titleEl.textContent = title;
            msgEl.textContent = message;
            
            // Remove all type classes
            content.classList.remove('border-success', 'border-danger', 'border-info', 'border-warning');
            
            if (type === 'success') {
                icon.innerHTML = '✓';
                content.style.borderLeft = '4px solid #10b981';
                content.classList.add('border-success');
            } else if (type === 'error') {
                icon.innerHTML = '✕';
                content.style.borderLeft = '4px solid #ef4444';
                content.classList.add('border-danger');
            } else if (type === 'info') {
                icon.innerHTML = 'ℹ';
                content.style.borderLeft = '4px solid #3b82f6';
                content.classList.add('border-info');
            }
            
            modal.show();
        }

        // Initialize
        $(document).ready(function() {
            loadReturnReasons();
        });

        function switchTab(tab) {
            $('.tab-content').removeClass('active');
            $('.tab-btn').removeClass('active');
            
            if (tab === 'form') {
                $('#form-tab').addClass('active');
                $('.tab-btn').eq(0).addClass('active');
            } else {
                $('#list-tab').addClass('active');
                $('.tab-btn').eq(1).addClass('active');
                loadReturnsList();
            }
        }

        // Load return reasons
        function loadReturnReasons() {
            $.get(`${API_URL}?action=get_reasons`, function(response) {
                if (response.status === 'success') {
                    returnReasons = response.data;
                    populateReasonSelect();
                }
            });
        }

        function populateReasonSelect() {
            let html = '<option value="">-- เลือกเหตุผล --</option>';
            
            // Group by category
            const returnable = returnReasons.filter(r => r.is_returnable == 1);
            const nonReturnable = returnReasons.filter(r => r.is_returnable == 0);
            
            if (returnable.length > 0) {
                html += '<optgroup label="สามารถคืนสต็อก">';
                returnable.forEach(reason => {
                    html += `<option value="${reason.reason_id}" data-returnable="1">${reason.reason_name}</option>`;
                });
                html += '</optgroup>';
            }
            
            if (nonReturnable.length > 0) {
                html += '<optgroup label="ไม่สามารถคืนสต็อก">';
                nonReturnable.forEach(reason => {
                    html += `<option value="${reason.reason_id}" data-returnable="0">${reason.reason_name}</option>`;
                });
                html += '</optgroup>';
            }
            
            $('#form-reason-id').html(html);
        }

        // Search Issue Tag
        $('#issue-tag-search').on('keyup', function() {
            let keyword = $(this).val().trim();
            if (keyword.length < 2) {
                $('#issue-tag-results').html('<p class="text-muted">ค้นหาเลขแท็คเพื่อดูรายการสินค้าที่อออก</p>');
                return;
            }

            $.get(`${API_URL}?action=search_by_issue_tag&keyword=${encodeURIComponent(keyword)}`, function(response) {
                if (response.status === 'success') {
                    let html = '';
                    if (response.data.length === 0) {
                        html = '<p class="text-muted">ไม่พบผลลัพธ์</p>';
                    } else {
                        response.data.forEach(order => {
                            const date = new Date(order.created_at).toLocaleDateString('th-TH');
                            html += `
                                <div class="po-item" onclick="selectSalesOrder(${order.so_id}, '${order.issue_tag}', '${order.customer_name}', '${order.created_at}', ${order.total_items})">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong>${order.issue_tag}</strong><br>
                                            <small class="text-muted">${order.customer_name} • ${date}</small>
                                        </div>
                                        <span class="badge bg-info">${order.total_items} รายการ</span>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    $('#issue-tag-results').html(html);
                }
            });
        });

        function selectSalesOrder(so_id, issue_tag, customer_name, created_at, total_items) {
            selectedSalesOrder = { so_id, issue_tag, customer_name, created_at, total_items };
            
            // Update UI
            $('#selected-issue-tag').text(issue_tag);
            $('#selected-issue-date').text(new Date(created_at).toLocaleDateString('th-TH'));
            $('#selected-customer-name').text(customer_name);
            $('#selected-sales-items').text(total_items);
            
            $('#sales-order-details-section').show();
            $('#issue-tag-search').val('');
            $('#issue-tag-results').html('');
            
            // Load sales order items
            loadSalesOrderItems(so_id);
        }

        function loadSalesOrderItems(so_id) {
            $.get(`${API_URL}?action=get_sales_order_items&so_id=${so_id}`, function(response) {
                if (response.status === 'success') {
                    let html = '';
                    if (response.data.length === 0) {
                        html = '<p class="text-warning"><strong>⚠️ ไม่พบสินค้าในเลขแท็คนี้</strong></p>';
                    } else {
                        response.data.forEach(item => {
                            const imageUrl = item.image ? '../' + item.image : '../images/noimg.png';
                            const availableQty = Number(item.available_qty || 0);
                            const returnedQty = Number(item.returned_qty || 0);
                            const alreadyReturned = Number(item.already_returned || 0) === 1;
                            const buttonDisabled = availableQty <= 0 || alreadyReturned;
                            const buttonLabel = buttonDisabled ? 'ตีกลับแล้ว' : 'เลือก';
                            const buttonClasses = buttonDisabled
                                ? 'btn btn-sm btn-outline-secondary disabled'
                                : 'btn btn-sm btn-outline-primary';
                            const disabledAttrs = buttonDisabled ? 'disabled aria-disabled="true"' : '';
                            const notice = buttonDisabled
                                ? '<p class="text-danger mb-0" style="font-size: 0.85rem;">รายการนี้มีการตีกลับแล้ว ไม่สามารถตีกลับซ้ำ</p>'
                                : '';

                            html += `
                                <div class="product-card">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="display: flex; gap: 1rem; flex: 1;">
                                            <img src="${imageUrl}" alt="Product" class="product-image" onerror="this.src='../images/noimg.png'">
                                            <div style="flex: 1;">
                                                <h6 class="mb-1">${item.product_name}</h6>
                                                <p class="text-muted mb-2" style="font-size: 0.9rem;">
                                                    SKU: ${item.sku} | Barcode: ${item.barcode || '-'}
                                                </p>
                                                <p class="mb-1" style="font-size: 0.9rem;">
                                                    <strong>จำนวนออก:</strong> ${item.issue_qty} | 
                                                    <strong>ตีกลับแล้ว:</strong> ${returnedQty} | 
                                                    <strong>สามารถตีกลับได้:</strong> ${availableQty}
                                                </p>
                                                ${notice}
                                            </div>
                                        </div>
                                        <button type="button" class="${buttonClasses}" ${disabledAttrs}
                                            data-so-id="${selectedSalesOrder.so_id}"
                                            data-item-id="${item.si_id}"
                                            data-product-id="${item.product_id}"
                                            data-product-name="${escapeAttr(item.product_name)}"
                                            data-sku="${escapeAttr(item.sku || '')}"
                                            data-barcode="${escapeAttr(item.barcode || '')}"
                                            data-original-qty="${item.issue_qty}"
                                            data-available-qty="${availableQty}"
                                            onclick="handleSelectProduct(this)">
                                            ${buttonLabel}
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    $('#sales-items-list').html(html);
                } else {
                    $('#sales-items-list').html('<p class="text-danger"><strong>❌ เกิดข้อผิดพลาด:</strong> ' + (response.message || 'Unknown error') + '</p>');
                }
            }).fail(function(xhr) {
                $('#sales-items-list').html('<p class="text-danger"><strong>❌ API Error:</strong> ' + xhr.status + '</p>');
            });
        }

        function resetSalesOrderSelection() {
            selectedSalesOrder = null;
            $('#sales-order-details-section').hide();
            $('#return-form-section').hide();
            resetReturnForm();
        }

        function handleSelectProduct(button) {
            if (button.classList.contains('disabled')) {
                showAlert('error', 'ไม่สามารถเลือกสินค้า', 'รายการนี้มีการตีกลับแล้ว');
                return;
            }

            const { soId, itemId, productId, productName, sku, barcode, originalQty, availableQty } = button.dataset;
            const skuBarcode = `${sku || '-'} / ${barcode || '-'}`;
            selectProduct(
                Number(soId),
                Number(itemId),
                Number(productId),
                productName,
                skuBarcode,
                parseFloat(originalQty),
                parseFloat(availableQty)
            );
        }

        function selectProduct(so_id, item_id, product_id, product_name, sku_barcode, original_qty, available_qty) {
            if (!available_qty || available_qty <= 0) {
                showAlert('error', 'ไม่สามารถเลือกสินค้า', 'รายการนี้มีการตีกลับแล้ว');
                return;
            }
            $('#form-so-id').val(so_id);
            $('#form-po-id').val('');
            $('#form-item-id').val(item_id);
            $('#form-product-id').val(product_id);
            $('#form-product-name').val(product_name);
            $('#form-sku-barcode').val(sku_barcode);
            $('#form-original-qty').val(original_qty);
            $('#form-available-qty').val(available_qty);
            $('#form-return-qty').val('').attr('max', available_qty);
            $('#form-reason-id').val('');
            $('#form-notes').val('');
            
            $('#return-form-section').show();
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $('#return-form-section').offset().top - 100
            }, 800);
        }

        function updateReasonInfo() {
            const reasonId = $('#form-reason-id').val();
            const reason = returnReasons.find(r => r.reason_id == reasonId);
            
            if (reason) {
                $('#reason-description').text(reason.description);
                const badge = $('#reason-badge');
                if (reason.is_returnable == 1) {
                    badge.removeClass('badge-danger').addClass('badge-success').text('✓ สามารถคืนสต็อก').show();
                } else {
                    badge.removeClass('badge-success').addClass('badge-danger').text('✗ ไม่สามารถคืนสต็อก').show();
                }
            }
        }

        function resetReturnForm() {
            $('#return-form')[0].reset();
            $('#return-form-section').hide();
        }

        // Submit return form
        $('#return-form').on('submit', function(e) {
            e.preventDefault();
            
            const data = {
                so_id: $('#form-so-id').val() || null,
                po_id: $('#form-po-id').val() || null,
                item_id: $('#form-item-id').val(),
                product_id: $('#form-product-id').val(),
                return_qty: $('#form-return-qty').val(),
                reason_id: $('#form-reason-id').val(),
                notes: $('#form-notes').val()
            };
            
            $.ajax({
                url: API_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ ...data, action: 'create_return' }),
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('success', '✓ บันทึกสำเร็จ!', `เลขที่: ${response.return_code}`);
                        setTimeout(() => {
                            resetReturnForm();
                            resetSalesOrderSelection();
                        }, 1500);
                    } else {
                        showAlert('error', '✕ เกิดข้อผิดพลาด', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error response:', xhr);
                    let errorMsg = 'เกิดข้อผิดพลาดในการสื่อสารกับเซิร์ฟเวอร์';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch(e) {}
                    showAlert('error', '✕ ข้อผิดพลาด', `${errorMsg}\n(Status: ${xhr.status})`);
                }
            });
        });

        // Load returns list
        function loadReturnsList() {
            const status = $('#filter-status').val() || 'all';
            const is_returnable = $('#filter-returnable').val() || 'all';
            
            $.get(`${API_URL}?action=get_returns&status=${status}&is_returnable=${is_returnable}&limit=100`, function(response) {
                if (response.status === 'success') {
                    let html = '';
                    
                    if (response.data.length === 0) {
                        html = '<p class="text-muted text-center">ไม่พบข้อมูลสินค้าตีกลับ</p>';
                    } else {
                        const table = `
                            <table class="table table-hover" style="font-size: 0.95rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>เลขที่</th>
                                        <th>สินค้า</th>
                                        <th>จำนวน</th>
                                        <th>เหตุผล</th>
                                        <th>ประเภท</th>
                                        <th>สถานะ</th>
                                        <th>บันทึกโดย</th>
                                        <th>วันที่</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${response.data.map(item => `
                                        <tr onclick="viewReturnDetail(${item.return_id})" style="cursor: pointer;">
                                            <td><strong>${item.return_code}</strong></td>
                                            <td>${item.product_name}<br><small class="text-muted">${item.sku}</small></td>
                                            <td>${item.return_qty}</td>
                                            <td>${item.reason_name}</td>
                                            <td>
                                                ${item.is_returnable == 1 
                                                    ? '<span class="badge bg-success">คืนได้</span>' 
                                                    : '<span class="badge bg-danger">คืนไม่ได้</span>'}
                                            </td>
                                            <td>${getStatusBadge(item.return_status)}</td>
                                            <td><small>${item.created_by_name}</small></td>
                                            <td><small>${new Date(item.created_at).toLocaleDateString('th-TH')}</small></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                        html = table;
                    }
                    
                    $('#returns-list').html(html);
                }
            });
        }

        function getStatusBadge(status) {
            const badges = {
                'pending':   '<span class="badge bg-warning">รอการอนุมัติ</span>',
                'approved':  '<span class="badge bg-success">เสร็จสิ้น</span>',
                'completed': '<span class="badge bg-success">เสร็จสิ้น</span>',
                'rejected':  '<span class="badge bg-danger">ปฏิเสธ</span>'
            };
            return badges[status] || status;
        }

        function viewReturnDetail(returnId) {
            $.get(`${API_URL}?action=get_return&return_id=${returnId}`, function(response) {
                if (response.status === 'success') {
                    const item = response.data;
                    const date = new Date(item.created_at).toLocaleDateString('th-TH');
                    const message = `
สินค้า: ${item.product_name} (${item.sku})
จำนวน: ${item.return_qty}
เหตุผล: ${item.reason_name}
ประเภท: ${item.is_returnable ? 'คืนสต็อกได้' : 'คืนไม่ได้'}
สถานะ: ${item.return_status}
หมายเหตุ: ${item.notes || '-'}
บันทึกโดย: ${item.created_by_name}
วันที่: ${date}`;
                    showAlert('info', `📋 ${item.return_code}`, message);
                }
            });
        }
    </script>
</body>
</html>
