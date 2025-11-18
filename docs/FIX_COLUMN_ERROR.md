# การแก้ไข Column Error - purchase_order_items

## 🐛 ปัญหา

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'quantity' in 'field list'
```

## 🔍 สาเหตุ

ตาราง `purchase_order_items` มีชื่อคอลัมน์ต่างจากที่ API ใช้:

| API ใช้ | ฐานข้อมูลจริง | แล้ว Fix? |
|--------|-------------|---------|
| `quantity` | `qty` | ✅ เพิ่มคอลัมน์ alias |
| `unit_price` | `price_per_unit` | ✅ เพิ่มคอลัมน์ alias |
| `unit` | ไม่มี | ✅ เพิ่มคอลัมน์ |
| `po_item_amount` | ไม่มี | ✅ เพิ่มคอลัมน์ |
| `temp_product_id` | ไม่มี | ✅ เพิ่มคอลัมน์ |

---

## ✅ วิธีแก้ไข

### วิธีที่ 1: อัตโนมัติ (ง่ายที่สุด) ⭐

ไปที่ URL นี้:
```
http://yoursite.com/db/run_migration.php
```

ระบบจะ:
1. สร้างตาราง `temp_products`
2. เพิ่มคอลัมน์ทั้งหมด 5 คอลัมน์ เข้า `purchase_order_items`
3. แสดงผลลัพธ์

---

### วิธีที่ 2: ด้วยมือ

รัน SQL ด้านล่างใน phpMyAdmin หรือ MySQL Workbench:

```sql
-- เพิ่มคอลัมน์ที่ขาดทั้งหมด
ALTER TABLE `purchase_order_items` 
ADD COLUMN IF NOT EXISTS `temp_product_id` int(11) DEFAULT NULL COMMENT 'ลิงก์ไปยัง temp_products' AFTER `product_id`,
ADD COLUMN IF NOT EXISTS `quantity` decimal(10,2) DEFAULT NULL COMMENT 'จำนวน (alias for qty)',
ADD COLUMN IF NOT EXISTS `unit_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคา/หน่วย (alias for price_per_unit)',
ADD COLUMN IF NOT EXISTS `unit` varchar(20) DEFAULT NULL COMMENT 'หน่วยนับ',
ADD COLUMN IF NOT EXISTS `po_item_amount` decimal(12,2) DEFAULT NULL COMMENT 'ยอดรวมรายการ';

-- เพิ่ม index
ALTER TABLE `purchase_order_items` 
ADD KEY IF NOT EXISTS `idx_temp_product_id` (`temp_product_id`);
```

---

## ✅ ตรวจสอบว่าสำเร็จ

รัน query นี้:

```sql
DESCRIBE purchase_order_items;
```

ผลลัพธ์ที่คาดหวัง - มีคอลัมน์:
- [x] `temp_product_id`
- [x] `quantity`
- [x] `unit_price`
- [x] `unit`
- [x] `po_item_amount`

---

## 📊 Column Structure

| Column | Type | NULL | Default | Comment |
|--------|------|------|---------|---------|
| item_id | INT | NO | - | Primary Key |
| po_id | INT | YES | NULL | PO Reference |
| product_id | INT | YES | NULL | Product Reference |
| **temp_product_id** | INT | YES | NULL | **Temp Product (NEW)** |
| qty | DECIMAL | YES | NULL | Original quantity |
| **quantity** | DECIMAL | YES | NULL | **Alias (NEW)** |
| price_per_unit | DECIMAL | YES | NULL | Original price |
| **unit_price** | DECIMAL | YES | NULL | **Alias (NEW)** |
| **unit** | VARCHAR | YES | NULL | **Unit of measure (NEW)** |
| **po_item_amount** | DECIMAL | YES | NULL | **Total amount (NEW)** |
| sale_price | DECIMAL | NO | - | Sale price |
| total | DECIMAL | YES | NULL | Total |
| created_at | TIMESTAMP | NO | - | Timestamp |

---

## 🎯 ต่อไปจากนี้

หลังจากเพิ่มคอลัมน์แล้ว:

1. ✅ สร้างใบ PO ได้
2. ✅ เพิ่มสินค้าได้
3. ✅ อัพโหลดรูปได้
4. ✅ บันทึกลงฐานข้อมูลได้
5. ✅ อนุมัติสินค้าได้

---

## 📋 Files

- ✅ `db/run_migration.php` - Migration runner
- ✅ `db/001_create_temp_products_table.sql` - temp_products schema
- ✅ `db/002_add_missing_columns_to_po_items.sql` - Missing columns

---

**Status:** ✅ Ready to Install
