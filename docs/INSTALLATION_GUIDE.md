# วิธีการติดตั้ง - ระบบสร้าง PO สำหรับสินค้าใหม่

## 🚀 ขั้นตอนการติดตั้ง

### ขั้นตอนที่ 1: รันการ Migration อัตโนมัติ (ง่ายที่สุด)

**วิธี A: ผ่านเว็บบราวเซอร์**

1. เข้าใจ URL นี้ในเว็บบราวเซอร์:
   ```
   http://yoursite.com/db/run_migration.php
   ```
   (แทนที่ `yoursite.com` ด้วย domain ของคุณ)

2. ต้องเป็น Admin เพื่อรันได้
3. ระบบจะแสดงผลลัพธ์ migration
4. ถ้าสำเร็จ จะเห็น "✅ Migration Completed Successfully"

---

### ขั้นตอนที่ 2: รัน SQL ด้วยมือ (ถ้า Migration ไม่สำเร็จ)

**วิธี B: ผ่าน MySQL Workbench หรือ phpMyAdmin**

1. เปิด MySQL Workbench หรือ phpMyAdmin
2. เลือก Database: `ichoice_`
3. คัดลอก SQL จากไฟล์: `db/001_create_temp_products_table.sql`
4. ปะและรัน SQL

**SQL ที่ต้องรัน:**

```sql
-- สร้างตาราง temp_products
CREATE TABLE IF NOT EXISTS `temp_products` (
  `temp_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL,
  `product_category` varchar(100) DEFAULT NULL,
  `product_image` longblob DEFAULT NULL,
  `provisional_sku` varchar(255) DEFAULT NULL,
  `provisional_barcode` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'ชิ้น',
  `remark` text DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','rejected','converted') DEFAULT 'draft',
  `po_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`temp_product_id`),
  KEY `fk_po_id` (`po_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_category` (`product_category`),
  CONSTRAINT `fk_po_id` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มคอลัมน์ temp_product_id ไปที่ purchase_order_items
ALTER TABLE `purchase_order_items` 
ADD COLUMN IF NOT EXISTS `temp_product_id` int(11) DEFAULT NULL AFTER `product_id`;
```

---

## ✅ ตรวจสอบว่าสำเร็จหรือไม่

หลังจากรัน migration ให้รัน query นี้เพื่อตรวจสอบ:

```sql
-- ตรวจสอบตาราง temp_products มีอยู่
SHOW TABLES LIKE 'temp_products';

-- ตรวจสอบคอลัมน์ใน temp_products
DESCRIBE temp_products;

-- ตรวจสอบ purchase_order_items มี temp_product_id
DESCRIBE purchase_order_items;
```

**ผลลัพธ์ที่คาดหวัง:**
- ตาราง `temp_products` มีอยู่ ✓
- มีคอลัมน์ `product_category` ✓
- มีคอลัมน์ `product_image` ✓
- purchase_order_items มีคอลัมน์ `temp_product_id` ✓

---

## 🎯 ไฟล์ที่เกี่ยวข้อง

| ไฟล์ | ลักษณะ | คำอธิบาย |
|------|--------|---------|
| `db/001_create_temp_products_table.sql` | SQL Migration | SQL script ที่รันเอง |
| `db/run_migration.php` | PHP Migration Runner | รัน migration ผ่านเว็บ |
| `db/add_image_category_to_temp_products.sql` | SQL Migration | เพิ่มคอลัมน์ image/category |

---

## 🔧 Troubleshooting

### ❌ Error: "Access Denied - Admin only"
**สาเหตุ:** ผู้ใช้ไม่ใช่ Admin
**วิธีแก้:** ใช้บัญชี Admin เข้าสู่ระบบแล้วลองใหม่

### ❌ Error: "Column already exists"
**สาเหตุ:** คอลัมน์ถูกสร้างไปแล้ว
**วิธีแก้:** ไม่เป็นไร ข้ามไปได้ - ข้อมูลยังอยู่ปกติ

### ❌ Error: "Table 'temp_products' doesn't exist"
**สาเหตุ:** Migration ยังไม่ได้รัน
**วิธีแก้:** 
1. ไปที่ `http://yoursite.com/db/run_migration.php`
2. หรือรัน SQL ด้วยมือจาก `db/001_create_temp_products_table.sql`

### ❌ Error: "Foreign key constraint fails"
**สาเหตุ:** ฟิลด์ po_id อ้างถึง po_id ที่ไม่มี
**วิธีแก้:** ตรวจสอบตาราง purchase_orders มี po_id

---

## 📊 Database Schema

### temp_products Table

| คอลัมน์ | ชนิด | บังคับ | ค่าเริ่มต้น | คำอธิบาย |
|--------|------|--------|-----------|---------|
| temp_product_id | INT | ✓ | AUTO_INCREMENT | Primary Key |
| product_name | VARCHAR(100) | ✓ | - | ชื่อสินค้า |
| product_category | VARCHAR(100) | | NULL | ประเภทสินค้า |
| product_image | LONGBLOB | | NULL | รูปภาพ (Base64) |
| provisional_sku | VARCHAR(255) | | NULL | SKU ชั่วคราว |
| provisional_barcode | VARCHAR(50) | | NULL | Barcode ชั่วคราว |
| unit | VARCHAR(20) | | 'ชิ้น' | หน่วยนับ |
| remark | TEXT | | NULL | หมายเหตุ |
| status | ENUM | | 'draft' | สถานะ |
| po_id | INT | ✓ | - | Foreign Key |
| created_by | INT | ✓ | - | สร้างโดย |
| approved_by | INT | | NULL | อนุมัติโดย |
| created_at | TIMESTAMP | | CURRENT_TIMESTAMP | วันที่สร้าง |
| approved_at | TIMESTAMP | | NULL | วันที่อนุมัติ |

---

## 🎉 เสร็จแล้ว!

หลังจากติดตั้งเสร็จ:

1. ✓ สามารถสร้างใบ PO สำหรับสินค้าใหม่ได้
2. ✓ สามารถอัพโหลดรูปภาพได้
3. ✓ สามารถเลือกประเภทสินค้าได้
4. ✓ สามารถอนุมัติและแปลงเป็นสินค้าถาวรได้

---

## 📞 ติดต่อสำหรับความช่วยเหลือ

ถ้ามีปัญหา:
1. ตรวจสอบไฟล์ log
2. ลองรัน migration อีกครั้ง
3. ติดต่อทีม IT

---

**Installation Date:** 16 November 2025  
**Version:** 1.0  
**Status:** Ready to Install
