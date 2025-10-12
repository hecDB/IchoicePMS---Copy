# การแก้ไข Error Column not found - หน้าสินค้าคงคลัง

## ปัญหาที่พบ

```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 
1054 Unknown column 'p.location' in 'field list'
```

## สาเหตุ

Query เดิมพยายามเรียกใช้คอลัมน์ `p.location` ที่ไม่มีในตาราง `products`

## วิธีแก้ไข

ลบการอ้างอิง `p.location` และใช้เฉพาะการ JOIN ตามโครงสร้างฐานข้อมูลที่มีอยู่จริง

## โครงสร้างฐานข้อมูลที่ถูกต้อง

### ตารางที่เกี่ยวข้อง:

1. **`products`** - ข้อมูลสินค้าหลัก
   - `product_id` (PK)
   - `name`, `sku`, `barcode`, `unit`, `image`

2. **`product_location`** - ความสัมพันธ์สินค้า-ตำแหน่ง
   - `product_id` (FK → products.product_id)
   - `location_id` (FK → locations.location_id)

3. **`locations`** - ข้อมูลตำแหน่ง
   - `location_id` (PK)
   - `row_code`, `bin`, `shelf`, `description`

### Relationship Diagram:
```
products (1) ←→ (1) product_location (1) ←→ (1) locations
   ↓                      ↓                      ↓
product_id            product_id           location_id
                      location_id          row_code, bin, shelf
```

## SQL Query ที่แก้ไขแล้ว

```sql
SELECT 
    p.product_id,
    p.name,
    p.sku,
    p.barcode,
    p.unit,
    p.image,
    COALESCE(SUM(ri.receive_qty), 0) AS total_stock,
    
    -- การแสดงตำแหน่ง (แก้ไขแล้ว)
    CASE 
        WHEN l.row_code IS NOT NULL AND l.bin IS NOT NULL AND l.shelf IS NOT NULL 
        THEN CONCAT(l.row_code, '-', l.bin, '-', l.shelf)
        WHEN l.description IS NOT NULL 
        THEN l.description
        ELSE 'ไม่ระบุตำแหน่ง'  -- ลบ WHEN p.location ออก
    END as location_display,
    
    l.description as location_description,
    
    -- Stock status logic
    CASE 
        WHEN COALESCE(SUM(ri.receive_qty), 0) > 100 THEN 'high'
        WHEN COALESCE(SUM(ri.receive_qty), 0) >= 20 THEN 'medium' 
        WHEN COALESCE(SUM(ri.receive_qty), 0) > 0 THEN 'low'
        ELSE 'out'
    END as stock_status
    
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
LEFT JOIN product_location pl ON pl.product_id = p.product_id    -- JOIN ตามที่ระบุ
LEFT JOIN locations l ON l.location_id = pl.location_id          -- JOIN ตามที่ระบุ

GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, p.image, 
         l.row_code, l.bin, l.shelf, l.description  -- ลบ p.location ออกจาก GROUP BY
         
HAVING total_stock >= 0
ORDER BY p.name
```

## การเปลี่ยนแปลงที่สำคัญ

### ❌ ที่ลบออก:
- `WHEN p.location IS NOT NULL THEN p.location` - คอลัมน์ไม่มีในตาราง
- `p.location` ใน GROUP BY clause

### ✅ ที่เก็บไว้:
- `LEFT JOIN product_location pl ON pl.product_id = p.product_id`
- `LEFT JOIN locations l ON l.location_id = pl.location_id`
- การแสดงผลแบบ `row_code-bin-shelf`
- Fallback เป็น `location.description`

## ผลลัพธ์การแสดงตำแหน่ง

### Priority ของการแสดงตำแหน่ง:
1. **First**: `A-1-2` (row_code-bin-shelf)
2. **Second**: `คลังหลัก` (description)
3. **Fallback**: `ไม่ระบุตำแหน่ง`

### ตัวอย่างผลลัพธ์:
```
สินค้า A → A-1-2 (คลังหลัก)
สินค้า B → คลังย่อย
สินค้า C → ไม่ระบุตำแหน่ง
```

## การทดสอบ

### ✅ Tests Passed:
- [x] PHP Syntax Check: No errors
- [x] Database Query: No SQL errors
- [x] Web Page Loading: Successful
- [x] Location Display: Working correctly
- [x] JOIN Operations: Data retrieved properly

### 🔍 Test Cases:
1. **สินค้ามีตำแหน่งครบ**: แสดง A-1-2
2. **สินค้ามีเฉพาะ description**: แสดงคำอธิบาย
3. **สินค้าไม่มีตำแหน่ง**: แสดง "ไม่ระบุตำแหน่ง"

## สรุป

การแก้ไขนี้ทำให้:
- ✅ หน้าเว็บทำงานได้ปกติ
- ✅ ไม่มี Database Error
- ✅ แสดงตำแหน่งสินค้าถูกต้อง
- ✅ ใช้โครงสร้างฐานข้อมูลที่มีอยู่จริง

**Status**: 🟢 Fixed Successfully
**Date**: October 13, 2025