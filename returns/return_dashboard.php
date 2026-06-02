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
    <title>แดชบอร์ดสินค้าตีกลับ - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link href="../assets/modern-table.css" rel="stylesheet">
    <link href="../assets/mainwrap-modern.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
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
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .stat-card.active-filter {
            box-shadow: 0 0 0 3px currentColor, 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .stat-card.pending.active-filter  { box-shadow: 0 0 0 3px #f59e0b, 0 4px 12px rgba(245,158,11,0.25); }
        .stat-card.approved.active-filter  { box-shadow: 0 0 0 3px #3b82f6, 0 4px 12px rgba(59,130,246,0.25); }
        .stat-card.returnable.active-filter { box-shadow: 0 0 0 3px #10b981, 0 4px 12px rgba(16,185,129,0.25); }
        .stat-card.non-returnable.active-filter { box-shadow: 0 0 0 3px #ef4444, 0 4px 12px rgba(239,68,68,0.25); }
        
        .stat-card.pending {
            border-left-color: #f59e0b;
        }
        
        .stat-card.approved {
            border-left-color: #3b82f6;
        }
        
        .stat-card.returnable {
            border-left-color: #10b981;
        }
        
        .stat-card.non-returnable {
            border-left-color: #ef4444;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .card {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .btn-action {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 6px;
        }
        
        .modal-content {
            border-radius: 12px;
        }
        
        .detail-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-label {
            font-weight: 500;
            color: #6b7280;
        }
        
        .detail-value {
            color: #1f2937;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px;
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
                        📊 แดชบอร์ดสินค้าตีกลับ
                    </h1>
                    <p style="color: #6b7280; font-size: 1rem;">
                        ติดตามและจัดการสินค้าตีกลับทั้งหมด
                    </p>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card pending" id="card-pending" onclick="filterByCard('pending')">
                            <span class="material-icons" style="font-size: 2.5rem; color: #f59e0b;">pending_actions</span>
                            <div class="stat-number" id="stat-pending">0</div>
                            <div class="stat-label">รอการอนุมัติ</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card approved" id="card-approved" onclick="filterByCard('approved')">
                            <span class="material-icons" style="font-size: 2.5rem; color: #3b82f6;">check_circle</span>
                            <div class="stat-number" id="stat-approved">0</div>
                            <div class="stat-label">เสร็จสิ้น</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card returnable" id="card-returnable" onclick="filterByCard('returnable')">
                            <span class="material-icons" style="font-size: 2.5rem; color: #10b981;">inventory</span>
                            <div class="stat-number" id="stat-returnable">0</div>
                            <div class="stat-label">คืนสต็อกได้</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card non-returnable" id="card-non-returnable" onclick="filterByCard('non-returnable')">
                            <span class="material-icons" style="font-size: 2.5rem; color: #ef4444;">block</span>
                            <div class="stat-number" id="stat-non-returnable">0</div>
                            <div class="stat-label">คืนไม่ได้</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <a href="return_items.php" class="btn btn-primary w-100">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">add</span>
                            บันทึกสินค้าตีกลับใหม่
                        </a>
                    </div>
                    <div class="col-md-6">
                        <button class="btn btn-outline-secondary w-100" onclick="refreshDashboard()">
                            <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                </div>

                <!-- Returns Table -->
                <div class="card">
                    <div class="card-header bg-light p-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">📋 รายการสินค้าตีกลับ</h6>
                        <div id="active-filter-label"></div>
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                        <div id="returns-container">
                            <p class="p-3 text-muted text-center">กำลังโหลด...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="modalTitle">รายละเอียดสินค้าตีกลับ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <div id="action-buttons"></div>
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

    <!-- Confirm Modal -->
    <div id="confirmModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="confirmTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage" class="mb-0 text-muted"></p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" id="confirmCancelBtn" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" id="confirmAcceptBtn">ยืนยัน</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        const API_URL = '../api/returned_items_api.php';
        let confirmModalInstance = null;
        let pendingConfirmAction = null;
        let returnsTable = null;
        let allReturnsData = [];
        let activeFilter = null;

        const filterConfig = {
            'pending':        { label: 'รอการอนุมัติ',  fn: r => r.return_status === 'pending' },
            'approved':       { label: 'เสร็จสิ้น',      fn: r => r.return_status === 'approved' || r.return_status === 'completed' },
            'returnable':     { label: 'คืนสต็อกได้',   fn: r => r.is_returnable == 1 },
            'non-returnable': { label: 'คืนไม่ได้',     fn: r => r.is_returnable == 0 }
        };

        function filterByCard(type) {
            if (activeFilter === type) {
                // Toggle off — show all
                activeFilter = null;
            } else {
                activeFilter = type;
            }
            updateActiveCardStyle();
            renderTable(activeFilter ? allReturnsData.filter(filterConfig[type].fn) : allReturnsData);
            updateFilterLabel();
        }

        function updateActiveCardStyle() {
            Object.keys(filterConfig).forEach(key => {
                const card = document.getElementById('card-' + key);
                if (card) {
                    card.classList.toggle('active-filter', activeFilter === key);
                }
            });
        }

        function updateFilterLabel() {
            const el = document.getElementById('active-filter-label');
            if (!el) return;
            if (activeFilter) {
                el.innerHTML = `<span class="badge bg-secondary" style="font-size:0.85rem; cursor:pointer;" onclick="filterByCard('${activeFilter}')">
                    <span class="material-icons" style="font-size:0.85rem;vertical-align:middle;">filter_list</span>
                    กรอง: ${filterConfig[activeFilter].label} &nbsp;✕
                </span>`;
            } else {
                el.innerHTML = '';
            }
        }

        function escapeForOnclick(value) {
            return String(value ?? '')
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/\r?\n/g, '\\n')
                .replace(/\u2028|\u2029/g, '');
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
            
            content.classList.remove('border-success', 'border-danger', 'border-info', 'border-warning');
            
            if (type === 'success') {
                icon.innerHTML = '✓';
                content.style.borderLeft = '4px solid #10b981';
            } else if (type === 'error') {
                icon.innerHTML = '✕';
                content.style.borderLeft = '4px solid #ef4444';
            } else if (type === 'info') {
                icon.innerHTML = 'ℹ';
                content.style.borderLeft = '4px solid #3b82f6';
            }
            
            modal.show();
        }

        $(document).ready(function() {
            const confirmModalEl = document.getElementById('confirmModal');
            confirmModalInstance = new bootstrap.Modal(confirmModalEl);
            document.getElementById('confirmAcceptBtn').addEventListener('click', function() {
                if (pendingConfirmAction) {
                    const action = pendingConfirmAction;
                    pendingConfirmAction = null;
                    confirmModalInstance.hide();
                    action();
                }
            });
            document.getElementById('confirmCancelBtn').addEventListener('click', function() {
                pendingConfirmAction = null;
            });

            loadDashboard();
            // Auto refresh every 30 seconds
            setInterval(loadDashboard, 30000);
        });

        function loadDashboard() {
            $.get(`${API_URL}?action=get_returns&limit=1000`, function(response) {
                if (response.status !== 'success') return;
                allReturnsData = response.data;

                // Update stat counts
                $('#stat-pending').text(allReturnsData.filter(r => r.return_status === 'pending').length);
                $('#stat-approved').text(allReturnsData.filter(r => r.return_status === 'approved' || r.return_status === 'completed').length);
                $('#stat-returnable').text(allReturnsData.filter(r => r.is_returnable == 1).length);
                $('#stat-non-returnable').text(allReturnsData.filter(r => r.is_returnable == 0).length);

                // Render table, respecting current filter
                const displayed = activeFilter
                    ? allReturnsData.filter(filterConfig[activeFilter].fn)
                    : allReturnsData;
                renderTable(displayed);
            });
        }

        function renderTable(data) {
            if (returnsTable) {
                returnsTable.destroy();
                returnsTable = null;
            }

            let html = '';
            if (data.length === 0) {
                html = '<p class="text-muted text-center p-3">ไม่พบข้อมูลสินค้าตีกลับ</p>';
            } else {
                html = `
                    <table class="table table-hover mb-0" style="font-size: 0.95rem;" id="returns-table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>เลขที่</th>
                                <th>สินค้า</th>
                                <th>จำนวน</th>
                                <th>เหตุผล</th>
                                <th>ประเภท</th>
                                <th>สถานะ</th>
                                <th>บันทึกโดย</th>
                                <th>วันที่</th>
                                <th>ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(item => `
                                <tr>
                                    <td><strong>${item.return_code}</strong></td>
                                    <td>
                                        ${item.product_name}<br>
                                        <small class="text-muted">${item.sku}</small>
                                    </td>
                                    <td>${item.return_qty}</td>
                                    <td><small>${item.reason_name}</small></td>
                                    <td>
                                        ${item.is_returnable == 1
                                            ? '<span class="badge bg-success">คืนได้</span>'
                                            : '<span class="badge bg-danger">คืนไม่ได้</span>'}
                                    </td>
                                    <td>${getStatusBadge(item.return_status)}</td>
                                    <td><small>${item.created_by_name}</small></td>
                                    <td><small>${new Date(item.created_at).toLocaleDateString('th-TH')}</small></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="viewDetail(${item.return_id})">
                                            <span class="material-icons" style="font-size: 1rem;">info</span>
                                        </button>
                                        ${item.return_status === 'pending' ? `
                                            <button class="btn btn-sm btn-outline-success btn-action" onclick="approveReturn(${item.return_id}, '${escapeForOnclick(item.reason_name || '')}')">
                                                <span class="material-icons" style="font-size: 1rem;">check</span>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-action" onclick="rejectReturn(${item.return_id})">
                                                <span class="material-icons" style="font-size: 1rem;">close</span>
                                            </button>
                                        ` : ''}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }

            $('#returns-container').html(html);
            initializeReturnsTable();
        }

        function loadReturns() { loadDashboard(); }

        function initializeReturnsTable() {
            const tableElement = $('#returns-table');
            if (!tableElement.length) {
                return;
            }

            returnsTable = tableElement.DataTable({
                orderCellsTop: true,
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: 'ค้นหา:',
                    lengthMenu: 'แสดง _MENU_ รายการ',
                    info: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
                    paginate: {
                        first: 'หน้าแรก',
                        last: 'หน้าสุดท้าย',
                        next: 'ถัดไป',
                        previous: 'ก่อนหน้า'
                    },
                    infoEmpty: 'ไม่มีข้อมูล',
                    zeroRecords: 'ไม่พบข้อมูลที่ค้นหา'
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

        function viewDetail(returnId) {
            $.get(`${API_URL}?action=get_return&return_id=${returnId}`, function(response) {
                if (response.status === 'success') {
                    const item = response.data;
                    const date = new Date(item.created_at).toLocaleDateString('th-TH');
                    
                    let html = `
                        <div class="detail-row">
                            <div class="detail-label">เลขที่สินค้าตีกลับ</div>
                            <div class="detail-value"><strong>${item.return_code}</strong></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">ชื่อสินค้า</div>
                            <div class="detail-value">${item.product_name}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">SKU / Barcode</div>
                            <div class="detail-value">${item.sku} / ${item.barcode || '-'}</div>
                        </div>
                        ${item.expiry_date ? `
                        <div class="detail-row">
                            <div class="detail-label">วันหมดอายุ</div>
                            <div class="detail-value">
                                <strong>${new Date(item.expiry_date).toLocaleDateString('th-TH')}</strong>
                            </div>
                        </div>
                        ` : ''}
                        <div class="detail-row">
                            <div class="detail-label">จำนวนตีกลับ</div>
                            <div class="detail-value">${item.return_qty}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">เหตุผลการตีกลับ</div>
                            <div class="detail-value">${item.reason_name}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">ประเภท</div>
                            <div class="detail-value">
                                ${item.is_returnable == 1 
                                    ? '<span class="badge bg-success">✓ สามารถคืนสต็อก</span>' 
                                    : '<span class="badge bg-danger">✗ ไม่สามารถคืนสต็อก</span>'}
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">สถานะ</div>
                            <div class="detail-value">${getStatusBadge(item.return_status)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">หมายเหตุ</div>
                            <div class="detail-value">${item.notes || '<em class="text-muted">ไม่มี</em>'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">บันทึกโดย</div>
                            <div class="detail-value">${item.created_by_name} (${date})</div>
                        </div>
                        ${item.approved_by_name ? `
                        <div class="detail-row">
                            <div class="detail-label">อนุมัติโดย</div>
                            <div class="detail-value">${item.approved_by_name} (${new Date(item.approved_at).toLocaleDateString('th-TH')})</div>
                        </div>
                        ` : ''}
                    `;
                    
                    $('#modalTitle').text(`รายละเอียด: ${item.return_code}`);
                    $('#modalBody').html(html);
                    
                    // Action buttons
                    let actionHtml = '';
                    if (item.return_status === 'pending') {
                        actionHtml = `
                            <button type="button" class="btn btn-success btn-sm" onclick="approveReturn(${item.return_id}, '${escapeForOnclick(item.reason_name || '')}')">
                                <span class="material-icons" style="vertical-align: middle; margin-right: 0.3rem;">check_circle</span>
                                อนุมัติ
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectReturn(${item.return_id})">
                                <span class="material-icons" style="vertical-align: middle; margin-right: 0.3rem;">cancel</span>
                                ปฏิเสธ
                            </button>
                        `;
                    }
                    $('#action-buttons').html(actionHtml);
                    
                    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                    modal.show();
                }
            });
        }

        function approveReturn(returnId, reasonName = '') {
            const normalizedReason = (reasonName || '').trim();
            const isDamagedPartial = normalizedReason === 'สินค้าชำรุดบางส่วน';
            const confirmTitle = 'ยืนยันการอนุมัติ';
            const confirmMessage = isDamagedPartial
                ? 'ต้องการส่งรายการนี้เข้าคิวตรวจสอบสินค้าชำรุดหรือไม่? ระบบจะยังไม่นำจำนวนกลับเข้าสต็อก'
                : 'ต้องการนำสินค้าตีกลับกลับเข้าสต็อกหรือไม่?';

            showConfirm(confirmTitle, confirmMessage, function() {
                $.ajax({
                    url: API_URL,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'approve_return',
                        return_id: returnId
                    }),
                    success: function(response) {
                        if (response.status === 'success') {
                            const successMessage = isDamagedPartial
                                ? 'ส่งรายการเข้าคิวตรวจสอบสินค้าชำรุดแล้ว'
                                : 'นำจำนวนเข้าสต็อกเรียบร้อยแล้ว';
                            showAlert('success', '✓ อนุมัติสำเร็จ!', successMessage);
                            const detailModalInstance = bootstrap.Modal.getInstance(document.getElementById('detailModal'));
                            if (detailModalInstance) {
                                detailModalInstance.hide();
                            }
                            loadDashboard();
                        } else {
                            showAlert('error', '✕ เกิดข้อผิดพลาด', response.message);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'เกิดข้อผิดพลาดในการสื่อสาร';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMsg = response.message;
                            }
                        } catch(e) {}
                        showAlert('error', '✕ ข้อผิดพลาด', errorMsg);
                    }
                });
            });
        }

        function rejectReturn(returnId) {
            const reason = prompt('ระบุเหตุผลการปฏิเสธ:');
            if (reason === null) return;
            
            $.ajax({
                url: API_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'reject_return',
                    return_id: returnId,
                    reason: reason
                }),
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('success', '✓ ปฏิเสธสำเร็จ!', 'บันทึกการปฏิเสธเรียบร้อยแล้ว');
                        const detailModalInstance = bootstrap.Modal.getInstance(document.getElementById('detailModal'));
                        if (detailModalInstance) {
                            detailModalInstance.hide();
                        }
                        loadDashboard();
                    } else {
                        showAlert('error', '✕ เกิดข้อผิดพลาด', response.message);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'เกิดข้อผิดพลาดในการสื่อสาร';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch(e) {}
                    showAlert('error', '✕ ข้อผิดพลาด', errorMsg);
                }
            });
        }

        function refreshDashboard() {
            loadDashboard();
            showAlert('info', '✓ รีเฟรชข้อมูล', 'ดึงข้อมูลล่าสุดเรียบร้อยแล้ว');
        }

        function showConfirm(title, message, onConfirm) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            pendingConfirmAction = onConfirm;
            confirmModalInstance.show();
        }
    </script>
</body>
</html>
