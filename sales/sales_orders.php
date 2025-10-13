<?php
require_once '../auth/auth_check.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการขาย - IChoice PMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .sales-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sales-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tag-number {
            font-size: 1.2rem;
            font-weight: bold;
            font-family: monospace;
        }
        .platform-badge {
            font-size: 0.8rem;
        }
        
        /* Shopee Badge - สีส้ม */
        .shopee-badge {
            background: linear-gradient(135deg, #ee4d2d 0%, #ff6b47 100%);
            color: white;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        /* Lazada Badge - สีฟ้าอมม่วง */
        .lazada-badge {
            background: linear-gradient(135deg, #0f136d 0%, #1e40af 100%);
            color: white;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        /* Header styling สำหรับแต่ละแพลตฟอร์ม */
        .sales-header.shopee-header {
            background: linear-gradient(135deg, #ee4d2d 0%, #ff6b47 100%);
        }
        
        .sales-header.lazada-header {
            background: linear-gradient(135deg, #0f136d 0%, #1e40af 100%);
        }
        
        .sales-header.general-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Expand/Collapse Styles */
        .expand-icon {
            transition: transform 0.3s ease;
            font-size: 1.5rem !important;
        }
        
        .collapse {
            transition: all 0.3s ease;
            max-height: 0;
            overflow: hidden;
        }
        
        .collapse.show {
            max-height: 2000px; /* เพียงพอสำหรับเนื้อหา */
        }
        
        .sales-header:hover {
            opacity: 0.9;
        }
        
        .summary-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        
        .item-row {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f8f9fa;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .search-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .loading-overlay {
            position: relative;
        }
        .loading-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
        }
        .loading-overlay.loading::after {
            display: block;
        }
        .qty-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .price-text {
            color: #2e7d32;
            font-weight: 600;
        }
        .total-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0 0 8px 8px;
            border-top: 1px solid #dee2e6;
        }
        
        /* Sidebar compatibility */
        body {
            background-color: #f8f9fa;
            font-family: 'Prompt', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 230px;
            padding: 24px;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(.4,0,.2,1);
            background-color: #f8f9fa;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 80px 16px 20px;
            }
        }
        
        .container-fluid {
            max-width: 1400px;
            padding: 0;
        }
        
        h2 {
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        h2 .material-symbols-outlined {
            vertical-align: middle;
            font-size: 1.8rem;
            margin-right: 0.5rem;
        }
        
        /* Dashboard Stats Styles */
        .dashboard-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stats-header {
            padding: 1.5rem 1.5rem 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stats-content {
            padding: 1rem 1.5rem 1.5rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .platform-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .platform-card.shopee {
            border-left-color: #ee4d2d;
        }
        
        .platform-card.lazada {
            border-left-color: #0f136d;
        }
        
        .platform-card.general {
            border-left-color: #6c757d;
        }
        
        .platform-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }
        
        .platform-stats {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .platform-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .platform-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .platform-amount {
            font-size: 1.1rem;
            font-weight: 700;
            color: #27ae60;
        }
        
        .platform-metrics {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .refresh-stats {
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }
        
        .refresh-stats:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include '../templates/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <span class="material-symbols-outlined me-2">receipt_long</span>
                    รายการขาย
                </h2>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleAllOrders()" id="toggleAllBtn">
                        <span class="material-symbols-outlined me-1">unfold_more</span>
                        ขยายทั้งหมด
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="exportData()">
                        <span class="material-symbols-outlined me-1">download</span>
                        ส่งออกข้อมูล
                    </button>
                    <button type="button" class="btn btn-primary" onclick="refreshData()">
                        <span class="material-symbols-outlined me-1">refresh</span>
                        รีเฟรช
                    </button>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats" id="dashboardStats">
                <div class="stats-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <span class="material-symbols-outlined me-2">analytics</span>
                            สรุปยอดขาย
                        </h5>
                        <button type="button" class="btn btn-link text-white refresh-stats p-0" onclick="loadDashboardStats()" title="รีเฟรชสถิติ">
                            <span class="material-symbols-outlined">refresh</span>
                        </button>
                    </div>
                </div>
                <div class="stats-content">
                    <!-- Loading State -->
                    <div id="statsLoading" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-light" role="status">
                            <span class="visually-hidden">กำลังโหลด...</span>
                        </div>
                        <small class="d-block mt-2 opacity-75">กำลังโหลดสถิติ...</small>
                    </div>
                    
                    <!-- Stats Content -->
                    <div id="statsContent" style="display: none;">
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number" id="totalOrders">0</div>
                                    <div class="stat-label">คำสั่งซื้อ</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number" id="totalTags">0</div>
                                    <div class="stat-label">แท็ค</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number" id="totalItems">0</div>
                                    <div class="stat-label">สินค้า</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number" id="totalAmount">฿0</div>
                                    <div class="stat-label">ยอดขายรวม</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="mb-3">
                                    <span class="material-symbols-outlined me-1" style="font-size: 1.2rem;">store</span>
                                    ยอดขายตามแพลตฟอร์ม
                                </h6>
                                <div class="platform-stats" id="platformStats">
                                    <!-- Platform stats will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="searchInput" class="form-label">ค้นหาแท็ค</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="ค้นหาเลขแท็ค...">
                    </div>
                    <div class="col-md-2">
                        <label for="platformFilter" class="form-label">แพลตฟอร์ม</label>
                        <select class="form-select" id="platformFilter">
                            <option value="">ทั้งหมด</option>
                            <option value="Shopee">🛍️ Shopee</option>
                            <option value="Lazada">🛒 Lazada</option>
                            <option value="General">📦 ทั่วไป</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="dateFrom" class="form-label">วันที่เริ่มต้น</label>
                        <input type="date" class="form-control" id="dateFrom">
                    </div>
                    <div class="col-md-2">
                        <label for="dateTo" class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" class="form-control" id="dateTo">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-primary me-2" onclick="loadSalesOrders()">
                            <span class="material-symbols-outlined me-1">search</span>
                            ค้นหา
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                            <span class="material-symbols-outlined me-1">clear</span>
                            ล้าง
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sales Orders List -->
            <div id="salesOrdersList" class="loading-overlay">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <p class="mt-2 text-muted">กำลังโหลดข้อมูล...</p>
                </div>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation" id="paginationContainer" style="display: none;">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </nav>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentPage = 1;
        let totalPages = 1;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่ม delay เล็กน้อยเพื่อให้ sidebar โหลดเสร็จก่อน
            setTimeout(function() {
                loadDashboardStats();
                loadSalesOrders();
            }, 100);
            
            // Set up enter key for search
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadSalesOrders();
                }
            });
            
            // Set up date filters to reload stats
            document.getElementById('dateFrom').addEventListener('change', loadDashboardStats);
            document.getElementById('dateTo').addEventListener('change', loadDashboardStats);
            
            // Initial layout setup
            const mainContent = document.querySelector('.main-content');
            if (window.innerWidth <= 1024) {
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.paddingTop = '80px';
                }
            } else {
                if (mainContent) {
                    mainContent.style.marginLeft = '230px';
                    mainContent.style.paddingTop = '24px';
                }
            }
        });

        function loadSalesOrders(page = 1) {
            currentPage = page;
            const container = document.getElementById('salesOrdersList');
            container.classList.add('loading');
            
            const params = new URLSearchParams({
                page: page,
                limit: 10,
                search: document.getElementById('searchInput').value,
                platform: document.getElementById('platformFilter').value,
                date_from: document.getElementById('dateFrom').value,
                date_to: document.getElementById('dateTo').value
            });

            fetch(`../api/sales_orders_api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    container.classList.remove('loading');
                    
                    if (data.success) {
                        displaySalesOrders(data.data);
                        updatePagination(data.pagination);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    container.classList.remove('loading');
                    console.error('Error:', error);
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <span class="material-symbols-outlined me-2">error</span>
                            เกิดข้อผิดพลาด: ${error.message}
                        </div>
                    `;
                });
        }

        function displaySalesOrders(orders) {
            const container = document.getElementById('salesOrdersList');
            
            // รีเซ็ตสถานะการขยาย/ย่อ
            allExpanded = false;
            const toggleBtn = document.getElementById('toggleAllBtn');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<span class="material-symbols-outlined me-1">unfold_more</span>ขยายทั้งหมด';
            }
            
            if (orders.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <span class="material-symbols-outlined" style="font-size: 4rem; color: #ccc;">receipt_long</span>
                        <p class="mt-3 text-muted">ไม่พบรายการขาย</p>
                    </div>
                `;
                return;
            }

            let html = '';
            orders.forEach(order => {
                html += createSalesOrderCard(order);
            });
            
            container.innerHTML = html;
        }

        function createSalesOrderCard(order) {
            const items = order.items || [];
            const orderId = order.sale_order_id;
            
            // สร้าง HTML สำหรับรายการสินค้า
            let itemsHtml = '';
            items.forEach(item => {
                const imageUrl = item.image ? `../images/${item.image}` : '../images/noimg.png';
                const lineTotal = parseFloat(item.line_total || 0);
                
                itemsHtml += `
                    <div class="item-row">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <img src="${imageUrl}" alt="${item.product_name}" class="product-image" 
                                     onerror="this.src='../images/noimg.png'">
                            </div>
                            <div class="col">
                                <div class="fw-bold">${item.product_name}</div>
                                <small class="text-muted">SKU: ${item.sku || 'N/A'}</small>
                                ${item.lot_info ? `<br><small class="text-info">Lot: ${item.lot_info}</small>` : ''}
                                ${item.expiry_date ? `<br><small class="text-warning">หมดอายุ: ${formatDate(item.expiry_date)}</small>` : ''}
                            </div>
                            <div class="col-auto text-end">
                                <div class="qty-badge">${item.issue_qty} ${item.unit || 'ชิ้น'}</div>
                                ${item.sale_price ? `<div class="price-text mt-1">฿${parseFloat(item.sale_price).toLocaleString()}</div>` : ''}
                                ${lineTotal > 0 ? `<div class="text-muted small">รวม: ฿${lineTotal.toLocaleString()}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            const platformBadge = order.platform ? 
                `<span class="${order.platform_class}">${order.platform_display || order.platform}</span>` : 
                '<span class="badge bg-secondary">📦 ไม่ระบุ</span>';
            
            // กำหนดสี header ตามแพลตฟอร์ม
            const headerClass = order.platform === 'Shopee' ? 'sales-header shopee-header' : 
                               order.platform === 'Lazada' ? 'sales-header lazada-header' : 
                               'sales-header general-header';

            return `
                <div class="sales-card">
                    <!-- Header - คลิกได้ -->
                    <div class="${headerClass}" style="cursor: pointer;" onclick="toggleOrderDetails('${orderId}')">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="tag-number">${order.issue_tag}</div>
                                    <small>วันที่: ${order.sale_date_formatted}</small>
                                </div>
                                <div class="me-3">
                                    ${platformBadge}
                                </div>
                                <div class="summary-info">
                                    <small>
                                        <strong>${order.actual_items || 0}</strong> รายการ 
                                        (<strong>${order.total_qty || 0}</strong> ชิ้น)
                                        ${parseFloat(order.total_amount) > 0 ? 
                                            ` • <strong>฿${order.total_amount_formatted}</strong>` : ''
                                        }
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <small class="me-3">โดย: ${order.issued_by_name || 'N/A'}</small>
                                <span class="expand-icon material-symbols-outlined" id="icon-${orderId}">
                                    expand_more
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- รายละเอียดสินค้า - ซ่อนไว้ -->
                    <div class="collapse" id="details-${orderId}">
                        <div class="sales-body">
                            ${itemsHtml}
                        </div>
                        <div class="total-summary">
                            <div class="row">
                                <div class="col">
                                    <strong>รวม ${order.actual_items || 0} รายการ</strong>
                                    <span class="text-muted ms-2">(${order.total_qty || 0} ชิ้น)</span>
                                </div>
                                <div class="col-auto">
                                    ${parseFloat(order.total_amount) > 0 ? 
                                        `<strong class="price-text">฿${order.total_amount_formatted}</strong>` : 
                                        '<span class="text-muted">ไม่ระบุราคา</span>'
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function updatePagination(pagination) {
            const container = document.getElementById('paginationContainer');
            const paginationUl = document.getElementById('pagination');
            
            if (pagination.total_pages <= 1) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            totalPages = pagination.total_pages;
            
            let html = '';
            
            // Previous button
            html += `
                <li class="page-item ${!pagination.has_prev ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadSalesOrders(${pagination.current_page - 1})">ก่อนหน้า</a>
                </li>
            `;
            
            // Page numbers
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            if (startPage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadSalesOrders(1)">1</a></li>`;
                if (startPage > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadSalesOrders(${i})">${i}</a>
                    </li>
                `;
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadSalesOrders(${pagination.total_pages})">${pagination.total_pages}</a></li>`;
            }
            
            // Next button
            html += `
                <li class="page-item ${!pagination.has_next ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadSalesOrders(${pagination.current_page + 1})">ถัดไป</a>
                </li>
            `;
            
            paginationUl.innerHTML = html;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            
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

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('platformFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            loadDashboardStats();
            loadSalesOrders();
        }

        function refreshData() {
            loadDashboardStats();
            loadSalesOrders(currentPage);
        }
        
        // ฟังก์ชันโหลดสถิติแดชบอร์ด
        function loadDashboardStats() {
            const statsLoading = document.getElementById('statsLoading');
            const statsContent = document.getElementById('statsContent');
            
            // แสดง loading state
            if (statsLoading) statsLoading.style.display = 'block';
            if (statsContent) statsContent.style.display = 'none';
            
            const params = new URLSearchParams({
                date_from: document.getElementById('dateFrom').value || '',
                date_to: document.getElementById('dateTo').value || ''
            });
            
            fetch(`../api/sales_dashboard_api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDashboardStats(data.data);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Dashboard Stats Error:', error);
                    if (statsLoading) {
                        statsLoading.innerHTML = `
                            <div class="text-center py-2">
                                <small class="text-light opacity-75">ไม่สามารถโหลดสถิติได้</small>
                            </div>
                        `;
                    }
                });
        }
        
        function displayDashboardStats(stats) {
            const statsLoading = document.getElementById('statsLoading');
            const statsContent = document.getElementById('statsContent');
            
            // ซ่อน loading และแสดงเนื้อหา
            if (statsLoading) statsLoading.style.display = 'none';
            if (statsContent) statsContent.style.display = 'block';
            
            // อัพเดตสถิติรวม
            const totalStats = stats.total_stats;
            if (totalStats) {
                const totalOrdersEl = document.getElementById('totalOrders');
                const totalTagsEl = document.getElementById('totalTags');
                const totalItemsEl = document.getElementById('totalItems');
                const totalAmountEl = document.getElementById('totalAmount');
                
                if (totalOrdersEl) totalOrdersEl.textContent = totalStats.total_orders.toLocaleString();
                if (totalTagsEl) totalTagsEl.textContent = totalStats.total_tags.toLocaleString();
                if (totalItemsEl) totalItemsEl.textContent = totalStats.total_items.toLocaleString();
                if (totalAmountEl) totalAmountEl.textContent = `฿${totalStats.total_amount_formatted}`;
            }
            
            // อัพเดตสถิติแพลตฟอร์ม
            const platformStatsEl = document.getElementById('platformStats');
            if (platformStatsEl && stats.platform_stats) {
                let platformHtml = '';
                
                if (stats.platform_stats.length === 0) {
                    platformHtml = '<div class="text-center py-3 opacity-75"><small>ไม่พบข้อมูลยอดขาย</small></div>';
                } else {
                    stats.platform_stats.forEach(platform => {
                        const platformClass = platform.platform.toLowerCase();
                        const percentage = totalStats.total_amount > 0 ? 
                            ((platform.total_amount / totalStats.total_amount) * 100).toFixed(1) : 0;
                        
                        platformHtml += `
                            <div class="platform-card ${platformClass}">
                                <div class="platform-header">
                                    <span class="platform-name">${platform.platform_display}</span>
                                    <span class="platform-amount">฿${platform.total_amount_formatted}</span>
                                </div>
                                <div class="platform-metrics">
                                    <span><strong>${platform.order_count}</strong> คำสั่งซื้อ</span>
                                    <span><strong>${platform.tag_count}</strong> แท็ค</span>
                                    <span><strong>${platform.total_qty}</strong> ชิ้น</span>
                                    <span><strong>${percentage}%</strong> ของยอดรวม</span>
                                </div>
                            </div>
                        `;
                    });
                }
                
                platformStatsEl.innerHTML = platformHtml;
            }
        }

        // ฟังก์ชันสำหรับขยาย/ย่อรายละเอียดสินค้า
        function toggleOrderDetails(orderId) {
            const detailsElement = document.getElementById(`details-${orderId}`);
            const iconElement = document.getElementById(`icon-${orderId}`);
            
            if (detailsElement.classList.contains('show')) {
                // ปิดรายละเอียด
                detailsElement.classList.remove('show');
                iconElement.textContent = 'expand_more';
                iconElement.style.transform = 'rotate(0deg)';
            } else {
                // เปิดรายละเอียด
                detailsElement.classList.add('show');
                iconElement.textContent = 'expand_less';
                iconElement.style.transform = 'rotate(180deg)';
            }
        }

        // ฟังก์ชันขยาย/ย่อทั้งหมด
        let allExpanded = false;
        function toggleAllOrders() {
            const allDetails = document.querySelectorAll('[id^="details-"]');
            const allIcons = document.querySelectorAll('[id^="icon-"]');
            const toggleBtn = document.getElementById('toggleAllBtn');
            
            if (allExpanded) {
                // ย่อทั้งหมด
                allDetails.forEach(detail => detail.classList.remove('show'));
                allIcons.forEach(icon => {
                    icon.textContent = 'expand_more';
                    icon.style.transform = 'rotate(0deg)';
                });
                toggleBtn.innerHTML = '<span class="material-symbols-outlined me-1">unfold_more</span>ขยายทั้งหมด';
                allExpanded = false;
            } else {
                // ขยายทั้งหมด
                allDetails.forEach(detail => detail.classList.add('show'));
                allIcons.forEach(icon => {
                    icon.textContent = 'expand_less';
                    icon.style.transform = 'rotate(180deg)';
                });
                toggleBtn.innerHTML = '<span class="material-symbols-outlined me-1">unfold_less</span>ย่อทั้งหมด';
                allExpanded = true;
            }
        }

        function exportData() {
            Swal.fire({
                title: 'ส่งออกข้อมูล',
                text: 'ฟีเจอร์นี้จะพัฒนาในเวอร์ชันถัดไป',
                icon: 'info'
            });
        }
        
        // Sidebar responsive behavior
        window.addEventListener('resize', function() {
            const mainContent = document.querySelector('.main-content');
            if (window.innerWidth <= 1024) {
                if (mainContent) {
                    mainContent.style.marginLeft = '0';
                    mainContent.style.paddingTop = '80px';
                }
            } else {
                if (mainContent) {
                    mainContent.style.marginLeft = '230px';
                    mainContent.style.paddingTop = '24px';
                }
            }
        });
    </script>
</body>
</html>