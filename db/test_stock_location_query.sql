-- SQL สำหรับทดสอบการแสดงตำแหน่งสินค้าในหน้าสินค้าคงคลัง
-- ไฟล์: test_stock_location_query.sql

-- 1. ทดสอบ Query หลักที่แก้ไขแล้ว
SELECT 
    p.product_id,
    p.name,
    p.sku,
    p.barcode,
    p.unit,
    COALESCE(SUM(ri.receive_qty), 0) AS total_stock,
    
    -- การแสดงตำแหน่ง (แก้ไขแล้ว - ลบ p.location ออก)
    CASE 
        WHEN l.row_code IS NOT NULL AND l.bin IS NOT NULL AND l.shelf IS NOT NULL 
        THEN CONCAT(l.row_code, '-', l.bin, '-', l.shelf)
        WHEN l.description IS NOT NULL 
        THEN l.description
        ELSE 'ไม่ระบุตำแหน่ง'
    END as location_display,
    
    l.description as location_description,
    l.row_code,
    l.bin,
    l.shelf
    
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
LEFT JOIN product_location pl ON pl.product_id = p.product_id
LEFT JOIN locations l ON l.location_id = pl.location_id

GROUP BY p.product_id, p.name, p.sku, p.barcode, p.unit, 
         l.row_code, l.bin, l.shelf, l.description
         
HAVING total_stock >= 0
ORDER BY p.name
LIMIT 10;

-- 2. ตรวจสอบโครงสร้างตาราง products
DESCRIBE products;

-- 3. ตรวจสอบโครงสร้างตาราง product_location
DESCRIBE product_location;

-- 4. ตรวจสอบโครงสร้างตาราง locations
DESCRIBE locations;

-- 5. ตรวจสอบความสัมพันธ์ระหว่างตาราง
SELECT 
    'products-product_location' as relationship,
    COUNT(*) as total_relations
FROM products p
INNER JOIN product_location pl ON p.product_id = pl.product_id

UNION ALL

SELECT 
    'product_location-locations' as relationship,
    COUNT(*) as total_relations
FROM product_location pl
INNER JOIN locations l ON pl.location_id = l.location_id

UNION ALL

SELECT 
    'products with locations' as relationship,
    COUNT(DISTINCT p.product_id) as total_relations
FROM products p
INNER JOIN product_location pl ON p.product_id = pl.product_id
INNER JOIN locations l ON pl.location_id = l.location_id;

-- 6. ตัวอย่างข้อมูลตำแหน่งที่มีอยู่
SELECT 
    l.location_id,
    l.row_code,
    l.bin,
    l.shelf,
    l.description,
    COUNT(pl.product_id) as products_count
FROM locations l
LEFT JOIN product_location pl ON l.location_id = pl.location_id
GROUP BY l.location_id, l.row_code, l.bin, l.shelf, l.description
ORDER BY l.row_code, l.bin, l.shelf;

-- 7. สินค้าที่มีตำแหน่งและไม่มีตำแหน่ง
SELECT 
    'มีตำแหน่ง' as status,
    COUNT(DISTINCT p.product_id) as product_count
FROM products p
INNER JOIN product_location pl ON p.product_id = pl.product_id
INNER JOIN locations l ON pl.location_id = l.location_id

UNION ALL

SELECT 
    'ไม่มีตำแหน่ง' as status,
    COUNT(*) as product_count
FROM products p
LEFT JOIN product_location pl ON p.product_id = pl.product_id
WHERE pl.product_id IS NULL;

-- 8. ตัวอย่างรูปแบบการแสดงตำแหน่งต่างๆ
SELECT 
    p.name as product_name,
    l.row_code,
    l.bin,
    l.shelf,
    l.description,
    CASE 
        WHEN l.row_code IS NOT NULL AND l.bin IS NOT NULL AND l.shelf IS NOT NULL 
        THEN CONCAT(l.row_code, '-', l.bin, '-', l.shelf)
        WHEN l.description IS NOT NULL 
        THEN l.description
        ELSE 'ไม่ระบุตำแหน่ง'
    END as location_display,
    'รูปแบบการแสดง' as note
FROM products p
LEFT JOIN product_location pl ON p.product_id = pl.product_id
LEFT JOIN locations l ON pl.location_id = l.location_id
LIMIT 20;

-- 9. ตรวจสอบว่ามีคอลัมน์ location ในตาราง products หรือไม่
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'products' 
  AND COLUMN_NAME LIKE '%location%';

-- 10. Performance check - ตรวจสอบประสิทธิภาพของ Query
EXPLAIN SELECT 
    p.product_id,
    p.name,
    COALESCE(SUM(ri.receive_qty), 0) AS total_stock,
    CASE 
        WHEN l.row_code IS NOT NULL AND l.bin IS NOT NULL AND l.shelf IS NOT NULL 
        THEN CONCAT(l.row_code, '-', l.bin, '-', l.shelf)
        WHEN l.description IS NOT NULL 
        THEN l.description
        ELSE 'ไม่ระบุตำแหน่ง'
    END as location_display
FROM products p
LEFT JOIN purchase_order_items poi ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON ri.item_id = poi.item_id
LEFT JOIN product_location pl ON pl.product_id = p.product_id
LEFT JOIN locations l ON l.location_id = pl.location_id
GROUP BY p.product_id, p.name, l.row_code, l.bin, l.shelf, l.description
HAVING total_stock >= 0
ORDER BY p.name
LIMIT 5;