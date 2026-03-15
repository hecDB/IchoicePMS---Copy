# การเพิ่ม source_type ใน temp_products - สรุปการเปลี่ยนแปลง

## 📋 วัตถุประสงค์

เพิ่มคอลัมน์ `source_type` ในตาราง `temp_products` เพื่อแยกประเภทสินค้าใหม่ว่ามาจาก:
- **NewProduct** = สินค้าใหม่จากใบสั่งซื้อ (Purchase Order)
- **Damaged** = สินค้าชำรุดแบบขายได้ (จากการตรวจสอบ Return Items)

---

## 🗂️ ไฟล์ที่เปลี่ยนแปลง

### 1. **Migration Script** - สร้างคอลัมน์ใหม่
📄 `add_temp_product_source_type.php`

**การใช้งาน:**
```bash
http://localhost/add_temp_product_source_type.php
```

**สิ่งที่ทำ:**
- เพิ่มคอลัมน์ `source_type VARCHAR(20)` ในตาราง `temp_products`
- อัปเดตข้อมูลเก่าโดยคาดเดาจาก `remark` field
  - ถ้ามีคำว่า "ชำรุด", "damaged", "ตำหนิ" → `source_type = 'Damaged'`
  - ที่เหลือ → `source_type = 'NewProduct'`
- แสดงสถิติข้อมูล

---

### 2. **API - Damaged Inspection**
📄 `api/returned_items_api.php`

**การเปลี่ยนแปลง:**

```php
// เดิม: INSERT ไม่มี source_type
INSERT INTO temp_products (
    product_name, product_category, product_image, unit,
    provisional_sku, provisional_barcode, remark, status,
    po_id, expiry_date, created_by, created_at
) VALUES (...)

// ใหม่: เพิ่ม source_type = 'Damaged'
INSERT INTO temp_products (
    product_name, product_category, product_image, unit,
    provisional_sku, provisional_barcode, remark, status,
    source_type,  // ← เพิ่ม
    po_id, expiry_date, created_by, created_at
) VALUES (..., 'Damaged', ...)  // ← เพิ่ม
```

**ผลลัพธ์:**
- เมื่อบันทึกสินค้าชำรุดแบบขายได้ จะตั้งค่า `source_type = 'Damaged'` โดยอัตโนมัติ

---

### 3. **API - Purchase Order New Product**
📄 `api/purchase_order_new_product_api.php`

**การเปลี่ยนแปลง:**

```php
// เดิม: INSERT ไม่มี source_type (7 columns)
INSERT INTO temp_products 
(product_name, product_category, product_image, unit, status, po_id, created_by) 
VALUES (?, ?, ?, ?, ?, ?, ?)

// ใหม่: เพิ่ม source_type = 'NewProduct' (8 columns)
INSERT INTO temp_products 
(product_name, product_category, product_image, unit, status, source_type, po_id, created_by) 
VALUES (?, ?, ?, ?, ?, 'NewProduct', ?, ?)
```

**ผลลัพธ์:**
- เมื่อสร้าง PO สินค้าใหม่ จะตั้งค่า `source_type = 'NewProduct'` โดยอัตโนมัติ

---

### 4. **UI - Transaction View**
📄 `receive/transaction_view_separated.php`

**การเปลี่ยนแปลง:**

```sql
-- เดิม: SELECT ไม่มี source_type
SELECT 
    tp.temp_product_id,
    tp.product_name,
    tp.status as temp_product_status,
    ...
FROM temp_products tp

-- ใหม่: เพิ่ม source_type ใน SELECT
SELECT 
    tp.temp_product_id,
    tp.product_name,
    tp.status as temp_product_status,
    tp.source_type,  -- ← เพิ่ม
    ...
FROM temp_products tp
```

**ผลลัพธ์:**
- หน้า transaction view จะดึงข้อมูล `source_type` มาแสดง (พร้อมสำหรับใช้งานต่อ)

---

## 🚀 ขั้นตอนการติดตั้ง

### 1. Run Migration Script
```
http://localhost/add_temp_product_source_type.php
```

### 2. ตรวจสอบผลลัพธ์
- Column `source_type` ถูกสร้างแล้ว ✅
- ข้อมูลเก่าถูกอัปเดตแล้ว ✅
- สถิติแสดงจำนวน NewProduct vs Damaged ✅

### 3. ทดสอบระบบ

**ทดสอบ Damaged Inspection:**
1. ไป `returns/damaged_return_inspections.php`
2. เลือกรายการชำรุดแบบขายได้
3. บันทึกข้อมูล → ตรวจสอบ `temp_products.source_type = 'Damaged'`

**ทดสอบ New Product PO:**
1. ไป `orders/purchase_order_create_new_product.php`
2. สร้าง PO สินค้าใหม่
3. บันทึกข้อมูล → ตรวจสอบ `temp_products.source_type = 'NewProduct'`

---

## 📊 โครงสร้างตาราง temp_products

```sql
CREATE TABLE temp_products (
    temp_product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255),
    product_category VARCHAR(100),
    product_image LONGBLOB,
    unit VARCHAR(50),
    provisional_sku VARCHAR(100),
    provisional_barcode VARCHAR(100),
    remark TEXT,
    status VARCHAR(50),
    source_type VARCHAR(20),  -- ← เพิ่มใหม่
    po_id INT,
    expiry_date DATE,
    sale_price DECIMAL(12,2),
    created_by INT,
    created_at TIMESTAMP,
    ...
);
```

**ค่าที่เป็นไปได้ของ source_type:**
- `'NewProduct'` - จาก Purchase Order
- `'Damaged'` - จาก Damaged Inspection
- `NULL` - ข้อมูลเก่าที่ยังไม่ถูกอัปเดต (แต่ migration จะอัปเดตให้อัตโนมัติ)

---

## 🔍 การใช้งาน source_type

**Query ตัวอย่าง:**

```sql
-- ดูสินค้าใหม่จาก PO เท่านั้น
SELECT * FROM temp_products WHERE source_type = 'NewProduct';

-- ดูสินค้าชำรุดแบบขายได้เท่านั้น
SELECT * FROM temp_products WHERE source_type = 'Damaged';

-- สถิติการใช้งาน
SELECT 
    source_type,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted
FROM temp_products
WHERE source_type IS NOT NULL
GROUP BY source_type;
```

---

## ✅ Checklist

- [x] สร้าง Migration Script (`add_temp_product_source_type.php`)
- [x] เพิ่ม `source_type` column ในตาราง `temp_products`
- [x] แก้ไข `api/returned_items_api.php` → บันทึก `'Damaged'`
- [x] แก้ไข `api/purchase_order_new_product_api.php` → บันทึก `'NewProduct'`
- [x] แก้ไข `receive/transaction_view_separated.php` → SELECT `source_type`
- [x] อัปเดตข้อมูลเก่าโดยอัตโนมัติ
- [ ] ทดสอบการสร้าง PO สินค้าใหม่
- [ ] ทดสอบการบันทึกสินค้าชำรุดแบขายได้
- [ ] ตรวจสอบข้อมูลใน database

---

## 📝 หมายเหตุ

1. **Backward Compatibility:** 
   - Code เดิมที่ไม่ใช้ `source_type` ยังทำงานได้ปกติ
   - Migration จะอัปเดตข้อมูลเก่าให้อัตโนมัติ

2. **Data Validation:**
   - ควรเพิ่ม CHECK constraint หรือ ENUM ในอนาคต:
     ```sql
     ALTER TABLE temp_products 
     ADD CONSTRAINT chk_source_type 
     CHECK (source_type IN ('NewProduct', 'Damaged'));
     ```

3. **Future Enhancement:**
   - สามารถเพิ่มประเภทอื่นๆ ได้ เช่น `'Adjustment'`, `'Transfer'` ฯลฯ
   - แสดง badge แยกสีตาม `source_type` ในหน้า UI

---

## 🐛 Troubleshooting

**ปัญหา: Column already exists**
```
Error: Duplicate column name 'source_type'
```
✅ **แก้ไข:** Column ถูกสร้างแล้ว ไม่ต้องทำอะไร

**ปัญหา: ข้อมูลเก่าเป็น NULL**
```sql
-- แก้ไขด้วย query นี้
UPDATE temp_products 
SET source_type = CASE
    WHEN remark LIKE '%ชำรุด%' OR remark LIKE '%damaged%' THEN 'Damaged'
    ELSE 'NewProduct'
END
WHERE source_type IS NULL;
```

**ปัญหา: INSERT ล้มเหลว Unknown column**
```
Error: Unknown column 'source_type' in 'field list'
```
✅ **แก้ไข:** ต้อง run migration script ก่อน

---

## 📞 Support

หากพบปัญหาหรือต้องการความช่วยเหลือ:
1. ตรวจสอบ error log: `logs/error.log`
2. ตรวจสอบ database structure: `DESCRIBE temp_products;`
3. ดูข้อมูลตัวอย่าง: `SELECT * FROM temp_products LIMIT 10;`

---

**เสร็จสิ้นการจัดทำเอกสาร** ✅  
*วันที่: <?= date('Y-m-d H:i:s') ?>*
