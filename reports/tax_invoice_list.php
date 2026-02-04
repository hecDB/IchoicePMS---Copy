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
        <div>
            <label>วิธีชำระเงิน</label>
            <select id="payment_method">
                <option value="">ทั้งหมด</option>
                <option value="cash">เงินสด</option>
                <option value="transfer">เงินโอน</option>
                <option value="shopee">Shopee</option>
                <option value="lazada">Lazada</option>
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
                    <th style="width:120px;">ชำระเงิน</th>
                    <th style="width:120px;" class="text-right">ยอดรวม</th>
                    <th style="width:120px;" class="text-right">ยอดชำระ</th>
                    <th style="width:100px;">ดูรายละเอียด</th>
                </tr>
            </thead>
            <tbody id="resultBody">
                <tr><td colspan="8" class="no-data">กำลังโหลด...</td></tr>
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

<script>
(function(){
    const resultBody = document.getElementById('resultBody');
    const modal = document.getElementById('modal');
    const closeModal = document.getElementById('closeModal');
    const detailGrid = document.getElementById('detailGrid');
    const detailItemsBody = document.querySelector('#detailItems tbody');

    function numberFmt(num){ return (num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function gatherFilters(){
        return {
            q: document.getElementById('q').value.trim(),
            inv_no: document.getElementById('inv_no').value.trim(),
            customer: document.getElementById('customer').value.trim(),
            date_from: document.getElementById('date_from').value,
            date_to: document.getElementById('date_to').value,
            platform: document.getElementById('platform').value,
            payment_method: document.getElementById('payment_method').value
        };
    }

    async function fetchList(){
        const params = new URLSearchParams(gatherFilters());
        resultBody.innerHTML = '<tr><td colspan="8" class="no-data">กำลังโหลด...</td></tr>';
        try {
            const res = await fetch('../api/list_tax_invoices.php?' + params.toString());
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'ไม่สามารถดึงข้อมูล');
            renderTable(data.data || []);
        } catch(err){
            resultBody.innerHTML = `<tr><td colspan="8" class="no-data">เกิดข้อผิดพลาด: ${err.message}</td></tr>`;
        }
    }

    function renderTable(rows){
        if (!rows.length){
            resultBody.innerHTML = '<tr><td colspan="8" class="no-data">ไม่พบข้อมูล</td></tr>';
            return;
        }
        resultBody.innerHTML = '';
        rows.forEach(row => {
            const tr = document.createElement('tr');
            const payBadge = row.payment_method ? `<span class="badge green">${row.payment_method}</span>` : '-';
            const platform = row.platform || '-';
            tr.innerHTML = `
                <td>${row.inv_no}</td>
                <td>${row.inv_date || '-'}</td>
                <td>${row.customer || '-'}</td>
                <td>${platform}</td>
                <td>${payBadge}</td>
                <td class="text-right">${numberFmt(row.grand_total)}</td>
                <td class="text-right">${numberFmt(row.payable)}</td>
                <td><button class="btn btn-secondary" data-id="${row.id}" style="padding:6px 10px;">ดู</button></td>
            `;
            tr.querySelector('button').addEventListener('click', () => openDetail(row.id));
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
                <div>เลขอ้างอิง: ${inv.ref_no || '-'}</div>
                <div>ลูกค้า: ${inv.customer || '-'}</div>
                <div>ช่องทางขาย: ${inv.platform || '-'}</div>
                <div>วิธีชำระเงิน: ${inv.payment_method || '-'}</div>
                <div>เลขผู้เสียภาษี: ${inv.tax_id || '-'}</div>
                <div>สาขา: ${inv.branch || '-'}</div>
                <div>ที่อยู่: ${inv.address || '-'}</div>
                <div>ยอดรวม: ${numberFmt(inv.grand_total)}</div>
                <div>ยอดชำระ: ${numberFmt(inv.payable)}</div>
                <div>VAT: ${numberFmt(inv.vat)}</div>
            `;
            detailItemsBody.innerHTML = '';
            (data.items || []).forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-center">${item.item_no}</td>
                    <td>${item.name}</td>
                    <td class="text-right">${numberFmt(item.qty)}</td>
                    <td>${item.unit || '-'}</td>
                    <td class="text-right">${numberFmt(item.price)}</td>
                    <td class="text-right">${numberFmt(item.line_total)}</td>
                `;
                detailItemsBody.appendChild(tr);
            });
        } catch(err){
            detailGrid.innerHTML = `<div style="color:#b91c1c;">เกิดข้อผิดพลาด: ${err.message}</div>`;
        }
    }

    closeModal.addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    document.getElementById('searchBtn').addEventListener('click', fetchList);
    document.getElementById('resetBtn').addEventListener('click', () => {
        ['q','inv_no','customer','date_from','date_to','platform','payment_method'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        fetchList();
    });

    fetchList();
})();
</script>
</body>
</html>
