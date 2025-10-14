<?php
session_start();
include '../config/db_connect.php';
include '../templates/sidebar.php';

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
    <title>ยิงสินค้าออก - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .scan-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
        }
        
        .scan-input {
            font-size: 1.2rem;
            padding: 0.75rem;
            border: none;
            border-radius: 10px;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .scan-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #10b981;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        
        .qty-input {
            width: 80px;
            text-align: center;
            font-weight: bold;
        }
        
        .tag-input {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border: none;
            padding: 0.75rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            text-align: center;
        }
        
        .issue-summary {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .btn-issue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .btn-issue:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .lot-info {
            background: #f3f4f6;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .fifo-badge {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .search-results {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-height: 300px;
            overflow-y: auto;
            display: none;
            position: absolute;
            width: 100%;
            z-index: 1000;
        }
        
        .search-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-item:hover {
            background-color: #f9fafb;
        }
        
        .search-item:last-child {
            border-bottom: none;
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
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #667eea;">shopping_cart_checkout</span>
                    ยิงสินค้าออก (ขายสินค้า)
                </h1>
                <p class="text-muted mb-0">ระบบจัดการการขายและการออกสินค้าจากคลัง</p>
            </div>
        </div>

        <div class="row">
            <!-- ส่วนสแกนและเพิ่มสินค้า -->
            <div class="col-lg-8">
                <!-- ช่องกรอกแท็คส่งออก -->
                <div class="card mb-4" style="border: none; border-radius: 15px;">
                    <div class="card-body" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 15px;">
                        <h5 class="card-title mb-3">
                            <span class="material-icons align-middle me-2">local_offer</span>
                            แท็คส่งออก
                        </h5>
                        <input type="text" 
                               id="issue-tag" 
                               class="tag-input" 
                               placeholder="สแกนหรือพิมพ์เลขแท็คส่งออก..." 
                               autofocus>
                        <small id="platform-info" class="text-muted mt-2" style="display: none; font-size: 0.875rem;">
                            <span class="material-icons align-middle" style="font-size: 1rem;">info</span>
                            <span id="platform-text"></span>
                        </small>
                    </div>
                </div>

                <!-- ช่องสแกนสินค้า -->
                <div class="scan-box" id="scan-section" style="display: none;">
                    <h5 class="mb-3">
                        <span class="material-icons align-middle me-2">qr_code_scanner</span>
                        สแกนหรือค้นหาสินค้า
                    </h5>
                    <div class="position-relative">
                        <input type="text" 
                               id="product-search" 
                               class="scan-input" 
                               placeholder="สแกนบาร์โค้ดหรือพิมพ์ SKU บางส่วน...">
                        <div id="search-results" class="search-results"></div>
                    </div>
                    <small class="opacity-75">💡 ระบบจะแสดงสินค้าที่มีสต็อกและเรียงตามล็อตเก่าที่สุดก่อน (FIFO)</small>
                </div>

                <!-- รายการสินค้าที่เลือก -->
                <div id="selected-products" class="mt-4"></div>
            </div>

            <!-- ส่วนสรุปและยืนยัน -->
            <div class="col-lg-4">
                <div class="issue-summary">
                    <h5 class="mb-3">
                        <span class="material-icons align-middle me-2">receipt_long</span>
                        สรุปการยิงสินค้า
                    </h5>
                    
                    <div id="summary-content">
                        <div class="text-center text-muted py-4">
                            <span class="material-icons mb-2" style="font-size: 3rem;">add_shopping_cart</span>
                            <p>ยังไม่มีสินค้าที่เลือก</p>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4" id="action-buttons" style="display: none;">
                        <button class="btn btn-issue" onclick="processIssue()">
                            <span class="material-icons align-middle me-2">send</span>
                            ยืนยันการยิงสินค้า
                        </button>
                        <button class="btn btn-outline-secondary" onclick="clearAll()">
                            <span class="material-icons align-middle me-2">clear_all</span>
                            ล้างทั้งหมด
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let selectedProducts = [];
let currentIssueTag = '';

$(document).ready(function() {
    // ตรวจสอบแท็คส่งออกขณะพิมพ์
    $('#issue-tag').on('input', function() {
        const tagValue = $(this).val().trim();
        checkPlatform(tagValue);
    });

    // เมื่อกรอกแท็คส่งออกเสร็จ
    $('#issue-tag').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            const tagValue = $(this).val().trim();
            if (tagValue) {
                currentIssueTag = tagValue;
                $('#scan-section').slideDown();
                $('#product-search').focus();
                $(this).prop('readonly', true);
                
                const platform = getPlatformFromTag(tagValue);
                let message = `แท็คส่งออก: ${tagValue}`;
                if (platform) {
                    message += ` (${platform})`;
                }
                
                Swal.fire({
                    title: 'เริ่มต้นการยิงสินค้า',
                    text: message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }
    });

    // ค้นหาสินค้า
    let searchTimeout;
    $('#product-search').on('input', function() {
        const query = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(function() {
                searchProducts(query);
            }, 300);
        } else {
            $('#search-results').hide();
        }
    });

    // เมื่อกดปุ่ม Enter ในช่องค้นหา
    $('#product-search').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            const query = $(this).val().trim();
            if (query) {
                searchProducts(query);
            }
        }
    });

    // ซ่อนผลการค้นหาเมื่อคลิกที่อื่น
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#product-search, #search-results').length) {
            $('#search-results').hide();
        }
    });
});

// ฟังก์ชันค้นหาสินค้า
function searchProducts(query) {
    $.ajax({
        url: '../api/product_search_api.php',
        method: 'GET',
        data: { 
            q: query,
            type: 'issue', // ระบุว่าเป็นการค้นหาสำหรับยิงสินค้า
            available_only: true // เฉพาะสินค้าที่มีสต็อก
        },
        success: function(response) {
            displaySearchResults(response.products || []);
        },
        error: function(xhr, status, error) {
            console.error('Search error:', error);
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถค้นหาสินค้าได้', 'error');
        }
    });
}

// แสดงผลการค้นหา
function displaySearchResults(products) {
    const resultsDiv = $('#search-results');
    
    if (products.length === 0) {
        resultsDiv.html('<div class="search-item text-center text-muted">ไม่พบสินค้าที่ค้นหา</div>').show();
        return;
    }
    
    let html = '';
    products.forEach(function(product) {
        html += `
            <div class="search-item" onclick="selectProduct(${product.product_id}, '${product.name}', '${product.sku}', '${product.barcode}', ${product.available_qty}, '${product.receive_id}', '${product.expiry_date || ''}', '${product.lot_info || ''}', ${product.sale_price || 0})">
                <div class="d-flex align-items-center">
                    <img src="../images/${product.image || 'noimg.png'}" 
                         class="product-image me-3" 
                         alt="${product.name}"
                         onerror="this.src='../images/noimg.png'">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${product.name}</h6>
                        <small class="text-muted">SKU: ${product.sku} | บาร์โค้ด: ${product.barcode}</small>
                        <br>
                        <small class="text-success">คงเหลือ: ${product.available_qty} ${product.unit} | ราคาขาย: ${product.sale_price || 0} บาท</small>
                        ${product.lot_info ? `<br><small class="text-info">${product.lot_info}</small>` : ''}
                        ${product.expiry_date ? `<br><small class="text-warning">หมดอายุ: ${formatDateThai(product.expiry_date)}</small>` : ''}
                    </div>
                    <span class="fifo-badge">FIFO</span>
                </div>
            </div>
        `;
    });
    
    resultsDiv.html(html).show();
}

// เลือกสินค้า
function selectProduct(productId, name, sku, barcode, availableQty, receiveId, expiryDate, lotInfo, salePrice) {
    $('#search-results').hide();
    $('#product-search').val('');
    
    // ตรวจสอบว่าสินค้านี้เลือกแล้วหรือยัง
    const existingIndex = selectedProducts.findIndex(p => p.product_id === productId && p.receive_id === receiveId);
    
    if (existingIndex !== -1) {
        Swal.fire('แจ้งเตือน', 'สินค้าล็อตนี้ถูกเลือกแล้ว', 'warning');
        return;
    }
    
    const product = {
        product_id: productId,
        receive_id: receiveId,
        name: name,
        sku: sku,
        barcode: barcode,
        available_qty: availableQty,
        expiry_date: expiryDate,
        lot_info: lotInfo,
        sale_price: parseFloat(salePrice) || 0,
        issue_qty: 1 // จำนวนเริ่มต้น
    };
    
    selectedProducts.push(product);
    updateSelectedProductsDisplay();
    updateSummary();
    
    $('#product-search').focus();
}

// อัปเดตการแสดงสินค้าที่เลือก
function updateSelectedProductsDisplay() {
    const container = $('#selected-products');
    
    if (selectedProducts.length === 0) {
        container.empty();
        return;
    }
    
    let html = '<h5 class="mb-3"><span class="material-icons align-middle me-2">shopping_cart</span>สินค้าที่เลือก</h5>';
    
    selectedProducts.forEach(function(product, index) {
        html += `
            <div class="product-card">
                <div class="d-flex align-items-center">
                    <img src="../images/${product.image || 'noimg.png'}" 
                         class="product-image me-3" 
                         onerror="this.src='../images/noimg.png'">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${product.name}</h6>
                        <small class="text-muted">SKU: ${product.sku}</small>
                        ${product.lot_info ? `<div class="lot-info"><strong>ล็อต:</strong> ${product.lot_info}</div>` : ''}
                        ${product.expiry_date ? `<div class="lot-info"><strong>วันหมดอายุ:</strong> ${formatDateThai(product.expiry_date)}</div>` : ''}
                        <div class="lot-info"><strong>คงเหลือ:</strong> ${product.available_qty} ชิ้น</div>
                        <div class="lot-info"><strong>ราคาขาย:</strong> ${product.sale_price ? product.sale_price.toFixed(2) : '0.00'} บาท</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 me-2">จำนวน:</label>
                        <input type="number" 
                               class="form-control qty-input" 
                               value="${product.issue_qty}" 
                               min="1" 
                               max="${product.available_qty}"
                               onchange="updateQuantity(${index}, this.value)">
                        <button class="btn btn-outline-danger btn-sm" onclick="removeProduct(${index})">
                            <span class="material-icons">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.html(html);
}

// อัปเดตจำนวน
function updateQuantity(index, newQty) {
    const qty = parseInt(newQty);
    const product = selectedProducts[index];
    
    if (qty < 1) {
        Swal.fire('แจ้งเตือน', 'จำนวนต้องมากกว่า 0', 'warning');
        updateSelectedProductsDisplay();
        return;
    }
    
    if (qty > product.available_qty) {
        Swal.fire('แจ้งเตือน', `จำนวนไม่เกิน ${product.available_qty} ชิ้น`, 'warning');
        updateSelectedProductsDisplay();
        return;
    }
    
    selectedProducts[index].issue_qty = qty;
    updateSummary();
}

// ลบสินค้า
function removeProduct(index) {
    selectedProducts.splice(index, 1);
    updateSelectedProductsDisplay();
    updateSummary();
}

// อัปเดตสรุป
function updateSummary() {
    const summaryContent = $('#summary-content');
    const actionButtons = $('#action-buttons');
    
    if (selectedProducts.length === 0) {
        summaryContent.html(`
            <div class="text-center text-muted py-4">
                <span class="material-icons mb-2" style="font-size: 3rem;">add_shopping_cart</span>
                <p>ยังไม่มีสินค้าที่เลือก</p>
            </div>
        `);
        actionButtons.hide();
        return;
    }
    
    let totalItems = selectedProducts.length;
    let totalQty = selectedProducts.reduce((sum, product) => sum + product.issue_qty, 0);
    let totalAmount = selectedProducts.reduce((sum, product) => sum + (product.issue_qty * (product.sale_price || 0)), 0);
    
    let html = `
        <div class="mb-3">
            <strong>แท็คส่งออก:</strong> ${currentIssueTag}
        </div>
        <div class="mb-3">
            <strong>จำนวนรายการ:</strong> ${totalItems} รายการ
        </div>
        <div class="mb-3">
            <strong>จำนวนชิ้นทั้งหมด:</strong> ${totalQty} ชิ้น
        </div>
        <div class="mb-3">
            <strong>ยอดขายรวม:</strong> <span class="text-success fw-bold">${totalAmount.toFixed(2)} บาท</span>
        </div>
        <hr>
        <div class="small">
    `;
    
    selectedProducts.forEach(function(product) {
        const itemTotal = product.issue_qty * (product.sale_price || 0);
        html += `
            <div class="d-flex justify-content-between mb-1">
                <span>${product.name}</span>
                <span>${product.issue_qty} ชิ้น (${itemTotal.toFixed(2)} บาท)</span>
            </div>
        `;
    });
    
    html += '</div>';
    
    summaryContent.html(html);
    actionButtons.show();
}

// ประมวลผลการยิงสินค้า
function processIssue() {
    if (selectedProducts.length === 0) {
        Swal.fire('แจ้งเตือน', 'กรุณาเลือกสินค้าก่อน', 'warning');
        return;
    }
    
    if (!currentIssueTag) {
        Swal.fire('แจ้งเตือน', 'กรุณากรอกแท็คส่งออก', 'warning');
        return;
    }

    const totalAmount = selectedProducts.reduce((sum, p) => sum + (p.issue_qty * (p.sale_price || 0)), 0);
    
    Swal.fire({
        title: 'ยืนยันการยิงสินค้า?',
        html: `
            <div class="text-start">
                <p><strong>แท็คส่งออก:</strong> ${currentIssueTag}</p>
                <p><strong>จำนวนรายการ:</strong> ${selectedProducts.length} รายการ</p>
                <p><strong>จำนวนชิ้นทั้งหมด:</strong> ${selectedProducts.reduce((sum, p) => sum + p.issue_qty, 0)} ชิ้น</p>
                <p><strong>ยอดขายรวม:</strong> <span class="text-success fw-bold">${totalAmount.toFixed(2)} บาท</span></p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#667eea'
    }).then((result) => {
        if (result.isConfirmed) {
            submitIssue();
        }
    });
}

// ส่งข้อมูลไปบันทึก
function submitIssue() {
    const issueData = {
        issue_tag: currentIssueTag,
        products: selectedProducts
    };
    
    $.ajax({
        url: '../api/issue_product_api.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(issueData),
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'ยิงสินค้าออกเรียบร้อยแล้ว',
                    icon: 'success',
                    confirmButtonText: 'ตกลง'
                }).then(() => {
                    clearAll();
                    location.reload();
                });
            } else {
                Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถยิงสินค้าได้', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Submit error:', error);
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถยิงสินค้าได้', 'error');
        }
    });
}

// ตรวจสอบแพลตฟอร์มจากแท็คโดยใช้ระบบใหม่
function getPlatformFromTag(tag) {
    if (!tag) return '';
    
    // เรียก API เพื่อตรวจสอบแพลตฟอร์ม
    return validateTagViaAPI(tag);
}

// ฟังก์ชันตรวจสอบแท็คผ่าน API แบบ synchronous (สำหรับความเข้ากันได้)
function validateTagViaAPI(tag) {
    try {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', '../includes/tag_validator.php?action=validate&tag=' + encodeURIComponent(tag), false);
        xhr.send();
        
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            return response.valid ? response.platform : '';
        }
    } catch (e) {
        console.error('Tag validation error:', e);
        // Fallback ไปใช้ระบบเก่า
        return getPlatformFromTagFallback(tag);
    }
    
    return '';
}

// ฟังก์ชัน fallback ในกรณีที่ API ไม่ทำงาน
function getPlatformFromTagFallback(tag) {
    if (!tag) return '';
    
    if (tag.length === 14) {
        // ตรวจสอบว่า 6 ตัวแรกเป็นตัวเลข และตัวที่ 7 เป็นภาษาอังกฤษ
        const firstSix = tag.substring(0, 6);
        const seventhChar = tag.substring(6, 7);
        
        if (/^\d{6}$/.test(firstSix) && /^[a-zA-Z]$/.test(seventhChar)) {
            return 'Shopee';
        }
    } else if (tag.length === 16 && /^\d{16}$/.test(tag)) {
        // ตรวจสอบว่าเป็นตัวเลข 16 หลัก
        return 'Lazada';
    }
    
    return '';
}

// แสดงข้อความแพลตฟอร์มแบบ async
function checkPlatform(tag) {
    const platformInfo = $('#platform-info');
    const platformText = $('#platform-text');
    
    if (!tag.trim()) {
        platformInfo.hide();
        return;
    }
    
    // แสดง loading
    platformText.html('<i class="material-icons">hourglass_empty</i> กำลังตรวจสอบ...');
    platformText.css('color', '#666');
    platformInfo.show();
    
    // เรียก API เพื่อตรวจสอบแพลตฟอร์ม
    $.ajax({
        url: '../includes/tag_validator.php',
        method: 'GET',
        data: { action: 'validate', tag: tag },
        dataType: 'json',
        success: function(response) {
            if (response.valid) {
                const platformText = `ระบุแพลตฟอร์ม: ${response.platform}`;
                const patternText = response.pattern_name ? ` (${response.pattern_name})` : '';
                
                $('#platform-text').html(`
                    <i class="material-icons">check_circle</i> 
                    ${platformText}${patternText}
                `);
                
                // เปลี่ยนสีตามแพลตฟอร์ม
                if (response.platform === 'Shopee') {
                    $('#platform-text').css('color', '#ff6b35');
                } else if (response.platform === 'Lazada') {
                    $('#platform-text').css('color', '#0f146d');
                } else {
                    $('#platform-text').css('color', '#28a745');
                }
            } else {
                $('#platform-text').html(`
                    <i class="material-icons">error</i> 
                    ไม่พบรูปแบบที่ตรงกัน
                `);
                $('#platform-text').css('color', '#dc3545');
            }
        },
        error: function() {
            // ใช้ระบบ fallback
            const platform = getPlatformFromTagFallback(tag);
            if (platform) {
                $('#platform-text').html(`
                    <i class="material-icons">info</i> 
                    ระบุแพลตฟอร์ม: ${platform} (ตรวจสอบด้วยระบบเก่า)
                `);
                $('#platform-text').css('color', '#ffc107');
            } else {
                $('#platform-text').html(`
                    <i class="material-icons">error</i> 
                    ไม่สามารถระบุแพลตฟอร์มได้
                `);
                $('#platform-text').css('color', '#dc3545');
            }
        }
    });
}

// ฟังก์ชันจัดรูปแบบวันที่ DD/MM/YYYY
function formatDateThai(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        
        return `${day}/${month}/${year}`;
    } catch (error) {
        return dateString; // ถ้าแปลงไม่ได้ให้คืนค่าเดิม
    }
}

// ล้างข้อมูลทั้งหมด
function clearAll() {
    selectedProducts = [];
    currentIssueTag = '';
    
    $('#issue-tag').val('').prop('readonly', false);
    $('#product-search').val('');
    $('#scan-section').hide();
    $('#search-results').hide();
    $('#platform-info').hide();
    
    updateSelectedProductsDisplay();
    updateSummary();
    
    $('#issue-tag').focus();
}
</script>

</body>
</html>