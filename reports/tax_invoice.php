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
        .totals td { padding: 6px; font-size: 12.5px; }
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
            body { background: #fff; }
            .mainwrap, .card, .controls { padding: 0; box-shadow: none; }
            .page-title { display: none; }
            .layout { display: block; }
            .card-form { display: none; }
            .sidebar, .sidebar-backdrop, .mobile-nav-toggle { display: none !important; }
            .invoice-sheet { border: none; padding: 0; }
            .inv-table { border-collapse: collapse !important; border-spacing: 0 !important; }
            .inv-table, .inv-table th, .inv-table td, .inv-table thead th { border: 1px solid #000 !important; box-sizing: border-box; }
            .inv-table thead { border-bottom: 1px solid #000 !important; }
            .inv-table thead th { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
                    <label for="inv_no">เลขที่ใบกำกับภาษี</label>
                    <input id="inv_no" type="text" value="202601-001" autocomplete="off">
                </div>
                <div class="field">
                    <label for="inv_date">วันที่ออกเอกสาร</label>
                    <input id="inv_date" type="date" value="<?= htmlspecialchars($today) ?>">
                </div>
                <div class="field">
                    <label for="ref_no">เลขที่อ้างอิง/คำสั่งซื้อ</label>
                    <input id="ref_no" type="text" placeholder="เช่น SO-2026-00123">
                </div>
                <div class="field">
                    <label for="platform">ช่องทางขาย</label>
                    <select id="platform">
                        <option value="">เลือกช่องทาง</option>
                        <option value="Shopee">Shopee</option>
                        <option value="Lazada">Lazada</option>
                        <option value="หน้าร้าน">หน้าร้าน</option>
                        <option value="อื่นๆ">อื่นๆ</option>
                    </select>
                </div>
                <div class="field">
                    <label for="payment_method">ช่องทางการชำระเงิน</label>
                    <select id="payment_method">
                        <option value="">เลือกวิธีชำระ</option>
                        <option value="cash">เงินสด</option>
                        <option value="transfer">เงินโอน</option>
                        <option value="shopee">รับเงิน ผ่าน : Shopee</option>
                        <option value="lazada">รับเงิน ผ่าน : Lazada</option>
                    </select>
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
                <div class="field">
                    <label for="branch">สาขา</label>
                    <input id="branch" type="text" placeholder="สำนักงานใหญ่/สาขา">
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
                        <div style="font-weight:700;font-size:15px;">ใบกำกับภาษี/ใบเสร็จรับเงิน</div>
                        <div style="margin-top:4px;">เลขที่: <span id="pv_inv_no">-</span></div>
                        <div>วันที่: <span id="pv_inv_date">-</span></div>
                        <div>เลขที่อ้างอิง: <span id="pv_ref">-</span></div>
                    </div>
                </div>

                <div class="invoice-block">
                    <h4>ข้อมูลผู้ซื้อ</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 12px;">
                        <div>ชื่อ/บริษัท: <strong id="pv_customer">-</strong></div>
                       
                        <div>ที่อยู่: <span id="pv_address">-</span></div>
                        <div>เลขประจำตัวผู้เสียภาษี: <span id="pv_tax_id">-</span></div>
                        <div>ช่องทางขาย: <span id="pv_platform">-</span></div>
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
                    <div style="border:1px solid #000; padding:10px; min-height:120px;">
                        
                        <div style="font-size:12px; margin-bottom:4px;">ชำระโดย</div>
                        <table class="pay-table" id="pv_payment_table"></table>
                    </div>
                    <table class="totals" style="width:100%;">
                        <tr>
                            <td>ยอดรวม<br>TOTAL AMOUNT</td>
                            <td class="text-right" id="pv_subtotal">0.00</td>
                        </tr>
                        <tr>
                            <td>ส่วนลดจากร้านค้า<br>DISCOUNT</td>
                            <td class="text-right" id="pv_discount">0.00</td>
                        </tr>
                        <tr>
                            <td>ค่าจัดส่ง</td>
                            <td class="text-right" id="pv_shipping">0.00</td>
                        </tr>
                        <tr>
                            <td>ยอดสุทธิหลังหักส่วนลด<br>TOTAL AMOUNT AFTER DISCOUNT</td>
                            <td class="text-right" id="pv_after_discount">0.00</td>
                        </tr>
                        <tr>
                            <td>มูลค่าที่คำนวณภาษี</td>
                            <td class="text-right" id="pv_before_vat">0.00</td>
                        </tr>
                        <tr>
                            <td>ภาษีมูลค่าเพิ่ม<br>VAT 7%</td>
                            <td class="text-right" id="pv_vat">0.00</td>
                        </tr>
                        <tr>
                            <td>รวมจำนวนเงินทั้งสิ้น</td>
                            <td class="text-right" id="pv_grand">0.00</td>
                        </tr>
                        <tr>
                            <td>ส่วนลดพิเศษ</td>
                            <td class="text-right" id="pv_special_discount">0.00</td>
                        </tr>
                        <tr>
                            <td style="font-weight:700;">ยอดชำระ</td>
                            <td class="text-right" style="font-weight:700;" id="pv_payable">0.00</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top:10px; font-size:13px; border:1px solid #000; padding:6px 10px;">
                    ตัวอักษร (สกุลบาทถ้วน): <span id="pv_amount_text">-</span>
                </div>

                <div class="footer-note">
                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
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
                                <div style="margin-top:16px; font-weight:700;">ผู้มีอำนาจลงนาม</div>
                                <div style="margin-top:22px;">ลงชื่อ: <span id="pv_sign_authorized">________________</span></div>
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
    const paymentOptions = [
        { value: 'cash', label: 'เงินสด' },
        { value: 'transfer', label: 'เงินโอน' },
        { value: 'shopee', label: 'รับเงิน ผ่าน : Shopee' },
        { value: 'lazada', label: 'รับเงิน ผ่าน : Lazada' }
    ];

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

    function renderPaymentTable(selectedMethod) {
        const paymentTable = document.getElementById('pv_payment_table');
        paymentTable.innerHTML = paymentOptions.map(opt => {
            const isSelected = opt.value === selectedMethod;
            const statusClass = isSelected ? 'pay-status is-true' : 'pay-status';
            const statusText = isSelected ? 'TRUE' : 'FALSE';
            return `
                <tr>
                    <td class="${statusClass}">${statusText}</td>
                    <td>${opt.label}</td>
                </tr>
            `;
        }).join('');
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
        const invNo = byIdValue('inv_no');
        const invDate = byIdValue('inv_date');
        const refNo = byIdValue('ref_no');
        const platform = byIdValue('platform');
        const paymentMethod = byIdValue('payment_method', '');
        const customer = byIdValue('customer');
        const taxId = byIdValue('tax_id');
        const address = byIdValue('address');
        const signReceiver = '________________';
        const signReceiverDate = '____/____/______';
        const signPayer = '________________';
        const signPayerDate = '____/____/______';
        const signAuthorized = '________________';
        const discount = parseFloat(byIdValue('discount', 0)) || 0;
        const shipping = parseFloat(byIdValue('shipping', 0)) || 0;
        const specialDiscount = parseFloat(byIdValue('special_discount', 0)) || 0;

        document.getElementById('pv_inv_no').textContent = invNo;
        document.getElementById('pv_inv_date').textContent = invDate;
        document.getElementById('pv_ref').textContent = refNo;
        document.getElementById('pv_platform').textContent = platform;
        document.getElementById('pv_customer').textContent = customer;
        document.getElementById('pv_tax_id').textContent = taxId;
        const pvBranch = document.getElementById('pv_branch');
        if (pvBranch) { pvBranch.textContent = '-'; }
        document.getElementById('pv_address').textContent = address;
        document.getElementById('pv_sign_receiver').textContent = signReceiver;
        document.getElementById('pv_sign_receiver_date').textContent = signReceiverDate;
        document.getElementById('pv_sign_payer').textContent = signPayer;
        document.getElementById('pv_sign_payer_date').textContent = signPayerDate;
        document.getElementById('pv_sign_authorized').textContent = signAuthorized;
        renderPaymentTable(paymentMethod);

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

        const totalAfterDiscount = Math.max(subtotal - discount + shipping, 0);
        const beforeVat = totalAfterDiscount / 1.07;
        const vat = totalAfterDiscount - beforeVat;
        const payable = Math.max(totalAfterDiscount - specialDiscount, 0);

        document.getElementById('pv_subtotal').textContent = numberFmt(subtotal);
        document.getElementById('pv_discount').textContent = numberFmt(discount);
        document.getElementById('pv_shipping').textContent = numberFmt(shipping);
        document.getElementById('pv_after_discount').textContent = numberFmt(totalAfterDiscount);
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
            inv_no: document.getElementById('inv_no').value,
            inv_date: document.getElementById('inv_date').value,
            ref_no: document.getElementById('ref_no').value,
            platform: document.getElementById('platform').value,
            payment_method: document.getElementById('payment_method').value,
            customer: document.getElementById('customer').value,
            tax_id: document.getElementById('tax_id').value,
            branch: document.getElementById('branch').value,
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

    addRow({ name: 'ตัวอย่างสินค้า', qty: 1, unit: 'ชิ้น', price: 0, total: 0 });
    bindRowEvents();
    updatePreview();

    addRowBtn.addEventListener('click', () => { addRow(); bindRowEvents(); });
    document.getElementById('payment_method').addEventListener('change', updatePreview);
    document.getElementById('discount').addEventListener('input', updatePreview);
    document.getElementById('shipping').addEventListener('input', updatePreview);
    document.getElementById('special_discount').addEventListener('input', updatePreview);
    previewBtn.addEventListener('click', () => { itemBody.querySelectorAll('tr').forEach(recalcRow); updatePreview(); });
    saveBtn.addEventListener('click', saveInvoice);
    printBtn.addEventListener('click', () => { updatePreview(); window.print(); });
})();
</script>
</body>
</html>
