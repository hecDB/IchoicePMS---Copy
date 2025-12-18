# 🎯 สรุปการปรับปรุงระบบสินค้าตีกลับ

## 📋 ระบบบันทึกข้อมูลที่สมบูรณ์

### ✅ เก็บข้อมูลที่บันทึก:

#### 1. **receive_id** - ลัตชุด/ชุดสินค้า
```
issue_items.receive_id → returned_items.receive_id
                      → damaged_return_inspections.receive_id
```

**ประโยชน์:**
- ติดตามว่าสินค้าออกมาจากลัตชุดไหน
- สามารถจัดเก็บสินค้าตีกลับตามลัตชุด
- วิเคราะห์ปัญหาคุณภาพตามลัตชุด

---

#### 2. **expiry_date** - วันหมดอายุ
```
issue_items.expiry_date → returned_items.expiry_date
                       → damaged_return_inspections.expiry_date
receive_items.expiry_date → returned_items.expiry_date
                         → damaged_return_inspections.expiry_date
```

**ประโยชน์:**
- ติดตามสินค้าหมดอายุ
- วางแผนการใช้สินค้า
- ตรวจสอบความสดใหม่ของสินค้าตีกลับ

---

### 📊 ตารางฐานข้อมูลที่ได้รับการอัปเดต

```sql
-- returned_items
┌─────────────────────────────────┐
│ return_id                       │ (Primary Key)
│ return_code                     │ (Unique)
│ po_id / po_number               │ (จาก PO)
│ receive_id  ✓ NEW               │ (ลัตชุด จาก issue_items)
│ so_id / issue_tag               │ (จาก Sales Order)
│ item_id / product_id            │ (สินค้า)
│ ...
│ expiry_date ✓ UPDATED           │ (วันหมดอายุ)
│ created_at / created_by         │
└─────────────────────────────────┘

-- damaged_return_inspections
┌─────────────────────────────────┐
│ inspection_id                   │ (Primary Key)
│ return_id                       │ (Foreign Key)
│ product_id / sku / barcode      │ (สินค้า)
│ receive_id ✓ NEW                │ (ลัตชุด)
│ expiry_date ✓ UPDATED           │ (วันหมดอายุ)
│ ...
│ new_sku / new_product_id        │ (สินค้าชำรุด)
└─────────────────────────────────┘

-- issue_items
┌─────────────────────────────────┐
│ issue_id                        │
│ product_id                      │
│ receive_id                      │ (ลัตชุด - มีอยู่แล้ว)
│ expiry_date ✓ NEW               │ (วันหมดอายุ)
│ ...
└─────────────────────────────────┘
```

---

## 🔄 ลำดับการบันทึกข้อมูล

### สำหรับการตีกลับจาก Sales Order:

```
1. ค้นหา Sales Order (issue_tag)
                ↓
2. ดึงข้อมูล issue_items
   - receive_id (ลัตชุด)
   - expiry_date (วันหมดอายุ)
                ↓
3. บันทึกลงตาราง returned_items
   - receive_id
   - expiry_date
   - สินค้า, จำนวน, เหตุผล, etc.
                ↓
4. ถ้าเหตุผล = "สินค้าชำรุดบางส่วน"
   → สร้างแรกไฟล์ damaged_return_inspections
     - receive_id
     - expiry_date
     - สต็อก PO ใหม่ (defect SKU)
```

---

## 💾 ตัวอย่างข้อมูลที่บันทึก

### ตัวอย่าง returned_items:

```
return_id: 1
return_code: RET-20251218-0001
po_id: NULL
po_number: NULL
receive_id: 42           ← ลัตชุกที่ออกสินค้า
so_id: 100
issue_tag: ISS-20251218-001
item_id: 50
product_id: 1
product_name: สินค้า A
sku: SKU-001
return_qty: 10
reason_name: สินค้าชำรุดบางส่วน
expiry_date: 2025-06-30  ← วันหมดอายุ
return_status: pending
return_from_sales: 1
```

### ตัวอย่าง damaged_return_inspections:

```
inspection_id: 1
return_id: 1
return_code: RET-20251218-0001
product_id: 1
receive_id: 42           ← ลัตชุดที่ออกสินค้า
expiry_date: 2025-06-30  ← วันหมดอายุ
po_id: 200
new_sku: ตำหนิ-SKU-001   ← สินค้าชำรุด
new_product_id: 2
status: pending
```

---

## 🧪 วิธีการทดสอบ

### 1. ตรวจสอบโครงสร้างฐานข้อมูล:
```bash
เปิดไฟล์: test_receive_id_capture.php
```

### 2. ทดสอบการบันทึกข้อมูล:
- เปิด `returns/return_items.php`
- ค้นหา Sales Order ที่มี receive_id
- สร้างการตีกลับสินค้า
- ตรวจสอบว่า receive_id ถูกบันทึก

### 3. ตรวจสอบฐานข้อมูล:
```sql
-- ตรวจสอบ returned_items
SELECT return_id, return_code, receive_id, expiry_date 
FROM returned_items 
WHERE return_from_sales = 1 
LIMIT 5;

-- ตรวจสอบ damaged_return_inspections
SELECT inspection_id, return_code, receive_id, expiry_date 
FROM damaged_return_inspections 
LIMIT 5;
```

---

## 📈 ประโยชน์ของการปรับปรุง

✅ **ติดตามลัตชุด**: รู้ว่าสินค้าออกมาจากไหน

✅ **จัดการสินค้าตีกลับ**: เก็บสินค้าตีกลับตามลัตชุด

✅ **วิเคราะห์คุณภาพ**: ตรวจสอบปัญหาตามลัตชุด

✅ **ติดตามวันหมดอายุ**: รู้วันหมดอายุของสินค้า

✅ **การตรวจสอบชำรุด**: ข้อมูลครบถ้วนสำหรับการตรวจสอบ

---

## 📝 ไฟล์ที่อัปเดต

| ไฟล์ | การเปลี่ยนแปลง |
|-----|-----------------|
| `api/returned_items_api.php` | เพิ่ม receive_id capture, expiry_date handling |
| `returns/return_dashboard.php` | แสดง expiry_date ในรายละเอียด |
| `test_receive_id_capture.php` | ไฟล์ทดสอบใหม่ |
| `RECEIVE_ID_TRACKING_UPDATE.md` | เอกสารรายละเอียด |

---

## ✨ ระบบพร้อมใช้งาน!

ระบบการบันทึกสินค้าตีกลับได้รับการอัปเดตให้บันทึกข้อมูลลัตชุด (receive_id) และวันหมดอายุแล้ว 🎉
