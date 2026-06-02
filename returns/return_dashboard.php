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

    <!-- Approve Edit Modal -->
    <div id="approveEditModal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-left: 4px solid #10b981;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <span class="material-icons" style="vertical-align:middle;color:#10b981;margin-right:0.4rem;">check_circle</span>
                        <span id="approveEditModalTitle">อนุมัติและนำสินค้ากลับคืนคลัง</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Product info banner -->
                    <div id="approve-product-banner" class="p-3 rounded mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="material-icons" style="font-size:2rem;color:#10b981;">inventory_2</span>
                            <div>
                                <div id="approve-product-name" style="font-weight:600;font-size:1.05rem;"></div>
                                <div id="approve-product-sku" class="text-muted" style="font-size:0.85rem;"></div>
                                <div>
                                    <span id="approve-return-code" class="badge bg-secondary me-1"></span>
                                    <span id="approve-returnable-badge"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="approve-return-id">
                    <input type="hidden" id="approve-reason-name">

                    <div class="mb-2">
                        <label class="form-label">หมายเหตุ</label>
                        <input type="text" class="form-control" id="approve-remark">
                    </div>

                    <div class="mb-2">
                        <label class="form-label">ตำแหน่ง</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <select class="form-select" id="approve-row-code">
                                    <option value="">แถว</option>
                                    <?php foreach (range('A','X') as $c) echo "<option value=\"{$c}\">{$c}</option>"; ?>
                                    <option value="T">T(ตู้)</option>
                                    <option value="sale(บน)">sale(บน)</option>
                                    <option value="sale(ล่าง)">sale(ล่าง)</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" id="approve-bin">
                                    <option value="">ล๊อค</option>
                                    <?php for ($i=1; $i<=10; $i++) echo "<option value=\"{$i}\">{$i}</option>"; ?>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" id="approve-shelf">
                                    <option value="">ชั้น</option>
                                    <?php for ($i=1; $i<=10; $i++) echo "<option value=\"{$i}\">{$i}</option>"; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Price section -->
                    <div class="card mb-2 border-0" style="background:#f8fafc;border:1px solid #e5e7eb!important;">
                        <div class="card-body p-2">

                            <!-- ราคาที่ขายไปจริง (แสดงเฉพาะเมื่อมีข้อมูล) -->
                            <div id="approve-sold-price-row" class="mb-2 d-none">
                                <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fef3c7;border:1px solid #f59e0b;">
                                    <span class="material-icons" style="font-size:1rem;color:#d97706;">sell</span>
                                    <small style="color:#92400e;font-weight:600;">ราคาที่ขายไป (ล่าสุด):</small>
                                    <strong id="approve-sold-price-display" style="color:#b45309;font-size:1.05rem;"></strong>
                                    <span class="badge ms-1" style="background:#f59e0b;color:white;font-size:0.65rem;">บาท</span>
                                </div>
                            </div>

                            <!-- ราคาใหม่ (ไม่บังคับ) -->
                            <div>
                                <label class="form-label mb-1" style="font-weight:600;color:#374151;">
                                    <span class="material-icons" style="font-size:0.95rem;vertical-align:middle;margin-right:0.25rem;color:#6b7280;">edit</span>
                                    อัปเดตราคาขาย
                                    <span class="text-muted fw-normal" style="font-size:0.8rem;">(ไม่บังคับ — เว้นว่างหากไม่ต้องการเปลี่ยน)</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" class="form-control" id="approve-price-new"
                                        style="color:#d97706;font-weight:600;" placeholder="ราคาขายใหม่...">
                                    <span class="input-group-text" style="background:#fef3c7;color:#92400e;font-weight:600;border-color:#fde68a;">บาท</span>
                                </div>
                            </div>

                            <input type="hidden" id="approve-exchange-rate" value="1">
                            <input type="hidden" id="approve-currency-code" value="THB">

                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" style="font-weight:600;color:#0f766e;">น้ำหนัก (กก.)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="approve-weight" style="color:#0f766e;font-weight:600;" placeholder="-" readonly>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">จำนวน</label>
                        <input type="number" min="1" class="form-control" id="approve-receive-qty" readonly style="background:#f3f4f6;cursor:not-allowed;">
                    </div>

                    <div class="mb-2">
                        <label class="form-label">วันหมดอายุ</label>
                        <input type="date" class="form-control" id="approve-expiry-date">
                    </div>

                    <!-- Damaged partial info -->
                    <div id="approve-damaged-info" class="d-none alert alert-warning p-2 mt-2" style="font-size:0.9rem;">
                        <span class="material-icons" style="font-size:1rem;vertical-align:middle;">warning</span>
                        รายการนี้เป็น <strong>สินค้าชำรุดบางส่วน</strong> — ระบบจะส่งเข้าคิวตรวจสอบ ไม่นำเข้าสต็อกทันที
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" id="approveEditSaveBtn">
                        <span class="material-icons" style="vertical-align:middle;margin-right:0.3rem;">check_circle</span>
                        บันทึกและอนุมัติ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div id="rejectModal" class="modal fade" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-left: 4px solid #ef4444;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <span class="material-icons" style="vertical-align: middle; color: #ef4444; margin-right: 0.4rem;">cancel</span>
                        ปฏิเสธสินค้าตีกลับ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size: 0.9rem;">กรุณาระบุเหตุผลในการปฏิเสธรายการนี้</p>
                    <div class="mb-3">
                        <label for="rejectReasonInput" class="form-label fw-medium">เหตุผลการปฏิเสธ</label>
                        <textarea id="rejectReasonInput" class="form-control" rows="3"
                            placeholder="ระบุเหตุผล..."
                            style="border-radius: 8px; resize: none;"></textarea>
                        <div id="rejectReasonError" class="text-danger mt-1" style="font-size: 0.85rem; display: none;">กรุณาระบุเหตุผล</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-danger" id="rejectConfirmBtn">
                        <span class="material-icons" style="vertical-align: middle; margin-right: 0.3rem; font-size: 1rem;">cancel</span>
                        ยืนยันปฏิเสธ
                    </button>
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
        let pendingRejectId = null;
        let rejectModalInstance = null;

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
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('alertModal'));
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
            document.getElementById('rejectConfirmBtn').addEventListener('click', doRejectReturn);

            // Approve Edit Modal
            const approveEditModalEl = document.getElementById('approveEditModal');
            window.approveEditModalInstance = new bootstrap.Modal(approveEditModalEl);
            document.getElementById('approveEditSaveBtn').addEventListener('click', doApproveWithEdit);

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
                        ${item.sale_order_id ? `
                        <div class="detail-row" style="background:#f0f9ff; margin: 0 -1rem; padding: 0.75rem 1rem;">
                            <div style="font-weight:600; color:#0369a1; margin-bottom:0.5rem;">
                                <span class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:0.3rem;">receipt_long</span>
                                ข้อมูลการขายที่ตีกลับ
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">เลขที่อ้างอิงขาย</div>
                            <div class="detail-value"><strong>#${item.sale_order_id}</strong></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">เลขแทร็กพัสดุ / Order ID</div>
                            <div class="detail-value">${item.issue_tag || '<em class="text-muted">-</em>'}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">แพลตฟอร์ม</div>
                            <div class="detail-value">
                                ${item.platform
                                    ? `<span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:0.85rem;">${item.platform}</span>`
                                    : '<em class="text-muted">-</em>'}
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">วันที่ขาย</div>
                            <div class="detail-value">${item.sale_date ? new Date(item.sale_date).toLocaleDateString('th-TH', {year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'}) : '<em class="text-muted">-</em>'}</div>
                        </div>
                        ` : ''}
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
            // Fetch full data for approve modal
            $.get(`${API_URL}?action=get_return_approve_data&return_id=${returnId}`, function(response) {
                if (response.status !== 'success') {
                    showAlert('error', '✕ โหลดข้อมูลไม่สำเร็จ', response.message || 'เกิดข้อผิดพลาด');
                    return;
                }
                const d = response.data;
                const isDamagedPartial = (d.reason_name || '').trim() === 'สินค้าชำรุดบางส่วน';

                // Populate modal
                document.getElementById('approve-return-id').value = d.return_id;
                document.getElementById('approve-reason-name').value = d.reason_name || '';
                document.getElementById('approveEditModalTitle').textContent =
                    isDamagedPartial ? 'ส่งเข้าคิวตรวจสอบสินค้าชำรุด' : 'อนุมัติและนำสินค้ากลับคืนคลัง';

                document.getElementById('approve-product-name').textContent = d.product_name || '-';
                document.getElementById('approve-product-sku').textContent = `SKU: ${d.sku || '-'}  |  Barcode: ${d.barcode || '-'}`;
                document.getElementById('approve-return-code').textContent = d.return_code || '';
                const retBadgeEl = document.getElementById('approve-returnable-badge');
                retBadgeEl.innerHTML = d.is_returnable == 1
                    ? '<span class="badge bg-success">คืนสต็อกได้</span>'
                    : '<span class="badge bg-danger">คืนสต็อกไม่ได้</span>';

                document.getElementById('approve-remark').value = `ตีกลับสินค้าจาก ${d.return_code || ''}`;
                document.getElementById('approve-receive-qty').value = Math.abs(parseFloat(d.return_qty || 0));
                document.getElementById('approve-expiry-date').value = d.expiry_date
                    ? d.expiry_date.substring(0, 10) : '';
                document.getElementById('approve-weight').value = d.remark_weight || '';

                // Price hidden fields (used for THB conversion when saving)
                document.getElementById('approve-exchange-rate').value = parseFloat(d.exchange_rate || 1);
                document.getElementById('approve-currency-code').value = d.currency_code || 'THB';

                // เคลียร์ช่องอัปเดตราคาใหม่ทุกครั้งที่เปิด modal
                document.getElementById('approve-price-new').value = '';

                // แสดงราคาที่ขายไป
                const soldRow = document.getElementById('approve-sold-price-row');
                const soldDisplay = document.getElementById('approve-sold-price-display');
                const actualSalePrice = parseFloat(d.actual_sale_price || 0);
                if (actualSalePrice > 0) {
                    soldDisplay.textContent = actualSalePrice.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    soldRow.classList.remove('d-none');
                } else {
                    soldRow.classList.add('d-none');
                }

                // Location
                function setSelectOrAdd(sel, val) {
                    val = (val || '').toString().trim();
                    const $sel = $(sel);
                    if (val && $sel.find(`option[value="${val}"]`).length === 0) {
                        $sel.append(`<option value="${val}">${val}</option>`);
                    }
                    $sel.val(val);
                }
                setSelectOrAdd('#approve-row-code', d.row_code);
                setSelectOrAdd('#approve-bin', d.bin);
                setSelectOrAdd('#approve-shelf', d.shelf);

                // Damaged partial info
                const dmgInfo = document.getElementById('approve-damaged-info');
                isDamagedPartial ? dmgInfo.classList.remove('d-none') : dmgInfo.classList.add('d-none');

                // Close detail modal first if open
                const detailModalEl = document.getElementById('detailModal');
                const detailModalInst = bootstrap.Modal.getInstance(detailModalEl);
                if (detailModalInst && detailModalEl.classList.contains('show')) {
                    detailModalEl.addEventListener('hidden.bs.modal', function openApprove() {
                        detailModalEl.removeEventListener('hidden.bs.modal', openApprove);
                        window.approveEditModalInstance.show();
                    });
                    detailModalInst.hide();
                } else {
                    window.approveEditModalInstance.show();
                }
            }).fail(function() {
                showAlert('error', '✕ ข้อผิดพลาด', 'ไม่สามารถโหลดข้อมูลได้');
            });
        }

        function doApproveWithEdit() {
            const returnId = document.getElementById('approve-return-id').value;
            const reasonName = document.getElementById('approve-reason-name').value;
            const isDamagedPartial = reasonName.trim() === 'สินค้าชำรุดบางส่วน';

            const remark         = document.getElementById('approve-remark').value.trim();
            const rowCode        = document.getElementById('approve-row-code').value;
            const bin            = document.getElementById('approve-bin').value;
            const shelf          = document.getElementById('approve-shelf').value;
            const receiveQty     = parseFloat(document.getElementById('approve-receive-qty').value) || 0;
            const expiryDate     = document.getElementById('approve-expiry-date').value;
            const priceNewRaw    = document.getElementById('approve-price-new').value;
            const priceNew       = priceNewRaw !== '' ? parseFloat(priceNewRaw) : null;

            if (receiveQty <= 0) {
                showAlert('error', '✕ กรุณากรอกจำนวน', 'จำนวนต้องมากกว่า 0');
                return;
            }

            const finalQty = Math.abs(receiveQty);

            const confirmMsg = isDamagedPartial
                ? 'ต้องการส่งรายการนี้เข้าคิวตรวจสอบสินค้าชำรุดหรือไม่?'
                : `ยืนยันการนำสินค้า ${Math.abs(finalQty)} ชิ้น กลับเข้าสต็อก?`;

            const saveBtn = document.getElementById('approveEditSaveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังบันทึก...';

            $.ajax({
                url: API_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action:                'approve_return',
                    return_id:             parseInt(returnId),
                    remark:                remark,
                    row_code:              rowCode,
                    bin:                   bin,
                    shelf:                 shelf,
                    receive_qty_override:  Math.abs(finalQty),
                    expiry_date_override:  expiryDate || null,
                    price_per_unit:        null,
                    sale_price:            priceNew,
                }),
                success: function(response) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<span class="material-icons" style="vertical-align:middle;margin-right:0.3rem;">check_circle</span>บันทึกและอนุมัติ';
                    if (response.status === 'success') {
                        const successMsg = isDamagedPartial
                            ? 'ส่งรายการเข้าคิวตรวจสอบสินค้าชำรุดแล้ว'
                            : 'นำสินค้ากลับเข้าสต็อกเรียบร้อยแล้ว และบันทึกในตารางความเคลื่อนไหวแล้ว';
                        window.approveEditModalInstance.hide();
                        showAlert('success', '✓ อนุมัติสำเร็จ!', successMsg);
                        loadDashboard();
                    } else {
                        showAlert('error', '✕ เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกได้');
                    }
                },
                error: function(xhr) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<span class="material-icons" style="vertical-align:middle;margin-right:0.3rem;">check_circle</span>บันทึกและอนุมัติ';
                    let errorMsg = 'เกิดข้อผิดพลาดในการสื่อสาร';
                    try {
                        const r = JSON.parse(xhr.responseText);
                        if (r.message) errorMsg = r.message;
                    } catch(e) {}
                    showAlert('error', '✕ ข้อผิดพลาด', errorMsg);
                }
            });
        }

        function rejectReturn(returnId) {
            pendingRejectId = returnId;
            const reasonInput = document.getElementById('rejectReasonInput');
            const errorEl = document.getElementById('rejectReasonError');
            reasonInput.value = '';
            errorEl.style.display = 'none';
            reasonInput.classList.remove('is-invalid');

            if (!rejectModalInstance) {
                rejectModalInstance = new bootstrap.Modal(document.getElementById('rejectModal'));
            }

            // ปิด detail modal ก่อน (ถ้าเปิดอยู่) แล้วค่อยเปิด reject modal
            // เพื่อป้องกัน backdrop ซ้อนกัน
            const detailModalEl = document.getElementById('detailModal');
            const detailModalInstance = bootstrap.Modal.getInstance(detailModalEl);
            if (detailModalInstance && detailModalEl.classList.contains('show')) {
                detailModalEl.addEventListener('hidden.bs.modal', function openReject() {
                    detailModalEl.removeEventListener('hidden.bs.modal', openReject);
                    rejectModalInstance.show();
                    document.getElementById('rejectModal').addEventListener('shown.bs.modal', function handler() {
                        reasonInput.focus();
                        document.getElementById('rejectModal').removeEventListener('shown.bs.modal', handler);
                    });
                });
                detailModalInstance.hide();
            } else {
                rejectModalInstance.show();
                document.getElementById('rejectModal').addEventListener('shown.bs.modal', function handler() {
                    reasonInput.focus();
                    document.getElementById('rejectModal').removeEventListener('shown.bs.modal', handler);
                });
            }
        }

        function doRejectReturn() {
            const reason = document.getElementById('rejectReasonInput').value.trim();
            const errorEl = document.getElementById('rejectReasonError');
            const reasonInput = document.getElementById('rejectReasonInput');

            if (!reason) {
                errorEl.style.display = 'block';
                reasonInput.classList.add('is-invalid');
                reasonInput.focus();
                return;
            }
            errorEl.style.display = 'none';
            reasonInput.classList.remove('is-invalid');

            const rejectBtn = document.getElementById('rejectConfirmBtn');
            rejectBtn.disabled = true;
            rejectBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังบันทึก...';

            $.ajax({
                url: API_URL,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 'reject_return',
                    return_id: pendingRejectId,
                    reason: reason
                }),
                success: function(response) {
                    rejectBtn.disabled = false;
                    rejectBtn.innerHTML = '<span class="material-icons" style="vertical-align:middle;margin-right:0.3rem;font-size:1rem;">cancel</span>ยืนยันปฏิเสธ';
                    if (response.status === 'success') {
                        const rejectModalEl = document.getElementById('rejectModal');
                        rejectModalEl.addEventListener('hidden.bs.modal', function onHidden() {
                            rejectModalEl.removeEventListener('hidden.bs.modal', onHidden);
                            showAlert('success', '✓ ปฏิเสธสำเร็จ!', 'บันทึกการปฏิเสธเรียบร้อยแล้ว');
                            loadDashboard();
                        });
                        rejectModalInstance.hide();
                    } else {
                        showAlert('error', '✕ เกิดข้อผิดพลาด', response.message);
                    }
                },
                error: function(xhr) {
                    rejectBtn.disabled = false;
                    rejectBtn.innerHTML = '<span class="material-icons" style="vertical-align:middle;margin-right:0.3rem;font-size:1rem;">cancel</span>ยืนยันปฏิเสธ';
                    let errorMsg = 'เกิดข้อผิดพลาดในการสื่อสาร';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.message) errorMsg = res.message;
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
