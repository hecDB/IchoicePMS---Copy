<?php
session_start();
require_once '../config/db_connect.php';
include '../templates/sidebar.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการใบกำกับภาษีที่บันทึก</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link rel="stylesheet" href="../assets/mainwrap-modern.css">
    <!-- SheetJS for Excel Export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <style>
        body { background: #f5f7fb; }
        .mainwrap { padding: 24px; }
        .page-title { display:flex; align-items:center; gap:12px; margin-bottom:18px; }
        .page-title .material-icons { font-size:32px; color:#385dfa; }
        .filters { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:24px; box-shadow:0 10px 24px rgba(31,41,55,0.08); }
        .filters-header { display:flex; align-items:center; gap:8px; margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid #f3f4f6; }
        .filters-header .material-icons { color:#385dfa; font-size:24px; }
        .filters-header h3 { margin:0; font-size:16px; font-weight:700; color:#111827; }
        .date-inputs { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:16px; }
        .input-group { position:relative; }
        .input-group label { font-size:13px; color:#4b5563; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-weight:600; }
        .input-group label .material-icons { font-size:18px; color:#6b7280; }
        .input-wrapper { position:relative; display:flex; align-items:center; }
        .input-wrapper .material-icons { position:absolute; left:12px; font-size:20px; color:#9ca3af; pointer-events:none; }
        .filters input { width:100%; padding:12px 12px 12px 44px; border:2px solid #e5e7eb; border-radius:12px; background:#fff; font-size:14px; transition:all 0.3s ease; font-family:inherit; }
        .filters input:hover { border-color:#cbd5e1; }
        .filters input:focus { outline:none; border-color:#385dfa; box-shadow:0 0 0 3px rgba(56,93,250,0.1); }
        .quick-dates { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; padding:12px; background:#f8fafc; border-radius:10px; }
        .quick-dates-label { font-size:12px; color:#6b7280; font-weight:600; width:100%; margin-bottom:4px; }
        .btn-quick { border:1px solid #e5e7eb; border-radius:8px; padding:6px 12px; font-weight:600; cursor:pointer; font-size:12px; background:#fff; color:#374151; transition:all 0.2s ease; }
        .btn-quick:hover { background:#385dfa; color:#fff; border-color:#385dfa; transform:translateY(-1px); box-shadow:0 4px 12px rgba(56,93,250,0.2); }
        .actions { display:flex; gap:12px; flex-wrap:wrap; }
        .btn { border:none; border-radius:12px; padding:12px 24px; font-weight:700; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:8px; transition:all 0.3s ease; }
        .btn .material-icons { font-size:18px; }
        .btn-primary { background: linear-gradient(135deg, #385dfa 0%, #4f46e5 100%); color:#fff; box-shadow:0 8px 18px rgba(56,93,250,0.25); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 12px 24px rgba(56,93,250,0.35); }
        .btn-secondary { background:#fff; color:#374151; border:2px solid #e5e7eb; }
        .btn-secondary:hover { background:#f9fafb; border-color:#cbd5e1; }
        .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; box-shadow:0 8px 18px rgba(16,185,129,0.25); }
        .btn-success:hover { transform:translateY(-2px); box-shadow:0 12px 24px rgba(16,185,129,0.35); }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px; box-shadow:0 12px 30px rgba(31,41,55,0.06); margin-top:14px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; border-bottom:1px solid #e5e7eb; font-size:13px; }
        th { background:#f8fafc; text-align:left; }
        .text-right { text-align:right; }
        .badge { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:600; }
        .badge.blue { background:rgba(56,93,250,0.12); color:#1d4ed8; }
        .badge.green { background:rgba(34,197,94,0.12); color:#15803d; }
        .badge.orange { background:rgba(249,115,22,0.12); color:#c2410c; }
        .badge.purple { background:rgba(168,85,247,0.12); color:#7e22ce; }
        .no-data { text-align:center; padding:20px; color:#6b7280; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; z-index:2100; }
        .modal { background:#fff; width:90%; max-width:920px; border-radius:14px; padding:16px; box-shadow:0 20px 48px rgba(0,0,0,0.18); max-height:90vh; overflow:auto; }
        .modal-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
        .modal-title { font-size:16px; font-weight:700; }
        .close-btn { border:none; background:#eef2ff; color:#4338ca; border-radius:8px; padding:6px 10px; cursor:pointer; }
        .detail-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:8px 12px; font-size:13px; margin-bottom:10px; }
        .items-table th, .items-table td { border:1px solid #e5e7eb; }
        .items-table th { background:#f3f4f6; }
        
        /* Print Modal Styles */
        #printModal { max-width: 1200px; }
        .invoice-sheet { background:#fff; color:#000; padding:16px; border:1px solid #d1d5db; min-height:auto; page-break-after: always; }
        .invoice-sheet:last-child { page-break-after: auto; }
        .invoice-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #111; padding-bottom:8px; }
        .invoice-brand { display:flex; align-items:center; gap:8px; }
        .invoice-brand h2 { margin:0; font-size:14px; }
        .invoice-meta { text-align:right; font-size:10px; }
        .invoice-block { margin-top:10px; font-size:10px; }
        .invoice-block h4 { margin:0 0 5px; font-size:11px; }
        .inv-table { width:100%; border-collapse:collapse; margin-top:6px; font-size:8px; border:1px solid #000; }
        .inv-table th, .inv-table td { border:1px solid #000; padding:3px 4px; box-sizing:border-box; font-size:8px; }
        .inv-table th { background:#f3f4f6; font-weight:600; }
        .summary-box { margin-top:8px; display:grid; grid-template-columns:1fr 180px; gap:10px; }
        .totals { border:1px solid #000; }
        .totals td { padding:3px 5px; font-size:9px; line-height:1.3; }
        .footer-note { margin-top:12px; font-size:9px; }
        
        @media print {
            @page { 
                size: A5 portrait; 
                margin: 10mm; 
            }
            body { 
                background: #fff; 
                margin: 0;
                padding: 0;
            }
            .mainwrap, .card, .controls, .filters { display: none !important; }
            .modal-backdrop { display: none !important; }
            .modal { display: none !important; }
            .modal-header { display: none !important; }
            .sidebar, .sidebar-backdrop, .mobile-nav-toggle { display: none !important; }
            #hiddenPrintContainer { 
                display: block !important; 
                position: static !important;
                width: 100% !important;
            }
            .invoice-sheet { 
                border: none !important; 
                padding: 12px !important; 
                margin: 0 !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
                min-height: auto !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .invoice-sheet:last-child { 
                page-break-after: auto !important; 
            }
            .invoice-header { 
                padding-bottom: 6px !important; 
                margin-bottom: 8px !important;
            }
            .invoice-brand h2 { font-size: 12px !important; }
            .invoice-brand > div > div { font-size: 8px !important; line-height: 1.4 !important; }
            .invoice-brand > div:first-child { width: 45px !important; height: 45px !important; }
            .invoice-meta { font-size: 9px !important; }
            .invoice-meta > div:first-child { font-size: 11px !important; }
            .invoice-meta > div:nth-child(2) { font-size: 10px !important; }
            .invoice-meta > div:last-child { font-size: 9px !important; }
            .invoice-block { margin-top: 8px !important; font-size: 8px !important; }
            .invoice-block h4 { font-size: 9px !important; margin-bottom: 4px !important; padding-bottom: 3px !important; }
            .invoice-block > div { gap: 8px !important; }
            .invoice-block > div > div { padding: 8px !important; }
            .invoice-block > div > div > div { font-size: 8px !important; line-height: 1.6 !important; }
            .inv-table { margin-top: 8px !important; font-size: 7px !important; }
            .inv-table, .inv-table th, .inv-table td { border: 1px solid #000 !important; padding: 3px !important; font-size: 7px !important; }
            .inv-table thead th { background: #f3f4f6 !important; font-weight: 600 !important; }
            .summary-box { margin-top: 8px !important; gap: 8px !important; grid-template-columns: 1fr 150px !important; }
            .summary-box > div { min-height: 90px !important; }
            .summary-box > div > div:nth-child(2) { font-size: 8px !important; padding: 6px 8px !important; }
            .totals td { padding: 3px 5px !important; font-size: 8px !important; line-height: 1.3 !important; }
            .totals tr:last-child td { font-size: 9px !important; }
            .footer-note { margin-top: 10px !important; font-size: 8px !important; }
            .footer-note table { font-size: 8px !important; }
            .footer-note table td { padding: 6px !important; font-size: 8px !important; }
            .footer-note table td div { font-size: 8px !important; line-height: 1.5 !important; }
            .footer-note table td div:not(:first-child) { margin-top: 10px !important; }
            .footer-note > div:last-child { margin-top: 6px !important; }
        }
    </style>
</head>
<body>
<div class="mainwrap">
    <div class="page-title">
        <span class="material-icons" aria-hidden="true">fact_check</span>
        <div>
            <h1 style="margin:0;font-size:22px;">ใบกำกับภาษีที่บันทึก</h1>
            <p style="margin:2px 0 0;color:#6b7280;">ค้นหาและตรวจสอบใบกำกับภาษีที่บันทึกไว้</p>
        </div>
    </div>

    <div class="filters">
        <div class="filters-header">
            <span class="material-icons">date_range</span>
            <h3>เลือกช่วงวันที่</h3>
        </div>
        
        <div class="quick-dates">
            <div class="quick-dates-label">ทางลัด:</div>
            <button class="btn-quick" data-range="today">วันนี้</button>
            <button class="btn-quick" data-range="yesterday">เมื่อวาน</button>
            <button class="btn-quick" data-range="thisWeek">สัปดาห์นี้</button>
            <button class="btn-quick" data-range="lastWeek">สัปดาห์ที่แล้ว</button>
            <button class="btn-quick" data-range="thisMonth">เดือนนี้</button>
            <button class="btn-quick" data-range="lastMonth">เดือนที่แล้ว</button>
            <button class="btn-quick" data-range="thisYear">ปีนี้</button>
        </div>
        
        <div class="date-inputs">
            <div class="input-group">
                <label>
                    <span class="material-icons">event</span>
                    วันที่เริ่ม
                </label>
                <div class="input-wrapper">
                    <span class="material-icons">calendar_today</span>
                    <input type="date" id="date_from">
                </div>
            </div>
            <div class="input-group">
                <label>
                    <span class="material-icons">event</span>
                    วันที่สิ้นสุด
                </label>
                <div class="input-wrapper">
                    <span class="material-icons">calendar_today</span>
                    <input type="date" id="date_to">
                </div>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn btn-primary" id="searchBtn">
                <span class="material-icons">search</span>
                ค้นหา
            </button>
            <button class="btn btn-secondary" id="resetBtn">
                <span class="material-icons">refresh</span>
                ล้างค่า
            </button>
            <button class="btn btn-success" id="exportBtn">
                <span class="material-icons">file_download</span>
                ส่งออก Excel
            </button>
        </div>
    </div>

    <div class="card">
        <table id="resultTable">
            <thead>
                <tr>
                    <th style="width:120px;">เลขที่</th>
                    <th style="width:110px;">วันที่</th>
                    <th>ลูกค้า</th>
                    <th style="width:110px;">ช่องทาง</th>
                    <th style="width:140px;">ประเภทเอกสาร</th>
                    <th style="width:120px;" class="text-right">ยอดรวม</th>
                    <th style="width:120px;" class="text-right">ยอดชำระ</th>
                    <th style="width:180px;">จัดการ</th>
                </tr>
            </thead>
            <tbody id="resultBody">
                <tr><td colspan="9" class="no-data">กำลังโหลด...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="modal">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">รายละเอียดใบกำกับภาษี</div>
            <button class="close-btn" id="closeModal"><span class="material-icons" style="vertical-align:middle;">close</span></button>
        </div>
        <div class="detail-grid" id="detailGrid"></div>
        <table class="items-table" id="detailItems">
            <thead>
                <tr>
                    <th style="width:60px;">#</th>
                    <th>รายการ</th>
                    <th style="width:100px;" class="text-right">จำนวน</th>
                    <th style="width:100px;">หน่วย</th>
                    <th style="width:120px;" class="text-right">ราคาต่อหน่วย</th>
                    <th style="width:120px;" class="text-right">รวม</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="printModal">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div class="modal-title">พิมพ์เอกสาร</div>
            <button class="close-btn" id="closePrintModal"><span class="material-icons" style="vertical-align:middle;">close</span></button>
        </div>
        <div style="margin-bottom:10px;">
            <button class="btn btn-primary" id="triggerPrint">
                <span class="material-icons" style="vertical-align:middle;font-size:16px;">print</span> พิมพ์เอกสาร (ต้นฉบับ + สำเนา)
            </button>
        </div>
        <div id="printContent"></div>
    </div>
</div>

<!-- Hidden container for direct printing -->
<div id="hiddenPrintContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; z-index: 9999;"></div>

<!-- Tax Invoice Print Module -->
<script src="../assets/tax-invoice-print.js"></script>

<script>
// ใช้ thaiBahtText จาก tax-invoice-print.js แล้ว
// ใช้ generateInvoiceHTML จาก tax-invoice-print.js แล้ว
// ใช้ printInvoiceDirectly จาก tax-invoice-print.js แล้ว

(function(){

    const resultBody = document.getElementById('resultBody');
    const modal = document.getElementById('modal');
    const closeModal = document.getElementById('closeModal');
    const detailGrid = document.getElementById('detailGrid');
    const detailItemsBody = document.querySelector('#detailItems tbody');
    
    let currentData = []; // Store current table data for export

    function numberFmt(num){ return (num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    
    function getDocTypeName(docType) {
        const types = {
            'tax_invoice': 'ใบกำกับภาษี',
            'payment_voucher': 'ใบสำคัญจ่าย',
            'quotation': 'ใบเสนอราคา',
            'invoice': 'ใบแจ้งหนี้'
        };
        return types[docType] || 'ใบกำกับภาษี';
    }
    
    function getDocTypeBadgeColor(docType) {
        const colors = {
            'tax_invoice': 'blue',
            'payment_voucher': 'green',
            'quotation': 'orange',
            'invoice': 'purple'
        };
        return colors[docType] || 'blue';
    }

    function gatherFilters(){
        return {
            date_from: document.getElementById('date_from').value,
            date_to: document.getElementById('date_to').value
        };
    }

    async function fetchList(){
        const params = new URLSearchParams(gatherFilters());
        resultBody.innerHTML = '<tr><td colspan="9" class="no-data">กำลังโหลด...</td></tr>';
        try {
            const res = await fetch('../api/list_tax_invoices.php?' + params.toString());
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'ไม่สามารถดึงข้อมูล');
            currentData = data.data || []; // Store data for export
            renderTable(currentData);
        } catch(err){
            resultBody.innerHTML = `<tr><td colspan="9" class="no-data">เกิดข้อผิดพลาด: ${err.message}</td></tr>`;
        }
    }

    function renderTable(rows){
        if (!rows.length){
            resultBody.innerHTML = '<tr><td colspan="9" class="no-data">ไม่พบข้อมูล</td></tr>';
            return;
        }
        resultBody.innerHTML = '';
        rows.forEach(row => {
            const tr = document.createElement('tr');
            const platform = row.platform || '-';
            const docTypeName = getDocTypeName(row.doc_type);
            const badgeColor = getDocTypeBadgeColor(row.doc_type);
            tr.innerHTML = `
                <td>${row.inv_no}</td>
                <td>${row.inv_date || '-'}</td>
                <td>${row.customer_name || '-'}</td>
                <td>${platform}</td>
                <td><span class="badge ${badgeColor}">${docTypeName}</span></td>
                <td class="text-right">${numberFmt(row.grand_total)}</td>
                <td class="text-right">${numberFmt(row.payable)}</td>
                <td>
                    <button class="btn btn-secondary" data-id="${row.id}" style="padding:6px 10px;margin-right:5px;">ดู</button>
                    <button class="btn btn-primary" data-id="${row.id}" style="padding:6px 10px;" onclick="printInvoiceDirectly(${row.id})">
                        <span class="material-icons" style="font-size:14px;vertical-align:middle;">print</span> พิมพ์
                    </button>
                </td>
            `;
            tr.querySelector('button.btn-secondary').addEventListener('click', () => openDetail(row.id));
            resultBody.appendChild(tr);
        });
    }

    async function openDetail(id){
        detailGrid.innerHTML = 'กำลังโหลด...';
        detailItemsBody.innerHTML = '';
        modal.style.display = 'flex';
        try {
            const res = await fetch('../api/get_tax_invoice_detail.php?id=' + id);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'ไม่พบข้อมูล');
            const inv = data.invoice;
            document.getElementById('modalTitle').textContent = 'รายละเอียดใบกำกับภาษี #' + inv.inv_no;
            detailGrid.innerHTML = `
                <div>วันที่: <strong>${inv.inv_date || '-'}</strong></div>
                <div>เลขแท็กขาย: ${inv.sales_tag || '-'}</div>
                <div>ลูกค้า: ${inv.customer_name || '-'}</div>
                <div>ช่องทางขาย: ${inv.platform || '-'}</div>
                <div>ประเภทเอกสาร: ${getDocTypeName(inv.doc_type)}</div>
                <div>เลขผู้เสียภาษี: ${inv.customer_tax_id || '-'}</div>
                <div>ที่อยู่: ${inv.customer_address || '-'}</div>
                <div>ยอดรวม: ${numberFmt(inv.grand_total)}</div>
                <div>ยอดชำระ: ${numberFmt(inv.payable)}</div>
                <div>VAT: ${numberFmt(inv.vat)}</div>
            `;
            detailItemsBody.innerHTML = '';
            (data.items || []).forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-center">${item.item_no}</td>
                    <td>${item.item_name}</td>
                    <td class="text-right">${numberFmt(item.qty)}</td>
                    <td>${item.unit || '-'}</td>
                    <td class="text-right">${numberFmt(item.unit_price)}</td>
                    <td class="text-right">${numberFmt(item.total_price)}</td>
                `;
                detailItemsBody.appendChild(tr);
            });
        } catch(err){
            detailGrid.innerHTML = `<div style="color:#b91c1c;">เกิดข้อผิดพลาด: ${err.message}</div>`;
        }
    }

    closeModal.addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    // Print Modal Management
    const printModal = document.getElementById('printModal');
    const closePrintModal = document.getElementById('closePrintModal');
    const printContent = document.getElementById('printContent');
    const triggerPrint = document.getElementById('triggerPrint');
    
    async function openPrintModal(id) {
        // เรียกใช้ฟังก์ชันจาก tax-invoice-print.js
        await printInvoiceWithPreview(id);
    }
    
    // ใช้ generateInvoiceHTML จาก tax-invoice-print.js แล้ว
    
    triggerPrint.addEventListener('click', () => {
        const invoiceSheet = document.getElementById('invoiceSheetToPrint');
        if (!invoiceSheet) return;
        
        const clonedSheet = invoiceSheet.cloneNode(true);
        invoiceSheet.classList.add('page-break');
        clonedSheet.id = 'invoiceSheetCopy';
        
        const copyStatus = clonedSheet.querySelector('.invoice-meta > div:last-child');
        if (copyStatus) {
            copyStatus.textContent = '(สำเนา / Copy)';
        }
        
        invoiceSheet.parentNode.appendChild(clonedSheet);
        
        window.print();
        
        setTimeout(() => {
            invoiceSheet.classList.remove('page-break');
            if (clonedSheet.parentNode) {
                clonedSheet.parentNode.removeChild(clonedSheet);
            }
        }, 100);
    });
    
    closePrintModal.addEventListener('click', () => { printModal.style.display = 'none'; });
    printModal.addEventListener('click', (e) => { if (e.target === printModal) printModal.style.display = 'none'; });
    
    // ใช้ printInvoiceDirectly จาก tax-invoice-print.js แล้ว
    
    // Export to global scope
    window.openPrintModal = openPrintModal;

    // Quick date range buttons
    document.querySelectorAll('.btn-quick').forEach(btn => {
        btn.addEventListener('click', function() {
            const range = this.dataset.range;
            const today = new Date();
            let fromDate, toDate;
            
            switch(range) {
                case 'today':
                    fromDate = toDate = today;
                    break;
                case 'yesterday':
                    fromDate = toDate = new Date(today.setDate(today.getDate() - 1));
                    break;
                case 'thisWeek':
                    const firstDay = today.getDate() - today.getDay();
                    fromDate = new Date(today.setDate(firstDay));
                    toDate = new Date();
                    break;
                case 'lastWeek':
                    const lastWeekEnd = new Date(today.setDate(today.getDate() - today.getDay() - 1));
                    const lastWeekStart = new Date(lastWeekEnd);
                    lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
                    fromDate = lastWeekStart;
                    toDate = lastWeekEnd;
                    break;
                case 'thisMonth':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    toDate = new Date();
                    break;
                case 'lastMonth':
                    fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'thisYear':
                    fromDate = new Date(today.getFullYear(), 0, 1);
                    toDate = new Date();
                    break;
            }
            
            if (fromDate && toDate) {
                document.getElementById('date_from').value = formatDate(fromDate);
                document.getElementById('date_to').value = formatDate(toDate);
                fetchList();
            }
        });
    });
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Export to Excel Function
    function exportToExcel() {
        if (!currentData || currentData.length === 0) {
            alert('ไม่มีข้อมูลให้ส่งออก');
            return;
        }
        
        // Prepare data for Excel
        const excelData = currentData.map(row => {
            return {
                'เลขที่ใบกำกับภาษี': row.inv_no || '',
                'วันทัี่': row.inv_date || '',
                'ชื่อลูกค้า': row.customer_name || '',
                'ช่องทางขาย': row.platform || '',
                'ประเภทเอกสาร': getDocTypeName(row.doc_type),
                'ยอดรวม': parseFloat(row.grand_total || 0).toFixed(2),
                'ยอดชำระ': parseFloat(row.payable || 0).toFixed(2)
            };
        });
        
        // Create workbook and worksheet
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(excelData);
        
        // Set column widths
        ws['!cols'] = [
            { wch: 18 },  // เลขที่
            { wch: 12 },  // วันที่
            { wch: 30 },  // ลูกค้า
            { wch: 15 },  // ช่องทาง
            { wch: 20 },  // ประเภท
            { wch: 15 },  // ยอดรวม
            { wch: 15 }   // ยอดชำระ
        ];
        
        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, 'รายการใบกำกับภาษี');
        
        // Generate filename with current date and time
        const now = new Date();
        const filename = `รายการใบกำกับภาษี_${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}_${String(now.getHours()).padStart(2,'0')}${String(now.getMinutes()).padStart(2,'0')}.xlsx`;
        
        // Write file
        XLSX.writeFile(wb, filename, { bookType: 'xlsx', type: 'binary' });
    }
    
    document.getElementById('exportBtn').addEventListener('click', exportToExcel);

    document.getElementById('searchBtn').addEventListener('click', fetchList);
    document.getElementById('resetBtn').addEventListener('click', () => {
        ['date_from','date_to'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        fetchList();
    });

    fetchList();
})();
</script>
</body>
</html>
