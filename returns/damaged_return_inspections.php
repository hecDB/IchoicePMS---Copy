<?php
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/combined_login_register.php');
    exit;
}

require '../templates/sidebar.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบสินค้าชำรุด - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8fafc;
        }
        .mainwrap {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            background-color: #f8fafc;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .status-filter button {
            border: none;
            background: none;
            padding: 0.75rem 1.25rem;
            border-radius: 999px;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.2s ease;
        }
        .status-filter button.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.35);
        }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
        }
        .table thead {
            background: #f1f5f9;
        }
        .table thead th {
            border: none;
            color: #334155;
            font-weight: 600;
        }
        .table tbody tr {
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .table tbody tr:hover {
            background: rgba(99, 102, 241, 0.08);
        }
        .detail-card {
            position: sticky;
            top: 2rem;
        }
        .badge-status {
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
        }
        .badge-status.pending {
            background-color: #fff1c2;
            color: #92400e;
        }
        .badge-status.completed {
            background-color: #bbf7d0;
            color: #166534;
        }
        .form-section-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }
        .form-label {
            font-weight: 500;
            color: #334155;
        }
    </style>
</head>
<body>
<div class="mainwrap">
    <div class="page-header">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <span class="material-icons align-middle me-1" style="font-size: 1.75rem; color: #6366f1;">fact_check</span>
                ตรวจสอบสินค้าชำรุดบางส่วน
            </h1>
            <p class="text-muted mb-0">เลือกสินค้าที่ตีกลับด้วยเหตุผล "สินค้าชำรุดบางส่วน" เพื่อเปลี่ยน SKU และนำกลับเข้าสต๊อก</p>
        </div>
        <div class="status-filter btn-group" role="group" aria-label="สถานะ">
            <button type="button" class="active" data-status="pending">รอตรวจสอบ</button>
            <button type="button" data-status="completed">ตรวจสอบแล้ว</button>
            <button type="button" data-status="all">ทั้งหมด</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold text-slate-700">
                            <span class="material-icons align-middle me-1" style="color: #10b981;">inventory</span>
                            รายการที่ต้องตรวจสอบ
                        </h5>
                        <button class="btn btn-outline-primary btn-sm" id="refreshListBtn">
                            <span class="material-icons align-middle" style="font-size: 1.1rem;">refresh</span>
                            รีเฟรช
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="inspectionTable">
                            <thead>
                                <tr>
                                    <th style="width: 16%;">เลขที่</th>
                                    <th style="width: 28%;">สินค้า</th>
                                    <th style="width: 12%;" class="text-end">จำนวน</th>
                                    <th style="width: 18%;">สถานะ</th>
                                    <th style="width: 22%;">บันทึกเมื่อ</th>
                                    <th style="width: 12%;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card detail-card" id="detailCard" style="display: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-0 fw-semibold text-slate-800">รายละเอียดสินค้าตีกลับ</h5>
                            <small class="text-muted" id="detailReturnCode"></small>
                        </div>
                        <span class="badge-status pending" id="detailStatus">รอตรวจสอบ</span>
                    </div>

                    <div class="mb-4">
                        <div class="form-section-title">ข้อมูลสินค้า</div>
                        <ul class="list-unstyled mb-0 small text-slate-600" id="detailInfoList"></ul>
                    </div>

                    <form id="inspectionForm" autocomplete="off">
                        <input type="hidden" id="inspectionId">
                        <input type="hidden" id="originalSkuHidden">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">SKU เดิม</label>
                                <input type="text" class="form-control" id="detailOriginalSku" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU ใหม่ (ระบบกำหนด)</label>
                                <input type="text" class="form-control" id="newSkuInput" readonly>
                                <small class="text-muted">ระบบจะเปลี่ยนเป็น "ตำหนิ-" ตาม SKU เดิม</small>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">จำนวนตีกลับ</label>
                                <input type="number" class="form-control" id="returnQtyInput" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">จำนวนที่จะนำกลับเข้าสต๊อก</label>
                                <input type="number" class="form-control" id="restockQtyInput" step="0.01" min="0.01" required>
                                <small class="text-muted">ปล่อยว่างเพื่อใช้จำนวนเท่ากับที่ตีกลับ</small>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">ราคาทุนใหม่ (บาท)</label>
                                <input type="number" class="form-control" id="costPriceInput" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ราคาขายใหม่ (บาท)</label>
                                <input type="number" class="form-control" id="salePriceInput" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">หมายเหตุการตรวจสอบ</label>
                            <textarea class="form-control" rows="3" id="inspectionNotesInput" placeholder="ระบุรายละเอียดสภาพสินค้า หรือรหัสตำหนิ..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <span class="material-icons align-middle me-1" style="font-size: 1.2rem;">check_circle</span>
                                ยืนยันสินค้ามีตำหนิและจัดการสต๊อก
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="cancelDetailBtn">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="placeholderCard" class="card detail-card" style="border-style: dashed;">
                <div class="card-body text-center text-muted py-5">
                    <span class="material-icons mb-3" style="font-size: 2.8rem; color: #cbd5f5;">inventory_2</span>
                    <p class="mb-1">เลือกสินค้าจากรายการด้านซ้ายเพื่อเริ่มตรวจสอบ</p>
                    <small>รองรับเฉพาะเหตุผล "สินค้าชำรุดบางส่วน"</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const API_BASE = '../api/returned_items_api.php';
let currentStatus = 'pending';
let inspectionsCache = [];
let currentInspection = null;

function escapeHtml(value) {
    return (value ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function generateDefectSku(originalSku) {
    const trimmed = (originalSku || '').trim();
    if (!trimmed) {
        return '';
    }
    const prefix = 'ตำหนิ-';
    return trimmed.startsWith(prefix) ? trimmed : `${prefix}${trimmed}`;
}

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    const numeric = Number(value);
    if (Number.isNaN(numeric)) {
        return '-';
    }
    return numeric.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusBadgeClass(status) {
    if (!status) return 'badge-status pending';
    return status === 'completed' ? 'badge-status completed' : 'badge-status pending';
}

function statusDisplay(status) {
    if (!status) return 'รอตรวจสอบ';
    return status === 'completed' ? 'ตรวจสอบแล้ว' : 'รอตรวจสอบ';
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('th-TH', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function formatDateOnly(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value || '-';
    }
    return date.toLocaleDateString('th-TH', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
}

async function loadInspections(status = 'pending') {
    currentStatus = status;
    const tbody = document.querySelector('#inspectionTable tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>';
    try {
        const response = await fetch(`${API_BASE}?action=list_damaged_inspections&status=${encodeURIComponent(status)}`);
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้');
        }
        inspectionsCache = result.data || [];
        if (inspectionsCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่พบรายการในสถานะนี้</td></tr>';
            return;
        }
        tbody.innerHTML = inspectionsCache.map(item => `
            <tr data-inspection-id="${item.inspection_id}">
                <td class="fw-semibold text-primary">${item.return_code}</td>
                <td>${item.product_name || '-'}</td>
                <td class="text-end">${Number(item.return_qty || 0).toLocaleString()}</td>
                <td><span class="${statusBadgeClass(item.status)}">${statusDisplay(item.status)}</span></td>
                <td>${formatDateTime(item.created_at)}</td>
                <td>
                    <button type="button" class="btn btn-outline-secondary btn-sm manage-btn" data-inspection-id="${item.inspection_id}">
                        <span class="material-icons align-middle" style="font-size: 1rem;">build</span>
                        จัดการ
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${error.message}</td></tr>`;
    }
}

async function loadInspectionDetail(inspectionId) {
    if (!inspectionId) return;
    try {
        const response = await fetch(`${API_BASE}?action=get_damaged_inspection&inspection_id=${inspectionId}`);
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message || 'ไม่พบข้อมูลรายการ');
        }
        populateDetail(result.data);
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: error.message });
    }
}

function populateDetail(data) {
    currentInspection = data;
    document.getElementById('placeholderCard').style.display = 'none';
    const detailCard = document.getElementById('detailCard');
    detailCard.style.display = 'block';

    document.getElementById('inspectionId').value = data.inspection_id;
    document.getElementById('originalSkuHidden').value = data.sku || '';
    document.getElementById('detailReturnCode').textContent = data.return_code;
    document.getElementById('detailStatus').textContent = statusDisplay(data.status);
    document.getElementById('detailStatus').className = statusBadgeClass(data.status);

    const infoList = document.getElementById('detailInfoList');
    const displaySku = data.sku || '';
    const expiryDisplay = data.expiry_date || data.return_expiry_date || '';
    infoList.innerHTML = `
        <li><strong>ชื่อสินค้า:</strong> ${escapeHtml(data.product_name || '-')}</li>
        <li><strong>SKU ตีกลับ:</strong> ${escapeHtml(displaySku || '-')}</li>
        <li><strong>จำนวนตีกลับ:</strong> ${Number(data.return_qty || 0).toLocaleString()}</li>
        <li><strong>วันหมดอายุ:</strong> ${formatDateOnly(expiryDisplay)}</li>
        <li><strong>PO อ้างอิง:</strong> ${data.po_number ? escapeHtml(data.po_number) : '-'}</li>
        <li><strong>ราคาทุนล่าสุด:</strong> ${formatCurrency(data.cost_price)}</li>
        <li><strong>ราคาขายล่าสุด:</strong> ${formatCurrency(data.sale_price)}</li>
        <li><strong>เหตุผล:</strong> ${escapeHtml(data.reason_name || '-')}</li>
        <li><strong>หมายเหตุการคืน:</strong> ${data.return_notes ? escapeHtml(data.return_notes) : '-'}</li>
        <li><strong>หมายเหตุการตรวจสอบเดิม:</strong> ${data.defect_notes ? escapeHtml(data.defect_notes) : '-'}</li>
        <li><strong>สร้างโดย:</strong> ${escapeHtml(data.created_by_name || '-')}</li>
        <li><strong>บันทึกเมื่อ:</strong> ${formatDateTime(data.created_at)}</li>
    `;

    document.getElementById('detailOriginalSku').value = displaySku;

    const returnQty = Number(data.return_qty || 0);
    document.getElementById('returnQtyInput').value = returnQty.toFixed(2);

    const restockSource = data.restock_qty || data.return_qty || 0;
    document.getElementById('restockQtyInput').value = Number(restockSource).toFixed(2);

    const autoSku = generateDefectSku(displaySku);
    const resolvedSku = autoSku || (data.new_sku || '');
    document.getElementById('newSkuInput').value = resolvedSku;

    const costPriceInput = document.getElementById('costPriceInput');
    const salePriceInput = document.getElementById('salePriceInput');
    const costValue = data.cost_price !== null && data.cost_price !== undefined && data.cost_price !== ''
        ? Number(data.cost_price)
        : null;
    const saleValue = data.sale_price !== null && data.sale_price !== undefined && data.sale_price !== ''
        ? Number(data.sale_price)
        : null;

    costPriceInput.value = costValue !== null && !Number.isNaN(costValue) ? costValue.toFixed(2) : '';
    salePriceInput.value = saleValue !== null && !Number.isNaN(saleValue) ? saleValue.toFixed(2) : '';

    document.getElementById('inspectionNotesInput').value = data.defect_notes || '';
}

function resetDetail() {
    document.getElementById('inspectionForm').reset();
    document.getElementById('originalSkuHidden').value = '';
    document.getElementById('detailCard').style.display = 'none';
    document.getElementById('placeholderCard').style.display = 'block';
    currentInspection = null;
}

async function submitInspection(event) {
    event.preventDefault();
    const inspectionId = document.getElementById('inspectionId').value;
    const restockQtyValue = parseFloat(document.getElementById('restockQtyInput').value);
    const inspectionNotes = document.getElementById('inspectionNotesInput').value.trim();
    const returnQtyValue = parseFloat(document.getElementById('returnQtyInput').value);
    const costPriceValue = parseFloat(document.getElementById('costPriceInput').value);
    const salePriceValue = parseFloat(document.getElementById('salePriceInput').value);
    const originalSku = currentInspection?.sku || document.getElementById('originalSkuHidden').value || '';
    const generatedSku = generateDefectSku(originalSku);

    if (!inspectionId) {
        Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบ', text: 'ไม่พบรหัสรายการตรวจสอบ' });
        return;
    }

    if (!generatedSku) {
        Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบ', text: 'กรุณากรอก SKU ใหม่ให้เรียบร้อย' });
        return;
    }

    const restockQty = Number.isNaN(restockQtyValue) ? returnQtyValue : restockQtyValue;
    const costPrice = Number.isNaN(costPriceValue) ? null : Number(costPriceValue.toFixed(2));
    const salePrice = Number.isNaN(salePriceValue) ? null : Number(salePriceValue.toFixed(2));

    document.getElementById('newSkuInput').value = generatedSku;

    if (!restockQty || restockQty <= 0) {
        Swal.fire({ icon: 'warning', title: 'จำนวนไม่ถูกต้อง', text: 'จำนวนที่จะนำกลับเข้าสต๊อกต้องมากกว่า 0' });
        return;
    }

    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'process_damaged_inspection',
                inspection_id: inspectionId,
                new_sku: generatedSku,
                restock_qty: restockQty,
                inspection_notes: inspectionNotes,
                cost_price: costPrice,
                sale_price: salePrice
            })
        });
        const result = await response.json();
        if (result.status !== 'success') {
            throw new Error(result.message || 'ไม่สามารถบันทึกข้อมูลได้');
        }
        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'บันทึกการตรวจสอบเรียบร้อย' });
        await loadInspections(currentStatus);
        resetDetail();
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: error.message });
    }
}

// Event bindings

document.querySelectorAll('.status-filter button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.status-filter button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadInspections(btn.dataset.status);
    });
});

document.getElementById('refreshListBtn').addEventListener('click', () => loadInspections(currentStatus));

document.getElementById('inspectionTable').addEventListener('click', event => {
    const manageBtn = event.target.closest('.manage-btn');
    if (manageBtn) {
        event.preventDefault();
        event.stopPropagation();
        loadInspectionDetail(manageBtn.dataset.inspectionId);
        return;
    }

    const row = event.target.closest('tr[data-inspection-id]');
    if (!row) {
        return;
    }
    loadInspectionDetail(row.dataset.inspection_id);
});

document.getElementById('inspectionForm').addEventListener('submit', submitInspection);
document.getElementById('cancelDetailBtn').addEventListener('click', resetDetail);

loadInspections();
</script>
</body>
</html>
