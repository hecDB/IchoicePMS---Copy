<?php
session_start();
require '../config/db_connect.php';

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
    <title>รับสินค้าด่วน (Scan Barcode) - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
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

        .scanner-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .scanner-input {
            font-size: 1.25rem;
            padding: 1rem 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .scanner-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: white;
        }

        .scan-icon {
            font-size: 3rem;
            color: #3b82f6;
            margin-bottom: 1rem;
        }

        .search-results {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
        }

        .product-result-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .product-result-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .po-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .po-item:hover {
            background: #f1f5f9;
            border-color: #3b82f6;
        }

        .po-item.selected {
            background: #dbeafe;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .quantity-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .loading-spinner {
            border: 3px solid #f1f5f9;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 2rem;
            height: 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .receive-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .receive-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .history-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .recent-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.3s ease;
        }

        .recent-item:hover {
            background-color: #f8fafc;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        /* Camera Scanner Styles */
        .camera-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 1rem;
            overflow: hidden;
        }

        .camera-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1rem;
        }

        .camera-controls {
            padding: 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .camera-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
        }

        .camera-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .camera-btn.stop {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .camera-btn.stop:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        #interactive {
            width: 100%;
            height: 300px;
            position: relative;
        }

        #interactive video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 2px solid #3b82f6;
            width: 250px;
            height: 150px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.1);
            z-index: 10;
            pointer-events: none;
        }

        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Responsive Design for Mobile */
        @media (max-width: 768px) {
            #interactive {
                height: 250px;
            }
            
            .scanner-overlay {
                width: 200px;
                height: 120px;
            }
            
            .camera-controls {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .camera-controls .col-md-4 {
                text-align: center !important;
            }
        }

        .camera-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            text-align: center;
            border-radius: 8px;
            margin: 1rem;
        }

        .scan-result {
            background: #10b981;
            color: white;
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            margin-top: 1rem;
            border-radius: 8px;
            display: none;
        }

        .camera-info {
            padding: 1rem;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.875rem;
            text-align: center;
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
                    <span class="material-icons align-middle me-2" style="font-size: 2rem; color: #3b82f6;">qr_code_scanner</span>
                    รับสินค้าด่วน
                </h1>
                <p class="text-muted mb-0">สแกนบาร์โค้ด หรือ ค้นหา SKU เพื่อรับเข้าสินค้าอย่างรวดเร็ว</p>
            </div>
            <a href="receive_po_items.php" class="btn btn-outline-secondary">
                <span class="material-icons me-1">list</span>
                รายการ PO ทั้งหมด
            </a>
        </div>

        <!-- Scanner Section -->
        <div class="scanner-container text-center">
            <span class="material-icons scan-icon">qr_code_scanner</span>
            <h4 class="mb-3">สแกนบาร์โค้ด หรือ พิมพ์ SKU</h4>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" 
                               id="barcodeInput" 
                               class="form-control scanner-input" 
                               placeholder="สแกนบาร์โค้ด หรือ พิมพ์ SKU / ชื่อสินค้า..."
                               autocomplete="off"
                               autofocus>
                        <button class="btn camera-btn" type="button" id="cameraBtn" title="กด Ctrl+C เพื่อเปิดกล้อง">
                            <span class="material-icons me-1">camera_alt</span>
                            เปิดกล้อง
                        </button>
                        <button class="btn receive-btn" type="button" id="searchBtn" title="กด Ctrl+F เพื่อค้นหา">
                            <span class="material-icons me-1">search</span>
                            ค้นหา
                        </button>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        💡 <strong>Shortcuts:</strong> Enter=ค้นหา | Ctrl+C=เปิดกล้อง | Ctrl+F=ค้นหา | Esc=ปิดกล้อง
                    </small>
                </div>
            </div>
            
            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="mt-3" style="display: none;">
                <div class="loading-spinner mx-auto"></div>
                <p class="text-muted mt-2">กำลังค้นหา...</p>
            </div>
        </div>

        <!-- Camera Scanner -->
        <div id="cameraSection" class="camera-container" style="display: none;">
            <div class="camera-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <span class="material-icons align-middle me-2">camera_alt</span>
                        สแกนบาร์โค้ดด้วยกล้อง
                    </h5>
                    <button class="btn camera-btn stop" id="stopCameraBtn">
                        <span class="material-icons me-1">stop</span>
                        ปิดกล้อง
                    </button>
                </div>
            </div>
            
            <div class="camera-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <span class="material-icons text-success me-2">videocam</span>
                            <span class="text-muted">กำลังสแกน... โปรดวางบาร์โค้ดในกรอบสีฟ้า</span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-outline-secondary btn-sm" id="switchCameraBtn">
                            <span class="material-icons me-1">switch_camera</span>
                            เปลี่ยนกล้อง
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="interactive">
                <div class="scanner-overlay"></div>
            </div>
            
            <div class="camera-info">
                <span class="material-icons align-middle me-1">info</span>
                รองรับ: Code 128, EAN-8, EAN-13, UPC-A, UPC-E และ Code 39
            </div>
            
            <div id="scanResult" class="scan-result">
                <span class="material-icons align-middle me-2">check_circle</span>
                <span id="scannedCode"></span>
            </div>
            
            <div id="cameraError" class="camera-error" style="display: none;">
                <span class="material-icons align-middle me-2">error</span>
                <span id="errorMessage"></span>
            </div>
        </div>

        <!-- Search Results -->
        <div id="searchResults" style="display: none;">
            <div class="search-results">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0">
                        <span class="material-icons align-middle me-2">inventory_2</span>
                        ผลการค้นหา
                    </h5>
                </div>
                <div id="resultsContainer" class="p-3">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="history-section">
            <h5 class="mb-3">
                <span class="material-icons align-middle me-2">history</span>
                รายการรับสินค้าล่าสุด
            </h5>
            <div id="recentActivities">
                <div class="text-center text-muted py-4">
                    <span class="material-icons mb-2" style="font-size: 2rem;">hourglass_empty</span>
                    <p>ยังไม่มีการรับสินค้าในวันนี้</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receive Confirmation Modal -->
<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="material-icons align-middle me-2">input</span>
                    ยืนยันการรับเข้าสินค้า
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>

<script>
$(document).ready(function() {
    let searchTimeout;
    let isScanning = false;
    let currentStream = null;
    
    // Auto-focus on barcode input
    $('#barcodeInput').focus();
    
    // Search on Enter key or button click
    $('#barcodeInput').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            searchProduct();
        }
    });
    
    $('#searchBtn').on('click', function() {
        searchProduct();
    });
    
    // Camera button click
    $('#cameraBtn').on('click', function() {
        if (isScanning) {
            stopScanner();
        } else {
            startScanner();
        }
    });
    
    // Stop camera button click
    $('#stopCameraBtn').on('click', function() {
        stopScanner();
    });
    
    // Switch camera button click
    $('#switchCameraBtn').on('click', function() {
        if (isScanning) {
            switchCamera();
        }
    });
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+C: Toggle camera
        if (e.ctrlKey && e.which === 67) {
            e.preventDefault();
            if (isScanning) {
                stopScanner();
            } else {
                startScanner();
            }
        }
        
        // Ctrl+F: Focus search
        if (e.ctrlKey && e.which === 70) {
            e.preventDefault();
            $('#barcodeInput').focus().select();
        }
        
        // Escape: Stop camera
        if (e.which === 27 && isScanning) {
            e.preventDefault();
            stopScanner();
        }
    });
    
    // Auto search on input (with debounce)
    $('#barcodeInput').on('input', function() {
        clearTimeout(searchTimeout);
        const value = $(this).val().trim();
        
        if (value.length >= 3) {
            searchTimeout = setTimeout(function() {
                searchProduct();
            }, 800);
        } else if (value.length === 0) {
            $('#searchResults').hide();
        }
    });
    
    function searchProduct() {
        const barcode = $('#barcodeInput').val().trim();
        
        if (!barcode) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณากรอกข้อมูล',
                text: 'กรุณาสแกนบาร์โค้ด หรือ พิมพ์ SKU/ชื่อสินค้า'
            });
            return;
        }
        
        showLoading(true);
        $('#searchResults').hide();
        
        $.ajax({
            url: '../api/barcode_search_api.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ barcode: barcode }),
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    displaySearchResults(response.data);
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'ไม่พบข้อมูล',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                showLoading(false);
                console.error('Search error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถค้นหาข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
                });
            }
        });
    }
    
    function showLoading(show) {
        if (show) {
            $('#loadingIndicator').show();
        } else {
            $('#loadingIndicator').hide();
        }
    }
    
    function displaySearchResults(products) {
        let html = '';
        
        products.forEach(function(product) {
            html += `
                <div class="product-result-card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold">${escapeHtml(product.product_name)}</h6>
                                <small class="text-muted">SKU: ${escapeHtml(product.sku)} | Barcode: ${escapeHtml(product.barcode || 'N/A')}</small>
                            </div>
                            <span class="quantity-badge">${product.purchase_orders.length} PO</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
            `;
            
            product.purchase_orders.forEach(function(po) {
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="po-item" data-item-id="${po.item_id}" data-product='${JSON.stringify(product)}' data-po='${JSON.stringify(po)}'>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold text-primary">${escapeHtml(po.po_number)}</div>
                                    <div class="small text-muted">${escapeHtml(po.supplier_name)}</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">วันที่สั่ง</div>
                                    <div class="small">${formatDate(po.order_date)}</div>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="small text-muted">สั่งซื้อ</div>
                                    <div class="fw-bold text-info">${po.ordered_qty}</div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">รับแล้ว</div>
                                    <div class="fw-bold text-success">${po.received_qty}</div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">คงเหลือ</div>
                                    <div class="fw-bold text-warning">${po.remaining_qty}</div>
                                </div>
                            </div>
                            
                            <div class="mt-2 text-center">
                                <div class="small text-muted">ราคา/หน่วย</div>
                                <div class="fw-bold">${formatNumber(po.unit_cost)} ${escapeHtml(po.currency_code)}</div>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <button class="btn receive-btn btn-sm receive-item-btn">
                                    <span class="material-icons me-1" style="font-size: 1rem;">input</span>
                                    รับเข้าสินค้า
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#resultsContainer').html(html);
        $('#searchResults').show();
        
        // Bind click events for PO items
        $('.receive-item-btn').on('click', function(e) {
            e.stopPropagation();
            const poItem = $(this).closest('.po-item');
            const product = poItem.data('product');
            const po = poItem.data('po');
            showReceiveModal(product, po);
        });
    }
    
    function showReceiveModal(product, po) {
        const modalContent = `
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">ข้อมูลสินค้า</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>${escapeHtml(product.product_name)}</strong>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">SKU</small>
                                    <div>${escapeHtml(product.sku)}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">หน่วย</small>
                                    <div>${escapeHtml(product.unit)}</div>
                                </div>
                            </div>
                            ${product.barcode ? `
                            <div class="mt-2">
                                <small class="text-muted">Barcode</small>
                                <div>${escapeHtml(product.barcode)}</div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">ใบสั่งซื้อ</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>${escapeHtml(po.po_number)}</strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">ผู้จำหน่าย</small>
                                <div>${escapeHtml(po.supplier_name)}</div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">สั่งซื้อ</small>
                                    <div class="fw-bold text-info">${po.ordered_qty} ${escapeHtml(product.unit)}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">รับแล้ว</small>
                                    <div class="fw-bold text-success">${po.received_qty} ${escapeHtml(product.unit)}</div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">คงเหลือรับได้</small>
                                <div class="fw-bold text-warning">${po.remaining_qty} ${escapeHtml(product.unit)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <form id="receiveForm">
                <input type="hidden" name="item_id" value="${po.item_id}">
                <input type="hidden" name="product_id" value="${product.product_id}">
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">จำนวนที่รับเข้า *</label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   name="receive_qty" 
                                   id="receiveQty"
                                   max="${po.remaining_qty}" 
                                   min="0.01" 
                                   step="0.01" 
                                   value="${po.remaining_qty}"
                                   required>
                            <span class="input-group-text">${escapeHtml(product.unit)}</span>
                        </div>
                        <small class="text-muted">สูงสุด: ${po.remaining_qty} ${escapeHtml(product.unit)}</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">หมายเหตุ (ถ้ามี)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="หมายเหตุการรับเข้าสินค้า..."></textarea>
                    </div>
                </div>
                
                <div class="text-end mt-4">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn receive-btn">
                        <span class="material-icons me-1" style="font-size: 1rem;">check</span>
                        ยืนยันรับเข้าสินค้า
                    </button>
                </div>
            </form>
        `;
        
        $('#modalContent').html(modalContent);
        $('#receiveModal').modal('show');
        
        // Focus on quantity input
        setTimeout(() => {
            $('#receiveQty').focus().select();
        }, 500);
        
        // Handle form submission
        $('#receiveForm').on('submit', function(e) {
            e.preventDefault();
            processReceive($(this));
        });
    }
    
    function processReceive(form) {
        const formData = new FormData(form[0]);
        const data = Object.fromEntries(formData);
        
        // Validate quantity
        const receiveQty = parseFloat(data.receive_qty);
        if (receiveQty <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'จำนวนไม่ถูกต้อง',
                text: 'กรุณากรอกจำนวนที่รับเข้ามากกว่า 0'
            });
            return;
        }
        
        // Show confirmation
        Swal.fire({
            title: 'ยืนยันการรับเข้าสินค้า',
            text: `จำนวน ${receiveQty} หน่วย`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                submitReceive(data);
            }
        });
    }
    
    function submitReceive(data) {
        $.ajax({
            url: 'process_receive_po.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                items: [{
                    item_id: data.item_id,
                    product_id: data.product_id,
                    received_qty: parseFloat(data.receive_qty),
                    notes: data.notes || ''
                }]
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'รับเข้าสินค้าสำเร็จ',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#receiveModal').modal('hide');
                        $('#barcodeInput').val('').focus();
                        $('#searchResults').hide();
                        loadRecentActivities();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Receive error:', error);
                console.error('Response:', xhr.responseText);
                
                let errorMessage = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
                
                // Try to parse JSON error response
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // If not JSON, use default message
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: errorMessage
                });
            }
        });
    }
    
    function loadRecentActivities() {
        // This would load recent receive activities
        // For now, we'll just show a placeholder
    }
    
    // Camera Scanner Functions
    function startScanner() {
        if (isScanning) return;
        
        // Check for camera permission
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            Swal.fire({
                icon: 'error',
                title: 'ไม่รองรับกล้อง',
                text: 'เบราว์เซอร์ของคุณไม่รองรับการเข้าถึงกล้อง'
            });
            return;
        }
        
        $('#cameraSection').show();
        $('#cameraBtn').html('<span class="material-icons me-1">videocam_off</span>ปิดกล้อง').addClass('stop');
        
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#interactive'),
                constraints: {
                    width: 640,
                    height: 480,
                    facingMode: "environment" // Use back camera
                }
            },
            locator: {
                patchSize: "medium",
                halfSample: true
            },
            numOfWorkers: 2,
            frequency: 10,
            decoder: {
                readers: [
                    "code_128_reader",
                    "ean_reader",
                    "ean_8_reader", 
                    "code_39_reader",
                    "code_39_vin_reader",
                    "codabar_reader",
                    "upc_reader",
                    "upc_e_reader"
                ]
            },
            locate: true
        }, function(err) {
            if (err) {
                console.error('Quagga initialization error:', err);
                
                let errorMsg = 'ไม่สามารถเปิดกล้องได้';
                if (err.name === 'NotAllowedError') {
                    errorMsg = 'ไม่ได้รับอนุญาตให้เข้าถึงกล้อง กรุณาอนุญาตการเข้าถึงกล้องในเบราว์เซอร์';
                } else if (err.name === 'NotFoundError') {
                    errorMsg = 'ไม่พบกล้องที่ใช้งานได้';
                } else if (err.name === 'NotReadableError') {
                    errorMsg = 'กล้องกำลังถูกใช้งานโดยแอปพลิเคชันอื่น';
                }
                
                $('#errorMessage').text(errorMsg);
                $('#cameraError').show();
                
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่สามารถเปิดกล้องได้',
                    text: errorMsg,
                    footer: 'ลองรีเฟรชหน้าเว็บหรือตรวจสอบการตั้งค่ากล้อง'
                });
                stopScanner();
                return;
            }
            
            Quagga.start();
            isScanning = true;
        });
        
        // Handle successful scan
        Quagga.onDetected(function(result) {
            if (result && result.codeResult && result.codeResult.code) {
                const code = result.codeResult.code;
                
                // Vibrate on successful scan (if supported)
                if (navigator.vibrate) {
                    navigator.vibrate([200, 100, 200]);
                }
                
                // Play beep sound (optional)
                playBeep();
                
                // Show scan result with animation
                $('#scannedCode').text(`สแกนสำเร็จ: ${code}`);
                $('#scanResult').slideDown().delay(2000).slideUp();
                
                // Fill input and search
                $('#barcodeInput').val(code);
                stopScanner();
                
                // Show success toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'สแกนบาร์โค้ดสำเร็จ',
                    text: code,
                    showConfirmButton: false,
                    timer: 2000
                });
                
                // Auto search after short delay
                setTimeout(function() {
                    searchProduct();
                }, 500);
            }
        });
    }
    
    function stopScanner() {
        if (!isScanning) return;
        
        Quagga.stop();
        $('#cameraSection').hide();
        $('#cameraBtn').html('<span class="material-icons me-1">camera_alt</span>เปิดกล้อง').removeClass('stop');
        isScanning = false;
        
        // Stop all video streams
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
    }
    
    function switchCamera() {
        if (!isScanning) return;
        
        stopScanner();
        
        // Toggle between front and back camera
        const constraints = Quagga.CameraAccess.getActiveStreamLabel().includes('front') 
            ? { facingMode: "environment" } 
            : { facingMode: "user" };
            
        setTimeout(() => {
            startScannerWithConstraints(constraints);
        }, 100);
    }
    
    function startScannerWithConstraints(constraints) {
        $('#cameraSection').show();
        $('#cameraBtn').html('<span class="material-icons me-1">videocam_off</span>ปิดกล้อง').addClass('stop');
        
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#interactive'),
                constraints: Object.assign({
                    width: 640,
                    height: 480
                }, constraints)
            },
            locator: {
                patchSize: "medium",
                halfSample: true
            },
            numOfWorkers: 2,
            frequency: 10,
            decoder: {
                readers: [
                    "code_128_reader",
                    "ean_reader",
                    "ean_8_reader", 
                    "code_39_reader",
                    "code_39_vin_reader",
                    "codabar_reader",
                    "upc_reader",
                    "upc_e_reader"
                ]
            },
            locate: true
        }, function(err) {
            if (err) {
                console.error('Camera switch error:', err);
                stopScanner();
                return;
            }
            
            Quagga.start();
            isScanning = true;
        });
    }
    
    // Play beep sound for successful scan
    function playBeep() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800; // Frequency in Hz
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            // Audio not supported or failed
            console.log('Beep sound not available');
        }
    }
    
    // Check camera permission status
    function checkCameraPermission() {
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'camera' }).then(function(permissionStatus) {
                console.log('Camera permission status:', permissionStatus.state);
                
                if (permissionStatus.state === 'denied') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ไม่ได้รับอนุญาตกล้อง',
                        text: 'กรุณาอนุญาตการเข้าถึงกล้องในการตั้งค่าเบราว์เซอร์',
                        footer: 'Settings > Privacy > Camera'
                    });
                }
                
                permissionStatus.onchange = function() {
                    console.log('Camera permission changed to:', this.state);
                    if (this.state === 'denied' && isScanning) {
                        stopScanner();
                    }
                };
            });
        }
    }
    
    // Utility functions
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('th-TH');
    }
    
    function formatNumber(number) {
        return parseFloat(number).toLocaleString('th-TH', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    }
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        const currentValue = $('#barcodeInput').val().trim();
        if (currentValue && $('#searchResults').is(':visible')) {
            searchProduct();
        }
    }, 30000);
    
    // Stop camera when page unloads
    $(window).on('beforeunload', function() {
        stopScanner();
    });
    
    // Stop camera when page loses focus
    $(window).on('blur', function() {
        if (isScanning) {
            stopScanner();
        }
    });
    
    // Check camera permission on page load
    checkCameraPermission();
    
    // Load recent activities on page load
    loadRecentActivities();
});
</script>

</body>
</html>