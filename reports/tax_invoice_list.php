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
    <style>
        body { background: #f5f7fb; }
        .mainwrap { padding: 24px; }
        .page-title { display:flex; align-items:center; gap:12px; margin-bottom:18px; }
        .page-title .material-icons { font-size:32px; color:#385dfa; }
        .filters { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:12px 14px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px; box-shadow:0 10px 24px rgba(31,41,55,0.06); }
        .filters label { font-size:13px; color:#4b5563; margin-bottom:6px; display:block; font-weight:600; }
        .filters input, .filters select { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:10px; background:#f8fafc; font-size:13px; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
        .btn { border:none; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; font-size:13px; }
        .btn-primary { background: linear-gradient(135deg, #385dfa 0%, #4f46e5 100%); color:#fff; box-shadow:0 8px 18px rgba(56,93,250,0.25); }
        .btn-secondary { background:#fff; color:#111827; border:1px solid #d1d5db; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px; box-shadow:0 12px 30px rgba(31,41,55,0.06); margin-top:14px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; border-bottom:1px solid #e5e7eb; font-size:13px; }
        th { background:#f8fafc; text-align:left; }
        .text-right { text-align:right; }
        .badge { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:600; }
        .badge.blue { background:rgba(56,93,250,0.12); color:#1d4ed8; }
        .badge.green { background:rgba(34,197,94,0.12); color:#15803d; }
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
        <div>
            <label>ค้นหา (เลขที่/ลูกค้า/อ้างอิง)</label>
            <input type="text" id="q" placeholder="เช่น 2026-001 หรือ ชื่อลูกค้า">
        </div>
        <div>
            <label>เลขที่ใบกำกับภาษี</label>
            <input type="text" id="inv_no" placeholder="คำค้นบางส่วน">
        </div>
        <div>
            <label>ชื่อลูกค้า</label>
            <input type="text" id="customer" placeholder="ชื่อลูกค้า">
        </div>
        <div>
            <label>วันที่เริ่ม</label>
            <input type="date" id="date_from">
        </div>
        <div>
            <label>วันที่สิ้นสุด</label>
            <input type="date" id="date_to">
        </div>
        <div>
            <label>ช่องทางขาย</label>
            <select id="platform">
                <option value="">ทั้งหมด</option>
                <option value="Shopee">Shopee</option>
                <option value="Lazada">Lazada</option>
                <option value="หน้าร้าน">หน้าร้าน</option>
                <option value="อื่นๆ">อื่นๆ</option>
            </select>
        </div>
        <div class="actions">
            <button class="btn btn-primary" id="searchBtn">ค้นหา</button>
            <button class="btn btn-secondary" id="resetBtn">ล้างค่า</button>
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

    function gatherFilters(){
        return {
            q: document.getElementById('q').value.trim(),
            inv_no: document.getElementById('inv_no').value.trim(),
            customer: document.getElementById('customer').value.trim(),
            date_from: document.getElementById('date_from').value,
            date_to: document.getElementById('date_to').value,
            platform: document.getElementById('platform').value
        };
    }

    async function fetchList(){
        const params = new URLSearchParams(gatherFilters());
        resultBody.innerHTML = '<tr><td colspan="9" class="no-data">กำลังโหลด...</td></tr>';
        try {
            const res = await fetch('../api/list_tax_invoices.php?' + params.toString());
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'ไม่สามารถดึงข้อมูล');
            renderTable(data.data || []);
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
            tr.innerHTML = `
                <td>${row.inv_no}</td>
                <td>${row.inv_date || '-'}</td>
                <td>${row.customer_name || '-'}</td>
                <td>${platform}</td>
                <td><span class="badge blue">${docTypeName}</span></td>
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

    document.getElementById('searchBtn').addEventListener('click', fetchList);
    document.getElementById('resetBtn').addEventListener('click', () => {
        ['q','inv_no','customer','date_from','date_to','platform'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        fetchList();
    });

    fetchList();
})();
</script>
</body>
</html>
