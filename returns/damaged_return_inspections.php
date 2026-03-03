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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <style>
        body {
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
            <p class="text-muted mb-0">เลือกสินค้าที่ตีกลับด้วยเหตุผล "สินค้าชำรุดบางส่วน" เพื่อเปลี่ยน SKU ใหม่ (ขายได้: บันทึก temp_products, ทิ้ง: ปิดงาน)</p>
        </div>
        <div class="status-filter btn-group" role="group" aria-label="สถานะ">
            <button type="button" data-status="pending">รอตรวจสอบ</button>
            <button type="button" data-status="completed">ตรวจสอบแล้ว</button>
            <button type="button" class="active" data-status="all">ทั้งหมด</button>
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

                    <div class="mb-3">
                        <label class="form-label">สถานะสินค้า</label>
                        <select class="form-select" id="dispositionSelect">
                            <option value="sellable">ขายได้ - บันทึกเข้า temp_products เพื่ออนุมัติ SKU ใหม่</option>
                            <option value="discard">ทิ้ง / ใช้ไม่ได้ - ไม่บันทึกลงสต๊อก</option>
                        </select>
                        <small class="text-muted">เลือกสถานะเพื่อกำหนดว่าจะจัดการสินค้านี้อย่างไร</small>
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
                                <label class="form-label">วันหมดอายุ (สำหรับสินค้าขายได้)</label>
                                <input type="date" class="form-control" id="expiryDateInput">
                                <small class="text-muted">จำเป็นถ้าสินค้านี้จะบันทึกเข้า temp_products</small>
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
                            <button type="submit" class="btn btn-primary flex-grow-1" id="submitInspectionBtn">
                                <span class="material-icons align-middle me-1" style="font-size: 1.2rem;">check_circle</span>
                                <span id="submitBtnText">ยืนยันสินค้ามีตำหนิและบันทึก</span>
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
                    <small>สินค้าขายได้จะบันทึกเข้า temp_products เพื่อรอการอนุมัติ</small>
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

function statusBadgeClass(status) {
    if (!status) return 'badge-status pending';
    return status === 'completed' ? 'badge-status completed' : 'badge-status pending';
}

function statusDisplay(status) {
    if (!status) return 'รอตรวจสอบ';
    return status === 'completed' ? 'ตรวจสอบแล้ว' : 'รอตรวจสอบ';
}

function formatCurrency(value) {
    // Handle null/undefined/empty values
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    
    const numeric = Number(value);
    
    // Check if conversion to number failed
    if (Number.isNaN(numeric)) {
        return '-';
    }
    
    // Format number with Thai locale (allow 0 and other valid numbers)
    return numeric.toLocaleString('th-TH', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
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

async function loadInspections(status = 'all') {
    currentStatus = status;
    const tbody = document.querySelector('#inspectionTable tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">กำลังโหลดข้อมูล...</td></tr>';
    try {
        console.log('📡 Loading inspections with status:', status);
        const response = await fetch(`${API_BASE}?action=list_damaged_inspections&status=${encodeURIComponent(status)}`);
        console.log('📊 Response status:', response.status);
        const result = await response.json();
        console.log('📦 API Response:', result);
        if (result.status !== 'success') {
            throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลได้');
        }
        inspectionsCache = result.data || [];
        console.log('✅ Data loaded, total records:', inspectionsCache.length);
        if (inspectionsCache.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">ไม่พบรายการในสถานะนี้</td></tr>';
            return;
        }
        tbody.innerHTML = inspectionsCache.map(item => `
            <tr data-inspection-id="${item.inspection_id}">
                <td class="fw-semibold text-primary">${item.return_code}</td>
                <td>${item.product_name || '-'}</td>
                <td class="text-end">${Number(item.return_qty || 0).toLocaleString()}</td>
                <td><span class="${statusBadgeClass(item.return_status)}">${statusDisplay(item.return_status)}</span></td>
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
    if (!inspectionId) {
        console.warn('⚠️ No inspection ID provided');
        return;
    }
    try {
        console.log('📡 Loading inspection detail for ID:', inspectionId);
        const response = await fetch(`${API_BASE}?action=get_damaged_inspection&inspection_id=${inspectionId}`);
        const result = await response.json();
        console.log('📦 Inspection detail loaded:', result);
        if (result.status !== 'success') {
            throw new Error(result.message || 'ไม่พบข้อมูลรายการ');
        }
        populateDetail(result.data);
    } catch (error) {
        console.error('❌ Error loading inspection:', error);
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
    document.getElementById('detailStatus').textContent = statusDisplay(data.return_status);
    document.getElementById('detailStatus').className = statusBadgeClass(data.return_status);

    // ═══════════════════════════════════════════════════════════════════════
    // DETECT PRODUCT TYPE AND DISPLAY APPROPRIATE INFO
    // ═══════════════════════════════════════════════════════════════════════
    const isOriginal = isOriginalProduct(data);
    const productTypeLabel = isOriginal 
        ? '<span class="badge bg-primary">📦 สินค้าชนิดเดิม</span>' 
        : '<span class="badge bg-info">🆕 สินค้าชนิดใหม่</span>';

    const infoList = document.getElementById('detailInfoList');
    const displaySku = data.sku || '';
    const expiryDisplay = data.expiry_date || data.return_expiry_date || '';
    
    // Debug: Log sale_price value
    console.log('💰 Debug - sale_price value:', {
        raw: data.sale_price,
        type: typeof data.sale_price,
        formatted: formatCurrency(data.sale_price),
        priceSource: data.price_source,
        pricePoNumber: data.price_po_number
    });
    
    // สร้างข้อความแสดงราคา
    let costPriceDisplay = formatCurrency(data.cost_price);
    let salePriceDisplay = formatCurrency(data.sale_price);
    let priceSourceInfo = '';
    
    if (data.price_source === 'no_po_history') {
        costPriceDisplay = '<span style="color: #dc2626;">ไม่มีคำสั่งซื้อมาก่อน</span>';
        salePriceDisplay = '<span style="color: #dc2626;">ไม่มีคำสั่งซื้อมาก่อน</span>';
        priceSourceInfo = '<li style="color: #dc2626;"><strong>⚠️ หมายเหตุ:</strong> สินค้านี้ไม่มีประวัติคำสั่งซื้อ กรุณากรอกราคาด้านล่าง</li>';
    } else if (data.price_source === 'latest_po' && data.price_po_number) {
        priceSourceInfo = `<li style="color: #059669;"><strong>📊 ราคาจาก:</strong> PO #${escapeHtml(data.price_po_number)}</li>`;
    }
    
    infoList.innerHTML = `
        <li><strong>ประเภทสินค้า:</strong> ${productTypeLabel}</li>
        <li><strong>ชื่อสินค้า:</strong> ${escapeHtml(data.product_name || '-')}</li>
        <li><strong>SKU ตีกลับ:</strong> ${escapeHtml(displaySku || '-')}</li>
        <li><strong>จำนวนตีกลับ:</strong> ${Number(data.return_qty || 0).toLocaleString()}</li>
        <li><strong>วันหมดอายุ:</strong> ${formatDateOnly(expiryDisplay)}</li>
        <li><strong>PO อ้างอิง:</strong> ${data.po_number ? escapeHtml(data.po_number) : '-'}</li>
        <li><strong>ราคาทุนล่าสุด:</strong> ${costPriceDisplay}</li>
        <li><strong>ราคาขายล่าสุด:</strong> <span style="color: #d946ef; font-weight: 600;">${salePriceDisplay}</span></li>
        ${priceSourceInfo}
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

    // Use new generateNewSku function
    const autoSku = generateNewSku(data);
    const resolvedSku = autoSku || (data.new_sku || '');
    document.getElementById('newSkuInput').value = resolvedSku;

    // ═══════════════════════════════════════════════════════════════════════
    // SHOW DISPOSITION MESSAGE BASED ON PRODUCT TYPE
    // ═══════════════════════════════════════════════════════════════════════
    // Set disposition select from existing notes (default to sellable)
    const dispositionSelect = document.getElementById('dispositionSelect');
    const disposition = resolveDisposition(data);
    dispositionSelect.value = disposition;
    
    // Update button text based on disposition
    const submitBtnText = document.getElementById('submitBtnText');
    if (submitBtnText) {
        if (disposition === 'sellable') {
            if (isOriginal) {
                submitBtnText.textContent = '✓ บันทึกสินค้าเดิมขายได้ (products table)';
            } else {
                submitBtnText.textContent = '✓ บันทึกสินค้าใหม่ขายได้ (temp_products)';
            }
        } else {
            submitBtnText.textContent = '✓ บันทึกสินค้าทิ้ง/ใช้ไม่ได้';
        }
    } else {
        console.warn('⚠️ submitBtnText element not found');
    }

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
    
    // ถ้าไม่มีราคาจาก PO history ให้บังคับกรอก และแสดง warning
    if (data.price_source === 'no_po_history') {
        costPriceInput.required = true;
        salePriceInput.required = true;
        costPriceInput.placeholder = 'กรุณากรอกราคาทุน (บังคับ)';
        salePriceInput.placeholder = 'กรุณากรอกราคาขาย (บังคับ)';
        costPriceInput.style.borderColor = '#f59e0b';
        salePriceInput.style.borderColor = '#f59e0b';
        
        console.warn('⚠️ No PO history - Price fields are now required');
    } else {
        costPriceInput.required = false;
        salePriceInput.required = false;
        costPriceInput.placeholder = 'ใส่ราคาใหม่หรือเว้นว่างเพื่อใช้ราคาล่าสุด';
        salePriceInput.placeholder = 'ใส่ราคาใหม่หรือเว้นว่างเพื่อใช้ราคาล่าสุด';
        costPriceInput.style.borderColor = '';
        salePriceInput.style.borderColor = '';
    }

    document.getElementById('inspectionNotesInput').value = data.defect_notes || '';

    // Lock editing for completed items
    const isEditable = (data.return_status || '') === 'pending';
    setDetailEditable(isEditable);
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
    const disposition = document.getElementById('dispositionSelect').value || 'sellable';
    const restockQtyValue = parseFloat(document.getElementById('restockQtyInput').value);
    const inspectionNotes = document.getElementById('inspectionNotesInput').value.trim();
    const returnQtyValue = parseFloat(document.getElementById('returnQtyInput').value);
    const costPriceValue = parseFloat(document.getElementById('costPriceInput').value);
    const salePriceValue = parseFloat(document.getElementById('salePriceInput').value);
    const expiryDateValue = document.getElementById('expiryDateInput').value;
    const originalSku = currentInspection?.sku || document.getElementById('originalSkuHidden').value || '';
    const generatedSku = generateNewSku(currentInspection);

    console.log('📋 submitInspection - Debug Info:', {
        inspectionId: inspectionId,
        currentInspection,
        isOriginal: isOriginalProduct(currentInspection),
        disposition,
        generatedSku
    });

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
    
    // Validate ราคาเมื่อไม่มีประวัติ PO และเป็นสินค้าขายได้
    if (disposition === 'sellable' && currentInspection?.price_source === 'no_po_history') {
        if (!costPrice || costPrice <= 0) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'กรุณากรอกราคาทุน', 
                text: 'สินค้านี้ไม่มีประวัติคำสั่งซื้อ กรุณากรอกราคาทุนใหม่' 
            });
            document.getElementById('costPriceInput').focus();
            return;
        }
        if (!salePrice || salePrice <= 0) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'กรุณากรอกราคาขาย', 
                text: 'สินค้านี้ไม่มีประวัติคำสั่งซื้อ กรุณากรอกราคาขายใหม่' 
            });
            document.getElementById('salePriceInput').focus();
            return;
        }
        console.log('✅ Price validation passed for no_po_history case');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SHOW VERIFICATION DIALOG BEFORE SUBMISSION
    // ═══════════════════════════════════════════════════════════════════════
    const confirmSubmit = await showVerificationDialog(
        currentInspection, 
        disposition, 
        generatedSku, 
        restockQty, 
        expiryDateValue, 
        costPrice, 
        salePrice
    );
    
    if (!confirmSubmit) {
        return; // User cancelled
    }

    // Validate expiry_date (สำหรับสินค้าขายได้)
    if (disposition === 'sellable' && expiryDateValue) {
        const selectedDate = new Date(expiryDateValue + 'T23:59:59');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            Swal.fire({ icon: 'warning', title: 'วันหมดอายุไม่ถูกต้อง', text: 'วันหมดอายุต้องไม่เป็นอดีต' });
            return;
        }
    }

    try {
        console.log('🔄 Sending request to API:');
        console.log('🔍 Debug - currentInspection:', currentInspection);
        
        // Determine product type for logging
        const isOriginal = isOriginalProduct(currentInspection);
        const productType = isOriginal ? 'Original' : 'New';
        console.log('📋 Product type detected:', productType);
        
        const payloadData = {
            action: 'process_damaged_inspection',
            inspection_id: inspectionId,
            disposition,
            new_sku: generatedSku,
            restock_qty: restockQty,
            inspection_notes: inspectionNotes,
            cost_price: costPrice,
            sale_price: salePrice,
            expiry_date: expiryDateValue || null,
            _metadata: {
                productType,
                isOriginal,
                timestamp: new Date().toISOString()
            }
        };
        console.log('📤 Payload:', payloadData);
        
        // Show loading state
        const submitBtn = document.getElementById('submitInspectionBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังบันทึก...';
        
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payloadData)
        });
        
        console.log('📥 Response Status:', response.status, response.statusText);
        
        const result = await response.json();
        console.log('📥 Response Body:', result);
        
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        if (result.status !== 'success') {
            throw new Error(result.message || 'ไม่สามารถบันทึกข้อมูลได้');
        }
        
        // Build success message based on disposition and product type
        let successMessage = '';
        if (disposition === 'sellable') {
            if (isOriginal) {
                successMessage = `✓ บันทึกสำเร็จ\n\nสินค้าเดิมได้รับการตรวจสอบ:\n• SKU ใหม่: ${generatedSku}\n• บันทึกไปยัง products table\n• เพิ่มรายการใหม่ลง PO #${currentInspection.po_number || '-'}\n• บันทึกการรับสินค้าชำรุดเข้าสต๊อก\n• บันทึกการเคลื่อนไหวสินค้า`;
            } else {
                successMessage = `✓ บันทึกสำเร็จ\n\nสินค้าใหม่ได้รับการตรวจสอบ:\n• SKU ใหม่: ${generatedSku} (temp)\n• บันทึกไปยัง temp_products\n• รอการอนุมัติข้อมูลสินค้า\n• หลังอนุมัติจะเพิ่มเข้า PO อัตโนมัติ`;
            }
        } else {
            successMessage = `✓ บันทึกสำเร็จ\n\nสินค้าจัดประเมินว่า:\n• ทิ้ง/ใช้ไม่ได้\n• เก็บไว้เป็นข้อมูลเฉยๆในระบบ`;
        }
        
        Swal.fire({ 
            icon: 'success', 
            title: 'ตรวจสอบสินค้าแล้ว', 
            text: successMessage,
            didOpen: (modal) => {
                modal.querySelector('.swal2-html-container').style.whiteSpace = 'pre-line';
            }
        });
        
        await loadInspections(currentStatus);
        resetDetail();
    } catch (error) {
        const submitBtn = document.getElementById('submitInspectionBtn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span class="material-icons align-middle me-1" style="font-size: 1.2rem;">check_circle</span>';
        submitBtn.innerHTML += '<span id="submitBtnText">ยืนยันสินค้ามีตำหนิและบันทึก</span>';
        
        console.error('❌ Error:', error);
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

// ═══════════════════════════════════════════════════════════════════════
// UPDATE SUBMIT BUTTON TEXT BASED ON DISPOSITION AND PRODUCT TYPE
// ═══════════════════════════════════════════════════════════════════════
document.getElementById('dispositionSelect').addEventListener('change', function() {
    const submitBtnText = document.getElementById('submitBtnText');
    if (!submitBtnText) {
        console.warn('⚠️ submitBtnText element not found in change handler');
        return;
    }
    
    const isOriginal = isOriginalProduct(currentInspection);
    
    if (this.value === 'sellable') {
        if (isOriginal) {
            submitBtnText.textContent = '✓ บันทึกสินค้าเดิมขายได้ (products table)';
        } else {
            submitBtnText.textContent = '✓ บันทึกสินค้าใหม่ขายได้ (temp_products)';
        }
    } else {
        submitBtnText.textContent = '✓ บันทึกสินค้าทิ้ง/ใช้ไม่ได้';
    }
});

loadInspections('all');

// Helper to resolve disposition from existing notes
function resolveDisposition(data) {
    const combinedNotes = `${data.return_notes || ''}\n${data.defect_notes || ''}`;
    if (/ทิ้ง\s*\/\s*ใช้ไม่ได้/.test(combinedNotes)) {
        return 'discard';
    }
    if (/ขายได้/.test(combinedNotes)) {
        return 'sellable';
    }
    return 'sellable';
}

// Helper to toggle editability for completed inspections
function setDetailEditable(isEditable) {
    const form = document.getElementById('inspectionForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    form.querySelectorAll('input, select, textarea, button[type="submit"]').forEach(el => {
        if (el.id === 'cancelDetailBtn') return; // keep cancel available
        el.disabled = !isEditable;
    });
    if (submitBtn) {
        if (isEditable) {
            const disposition = document.getElementById('dispositionSelect').value || 'sellable';
            submitBtn.textContent = disposition === 'sellable' 
                ? 'ยืนยันสินค้ามีตำหนิและบันทึก temp_products' 
                : 'ยืนยันจัดประเมินสินค้าและปิดงาน';
        } else {
            submitBtn.innerHTML = '<span class="material-icons align-middle me-1" style="font-size: 1.2rem;">check_circle</span>รายการนี้ตรวจสอบแล้ว';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════
// VERIFICATION FUNCTIONS FOR DAMAGED ITEM INSPECTION
// ═══════════════════════════════════════════════════════════════════════

/**
 * Determine if product is original (existing in products table) or new
 */
function isOriginalProduct(inspection) {
    if (!inspection) return false;
    // Original product: product_id is set and not null/0
    return (inspection.product_id && parseInt(inspection.product_id) > 0) || false;
}

/**
 * Generate new SKU based on product type and disposition
 * For original products: "ตำหนิ-" + original SKU
 * For new products: "ตำหนิ-" + system generated part
 */
function generateNewSku(inspection, customSuffix = null) {
    const originalSku = inspection?.sku || '';
    const prefix = 'ตำหนิ-';
    
    if (!originalSku) {
        // No SKU provided - return prefix with timestamp
        if (customSuffix) {
            return `${prefix}${customSuffix}`;
        }
        return `${prefix}${Date.now().toString(36).toUpperCase()}`;
    }
    
    // Remove existing prefix if present
    const cleanSku = originalSku.startsWith(prefix) ? originalSku.substring(prefix.length) : originalSku;
    return `${prefix}${cleanSku}`;
}

/**
 * Generate new barcode - format: BAR-[itemId]-[timestamp][random]
 */
function generateNewBarcode(itemId) {
    const timestamp = Date.now().toString(36).toUpperCase();
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `BAR-${itemId}-${timestamp}${random}`;
}

/**
 * Verify and validate inspection data before submission
 */
function validateInspectionData(inspection, disposition, restockQty, expiryDate, newSku) {
    const errors = [];
    const warnings = [];
    
    // Check inspection data
    if (!inspection) {
        errors.push('ไม่พบข้อมูลรายการตรวจสอบ');
        return { valid: false, errors, warnings };
    }
    
    // Check SKU
    if (!newSku || newSku.trim() === '') {
        errors.push('SKU ใหม่ไม่สามารถว่างได้');
    }
    
    // Check restock quantity
    const returnQty = parseFloat(inspection.return_qty || 0);
    if (restockQty <= 0) {
        errors.push('จำนวนที่จะนำกลับเข้าสต๊อกต้องมากกว่า 0');
    }
    if (restockQty > returnQty) {
        errors.push(`จำนวนนำกลับไม่สามารถเกิน ${returnQty} ชิ้น`);
    }
    
    // For sellable items
    if (disposition === 'sellable') {
        // Check expiry date
        if (!expiryDate) {
            warnings.push('⚠️ ควรระบุวันหมดอายุสำหรับสินค้าขายได้');
        } else {
            const selectedDate = new Date(expiryDate + 'T23:59:59');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selectedDate < today) {
                errors.push('วันหมดอายุต้องไม่เป็นอดีต');
            }
        }
        
        // Determine product type
        const isOriginal = isOriginalProduct(inspection);
        
        if (isOriginal) {
            // Original product - should update products table
            if (!inspection.product_id) {
                errors.push('ไม่พบรหัสสินค้า (product_id)');
            }
            warnings.push('📝 สินค้าเดิม: ระบบจะสร้าง SKU/Barcode ใหม่ในตาราง products');
        } else {
            // New product - should use temp_products
            warnings.push('📝 สินค้าใหม่: ระบบจะบันทึกลง temp_products รอการอนุมัติ');
        }
    } else {
        // Discard case
        warnings.push('⚠️ สินค้านี้จะถูกจัดประเมินว่าไม่สามารถใช้ได้ และปิดงาน');
    }
    
    return {
        valid: errors.length === 0,
        errors,
        warnings
    };
}

/**
 * Build warning message for user confirmation
 */
function buildVerificationDialog(inspection, disposition) {
    const isOriginal = isOriginalProduct(inspection);
    const poRef = inspection.po_number ? `PO #${inspection.po_number}` : (inspection.po_id ? `PO ID: ${inspection.po_id}` : 'N/A');
    let content = '';
    
    if (disposition === 'sellable') {
        if (isOriginal) {
            content = `
                <div class="alert alert-info mb-3">
                    <h6 class="fw-bold mb-2">✓ สินค้าชนิดเดิม (ขายได้)</h6>
                    <ul class="mb-0 small">
                        <li>✓ สร้าง SKU ใหม่: <code>ตำหนิ-${inspection.sku}</code></li>
                        <li>✓ สร้าง Barcode ใหม่</li>
                        <li>✓ บันทึกในตาราง <code>products</code></li>
                        <li>✓ <strong>เพิ่มรายการสินค้าชำรุดลง ${poRef}</strong></li>
                        <li>✓ <strong>บันทึกการรับสินค้าชำรุดเข้าสต๊อก</strong></li>
                        <li>✓ บันทึกการเคลื่อนไหวสินค้า</li>
                    </ul>
                </div>
            `;
        } else {
            content = `
                <div class="alert alert-info mb-3">
                    <h6 class="fw-bold mb-2">✓ สินค้าชนิดใหม่ (ขายได้)</h6>
                    <ul class="mb-0 small">
                        <li>✓ สร้าง SKU ใหม่: <code>ตำหนิ-[auto]</code></li>
                        <li>✓ สร้าง Barcode ใหม่</li>
                        <li>✓ บันทึกในตาราง <code>temp_products</code></li>
                        <li>✓ เก็บอ้างอิง ${poRef}</li>
                        <li>⏳ รอการอนุมัติและแก้ไขข้อมูลสินค้า</li>
                        <li>⏳ หลังอนุมัติจะเพิ่มเข้า PO อัตโนมัติ</li>
                    </ul>
                </div>
            `;
        }
    } else {
        content = `
            <div class="alert alert-warning mb-3">
                <h6 class="fw-bold mb-2">⚠️ สินค้าทิ้ง / ใช้ไม่ได้</h6>
                <ul class="mb-0 small">
                    <li>• อัปเดตสถานะในตาราง <code>returned_items</code></li>
                    <li>• ไม่บันทึกเข้าสต๊อก</li>
                    <li>• ไม่เพิ่มเข้า PO</li>
                    <li>• เก็บข้อมูลเพื่อการตรวจสอบเท่านั้น</li>
                </ul>
            </div>
        `;
    }
    
    return content;
}

/**
 * Show comprehensive inspection verification dialog
 */
function showVerificationDialog(inspection, disposition, newSku, restockQty, expiryDate, costPrice, salePrice) {
    const validation = validateInspectionData(inspection, disposition, restockQty, expiryDate, newSku);
    
    // Show errors if any
    if (validation.errors.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อมูลไม่ถูกต้อง',
            html: '<ul class="text-start"><li>' + validation.errors.join('</li><li>') + '</li></ul>',
            confirmButtonText: 'แก้ไข'
        });
        return false;
    }
    
    // Build summary
    const isOriginal = isOriginalProduct(inspection);
    const productType = isOriginal ? '📦 สินค้าชนิดเดิม' : '🆕 สินค้าชนิดใหม่';
    const dispositionLabel = disposition === 'sellable' ? '✓ ขายได้' : '✗ ทิ้ง/ใช้ไม่ได้';
    
    const summaryHtml = `
        <div class="text-start">
            ${buildVerificationDialog(inspection, disposition)}
            
            <div class="card border-0 bg-light mb-3">
                <div class="card-body small">
                    <div class="row mb-2">
                        <div class="col-6"><strong>ประเภท:</strong> ${productType}</div>
                        <div class="col-6"><strong>สถานะ:</strong> ${dispositionLabel}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6"><strong>SKU ใหม่:</strong> <code>${newSku}</code></div>
                        <div class="col-6"><strong>จำนวน:</strong> ${Number(restockQty).toLocaleString()} ชิ้น</div>
                    </div>
                    ${expiryDate ? `<div class="row mb-2">
                        <div class="col-12"><strong>วันหมดอายุ:</strong> ${new Date(expiryDate).toLocaleDateString('th-TH')}</div>
                    </div>` : ''}
                    ${costPrice || salePrice ? `<div class="row">
                        <div class="col-6"><strong>ราคาทุน:</strong> ${formatCurrency(costPrice)}</div>
                        <div class="col-6"><strong>ราคาขาย:</strong> ${formatCurrency(salePrice)}</div>
                    </div>` : ''}
                </div>
            </div>
            
            ${validation.warnings.length > 0 ? `
                <div class="alert alert-info mb-0">
                    <strong>📋 หมายเหตุ:</strong>
                    <ul class="mb-0 mt-2">
                        ${validation.warnings.map(w => `<li>${w}</li>`).join('')}
                    </ul>
                </div>
            ` : ''}
        </div>
    `;
    
    return new Promise((resolve) => {
        Swal.fire({
            title: 'ยืนยันการตรวจสอบสินค้า',
            html: summaryHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '✓ ยืนยันและบันทึก',
            cancelButtonText: 'ยกเลิก',
            scrollContainer: '.swal2-html-container'
        }).then((result) => {
            resolve(result.isConfirmed);
        });
    });
}
</script>
</body>
</html>
