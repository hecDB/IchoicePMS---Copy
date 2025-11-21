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
        
        .selected-product {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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

        /* Modal fixes for proper centering without scrolling */
        .modal {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .modal-dialog {
            max-height: none;
            margin: 0;
        }

        .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Ensure modal backdrop doesn't cause scroll */
        .modal-backdrop {
            position: fixed;
        }

        /* Prevent body scroll when modal is open */
        body.modal-open {
            overflow: hidden !important;
            padding-right: 0 !important;
            position: fixed !important;
            width: 100% !important;
            top: 0 !important;
        }
        
        /* Override scrollbar padding that Bootstrap adds */
        body.modal-open .mainwrap {
            position: relative;
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
                            <div class="selected-product">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <img id="product-image" src="" alt="Product" class="product-image">
                                    </div>
                                    <div class="col">
                                        <div>
                                            <strong>ชื่อสินค้า:</strong> <span id="product-name"></span>
                                        </div>
                                        <div>
                                            <strong>SKU:</strong> <span id="product-sku"></span>
                                        </div>
                                        <div>
                                            <strong>บาร์โค้ด:</strong> <span id="product-barcode"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                <label for="missing-quantity" class="form-label">จำนวนที่สูญหาย/หาไม่เจอ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">
                                        <span class="material-icons">production_quantity_limits</span>
                                    </span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="missing-quantity" 
                                           min="1" 
                                           step="0.01"
                                           placeholder="กรอกจำนวน">
                                    <span class="input-group-text">ชิ้น</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="remark" class="form-label">หมายเหตุ</label>
                                <textarea class="form-control" 
                                          id="remark" 
                                          rows="3" 
                                          placeholder="เพิ่มหมายเหตุเกี่ยวกับการสูญหาย เช่น: หาไม่เจอในตู้เก็บ, ชำรุด, ฯลฯ"></textarea>
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
                        <div id="missing-items-list" style="min-height: 200px; overflow-x: auto;">
                            <table class="table table-hover mb-0" style="width: 100%; min-width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">ลำดับ</th>
                                        <th>สินค้า</th>
                                        <th style="width: 80px;">จำนวน</th>
                                        <th>บันทึกโดย</th>
                                        <th style="width: 100px;">เวลา</th>
                                        <th style="width: 120px;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="missing-items-tbody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">แก้ไขสินค้าสูญหาย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">สินค้า</label>
                        <div class="form-control-plaintext fw-bold" id="edit-product-name"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">จำนวนที่สูญหาย <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit-quantity" min="1" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="edit-remark" rows="3" placeholder="กรอกหมายเหตุ..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="save-edit-btn">
                        <span class="material-icons" style="vertical-align: middle; font-size: 1rem;">save</span>
                        บันทึกการแก้ไข
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let selectedProduct = null;
        let missingItems = [];
        let currentEditingId = null;

        // Get current user ID
        const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

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
                            const imagePath = product.image ? '../images/' + product.image : '../images/noimg.png';
                            html += `<div class="product-item" data-product='${JSON.stringify(product)}'>
                                <div class="row align-items-center g-2">
                                    <div class="col-auto">
                                        <img src="${imagePath}" alt="Product" class="product-image" onerror="this.src='../images/noimg.png'">
                                    </div>
                                    <div class="col">
                                        <div><strong>${product.product_name}</strong></div>
                                        <small class="text-muted">SKU: ${product.sku} | Barcode: ${product.barcode}</small>
                                    </div>
                                </div>
                            </div>`;
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
            selectedProduct = JSON.parse($(this).attr('data-product'));
            
            // Update UI
            $('#product-image').attr('src', '../images/' + (selectedProduct.image || 'noimg.png')).on('error', function() {
                $(this).attr('src', '../images/noimg.png');
            });
            $('#product-name').text(selectedProduct.product_name);
            $('#product-sku').text(selectedProduct.sku);
            $('#product-barcode').text(selectedProduct.barcode);
            
            // Clear search and hide results
            $('#barcode-search').val('');
            $('#search-results').removeClass('show').empty();
            $('#missing-quantity').val('').focus();
            
            // Show selected product section
            $('#selected-product-section').show();
        });

        // Submit missing product
        $('#submit-btn').click(function() {
            if (!selectedProduct) {
                Swal.fire('ข้อผิดพลาด', 'กรุณาเลือกสินค้า', 'warning');
                return;
            }

            const quantity = parseFloat($('#missing-quantity').val()) || 0;
            const remark = $('#remark').val().trim();

            if (quantity <= 0) {
                Swal.fire('ข้อผิดพลาด', 'กรุณากรอกจำนวนที่มากกว่า 0', 'warning');
                return;
            }

            $(this).prop('disabled', true);
            
            $.post('../api/record_missing_product_api.php', {
                product_id: selectedProduct.product_id,
                quantity_missing: quantity,
                remark: remark,
                reported_by: userId
            }, function(resp) {
                $('#submit-btn').prop('disabled', false);
                
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'บันทึกสินค้าสูญหาย: ' + resp.product_name + ' จำนวน ' + resp.quantity + ' ชิ้น',
                        timer: 2000
                    });

                    // Reset form
                    selectedProduct = null;
                    $('#barcode-search').val('').focus();
                    $('#missing-quantity').val('');
                    $('#remark').val('');
                    $('#selected-product-section').hide();

                    // Reload missing items list
                    loadMissingItemsList();
                } else {
                    Swal.fire('ข้อผิดพลาด', resp.message || 'ไม่สามารถบันทึกได้', 'error');
                }
            }).fail(function() {
                $('#submit-btn').prop('disabled', false);
                Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            });
        });

        // Load missing items list
        function loadMissingItemsList() {
            const today = new Date().toISOString().split('T')[0];
            $.get('../api/get_missing_products_api.php', { date: today }, function(resp) {
                if (resp.success && resp.data.length > 0) {
                    let html = '';
                    resp.data.forEach((item, index) => {
                        html += `<tr>
                            <td>${index + 1}</td>
                            <td>
                                <strong>${item.product_name}</strong><br>
                                <small class="text-muted">SKU: ${item.sku}</small>
                            </td>
                            <td class="text-center">${item.quantity_missing}</td>
                            <td>${item.created_by_name || 'N/A'}</td>
                            <td>${new Date(item.created_at).toLocaleTimeString('th-TH')}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-missing-btn" data-id="${item.missing_id}">
                                    <span class="material-icons" style="font-size: 1rem;">edit</span>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-missing-btn" data-id="${item.missing_id}">
                                    <span class="material-icons" style="font-size: 1rem;">delete</span>
                                </button>
                            </td>
                        </tr>`;
                    });
                    $('#missing-items-tbody').html(html);
                } else {
                    $('#missing-items-tbody').html(`<tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <span class="material-icons">inbox</span><br>
                            ยังไม่มีรายการสินค้าสูญหาย
                        </td>
                    </tr>`);
                }
            });
        }

        // Load on page load
        $(document).ready(function() {
            loadMissingItemsList();
            $('#barcode-search').focus();
            
            // Store scroll position before modal opens
            let scrollPosition = 0;
            
            // Handle modal show event
            const editModalElement = document.getElementById('editModal');
            editModalElement.addEventListener('show.bs.modal', function() {
                scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
                $('body').css('top', -scrollPosition + 'px');
            });
            
            // Handle modal hide event
            editModalElement.addEventListener('hide.bs.modal', function() {
                $('body').css('top', '0px');
                window.scrollTo(0, scrollPosition);
            });
        });

        // Edit button handler
        $(document).on('click', '.edit-missing-btn', function() {
            currentEditingId = $(this).data('id');
            const row = $(this).closest('tr');
            const productName = row.find('td:eq(1) strong').text();
            const quantity = row.find('td:eq(2)').text();
            
            // Set modal fields
            $('#edit-product-name').text(productName);
            $('#edit-quantity').val(quantity);
            $('#edit-remark').val('');
            
            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        });

        // Save edit button handler
        $('#save-edit-btn').click(function() {
            if (!currentEditingId) {
                Swal.fire('ข้อผิดพลาด', 'ไม่พบรายการที่จะแก้ไข', 'error');
                return;
            }

            const quantity = parseFloat($('#edit-quantity').val()) || 0;
            const remark = $('#edit-remark').val().trim();

            if (quantity <= 0) {
                Swal.fire('ข้อผิดพลาด', 'กรุณากรอกจำนวนที่มากกว่า 0', 'warning');
                return;
            }

            $(this).prop('disabled', true);
            
            $.post('../api/update_missing_product_api.php', {
                missing_id: currentEditingId,
                quantity_missing: quantity,
                remark: remark
            }, function(resp) {
                $('#save-edit-btn').prop('disabled', false);
                
                if (resp.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'อัปเดตสำเร็จ',
                        text: 'บันทึกการแก้ไขเรียบร้อย',
                        timer: 1500
                    });
                    
                    // Close modal and reload list
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    loadMissingItemsList();
                    currentEditingId = null;
                } else {
                    Swal.fire('ข้อผิดพลาด', resp.message || 'ไม่สามารถอัปเดตได้', 'error');
                }
            }).fail(function() {
                $('#save-edit-btn').prop('disabled', false);
                Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
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
