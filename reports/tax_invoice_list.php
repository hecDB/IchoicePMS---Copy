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
        .invoice-sheet { background:#fff; color:#000; padding:24px; border:1px solid #d1d5db; min-height:1000px; }
        .invoice-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #111; padding-bottom:10px; }
        .invoice-brand { display:flex; align-items:center; gap:10px; }
        .invoice-brand h2 { margin:0; font-size:18px; }
        .invoice-meta { text-align:right; font-size:12px; }
        .invoice-block { margin-top:12px; font-size:13px; }
        .invoice-block h4 { margin:0 0 6px; font-size:13px; }
        .inv-table { width:100%; border-collapse:collapse; margin-top:8px; font-size:12.5px; border:1px solid #000; }
        .inv-table th, .inv-table td { border:1px solid #000; padding:6px; box-sizing:border-box; }
        .inv-table th { background:#f3f4f6; }
        .summary-box { margin-top:10px; display:grid; grid-template-columns:1fr 220px; gap:12px; }
        .totals { border:1px solid #000; }
        .totals td { padding:4px 6px; font-size:12.5px; line-height:1.3; }
        .footer-note { margin-top:18px; font-size:12px; }
        
        @media print {
            .invoice-sheet.page-break { page-break-after: always; }
            @page { size: A5; margin: 8mm; }
            body { background: #fff; }
            .mainwrap, .card, .controls, .filters { display: none !important; }
            .modal-backdrop { background: #fff !important; }
            .modal { max-width: 100% !important; box-shadow: none !important; border-radius: 0 !important; padding: 0 !important; }
            .modal-header { display: none !important; }
            .sidebar, .sidebar-backdrop, .mobile-nav-toggle { display: none !important; }
            .invoice-sheet { border: none; padding: 8px !important; font-size: 8px !important; min-height: auto !important; }
            .invoice-header { padding-bottom: 6px !important; }
            .invoice-brand h2 { font-size: 11px !important; }
            .invoice-brand > div > div { font-size: 7px !important; line-height: 1.3 !important; }
            .invoice-brand > div:first-child { width: 40px !important; height: 40px !important; }
            .invoice-meta { font-size: 8px !important; }
            .invoice-meta > div:first-child { font-size: 10px !important; }
            .invoice-meta > div:nth-child(2) { font-size: 9px !important; }
            .invoice-meta > div:last-child { font-size: 8px !important; }
            .invoice-block { margin-top: 6px !important; font-size: 7px !important; }
            .invoice-block h4 { font-size: 8px !important; margin-bottom: 4px !important; padding-bottom: 3px !important; }
            .invoice-block > div { gap: 8px !important; }
            .invoice-block > div > div { padding: 6px !important; }
            .invoice-block > div > div > div { font-size: 7px !important; line-height: 1.5 !important; }
            .inv-table { margin-top: 6px !important; font-size: 7px !important; }
            .inv-table, .inv-table th, .inv-table td { border: 1px solid #000 !important; padding: 3px 4px !important; }
            .inv-table thead th { background: #f3f4f6 !important; }
            .summary-box { margin-top: 6px !important; gap: 6px !important; grid-template-columns: 1fr 140px !important; }
            .summary-box > div { min-height: 100px !important; }
            .summary-box > div > div:nth-child(2) { font-size: 7px !important; padding: 4px 6px !important; }
            .totals td { padding: 2px 4px !important; font-size: 7px !important; line-height: 1.2 !important; }
            .totals tr:last-child td { font-size: 8px !important; }
            .footer-note { margin-top: 8px !important; font-size: 7px !important; }
            .footer-note table { font-size: 7px !important; }
            .footer-note table td { padding: 4px !important; font-size: 7px !important; }
            .footer-note table td div { font-size: 7px !important; line-height: 1.4 !important; }
            .footer-note table td div:not(:first-child) { margin-top: 8px !important; }
            .footer-note > div:last-child { margin-top: 4px !important; }
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

<script>
function thaiBahtText(amount) {
    const numText = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    const unitText = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

    function readNumber(num) {
        let result = '';
        const s = num.toString();
        for (let i = 0; i < s.length; i++) {
            const digit = parseInt(s.charAt(i), 10);
            const pos = s.length - i - 1;
            if (digit === 0) continue;
            if (pos === 0 && digit === 1 && s.length > 1) result += 'เอ็ด';
            else if (pos === 1 && digit === 2) result += 'ยี่';
            else if (pos === 1 && digit === 1) result += '';
            else result += numText[digit];
            result += unitText[pos];
        }
        return result || numText[0];
    }

    function readMillion(num) {
        if (num === 0) return numText[0];
        let result = '';
        const millions = Math.floor(num / 1000000);
        const rest = num % 1000000;
        if (millions > 0) result += readMillion(millions) + 'ล้าน';
        if (rest > 0) result += readNumber(rest);
        return result;
    }

    const amt = Math.max(0, Number(amount) || 0);
    const integerPart = Math.floor(amt);
    const satang = Math.round((amt - integerPart) * 100);
    let text = readMillion(integerPart) + 'บาท';
    if (satang === 0) text += 'ถ้วน';
    else text += readMillion(satang) + 'สตางค์';
    return text;
}

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
                    <button class="btn btn-primary" data-id="${row.id}" style="padding:6px 10px;" onclick="openPrintModal(${row.id})">
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
        printContent.innerHTML = '<div style="text-align:center;padding:20px;">กำลังโหลดข้อมูล...</div>';
        printModal.style.display = 'flex';
        
        try {
            const res = await fetch('../api/get_tax_invoice_detail.php?id=' + id);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'ไม่พบข้อมูล');
            
            const inv = data.invoice;
            const items = data.items || [];
            
            // สร้างเอกสาร
            const invoiceHTML = generateInvoiceHTML(inv, items);
            printContent.innerHTML = invoiceHTML;
            
        } catch (err) {
            printContent.innerHTML = `<div style="color:#b91c1c;padding:20px;text-align:center;">เกิดข้อผิดพลาด: ${err.message}</div>`;
        }
    }
    
    function generateInvoiceHTML(inv, items) {
        const docTypeLabels = {
            tax_invoice: { titleTh: 'ใบกำกับภาษี/ใบเสร็จรับเงิน', titleEn: 'TAX INVOICE' },
            payment_voucher: { titleTh: 'ใบสำคัญจ่าย', titleEn: 'PAYMENT VOUCHER' },
            quotation: { titleTh: 'ใบเสนอราคา', titleEn: 'QUOTATION' },
            invoice: { titleTh: 'ใบแจ้งหนี้', titleEn: 'INVOICE' }
        };
        
        const docInfo = docTypeLabels[inv.doc_type] || docTypeLabels.tax_invoice;
        
        // แปลงวันที่เป็น พ.ศ.
        let formattedDate = '____/____/______';
        if (inv.inv_date) {
            const dateObj = new Date(inv.inv_date);
            const day = String(dateObj.getDate()).padStart(2, '0');
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const year = dateObj.getFullYear() + 543;
            formattedDate = `${day}/${month}/${year}`;
        }
        
        let itemsHTML = '';
        items.forEach((item, idx) => {
            itemsHTML += `
                <tr>
                    <td class="text-center">${idx + 1}</td>
                    <td>${item.item_name}</td>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-center">${item.unit}</td>
                    <td class="text-right">${numberFmt(item.unit_price)}</td>
                    <td class="text-right">${numberFmt(item.total_price)}</td>
                </tr>
            `;
        });
        
        return `
        <div class="invoice-sheet" id="invoiceSheetToPrint">
            <div class="invoice-header">
                <div class="invoice-brand">
                    <div style="width:70px;height:70px;border:1px solid #000;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                        <img src="../images/Ichoices.png" alt="ICHOICE Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                    </div>
                    <div>
                        <h2 style="margin:0;">บริษัท ไอช้อยซ์ จำกัด</h2>
                        <div style="font-size:12px;line-height:1.4;">
                            ICHOICE CO., LTD.<br>
                            สำนักงานใหญ่ : 422/29 ถนนจันทรลาภ ต.ช้างคลาน อ.เมือง จ.เชียงใหม่ 50100<br>
                            เลขประจำตัวผู้เสียภาษี 0505564015873
                        </div>
                    </div>
                </div>
                <div class="invoice-meta">
                    <div style="font-weight:700;font-size:16px;">${docInfo.titleTh}</div>
                    <div style="font-weight:700;font-size:15px;margin-top:2px;">${docInfo.titleEn}</div>
                    <div style="margin-top:6px;font-size:13px;font-weight:600;">(ต้นฉบับ / Original)</div>
                </div>
            </div>

            <div class="invoice-block">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                    <div style="border:1px solid #d1d5db;padding:12px;border-radius:8px;background:#fafafa;">
                        <h4 style="margin:0 0 10px;border-bottom:1px solid #d1d5db;padding-bottom:6px;font-size:13px;">ข้อมูลลูกค้า</h4>
                        <div style="font-size:12px;line-height:1.8;">
                            <div><strong>ลูกค้า:</strong> ${inv.customer_name || '-'}</div>
                            <div><strong>ที่อยู่:</strong> ${inv.customer_address || '-'}</div>
                            <div><strong>เลขผู้เสียภาษี:</strong> ${inv.customer_tax_id || '-'}</div>
                        </div>
                    </div>
                    <div style="border:1px solid #d1d5db;padding:12px;border-radius:8px;background:#fafafa;">
                        <h4 style="margin:0 0 10px;border-bottom:1px solid #d1d5db;padding-bottom:6px;font-size:13px;">ข้อมูลเอกสาร</h4>
                        <div style="font-size:12px;line-height:1.8;">
                            <div><strong>เลขที่:</strong> ${inv.inv_no}</div>
                            <div><strong>เลขแท็กขาย:</strong> ${inv.sales_tag || '-'}</div>
                            <div><strong>วันที่ออกบิล:</strong> ${inv.inv_date || '-'}</div>
                            <div><strong>ช่องทางสั่งซื้อ:</strong> ${inv.platform || '-'}</div>
                        </div>
                    </div>
                </div>
            </div>

            <table class="inv-table" style="margin-top:8px;">
                <thead>
                    <tr>
                        <th style="width:6%;" class="text-center">ลำดับ</th>
                        <th style="width:44%;">รายการ</th>
                        <th style="width:12%;" class="text-center">จำนวน</th>
                        <th style="width:12%;" class="text-center">หน่วย</th>
                        <th style="width:13%;" class="text-right">ราคาต่อหน่วย</th>
                        <th style="width:13%;" class="text-right">จำนวนเงิน</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>

            <div class="summary-box">
                <div style="border:1px solid #000; padding:0; min-height:180px; display:flex; flex-direction:column;">
                    <div style="flex:2;"></div>
                    <div style="font-weight:700; padding:8px 10px; background:#e0e0e0; font-size:12px; line-height:1.4;">
                        ตัวอักษร : ${inv.amount_text || thaiBahtText(inv.payable)}
                    </div>
                    <div style="flex:1;"></div>
                </div>
                <table class="totals" style="width:100%;">
                    <tr>
                        <td>รวมเงิน<br>SUB TOTAL</td>
                        <td class="text-right">${numberFmt(inv.subtotal)}</td>
                    </tr>
                    <tr>
                        <td>หักส่วนลด<br>DISCOUNT</td>
                        <td class="text-right">${numberFmt(inv.discount)}</td>
                    </tr>
                    <tr>
                        <td>มูลค่าก่อนภาษี<br>BEFORE VAT</td>
                        <td class="text-right">${numberFmt(inv.before_vat)}</td>
                    </tr>
                    <tr>
                        <td>ภาษีมูลค่าเพิ่ม<br>VAT</td>
                        <td class="text-right">${numberFmt(inv.vat)}</td>
                    </tr>
                    <tr style="background:#e0e0e0;">
                        <td style="font-weight:700; padding:8px 10px;">รวมทั้งสิ้น<br>GRAND TOTAL</td>
                        <td class="text-right" style="font-weight:700;">${numberFmt(inv.grand_total)}</td>
                    </tr>
                    <tr>
                        <td>ส่วนลดอื่น<br>OTHERS DISCOUNTS</td>
                        <td class="text-right">${numberFmt(inv.special_discount)}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:700; font-size:14px;">จำนวนเงินที่ชำระ<br>ACTUAL PAYMENT</td>
                        <td class="text-right" style="font-weight:700; font-size:14px;">${numberFmt(inv.payable)}</td>
                    </tr>
                </table>
            </div>

            <div class="footer-note">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="border:1px solid #000; padding:10px; width:55%; vertical-align:top;">
                            <div style="font-weight:700;">ผู้รับสินค้า/บริการ</div>
                            <div style="margin-top:22px;">ลงชื่อ: ________________</div>
                            <div>วันที่: ____/____/______</div>
                        </td>
                        <td style="border:1px solid #000; padding:10px; width:45%; vertical-align:top;">
                            <div style="font-weight:700;">ผู้รับเงิน</div>
                            <div style="margin-top:22px;">ลงชื่อ: ________________</div>
                            <div>วันที่: ${formattedDate}</div>
                        </td>
                    </tr>
                </table>
                <div style="margin-top:8px;">เอกสารนี้ออกโดยระบบ IchoicePMS</div>
            </div>
        </div>
        `;
    }
    
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
