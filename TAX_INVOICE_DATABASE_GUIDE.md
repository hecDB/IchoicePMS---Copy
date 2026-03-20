# คู่มือการใช้งานระบบใบกำกับภาษี
## Tax Invoice System - Database Setup Guide

---

## 📋 ภาพรวมระบบ

ระบบใบกำกับภาษีนี้รองรับการสร้างเอกสารทางการเงิน 4 ประเภท:
1. **ใบกำกับภาษี** (Tax Invoice)
2. **ใบสำคัญจ่าย** (Payment Voucher)
3. **ใบเสนอราคา** (Quotation)
4. **ใบแจ้งหนี้** (Invoice)

---

## 🗄️ โครงสร้างฐานข้อมูล

### ตาราง `tax_invoices` (ตารางหลัก)

เก็บข้อมูลหลักของเอกสารทุกประเภท

| คอลัมน์ | ประเภท | คำอธิบาย |
|---------|--------|----------|
| `id` | INT | รหัสเอกสาร (Primary Key) |
| `doc_type` | VARCHAR(50) | ประเภทเอกสาร (tax_invoice, payment_voucher, quotation, invoice) |
| `inv_no` | VARCHAR(100) | เลขที่เอกสาร (Unique) |
| `sales_tag` | VARCHAR(100) | เลขแท็กรายการขายสินค้า |
| `inv_date` | DATE | วันที่ออกเอกสาร |
| `platform` | VARCHAR(100) | ช่องทางการสั่งซื้อ (Shopee, Lazada, Tiktok, อื่นๆ) |
| **ข้อมูลลูกค้า** | | |
| `customer_name` | VARCHAR(255) | ชื่อลูกค้า/บริษัท |
| `customer_tax_id` | VARCHAR(20) | เลขประจำตัวผู้เสียภาษี |
| `customer_address` | TEXT | ที่อยู่ลูกค้า |
| **ข้อมูลการคำนวณ** | | |
| `subtotal` | DECIMAL(12,2) | รวมเงิน (ก่อนหักส่วนลด) |
| `discount` | DECIMAL(12,2) | ส่วนลดรวม |
| `shipping` | DECIMAL(12,2) | ค่าจัดส่ง |
| `before_vat` | DECIMAL(12,2) | มูลค่าก่อนภาษี |
| `vat` | DECIMAL(12,2) | ภาษีมูลค่าเพิ่ม 7% |
| `grand_total` | DECIMAL(12,2) | รวมทั้งสิ้น |
| `special_discount` | DECIMAL(12,2) | ส่วนลดพิเศษ |
| `payable` | DECIMAL(12,2) | จำนวนเงินที่ชำระ (สุทธิ) |
| `amount_text` | VARCHAR(500) | จำนวนเงินเป็นตัวอักษร |
| `status` | VARCHAR(20) | สถานะ (active, cancelled, void) |
| `created_at` | TIMESTAMP | วันที่สร้าง |
| `updated_at` | TIMESTAMP | วันที่แก้ไขล่าสุด |

### ตาราง `tax_invoice_items` (รายการสินค้า)

เก็บรายละเอียดสินค้า/บริการในแต่ละเอกสาร

| คอลัมน์ | ประเภท | คำอธิบาย |
|---------|--------|----------|
| `id` | INT | รหัสรายการ (Primary Key) |
| `invoice_id` | INT | อ้างอิงไปยัง tax_invoices.id (Foreign Key) |
| `seq` | INT | ลำดับรายการ |
| `item_name` | VARCHAR(500) | ชื่อสินค้า/บริการ |
| `qty` | DECIMAL(12,2) | จำนวน |
| `unit` | VARCHAR(50) | หน่วยนับ (ชิ้น, กล่อง, etc.) |
| `unit_price` | DECIMAL(12,2) | ราคาต่อหน่วย |
| `total_price` | DECIMAL(12,2) | จำนวนเงิน (qty × unit_price) |
| `product_id` | INT | รหัสสินค้า (ถ้ามี) |
| `created_at` | TIMESTAMP | วันที่สร้าง |

### View `v_tax_invoices_summary`

สรุปข้อมูลเอกสารพร้อมจำนวนรายการสินค้า

---

## 🚀 ขั้นตอนการติดตั้ง

### 1. สร้างตารางฐานข้อมูล

เรียกใช้สคริปต์สร้างตารางผ่านเว็บบราว์เซอร์:

```
http://localhost/[your-project]/setup_tax_invoices_table.php
```

หรือรันผ่าน MySQL Client:

```bash
mysql -u root -p ichoice < db/create_tax_invoices_table.sql
```

### 2. ตรวจสอบการติดตั้ง

หลังรันสคริปต์จะแสดง:
- ✓ สร้างตาราง: tax_invoices
- ✓ สร้างตาราง: tax_invoice_items  
- ✓ สร้าง VIEW: v_tax_invoices_summary

### 3. ทดสอบระบบ

เข้าไปที่หน้าสร้างเอกสาร:

```
http://localhost/[your-project]/reports/tax_invoice.php
```

---

## 📊 ข้อมูลที่ระบบเก็บบันทึก

### ข้อมูลจากฟอร์ม

1. **ข้อมูลเอกสาร**
   - ประเภทเอกสาร (doc_type)
   - เลขที่เอกสาร (inv_no)
   - เลขแท็กขาย (sales_tag)
   - วันที่ออกบิล (inv_date)
   - ช่องทางการสั่งซื้อ (platform)

2. **ข้อมูลลูกค้า**
   - ชื่อลูกค้า/บริษัท (customer_name)
   - เลขผู้เสียภาษี (customer_tax_id)
   - ที่อยู่ (customer_address)

3. **รายการสินค้า/บริการ**
   - รายละเอียด (item_name)
   - จำนวน (qty)
   - หน่วย (unit)
   - ราคาต่อหน่วย (unit_price)
   - รวม (total_price)

4. **ข้อมูลการคำนวณ**
   - ส่วนลดรวม (discount)
   - ค่าจัดส่ง (shipping)
   - ส่วนลดพิเศษ (special_discount)
   - **ระบบคำนวณอัตโนมัติ:**
     - รวมเงิน (subtotal)
     - มูลค่าก่อนภาษี (before_vat)
     - ภาษี 7% (vat)
     - รวมทั้งสิ้น (grand_total)
     - จำนวนเงินที่ชำระ (payable)
     - จำนวนเงินเป็นตัวอักษร (amount_text)

---

## 🔧 API Endpoint

### `POST /api/save_tax_invoice.php`

บันทึกข้อมูลเอกสาร

**Request Body (JSON):**

```json
{
  "doc_type": "tax_invoice",
  "inv_no": "202601-001",
  "sales_tag": "TAG-001",
  "inv_date": "2026-03-20",
  "platform": "Shopee",
  "customer": "บริษัท ตัวอย่าง จำกัด",
  "tax_id": "1234567890123",
  "address": "123 ถนนตัวอย่าง...",
  "discount": 100.00,
  "shipping": 50.00,
  "special_discount": 0.00,
  "items": [
    {
      "name": "สินค้า A",
      "qty": 2,
      "unit": "ชิ้น",
      "price": 500.00
    },
    {
      "name": "สินค้า B",
      "qty": 1,
      "unit": "กล่อง",
      "price": 300.00
    }
  ]
}
```

**Response (Success):**

```json
{
  "success": true,
  "message": "บันทึกเอกสารสำเร็จ",
  "invoice_id": 1,
  "inv_no": "202601-001"
}
```

**Response (Error):**

```json
{
  "success": false,
  "error": "ข้อมูลไม่ครบถ้วน: customer"
}
```

---

## 📝 หมายเหตุสำคัญ

### การคำนวณภาษี

ระบบคำนวณภาษีมูลค่าเพิ่ม (VAT) 7% โดยอัตโนมัติ:

```
subtotal = ยอดรวมสินค้า + ค่าจัดส่ง
totalAfterDiscount = subtotal - ส่วนลด
beforeVat = totalAfterDiscount / 1.07
vat = totalAfterDiscount - beforeVat
grandTotal = totalAfterDiscount
payable = grandTotal - ส่วนลดพิเศษ
```

### การแปลงจำนวนเงินเป็นตัวอักษร

ระบบแปลงจำนวนเงินเป็นภาษาไทยอัตโนมัติ:
- 1,234.56 → "หนึ่งพันสองร้อยสามสิบสี่บาทห้าสิบหกสตางค์"
- 1,000.00 → "หนึ่งพันบาทถ้วน"

### การพิมพ์เอกสาร

เมื่อกดปุ่ม "พิมพ์ใบกำกับภาษี" ระบบจะ:
1. แสดงเอกสารต้นฉบับ (Original) และสำเนา (Copy) ใน Print Preview เดียวกัน
2. แยกหน้าด้วย page-break
3. ปรับขนาดให้เหมาะสมกับกระดาษ A5

---

## 🔍 การตรวจสอบข้อมูล

### ดูข้อมูลในฐานข้อมูล

```sql
-- ดูเอกสารทั้งหมด
SELECT * FROM tax_invoices ORDER BY created_at DESC;

-- ดูสรุปเอกสาร
SELECT * FROM v_tax_invoices_summary;

-- ดูรายการสินค้าของเอกสารหมายเลข 1
SELECT * FROM tax_invoice_items WHERE invoice_id = 1 ORDER BY seq;

-- ดูเอกสารพร้อมรายการสินค้า
SELECT 
    ti.inv_no,
    ti.inv_date,
    ti.customer_name,
    ti.payable,
    tii.seq,
    tii.item_name,
    tii.qty,
    tii.unit_price
FROM tax_invoices ti
LEFT JOIN tax_invoice_items tii ON ti.id = tii.invoice_id
WHERE ti.inv_no = '202601-001'
ORDER BY tii.seq;
```

---

## 🐛 การแก้ไขปัญหา

### ปัญหา: ตารางซ้ำ (Table already exists)

```sql
DROP TABLE IF EXISTS tax_invoice_items;
DROP TABLE IF EXISTS tax_invoices;
```

จากนั้นรันสคริปต์สร้างตารางใหม่

### ปัญหา: ไม่สามารถบันทึกข้อมูลได้

1. ตรวจสอบว่าตารางถูกสร้างแล้ว
2. ตรวจสอบ column names ว่าตรงกับ schema
3. ตรวจสอบ error log ใน browser console
4. ตรวจสอบ PHP error log

### ปัญหา: เลขที่เอกสารซ้ำ

ระบบจะแจ้งเตือน "เลขที่ใบกำกับภาษีซ้ำ" 
- เปลี่ยนเลขที่เอกสารใหม่
- หรือลบเอกสารเดิมออกก่อน

---

## 📞 ติดต่อสอบถาม

หากพบปัญหาการใช้งาน:
1. ตรวจสอบ error message ใน console
2. ตรวจสอบ network tab ว่า API response อะไร
3. ตรวจสอบว่าตารางถูกสร้างครบถ้วน

---

**เอกสารนี้สร้างโดย:** IchoicePMS System  
**วันที่:** 20 มีนาคม 2569  
**เวอร์ชัน:** 1.0
