/**
 * Tax Invoice Print Module
 * ระบบพิมพ์ใบกำกับภาษี - ใช้ได้ทั้งต้นฉบับและสำเนา
 * 
 * การใช้งาน:
 * 1. เพิ่ม script tag: <script src="../assets/tax-invoice-print.js"></script>
 * 2. เรียกฟังก์ชัน: printInvoiceDirectly(invoiceId)
 */

// ฟังก์ชันแปลงตัวเลขเป็นคำอ่านภาษาไทย
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

// ฟังก์ชันจัดรูปแบบตัวเลข
function numberFmt(num) {
    return (num || 0).toLocaleString('en-US', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
}

/**
 * สร้าง HTML สำหรับใบกำกับภาษี
 * @param {Object} inv - ข้อมูลใบกำกับภาษี
 * @param {Array} items - รายการสินค้า
 * @param {Boolean} isCopy - เป็นสำเนาหรือไม่
 * @returns {String} HTML ของเอกสาร
 */
function generateInvoiceHTML(inv, items, isCopy = false) {
    const docTypeLabels = {
        tax_invoice: { titleTh: 'ใบกำกับภาษี/ใบเสร็จรับเงิน', titleEn: 'TAX INVOICE' },
        payment_voucher: { titleTh: 'ใบสำคัญจ่าย', titleEn: 'PAYMENT VOUCHER' },
        quotation: { titleTh: 'ใบเสนอราคา', titleEn: 'QUOTATION' },
        invoice: { titleTh: 'ใบแจ้งหนี้', titleEn: 'INVOICE' }
    };
    
    const docInfo = docTypeLabels[inv.doc_type] || docTypeLabels.tax_invoice;
    const copyLabel = isCopy ? '(สำเนา / Copy)' : '(ต้นฉบับ / Original)';
    
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
    <div class="invoice-sheet">
        <div class="invoice-header">
            <div class="invoice-brand">
                <div style="width:60px;height:60px;border:1px solid #000;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <img src="../images/Ichoices.png" alt="ICHOICE Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                </div>
                <div>
                    <h2 style="margin:0;">บริษัท ไอช้อยซ์ จำกัด</h2>
                    <div style="font-size:10px;line-height:1.5;">
                        ICHOICE CO., LTD.<br>
                        สำนักงานใหญ่ : 422/29 ถนนจันทรลาภ ต.ช้างคลาน อ.เมือง จ.เชียงใหม่ 50100<br>
                        เลขประจำตัวผู้เสียภาษี 0505564015873
                    </div>
                </div>
            </div>
            <div class="invoice-meta">
                <div style="font-weight:700;font-size:14px;">${docInfo.titleTh}</div>
                <div style="font-weight:700;font-size:13px;margin-top:2px;">${docInfo.titleEn}</div>
                <div style="margin-top:4px;font-size:11px;font-weight:600;">${copyLabel}</div>
            </div>
        </div>

        <div class="invoice-block">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="border:1px solid #d1d5db;padding:10px;border-radius:6px;background:#fafafa;">
                    <h4 style="margin:0 0 8px;border-bottom:1px solid #d1d5db;padding-bottom:4px;font-size:11px;">ข้อมูลลูกค้า</h4>
                    <div style="font-size:10px;line-height:1.7;">
                        <div><strong>ลูกค้า:</strong> ${inv.customer_name || '-'}</div>
                        <div><strong>ที่อยู่:</strong> ${inv.customer_address || '-'}</div>
                        <div><strong>เลขผู้เสียภาษี:</strong> ${inv.customer_tax_id || '-'}</div>
                    </div>
                </div>
                <div style="border:1px solid #d1d5db;padding:10px;border-radius:6px;background:#fafafa;">
                    <h4 style="margin:0 0 8px;border-bottom:1px solid #d1d5db;padding-bottom:4px;font-size:11px;">ข้อมูลเอกสาร</h4>
                    <div style="font-size:10px;line-height:1.7;">
                        <div><strong>เลขที่:</strong> ${inv.inv_no}</div>
                        <div><strong>เลขแท็กขาย:</strong> ${inv.sales_tag || '-'}</div>
                        <div><strong>วันที่ออกบิล:</strong> ${inv.inv_date || '-'}</div>
                        <div><strong>ช่องทางสั่งซื้อ:</strong> ${inv.platform || '-'}</div>
                    </div>
                </div>
            </div>
        </div>

        <table class="inv-table" style="margin-top:10px;">
            <thead>
                <tr>
                    <th style="width:7%;" class="text-center">ลำดับ</th>
                    <th style="width:42%;">รายการ</th>
                    <th style="width:13%;" class="text-center">จำนวน</th>
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
            <div style="border:1px solid #000; padding:0; min-height:120px; display:flex; flex-direction:column;">
                <div style="flex:2;"></div>
                <div style="font-weight:700; padding:6px 8px; background:#e0e0e0; font-size:10px; line-height:1.4;">
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
                    <td style="font-weight:700; padding:6px 8px;">รวมทั้งสิ้น<br>GRAND TOTAL</td>
                    <td class="text-right" style="font-weight:700;">${numberFmt(inv.grand_total)}</td>
                </tr>
                <tr>
                    <td>ส่วนลดอื่น<br>OTHERS DISCOUNTS</td>
                    <td class="text-right">${numberFmt(inv.special_discount)}</td>
                </tr>
                <tr>
                    <td style="font-weight:700; font-size:11px;">จำนวนเงินที่ชำระ<br>ACTUAL PAYMENT</td>
                    <td class="text-right" style="font-weight:700; font-size:11px;">${numberFmt(inv.payable)}</td>
                </tr>
            </table>
        </div>

        <div class="footer-note">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="border:1px solid #000; padding:8px; width:55%; vertical-align:top;">
                        <div style="font-weight:700;font-size:10px;">ผู้รับสินค้า/บริการ</div>
                        <div style="margin-top:16px;font-size:9px;">ลงชื่อ: ________________</div>
                        <div style="font-size:9px;">วันที่: ____/____/______</div>
                    </td>
                    <td style="border:1px solid #000; padding:8px; width:45%; vertical-align:top;">
                        <div style="font-weight:700;font-size:10px;">ผู้รับเงิน</div>
                        <div style="margin-top:16px;font-size:9px;">ลงชื่อ: ________________</div>
                        <div style="font-size:9px;">วันที่: ${formattedDate}</div>
                    </td>
                </tr>
            </table>
            <div style="margin-top:6px;font-size:9px;">เอกสารนี้ออกโดยระบบ IchoicePMS</div>
        </div>
    </div>
    `;
}

/**
 * พิมพ์ใบกำกับภาษีโดยตรง (ต้นฉบับ + สำเนา)
 * @param {Number} id - รหัสใบกำกับภาษี
 * @param {String} apiPath - path ของ API (default: '../api/get_tax_invoice_detail.php')
 */
async function printInvoiceDirectly(id, apiPath = '../api/get_tax_invoice_detail.php') {
    // ตรวจสอบหรือสร้าง container สำหรับพิมพ์
    let container = document.getElementById('hiddenPrintContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'hiddenPrintContainer';
        container.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; z-index: 9999;';
        document.body.appendChild(container);
    }
    
    container.innerHTML = '<div style="text-align:center;padding:20px;">กำลังโหลดข้อมูล...</div>';
    
    try {
        const res = await fetch(`${apiPath}?id=${id}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'ไม่พบข้อมูล');
        
        const inv = data.invoice;
        const items = data.items || [];
        
        // สร้างเอกสารต้นฉบับ
        const originalHTML = generateInvoiceHTML(inv, items, false);
        
        // สร้างเอกสารสำเนา
        const copyHTML = generateInvoiceHTML(inv, items, true);
        
        // ใส่ทั้งสองฉบับใน container
        container.innerHTML = originalHTML + copyHTML;
        
        // พิมพ์
        window.print();
        
        // ลบเนื้อหาหลังพิมพ์เสร็จ
        setTimeout(() => {
            container.innerHTML = '';
        }, 100);
        
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + err.message);
        container.innerHTML = '';
    }
}

/**
 * พิมพ์ใบกำกับภาษีพร้อมแสดงโมดอลพรีวิว
 * @param {Number} id - รหัสใบกำกับภาษี
 * @param {String} apiPath - path ของ API (default: '../api/get_tax_invoice_detail.php')
 */
async function printInvoiceWithPreview(id, apiPath = '../api/get_tax_invoice_detail.php') {
    // ตรวจสอบหรือสร้าง modal
    let modal = document.getElementById('printModal');
    let printContent = document.getElementById('printContent');
    
    if (!modal || !printContent) {
        console.error('printModal หรือ printContent element ไม่พบในหน้า');
        return;
    }
    
    printContent.innerHTML = '<div style="text-align:center;padding:20px;">กำลังโหลดข้อมูล...</div>';
    modal.style.display = 'flex';
    
    try {
        const res = await fetch(`${apiPath}?id=${id}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'ไม่พบข้อมูล');
        
        const inv = data.invoice;
        const items = data.items || [];
        
        // สร้างเอกสาร
        const invoiceHTML = generateInvoiceHTML(inv, items, false);
        printContent.innerHTML = invoiceHTML;
        
    } catch (err) {
        printContent.innerHTML = `<div style="color:#b91c1c;padding:20px;text-align:center;">เกิดข้อผิดพลาด: ${err.message}</div>`;
    }
}

// Export functions to global scope
window.thaiBahtText = thaiBahtText;
window.generateInvoiceHTML = generateInvoiceHTML;
window.printInvoiceDirectly = printInvoiceDirectly;
window.printInvoiceWithPreview = printInvoiceWithPreview;
