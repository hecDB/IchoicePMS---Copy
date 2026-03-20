<?php
session_start();
require_once '../config/db_connect.php';
include '../templates/sidebar.php';

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบกำกับภาษี - IchoicePMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="../assets/base.css">
    <link rel="stylesheet" href="../assets/sidebar.css">
    <link rel="stylesheet" href="../assets/components.css">
    <link rel="stylesheet" href="../assets/mainwrap-modern.css">
    <style>
        body { background: #f5f7fb; }
        .mainwrap { padding: 28px; }
        .page-title { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .page-title .material-icons { font-size: 32px; color: #385dfa; }
        .layout { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 18px; }
        @media (max-width: 1100px) { .layout { grid-template-columns: 1fr; } }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px 20px; box-shadow: 0 10px 30px rgba(31,41,55,0.06); }
        .card h3 { margin: 0 0 10px; font-size: 18px; color: #111827; }
        .field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 14px; margin-bottom: 12px; }
        .field label { display: block; font-size: 13px; color: #4b5563; margin-bottom: 6px; font-weight: 600; }
        .field input, .field textarea, .field select { width: 100%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 14px; background: #f8fafc; }
        .field textarea { min-height: 70px; }
        .item-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .item-table th, .item-table td { border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 13px; }
        .item-table th { background: #f8fafc; text-align: left; }
        .item-table input { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; font-size: 13px; background: #fff; }
        .controls { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        .btn { border: none; border-radius: 10px; padding: 11px 16px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .btn-primary { background: linear-gradient(135deg, #385dfa 0%, #4f46e5 100%); color: #fff; box-shadow: 0 8px 18px rgba(56,93,250,0.25); }
        .btn-secondary { background: #fff; color: #111827; border: 1px solid #d1d5db; }
        .btn-danger { background: #ef4444; color: #fff; }
        .preview-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 12px; }
        .invoice-sheet { background: #fff; color: #000; padding: 24px; border: 1px solid #d1d5db; min-height: 1000px; }
        .invoice-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111; padding-bottom: 10px; }
        @media print {
            .invoice-sheet.page-break { page-break-after: always; }
        }
        .invoice-brand { display: flex; align-items: center; gap: 10px; }
        .invoice-brand h2 { margin: 0; font-size: 18px; }
        .invoice-meta { text-align: right; font-size: 12px; }
        .invoice-block { margin-top: 12px; font-size: 13px; }
        .invoice-block h4 { margin: 0 0 6px; font-size: 13px; }
        .inv-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12.5px; border: 1px solid #000; }
        .inv-table th, .inv-table td { border: 1px solid #000; padding: 6px; box-sizing: border-box; }
        .inv-table thead th { border: 1px solid #000; }
        .inv-table thead { border-bottom: 1px solid #000; }
        .inv-table th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .mt-8 { margin-top: 8px; }
        .summary-box { margin-top: 10px; display: grid; grid-template-columns: 1fr 220px; gap: 12px; }
        .totals { border: 1px solid #000; }
        .totals td { padding: 4px 6px; font-size: 12.5px; line-height: 1.3; }
        .totals tr[style*="background"] td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .footer-note { margin-top: 18px; font-size: 12px; }
        .pay-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .pay-table td { padding: 4px 6px; border-bottom: 1px solid #d1d5db; }
        .pay-status { width: 46px; font-weight: 700; }
        .pay-status.is-true { color: #16a34a; }
        .toast { position: fixed; bottom: 24px; right: 24px; background: #111827; color: #fff; padding: 12px 16px; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,0.18); display: flex; align-items: center; gap: 10px; font-size: 13px; opacity: 0; transform: translateY(8px); transition: opacity 0.2s ease, transform 0.2s ease; z-index: 2000; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: linear-gradient(135deg, #16a34a, #0f9f57); }
        .toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); }
        @media print {
            @page { size: A5; margin: 8mm; }
            body { background: #fff; }
            .mainwrap, .card, .controls { padding: 0; box-shadow: none; }
            .page-title { display: none; }
            .layout { display: block; }
            .card-form { display: none; }
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
            .inv-table { margin-top: 6px !important; font-size: 7px !important; border-collapse: collapse !important; border-spacing: 0 !important; }
            .inv-table, .inv-table th, .inv-table td, .inv-table thead th { border: 1px solid #000 !important; box-sizing: border-box; padding: 3px 4px !important; }
            .inv-table thead { border-bottom: 1px solid #000 !important; }
            .inv-table thead th { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
        <span class="material-icons" aria-hidden="true">description</span>
        <div>
            <h1 style="margin:0;font-size:22px;">ใบกำกับภาษี</h1>
            <p style="margin:2px 0 0;color:#6b7280;">กรอกข้อมูลและพิมพ์ใบกำกับภาษีตามตัวอย่าง</p>
        </div>
    </div>

    <div class="layout">
        <div class="card card-form">
            <h3>กรอกข้อมูล</h3>
            <div class="field-grid">
                <div class="field">
                    <label for="doc_type">ปรุะเภทเอกสาร</label>
                    <select id="doc_type">
                        <option value="tax_invoice">ใบกำกับภาษี</option>
                        <option value="payment_voucher">ใบสำคัญจ่าย</option>
                        <option value="quotation">ใบเสนอราคา</option>
                        <option value="invoice">ใบแจ้งหนี้</option>
                    </select>
                </div>
                <div class="field">
                    <label for="inv_no"><span id="doc_no_label">เลขที่ใบกำกับภาษี</span></label>
                    <input id="inv_no" type="text" value="202601-001" autocomplete="off">
                </div>
                <div class="field">
                    <label for="sales_tag">เลขแท็กรายการขายสินค้า</label>
                    <input id="sales_tag" type="text" placeholder="เช่น TAG-001" autocomplete="off">
                </div>
                <div class="field">
                    <label for="inv_date">วันที่ออกบิล</label>
                    <input id="inv_date" type="date" value="<?= htmlspecialchars($today) ?>">
                </div>
                <div class="field">
                    <label for="platform">ช่องทางการสั่งซื้อ</label>
                    <select id="platform">
                        <option value="">เลือกช่องทาง</option>
                        <option value="Shopee">Shopee</option>
                        <option value="Lazada">Lazada</option>
                        <option value="Tiktok">Tiktok</option>
                        <option value="อื่นๆ">อื่นๆ (โปรดระบุ)</option>
                    </select>
                </div>
                <div class="field" id="platform_other_field" style="display:none;">
                    <label for="platform_other">ระบุช่องทาง</label>
                    <input id="platform_other" type="text" placeholder="ระบุช่องทาง...">
                </div>
            </div>

            <div class="field-grid">
                <div class="field" style="grid-column: span 2;">
                    <label for="customer">ชื่อลูกค้า/บริษัท</label>
                    <input id="customer" type="text" placeholder="ชื่อลูกค้า/บริษัท">
                </div>
                <div class="field">
                    <label for="tax_id">เลขประจำตัวผู้เสียภาษี</label>
                    <input id="tax_id" type="text" placeholder="13 หลัก">
                </div>
                <div class="field" style="grid-column: span 2;">
                    <label for="address">ที่อยู่</label>
                    <textarea id="address" placeholder="ที่อยู่ผู้ซื้อ"></textarea>
                </div>
            </div>

            <h3 style="margin-top:14px;">สินค้า/บริการ</h3>
            <table class="item-table" id="itemTable">
                <thead>
                    <tr>
                        <th style="width:34%;">รายละเอียด</th>
                        <th style="width:14%;">จำนวน</th>
                        <th style="width:14%;">หน่วย</th>
                        <th style="width:18%;">ราคาต่อหน่วย</th>
                        <th style="width:16%;">รวม</th>
                        <th style="width:8%;" class="text-center">ลบ</th>
                    </tr>
                </thead>
                <tbody id="itemBody"></tbody>
            </table>
            <div class="controls">
                <button type="button" class="btn btn-secondary" id="addRowBtn"><span class="material-icons" style="font-size:16px;">add</span> เพิ่มรายการ</button>
                <div class="field" style="margin-left:auto; max-width:140px;">
                    <label for="discount">ส่วนลด (รวม)</label>
                    <input id="discount" type="number" min="0" step="0.01" value="0">
                </div>
                <div class="field" style="max-width:140px;">
                    <label for="shipping">ค่าจัดส่ง</label>
                    <input id="shipping" type="number" min="0" step="0.01" value="0">
                </div>
                <div class="field" style="max-width:140px;">
                    <label for="special_discount">ส่วนลดพิเศษ</label>
                    <input id="special_discount" type="number" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="controls">
                <button type="button" class="btn btn-primary" id="previewBtn">อัปเดตตัวอย่าง</button>
                <button type="button" class="btn btn-primary" id="saveBtn">บันทึกข้อมูล</button>
                <button type="button" class="btn btn-secondary" id="printBtn">พิมพ์ใบกำกับภาษี</button>
            </div>
        </div>

        <div class="preview-wrap">
            <div class="invoice-sheet" id="invoiceSheet">
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
                        <div style="font-weight:700;font-size:16px;" id="pv_doc_title_th">ใบกำกับภาษี/ใบเสร็จรับเงิน</div>
                        <div style="font-weight:700;font-size:15px;margin-top:2px;" id="pv_doc_title_en">TAX INVOICE</div>
                        <div style="margin-top:6px;font-size:13px;font-weight:600;" id="pv_doc_status">(ต้นฉบับ / Original)</div>
                    </div>
                </div>

                <div class="invoice-block">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                        <!-- บล็อกซ้าย -->
                        <div style="border:1px solid #d1d5db;padding:12px;border-radius:8px;background:#fafafa;">
                            <h4 style="margin:0 0 10px;border-bottom:1px solid #d1d5db;padding-bottom:6px;font-size:13px;">ข้อมูลลูกค้า</h4>
                            <div style="font-size:12px;line-height:1.8;">
                                <div><strong>ลูกค้า:</strong> <span id="pv_customer">-</span></div>
                                <div><strong>ที่อยู่:</strong> <span id="pv_address">-</span></div>
                                <div><strong>เลขผู้เสียภาษี:</strong> <span id="pv_tax_id">-</span></div>
                            </div>
                        </div>
                        <!-- บล็อกขวา -->
                        <div style="border:1px solid #d1d5db;padding:12px;border-radius:8px;background:#fafafa;">
                            <h4 style="margin:0 0 10px;border-bottom:1px solid #d1d5db;padding-bottom:6px;font-size:13px;">ข้อมูลเอกสาร</h4>
                            <div style="font-size:12px;line-height:1.8;">
                                <div><strong><span id="pv_doc_no_label">เลขที่:</span></strong> <span id="pv_inv_no">-</span></div>
                                <div><strong>เลขแท็กขาย:</strong> <span id="pv_sales_tag">-</span></div>
                                <div><strong>วันที่ออกบิล:</strong> <span id="pv_inv_date">-</span></div>
                                <div><strong>ช่องทางสั่งซื้อ:</strong> <span id="pv_platform">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="inv-table mt-8" id="pv_items">
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
                    <tbody id="pv_items_body">
                        <tr><td class="text-center" colspan="6" style="padding:10px;">กรอกสินค้าในแบบฟอร์มด้านซ้าย</td></tr>
                    </tbody>
                </table>

                <div class="summary-box">
                    <div style="border:1px solid #000; padding:0; min-height:180px; display:flex; flex-direction:column;">
                        <div style="flex:2;"></div>
                        <div style="font-weight:700; padding:8px 10px; background:#e0e0e0; font-size:12px; line-height:1.4;">
                            ตัวอักษร : <span id="pv_amount_text">-</span>
                        </div>
                        <div style="flex:1;"></div>
                    </div>
                    <table class="totals" style="width:100%;">
                        <tr>
                            <td>รวมเงิน<br>SUB TOTAL</td>
                            <td class="text-right" id="pv_subtotal">0.00</td>
                        </tr>
                        <tr>
                            <td>หักส่วนลด<br>DISCOUNT</td>
                            <td class="text-right" id="pv_discount">0.00</td>
                        </tr>
                        <tr>
                            <td>มูลค่าก่อนภาษี<br>BEFORE VAT</td>
                            <td class="text-right" id="pv_before_vat">0.00</td>
                        </tr>
                        <tr>
                            <td>ภาษีมูลค่าเพิ่ม<br>VAT</td>
                            <td class="text-right" id="pv_vat">0.00</td>
                        </tr>
                        <tr style="background:#e0e0e0;">
                            <td style="font-weight:700; padding:8px 10px;">รวมทั้งสิ้น<br>GRAND TOTAL</td>
                            <td class="text-right" style="font-weight:700;" id="pv_grand">0.00</td>
                        </tr>
                        <tr>
                            <td>ส่วนลดอื่น<br>OTHERS DISCOUNTS</td>
                            <td class="text-right" id="pv_special_discount">0.00</td>
                        </tr>
                        <tr>
                            <td style="font-weight:700; font-size:14px;">จำนวนเงินที่ชำระ<br>ACTUAL PAYMENT</td>
                            <td class="text-right" style="font-weight:700; font-size:14px;" id="pv_payable">0.00</td>
                        </tr>
                    </table>
                </div>

                <div class="footer-note">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="border:1px solid #000; padding:10px; width:55%; vertical-align:top;">
                                <div style="font-weight:700;">ผู้รับสินค้า/บริการ</div>
                                <div style="margin-top:22px;">ลงชื่อ: <span id="pv_sign_receiver">________________</span></div>
                                <div>วันที่: <span id="pv_sign_receiver_date">____/____/______</span></div>
                            </td>
                            <td style="border:1px solid #000; padding:10px; width:45%; vertical-align:top;">
                                <div style="font-weight:700;">ผู้รับเงิน</div>
                                <div style="margin-top:22px;">ลงชื่อ: <span id="pv_sign_payer">________________</span></div>
                                <div>วันที่: <span id="pv_sign_payer_date">____/____/______</span></div>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top:8px;">เอกสารนี้ออกโดยระบบ IchoicePMS</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const itemBody = document.getElementById('itemBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const previewBtn = document.getElementById('previewBtn');
    const printBtn = document.getElementById('printBtn');
    const saveBtn = document.getElementById('saveBtn');
    const docTypeSelect = document.getElementById('doc_type');
    const docNoLabel = document.getElementById('doc_no_label');
    
    const docTypeLabels = {
        tax_invoice: { titleTh: 'ใบกำกับภาษี/ใบเสร็จรับเงิน', titleEn: 'TAX INVOICE', label: 'เลขที่ใบกำกับภาษี', docNoLabel: 'เลขที่ใบกำกับภาษี:' },
        payment_voucher: { titleTh: 'ใบสำคัญจ่าย', titleEn: 'PAYMENT VOUCHER', label: 'เลขที่ใบสำคัญจ่าย', docNoLabel: 'เลขที่ใบสำคัญจ่าย:' },
        quotation: { titleTh: 'ใบเสนอราคา', titleEn: 'QUOTATION', label: 'เลขที่ใบเสนอราคา', docNoLabel: 'เลขที่ใบเสนอราคา:' },
        invoice: { titleTh: 'ใบแจ้งหนี้', titleEn: 'INVOICE', label: 'เลขที่ใบแจ้งหนี้', docNoLabel: 'เลขที่ใบแจ้งหนี้:' }
    };

    function addRow(data = {}) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="item-name" placeholder="รายละเอียด" value="${data.name || ''}"></td>
            <td><input type="number" class="item-qty" min="0" step="0.01" value="${data.qty || 1}"></td>
            <td><input type="text" class="item-unit" value="${data.unit || 'ชิ้น'}"></td>
            <td><input type="number" class="item-price" min="0" step="0.01" value="${data.price || 0}"></td>
            <td><input type="number" class="item-total" min="0" step="0.01" value="${data.total || 0}" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-danger" style="padding:6px 10px;" aria-label="ลบแถว">x</button></td>
        `;
        itemBody.appendChild(tr);
        recalcRow(tr);
    }

    function recalcRow(tr) {
        const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
        const price = parseFloat(tr.querySelector('.item-price').value) || 0;
        const total = qty * price;
        tr.querySelector('.item-total').value = total.toFixed(2);
    }

    function bindRowEvents() {
        itemBody.querySelectorAll('tr').forEach(tr => {
            tr.querySelectorAll('.item-qty, .item-price').forEach(inp => {
                inp.addEventListener('input', () => recalcRow(tr));
            });
            const del = tr.querySelector('button.btn-danger');
            del.addEventListener('click', () => { tr.remove(); updatePreview(); });
        });
    }

    function numberFmt(num) { return (num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        const icon = type === 'success' ? 'check_circle' : 'error';
        toast.innerHTML = `<span class="material-icons" style="font-size:18px;">${icon}</span><span>${message}</span>`;
        document.body.appendChild(toast);
        // trigger transition
        void toast.offsetWidth;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 250);
        }, 2500);
    }

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
                if (pos === 0 && digit === 1 && s.length > 1) {
                    result += 'เอ็ด';
                } else if (pos === 1 && digit === 2) {
                    result += 'ยี่';
                } else if (pos === 1 && digit === 1) {
                    result += '';
                } else {
                    result += numText[digit];
                }
                result += unitText[pos];
            }
            return result || numText[0];
        }

        function readMillion(num) {
            if (num === 0) return numText[0];
            let result = '';
            let rest = num;
            const millions = Math.floor(rest / 1000000);
            if (millions > 0) {
                result += readMillion(millions) + 'ล้าน';
                rest = rest % 1000000;
            }
            if (rest > 0) {
                result += readNumber(rest);
            }
            return result;
        }

        const amt = Math.max(0, Number(amount) || 0);
        const integerPart = Math.floor(amt);
        const satang = Math.round((amt - integerPart) * 100);

        let text = readMillion(integerPart) + 'บาท';
        if (satang === 0) {
            text += 'ถ้วน';
        } else {
            text += readMillion(satang) + 'สตางค์';
        }
        return text;
    }

    function updatePreview() {
        const byIdValue = (id, fallback = '-') => {
            const el = document.getElementById(id);
            return el ? (el.value || fallback) : fallback;
        };
        const docType = byIdValue('doc_type', 'tax_invoice');
        const docTitleTh = docTypeLabels[docType].titleTh;
        const docTitleEn = docTypeLabels[docType].titleEn;
        const docNoLabel = docTypeLabels[docType].docNoLabel;
        const invNo = byIdValue('inv_no');
        const salesTag = byIdValue('sales_tag');
        const invDate = byIdValue('inv_date');
        const platformSelect = document.getElementById('platform');
        const platformOther = document.getElementById('platform_other');
        const platform = platformSelect.value === 'อื่นๆ' && platformOther.value 
            ? platformOther.value 
            : platformSelect.value || '-';
        const paymentMethod = byIdValue('payment_method', '');
        const customer = byIdValue('customer');
        const taxId = byIdValue('tax_id');
        const address = byIdValue('address');
        const signReceiver = '________________';
        const signPayer = '________________';
        
        // แปลงวันที่เป็นรูปแบบ DD/MM/YYYY (พ.ศ.)
        let formattedDate = '____/____/______';
        if (invDate && invDate !== '-') {
            const dateObj = new Date(invDate);
            const day = String(dateObj.getDate()).padStart(2, '0');
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const year = dateObj.getFullYear() + 543; // แปลงเป็น พ.ศ.
            formattedDate = `${day}/${month}/${year}`;
        }
        
        const discount = parseFloat(byIdValue('discount', 0)) || 0;
        const shipping = parseFloat(byIdValue('shipping', 0)) || 0;
        const specialDiscount = parseFloat(byIdValue('special_discount', 0)) || 0;

        document.getElementById('pv_doc_title_th').textContent = docTitleTh;
        document.getElementById('pv_doc_title_en').textContent = docTitleEn;
        document.getElementById('pv_doc_no_label').textContent = docNoLabel;
        document.getElementById('pv_inv_no').textContent = invNo;
        document.getElementById('pv_sales_tag').textContent = salesTag;
        document.getElementById('pv_inv_date').textContent = invDate;
        document.getElementById('pv_platform').textContent = platform;
        document.getElementById('pv_customer').textContent = customer;
        document.getElementById('pv_tax_id').textContent = taxId;
        const pvBranch = document.getElementById('pv_branch');
        if (pvBranch) { pvBranch.textContent = '-'; }
        document.getElementById('pv_address').textContent = address;
        document.getElementById('pv_sign_receiver').textContent = signReceiver;
        document.getElementById('pv_sign_receiver_date').textContent = '____/____/______';
        document.getElementById('pv_sign_payer').textContent = signPayer;
        document.getElementById('pv_sign_payer_date').textContent = formattedDate;

        const pvBody = document.getElementById('pv_items_body');
        pvBody.innerHTML = '';

        let subtotal = 0;
        const rows = itemBody.querySelectorAll('tr');
        if (rows.length === 0) {
            pvBody.innerHTML = '<tr><td class="text-center" colspan="6" style="padding:10px;">กรอกสินค้าในแบบฟอร์มด้านซ้าย</td></tr>';
        } else {
            rows.forEach((tr, idx) => {
                const name = tr.querySelector('.item-name').value || '-';
                const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
                const unit = tr.querySelector('.item-unit').value || '';
                const price = parseFloat(tr.querySelector('.item-price').value) || 0;
                const total = qty * price;
                subtotal += total;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center">${idx + 1}</td>
                    <td>${name}</td>
                    <td class="text-center">${qty}</td>
                    <td class="text-center">${unit}</td>
                    <td class="text-right">${numberFmt(price)}</td>
                    <td class="text-right">${numberFmt(total)}</td>
                `;
                pvBody.appendChild(row);
            });
        }

        const totalAfterDiscount = Math.max(subtotal - discount, 0);
        const beforeVat = totalAfterDiscount / 1.07;
        const vat = totalAfterDiscount - beforeVat;
        const payable = Math.max(totalAfterDiscount - specialDiscount, 0);

        document.getElementById('pv_subtotal').textContent = numberFmt(subtotal);
        document.getElementById('pv_discount').textContent = numberFmt(discount);
        document.getElementById('pv_before_vat').textContent = numberFmt(beforeVat);
        document.getElementById('pv_vat').textContent = numberFmt(vat);
        document.getElementById('pv_grand').textContent = numberFmt(totalAfterDiscount);
        document.getElementById('pv_special_discount').textContent = numberFmt(specialDiscount);
        document.getElementById('pv_payable').textContent = numberFmt(payable);
        document.getElementById('pv_amount_text').textContent = thaiBahtText(payable);
    }

    async function saveInvoice() {
        itemBody.querySelectorAll('tr').forEach(recalcRow);
        updatePreview();

        const rows = itemBody.querySelectorAll('tr');
        const items = [];
        rows.forEach(tr => {
            const name = (tr.querySelector('.item-name').value || '').trim();
            const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
            const unit = (tr.querySelector('.item-unit').value || '').trim();
            const price = parseFloat(tr.querySelector('.item-price').value) || 0;
            if (name && qty > 0) {
                items.push({ name, qty, unit, price });
            }
        });

        if (!items.length) {
            alert('กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ');
            return;
        }

        const payload = {
            doc_type: document.getElementById('doc_type').value,
            inv_no: document.getElementById('inv_no').value,
            sales_tag: document.getElementById('sales_tag').value,
            inv_date: document.getElementById('inv_date').value,
            platform: document.getElementById('platform').value === 'อื่นๆ' 
                ? document.getElementById('platform_other').value 
                : document.getElementById('platform').value,
            customer: document.getElementById('customer').value,
            tax_id: document.getElementById('tax_id').value,
            address: document.getElementById('address').value,
            discount: parseFloat(document.getElementById('discount').value) || 0,
            shipping: parseFloat(document.getElementById('shipping').value) || 0,
            special_discount: parseFloat(document.getElementById('special_discount').value) || 0,
            items
        };

        const originalLabel = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = 'กำลังบันทึก...';

        try {
            const res = await fetch('../api/save_tax_invoice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                showToast('success', 'บันทึกสำเร็จ เลขที่ ' + payload.inv_no);
            } else {
                showToast('error', 'บันทึกไม่สำเร็จ: ' + (data.error || 'ไม่ทราบสาเหตุ'));
            }
        } catch (err) {
            showToast('error', 'บันทึกไม่สำเร็จ: ' + err.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = originalLabel;
        }
    }

    function updateDocTypeLabel() {
        const docType = docTypeSelect.value;
        docNoLabel.textContent = docTypeLabels[docType].label;
        updatePreview();
    }
    
    // จัดการแสดง/ซ่อนช่องระบุช่องทางอื่นๆ
    const platformSelect = document.getElementById('platform');
    const platformOtherField = document.getElementById('platform_other_field');
    const platformOtherInput = document.getElementById('platform_other');
    
    platformSelect.addEventListener('change', function() {
        if (this.value === 'อื่นๆ') {
            platformOtherField.style.display = 'block';
            platformOtherInput.focus();
        } else {
            platformOtherField.style.display = 'none';
            platformOtherInput.value = '';
        }
        updatePreview();
    });
    
    addRow({ name: 'ตัวอย่างสินค้า', qty: 1, unit: 'ชิ้น', price: 0, total: 0 });
    bindRowEvents();
    updatePreview();

    docTypeSelect.addEventListener('change', updateDocTypeLabel);
    addRowBtn.addEventListener('click', () => { addRow(); bindRowEvents(); });
    document.getElementById('sales_tag').addEventListener('input', updatePreview);
    platformOtherInput.addEventListener('input', updatePreview);
    document.getElementById('inv_no').addEventListener('input', updatePreview);
    document.getElementById('inv_date').addEventListener('change', updatePreview);
    document.getElementById('customer').addEventListener('input', updatePreview);
    document.getElementById('tax_id').addEventListener('input', updatePreview);
    document.getElementById('address').addEventListener('input', updatePreview);
    document.getElementById('discount').addEventListener('input', updatePreview);
    document.getElementById('shipping').addEventListener('input', updatePreview);
    document.getElementById('special_discount').addEventListener('input', updatePreview);
    previewBtn.addEventListener('click', () => { itemBody.querySelectorAll('tr').forEach(recalcRow); updatePreview(); });
    saveBtn.addEventListener('click', saveInvoice);
    printBtn.addEventListener('click', () => {
        updatePreview();
        
        // สร้างโคลนสำหรับเอกสารสำเนา
        const invoiceSheet = document.getElementById('invoiceSheet');
        const clonedSheet = invoiceSheet.cloneNode(true);
        
        // เปลี่ยนสถานะเอกสารต้นฉบับให้มี page-break
        invoiceSheet.classList.add('page-break');
        
        // เปลี่ยนสถานะในโคลนเป็นสำเนา
        clonedSheet.id = 'invoiceSheetCopy';
        const copyStatus = clonedSheet.querySelector('#pv_doc_status');
        if (copyStatus) {
            copyStatus.textContent = '(สำเนา / Copy)';
        }
        
        // เพิ่มโคลนไปหลังต้นฉบับ
        invoiceSheet.parentNode.appendChild(clonedSheet);
        
        // พิมพ์ทั้งสองฉบับพร้อมกัน
        window.print();
        
        // ลบโคลนและ class page-break หลังพิมพ์เสร็จ
        setTimeout(() => {
            invoiceSheet.classList.remove('page-break');
            if (clonedSheet.parentNode) {
                clonedSheet.parentNode.removeChild(clonedSheet);
            }
        }, 100);
    });
})();
</script>
</body>
</html>
