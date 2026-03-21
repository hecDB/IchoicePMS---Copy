# คู่มือการใช้งาน Tax Invoice Print Module

โมดูลการพิมพ์ใบกำกับภาษีที่สามารถนำไปใช้ซ้ำได้ในหน้าต่างๆ

## ไฟล์ที่เกี่ยวข้อง
- `assets/tax-invoice-print.js` - โมดูลหลักสำหรับการพิมพ์

## CSS ที่ต้องมีในหน้า

```css
/* Print Modal Styles - ใส่ใน <style> tag */
.invoice-sheet { 
    background:#fff; 
    color:#000; 
    padding:16px; 
    border:1px solid #d1d5db; 
    min-height:auto; 
    page-break-after: always; 
}
.invoice-sheet:last-child { page-break-after: auto; }
.invoice-header { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    border-bottom:2px solid #111; 
    padding-bottom:8px; 
}
.invoice-brand { display:flex; align-items:center; gap:8px; }
.invoice-brand h2 { margin:0; font-size:14px; }
.invoice-meta { text-align:right; font-size:10px; }
.invoice-block { margin-top:10px; font-size:10px; }
.invoice-block h4 { margin:0 0 5px; font-size:11px; }
.inv-table { 
    width:100%; 
    border-collapse:collapse; 
    margin-top:6px; 
    font-size:8px; 
    border:1px solid #000; 
}
.inv-table th, .inv-table td { 
    border:1px solid #000; 
    padding:3px 4px; 
    box-sizing:border-box; 
    font-size:8px; 
}
.inv-table th { background:#f3f4f6; font-weight:600; }
.summary-box { 
    margin-top:8px; 
    display:grid; 
    grid-template-columns:1fr 180px; 
    gap:10px; 
}
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
    /* ซ่อนทุกอย่างยกเว้นเอกสารพิมพ์ */
    .mainwrap, .card, .controls, .filters { display: none !important; }
    .modal-backdrop { display: none !important; }
    .modal { display: none !important; }
    .sidebar, .sidebar-backdrop { display: none !important; }
    
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
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .invoice-sheet:last-child { 
        page-break-after: auto !important; 
    }
    /* ปรับขนาดตัวอักษรสำหรับการพิมพ์ */
    .invoice-brand h2 { font-size: 12px !important; }
    .invoice-brand > div > div { font-size: 8px !important; line-height: 1.4 !important; }
    .invoice-brand > div:first-child { width: 45px !important; height: 45px !important; }
    .invoice-meta { font-size: 9px !important; }
    .invoice-block { margin-top: 8px !important; font-size: 8px !important; }
    .inv-table { margin-top: 8px !important; font-size: 7px !important; }
    .inv-table, .inv-table th, .inv-table td { 
        border: 1px solid #000 !important; 
        padding: 3px !important; 
        font-size: 7px !important; 
    }
    .totals td { padding: 3px 5px !important; font-size: 8px !important; }
    .footer-note { margin-top: 10px !important; font-size: 8px !important; }
}
```

## วิธีการใช้งาน

### 1. เพิ่ม Script Tag ในหน้า HTML

```html
<!-- Hidden container สำหรับการพิมพ์ -->
<div id="hiddenPrintContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; z-index: 9999;"></div>

<!-- โหลดโมดูลพิมพ์ -->
<script src="../assets/tax-invoice-print.js"></script>
```

### 2. วิธีการเรียกใช้งาน

#### 2.1 พิมพ์โดยตรง (ต้นฉบับ + สำเนา)

```javascript
// พิมพ์ทันทีโดยไม่มีพรีวิว
printInvoiceDirectly(invoiceId);

// หรือระบุ API path เอง
printInvoiceDirectly(invoiceId, '../api/get_tax_invoice_detail.php');
```

**ตัวอย่างการใช้ในปุ่ม:**
```html
<button onclick="printInvoiceDirectly(123)">
    <span class="material-icons">print</span> พิมพ์
</button>
```

#### 2.2 พิมพ์พร้อมแสดงพรีวิว

```javascript
// แสดงโมดอลพรีวิวก่อนพิมพ์
await printInvoiceWithPreview(invoiceId);

// หรือระบุ API path เอง
await printInvoiceWithPreview(invoiceId, '../api/get_tax_invoice_detail.php');
```

**หมายเหตุ:** สำหรับ preview ต้องมี element ต่อไปนี้ในหน้า:
```html
<div class="modal-backdrop" id="printModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">พิมพ์เอกสาร</div>
            <button class="close-btn" id="closePrintModal">ปิด</button>
        </div>
        <div id="printContent"></div>
    </div>
</div>
```

### 3. ฟังก์ชันที่มีให้ใช้งาน

#### `printInvoiceDirectly(id, apiPath)`
พิมพ์เอกสารทันที (ต้นฉบับ + สำเนา) โดยไม่แสดงพรีวิว

**Parameters:**
- `id` (Number) - รหัสใบกำกับภาษี
- `apiPath` (String, optional) - path ของ API (default: `'../api/get_tax_invoice_detail.php'`)

**Returns:** Promise

#### `printInvoiceWithPreview(id, apiPath)`
แสดงโมดอลพรีวิวก่อนพิมพ์

**Parameters:**
- `id` (Number) - รหัสใบกำกับภาษี
- `apiPath` (String, optional) - path ของ API (default: `'../api/get_tax_invoice_detail.php'`)

**Returns:** Promise

#### `generateInvoiceHTML(invoice, items, isCopy)`
สร้าง HTML สำหรับเอกสาร (ใช้ภายใน)

**Parameters:**
- `invoice` (Object) - ข้อมูลใบกำกับภาษี
- `items` (Array) - รายการสินค้า
- `isCopy` (Boolean) - เป็นสำเนาหรือไม่

**Returns:** String (HTML)

#### `thaiBahtText(amount)`
แปลงตัวเลขเป็นคำอ่านภาษาไทย

**Parameters:**
- `amount` (Number) - จำนวนเงิน

**Returns:** String (เช่น "หนึ่งหมื่นห้าร้อยบาทถ้วน")

## ตัวอย่างการใช้งานในหน้าต่างๆ

### ตัวอย่างที่ 1: รายการใบกำกับภาษี
```javascript
function renderTable(rows) {
    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.inv_no}</td>
            <td>${row.customer_name}</td>
            <td>
                <button onclick="printInvoiceDirectly(${row.id})">
                    พิมพ์
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}
```

### ตัวอย่างที่ 2: หน้ารายละเอียดใบกำกับภาษี
```javascript
// ในหน้า invoice_detail.php
document.getElementById('printBtn').addEventListener('click', () => {
    const invoiceId = getInvoiceIdFromUrl();
    printInvoiceDirectly(invoiceId);
});
```

### ตัวอย่างที่ 3: ใช้ร่วมกับ async/await
```javascript
async function printAndDoSomething(id) {
    try {
        await printInvoiceDirectly(id);
        console.log('พิมพ์เสร็จแล้ว');
        // ทำอะไรต่อ...
    } catch (error) {
        console.error('เกิดข้อผิดพลาด:', error);
    }
}
```

## โครงสร้างข้อมูลที่ API ต้อง Return

API ที่ระบุใน `apiPath` ต้อง return JSON ในรูปแบบนี้:

```json
{
    "success": true,
    "invoice": {
        "id": 1,
        "inv_no": "2026-001",
        "inv_date": "2026-03-20",
        "sales_tag": "TAG-001",
        "doc_type": "tax_invoice",
        "customer_name": "บริษัท ทดสอบ จำกัด",
        "customer_address": "123 ถนนทดสอบ",
        "customer_tax_id": "0123456789012",
        "platform": "Lazada",
        "subtotal": 1400.00,
        "discount": 0.00,
        "before_vat": 1400.00,
        "vat": 98.00,
        "grand_total": 1498.00,
        "special_discount": 0.00,
        "payable": 1498.00,
        "amount_text": "หนึ่งพันสี่ร้อยเก้าสิบแปดบาทถ้วน"
    },
    "items": [
        {
            "item_no": 1,
            "item_name": "สินค้าตัวอย่าง",
            "qty": 10,
            "unit": "ชิ้น",
            "unit_price": 200.00,
            "total_price": 2000.00
        }
    ]
}
```

## การทดสอบ

```javascript
// ทดสอบในคอนโซล
printInvoiceDirectly(1); // แทน 1 ด้วย ID ที่มีอยู่จริง
```

##หมายเหตุ
- โมดูลนี้จะสร้าง `hiddenPrintContainer` เองหากไม่มี
- ฟังก์ชันทั้งหมดถูก export ไปยัง `window` scope แล้ว
- รองรับเอกสารทุกประเภท: ใบกำกับภาษี, ใบสำคัญจ่าย, ใบเสนอราคา, ใบแจ้งหนี้
- พิมพ์ออกมาเป็นกระดาษ A5 แนวตั้ง
