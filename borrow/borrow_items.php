<?php
session_start();
include '../templates/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

// Get statistics
$sql = "SELECT 
    COUNT(*) as total_borrows,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_borrows,
    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_borrows,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_borrows
FROM item_borrows";

$stmt = $pdo->query($sql);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืมสินค้า (Borrow Items)</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&family=Material+Icons&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.active {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.overdue {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .stat-card.returned {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-bar select, .filter-bar input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        
        .table-responsive {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.returned {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge.overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.cancelled {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background: #138496;
        }
        
        .btn-return {
            background: #28a745;
            color: white;
        }
        
        .btn-return:hover {
            background: #218838;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="mainwrap">
        <div class="topbar">
            ยืมสินค้า (Borrow Items)
        </div>
        
        <div class="main">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_borrows'] ?? 0; ?></div>
                    <div class="stat-label">รายการยืมทั้งหมด</div>
                </div>
                <div class="stat-card active">
                    <div class="stat-number"><?php echo $stats['active_borrows'] ?? 0; ?></div>
                    <div class="stat-label">กำลังยืม</div>
                </div>
                <div class="stat-card overdue">
                    <div class="stat-number"><?php echo $stats['overdue_borrows'] ?? 0; ?></div>
                    <div class="stat-label">เกินกำหนด</div>
                </div>
                <div class="stat-card returned">
                    <div class="stat-number"><?php echo $stats['returned_borrows'] ?? 0; ?></div>
                    <div class="stat-label">คืนแล้ว</div>
                </div>
            </div>
            
            <!-- Filter and Action Bar -->
            <div class="filter-bar">
                <select id="statusFilter" style="flex: 1; min-width: 150px;">
                    <option value="">ทั้งหมด</option>
                    <option value="active">กำลังยืม</option>
                    <option value="returned">คืนแล้ว</option>
                    <option value="overdue">เกินกำหนด</option>
                    <option value="cancelled">ยกเลิก</option>
                </select>
                
                <button class="btn-primary" onclick="openBorrowForm()">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">add</i>
                    เพิ่มการยืมใหม่
                </button>
            </div>
            
            <!-- Table -->
            <div class="table-responsive">
                <table id="borrowTable" class="table">
                    <thead>
                        <tr>
                            <th>รหัสยืม</th>
                            <th>ผู้ยืม</th>
                            <th>หมวดหมู่</th>
                            <th>วันที่ยืม</th>
                            <th>วันที่คืนที่คาดหวัง</th>
                            <th>สถานะ</th>
                            <th>จำนวนสินค้า</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Borrow Form Modal -->
    <div id="borrowFormModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 900px; width: 95%; max-height: 90vh; overflow-y: auto;">
            <h2 style="margin-top: 0;">📝 ฟอร์มยืมสินค้า</h2>
            
            <form id="borrowForm" style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">ชื่อผู้ยืม * (ล็อกจากบัญชีผู้ใช้)</label>
                    <input type="text" id="borrowerName" required readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5; cursor: not-allowed;">
                </div>
                
                
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">หมวดหมู่ *</label>
                    <select id="categoryId" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                    </select>
                </div>
                
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">เป้าประสงค์</label>
                    <textarea id="purpose" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px;"></textarea>
                </div>
                
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">วันที่คาดว่าจะคืน</label>
                    <input type="datetime-local" id="expectedReturnDate" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">หมายเหตุ</label>
                    <textarea id="notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px;"></textarea>
                </div>
                
                <div style="border-top: 1px solid #ddd; padding-top: 15px;">
                    <h3>รายการสินค้า</h3>
                    <div id="itemsList" style="margin-bottom: 15px;"></div>
                    <button type="button" onclick="addBorrowItem()" class="btn-primary" style="width: 100%;">
                        <i class="material-icons" style="vertical-align: middle;">add</i> เพิ่มสินค้า
                    </button>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeBorrowForm()" class="btn-primary" style="background: #6c757d;">ยกเลิก</button>
                    <button type="submit" class="btn-primary">💾 บันทึกการยืม</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    let borrowTable;
    let borrowItems = [];
    let productSearchCache = {};
    
    // Initialize
    $(function() {
        borrowTable = $('#borrowTable').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
            pageLength: 25,
            order: [[3, 'desc']]
        });
        
        loadBorrows();
        loadCategories();
        
        // Filter handler
        $('#statusFilter').on('change', loadBorrows);
    });
    
    // Load borrows
    function loadBorrows() {
        const status = $('#statusFilter').val();
        const url = '../api/borrow_api.php?action=list' + (status ? '&status=' + status : '');
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const rows = data.data.map((borrow, index) => [
                        borrow.borrow_number,
                        borrow.borrower_name,
                        borrow.category_name || '-',
                        new Date(borrow.borrow_date).toLocaleDateString('th-TH'),
                        borrow.expected_return_date ? new Date(borrow.expected_return_date).toLocaleDateString('th-TH') : '-',
                        `<span class="badge ${borrow.status}">${getStatusText(borrow.status)}</span>`,
                        countItemsForBorrow(borrow.borrow_id),
                        `<div class="action-buttons">
                            <button class="btn-sm btn-view" onclick="viewBorrow(${borrow.borrow_id})">ดู</button>
                            ${borrow.status === 'active' ? `<button class="btn-sm btn-return" onclick="returnBorrow(${borrow.borrow_id})">คืน</button>` : ''}
                        </div>`
                    ]);
                    
                    borrowTable.clear().rows.add(rows).draw();
                }
            });
    }
    
    function countItemsForBorrow(borrowId) {
        // This would be better handled by fetching from API
        return '-';
    }
    
    function getStatusText(status) {
        const map = {
            'active': 'กำลังยืม',
            'returned': 'คืนแล้ว',
            'overdue': 'เกินกำหนด',
            'cancelled': 'ยกเลิก'
        };
        return map[status] || status;
    }
    
    // Load categories
    function loadCategories() {
        fetch('../api/borrow_api.php?action=categories')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let html = '<option value="">-- เลือกหมวดหมู่ --</option>';
                    data.data.forEach(cat => {
                        html += `<option value="${cat.category_id}">${cat.category_name}</option>`;
                    });
                    $('#categoryId').html(html);
                }
            });
    }
    
    // Search products autocomplete
    function searchProducts(query) {
        if (!query || query.trim().length < 1) return Promise.resolve([]);
        
        // สร้าง cache key
        const cacheKey = query.trim().toLowerCase();
        if (productSearchCache[cacheKey]) {
            return Promise.resolve(productSearchCache[cacheKey]);
        }
        
        return fetch('../api/product_search_api.php?q=' + encodeURIComponent(query) + '&limit=20')
            .then(res => res.json())
            .then(data => {
                let products = [];
                
                // จัดการ response format - API ตอบกลับ array โดยตรง
                if (Array.isArray(data)) {
                    products = data.map(p => ({
                        product_id: p.product_id,
                        product_name: p.name || p.display_name || '',
                        sku: p.sku || '',
                        quantity: p.stock_qty || 0
                    }));
                } else if (data.success && Array.isArray(data.data)) {
                    products = data.data.map(p => ({
                        product_id: p.product_id,
                        product_name: p.name || p.display_name || '',
                        sku: p.sku || '',
                        quantity: p.stock_qty || 0
                    }));
                }
                
                productSearchCache[cacheKey] = products;
                return products;
            })
            .catch(err => {
                console.error('Search error:', err);
                return [];
            });
    }
    
    // Open borrow form
    function openBorrowForm() {
        borrowItems = [];
        document.getElementById('borrowForm').reset();
        document.getElementById('itemsList').innerHTML = '';
        document.getElementById('borrowFormModal').style.display = 'flex';
        
        // Populate user info from session
        const userName = <?php echo json_encode($_SESSION['user_name'] ?? 'Guest'); ?>;
        
        document.getElementById('borrowerName').value = userName;
    }
    
    function closeBorrowForm() {
        document.getElementById('borrowFormModal').style.display = 'none';
    }
    
    // Add borrow item
    function addBorrowItem() {
        const itemIndex = borrowItems.length;
        borrowItems.push({
            product_id: '',
            product_name: '',
            sku: '',
            qty: 1,
            unit: 'ชิ้น',
            notes: ''
        });
        
        renderBorrowItems();
    }
    
    function removeBorrowItem(index) {
        borrowItems.splice(index, 1);
        renderBorrowItems();
    }
    
    function renderBorrowItems() {
        let html = '';
        borrowItems.forEach((item, index) => {
            html += `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 10px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 60px; gap: 10px; margin-bottom: 10px;">
                        <div style="position: relative;">
                            <input type="text" 
                                id="productInput_${index}" 
                                placeholder="🔍 ค้นหาสินค้า (ชื่อ/SKU)" 
                                value="${item.product_name}" 
                                onkeyup="handleProductSearch(${index}, this.value)"
                                style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                            <div id="suggestions_${index}" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; border-radius: 0 0 4px 4px;"></div>
                        </div>
                        <input type="number" placeholder="จำนวน" value="${item.qty}" min="1" onchange="borrowItems[${index}].qty = this.value" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <input type="text" placeholder="หน่วย" value="${item.unit}" onchange="borrowItems[${index}].unit = this.value" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="button" onclick="removeBorrowItem(${index})" class="btn-sm btn-delete" style="padding: 8px; height: 36px;">ลบ</button>
                    </div>
                </div>
            `;
        });
        document.getElementById('itemsList').innerHTML = html;
    }
    
    function handleProductSearch(index, query) {
        const suggestionsDiv = document.getElementById('suggestions_' + index);
        
        if (!query || query.trim().length < 1) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        
        searchProducts(query.trim()).then(products => {
            console.log('Search results:', products); // Debug
            
            if (!products || products.length === 0) {
                suggestionsDiv.innerHTML = '<div style="padding: 10px; color: #999;">ไม่พบสินค้า</div>';
                suggestionsDiv.style.display = 'block';
                return;
            }
            
            let html = '';
            products.forEach(product => {
                const productName = (product.product_name || product.name || '').replace(/'/g, "\\'");
                const productSku = (product.sku || '').replace(/'/g, "\\'");
                
                html += `
                    <div style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; background: white;" 
                         onmouseover="this.style.background='#f5f5f5'" 
                         onmouseout="this.style.background='white'"
                         onclick="selectProduct(${index}, ${product.product_id}, '${productName}', '${productSku}')">
                        <strong>${product.product_name || product.name || '-'}</strong><br>
                        <small style="color: #666;">SKU: ${product.sku || '-'}</small>
                    </div>
                `;
            });
            
            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = 'block';
        });
    }
    
    function selectProduct(index, productId, productName, sku) {
        borrowItems[index].product_id = productId;
        borrowItems[index].product_name = productName;
        borrowItems[index].sku = sku;
        
        document.getElementById('productInput_' + index).value = productName;
        document.getElementById('suggestions_' + index).style.display = 'none';
    }
    
    // Submit form
    document.getElementById('borrowForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (borrowItems.length === 0) {
            Swal.fire('ข้อผิดพลาด', 'กรุณาเพิ่มสินค้าอย่างน้อย 1 ชิ้น', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'create');
        formData.append('borrower_name', document.getElementById('borrowerName').value);
        formData.append('category_id', document.getElementById('categoryId').value);
        formData.append('purpose', document.getElementById('purpose').value);
        formData.append('expected_return_date', document.getElementById('expectedReturnDate').value);
        formData.append('notes', document.getElementById('notes').value);
        formData.append('created_by', <?php echo $_SESSION['user_id']; ?>);
        formData.append('items', JSON.stringify(borrowItems));
        
        fetch('../api/borrow_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire('สำเร็จ', 'สร้างรายการยืมเรียบร้อย: ' + data.borrow_number, 'success')
                    .then(() => {
                        closeBorrowForm();
                        loadBorrows();
                    });
            } else {
                Swal.fire('ข้อผิดพลาด', data.message, 'error');
            }
        });
    });
    
    // View borrow
    function viewBorrow(borrowId) {
        fetch('../api/borrow_api.php?action=get&id=' + borrowId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const b = data.borrow;
                    const items = data.items;
                    
                    let itemsHtml = '';
                    items.forEach(item => {
                        itemsHtml += `
                            <tr>
                                <td>${item.product_name}</td>
                                <td style="text-align: right;">${item.qty}</td>
                                <td>${item.unit}</td>
                            </tr>
                        `;
                    });
                    
                    const html = `
                        <div style="text-align: left;">
                            <p><strong>รหัสยืม:</strong> ${b.borrow_number}</p>
                            <p><strong>ผู้ยืม:</strong> ${b.borrower_name}</p>
                            <p><strong>เบอร์โทร:</strong> ${b.borrower_phone || '-'}</p>
                            <p><strong>อีเมล:</strong> ${b.borrower_email || '-'}</p>
                            <p><strong>วันที่ยืม:</strong> ${new Date(b.borrow_date).toLocaleDateString('th-TH')}</p>
                            <p><strong>วันที่คืนที่คาดหวัง:</strong> ${b.expected_return_date ? new Date(b.expected_return_date).toLocaleDateString('th-TH') : '-'}</p>
                            <p><strong>สถานะ:</strong> <span class="badge ${b.status}">${getStatusText(b.status)}</span></p>
                            <h4>รายการสินค้า</h4>
                            <table class="table" style="margin: 10px 0;">
                                <thead>
                                    <tr>
                                        <th>ชื่อสินค้า</th>
                                        <th style="text-align: right;">จำนวน</th>
                                        <th>หน่วย</th>
                                    </tr>
                                </thead>
                                <tbody>${itemsHtml}</tbody>
                            </table>
                        </div>
                    `;
                    
                    Swal.fire({
                        title: 'รายละเอียดการยืม',
                        html: html,
                        icon: 'info',
                        confirmButtonText: 'ปิด'
                    });
                }
            });
    }
    
    // Return borrow
    function returnBorrow(borrowId) {
        Swal.fire({
            title: 'บันทึกการคืนสินค้า',
            html: `
                <div style="text-align: left;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">หมายเหตุการคืน</label>
                    <textarea id="returnNotes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 80px;"></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'บันทึกการคืน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'return');
                formData.append('borrow_id', borrowId);
                formData.append('actual_return_date', new Date().toISOString());
                formData.append('return_notes', document.getElementById('returnNotes').value);
                
                fetch('../api/borrow_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('สำเร็จ', 'บันทึกการคืนเรียบร้อย', 'success')
                            .then(() => loadBorrows());
                    } else {
                        Swal.fire('ข้อผิดพลาด', data.message, 'error');
                    }
                });
            }
        });
    }
    </script>
</body>
</html>
