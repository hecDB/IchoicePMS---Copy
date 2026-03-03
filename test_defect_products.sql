-- ═══════════════════════════════════════════════════════════════════════════
-- SQL Queries สำหรับตรวจสอบการบันทึกสินค้าชำรุดแบบขายได้
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. ตรวจสอบสินค้าชำรุดใหม่ที่สร้างในตาราง products
-- Note: ตาราง products ไม่มี column 'stock'
SELECT 
    product_id, 
    name, 
    sku, 
    barcode, 
    unit,
    is_active, 
    created_at, 
    created_by,
    product_category_id,
    category_name
FROM products 
WHERE sku LIKE 'ตำหนิ-%' 
ORDER BY created_at DESC 
LIMIT 10;

-- 2. ตรวจสอบรายการในตาราง purchase_order_items
-- Note: ตาราง purchase_order_items ไม่มี columns 'remark', 'origin_remark'
SELECT 
    poi.item_id, 
    poi.po_id, 
    poi.product_id, 
    poi.qty, 
    poi.price_per_unit,
    poi.sale_price,
    poi.total,
    poi.created_at,
    p.sku, 
    p.name
FROM purchase_order_items poi
JOIN products p ON poi.product_id = p.product_id
WHERE p.sku LIKE 'ตำหนิ-%'
ORDER BY poi.created_at DESC 
LIMIT 10;

-- 3. ตรวจสอบการรับเข้าในตาราง receive_items
-- Note: ตาราง receive_items ไม่มี 'product_id', 'receive_date', 'received_by', 'notes'
--       แต่มี 'item_id', 'po_id', 'created_at', 'created_by', 'remark'
SELECT 
    ri.receive_id, 
    ri.item_id, 
    ri.po_id,
    ri.receive_qty, 
    ri.remark,
    ri.created_by,
    ri.created_at,
    poi.product_id,
    p.sku, 
    p.name
FROM receive_items ri
JOIN purchase_order_items poi ON ri.item_id = poi.item_id
JOIN products p ON poi.product_id = p.product_id  
WHERE p.sku LIKE 'ตำหนิ-%'
ORDER BY ri.created_at DESC 
LIMIT 10;

-- 4. ตรวจสอบสินค้าชำรุดพร้อม PO และการรับเข้าแบบครบถ้วน
SELECT 
    p.product_id,
    p.sku,
    p.name,
    p.barcode,
    p.is_active,
    p.created_at as product_created_at,
    poi.item_id,
    poi.po_id,
    poi.qty as po_qty,
    ri.receive_id,
    ri.receive_qty,
    ri.remark,
    ri.created_at as receive_created_at
FROM products p
LEFT JOIN purchase_order_items poi ON p.product_id = poi.product_id
LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
WHERE p.sku LIKE 'ตำหนิ-%'
ORDER BY p.created_at DESC
LIMIT 10;

-- 5. สรุปจำนวนสินค้าชำรุดแต่ละ PO
SELECT 
    poi.po_id,
    COUNT(DISTINCT poi.product_id) as defect_products_count,
    SUM(poi.qty) as total_defect_qty,
    SUM(ri.receive_qty) as total_received_qty
FROM purchase_order_items poi
JOIN products p ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
WHERE p.sku LIKE 'ตำหนิ-%'
GROUP BY poi.po_id
ORDER BY poi.po_id DESC;

-- 6. ตรวจสอบ returned_items ที่เชื่อมโยงกับสินค้าชำรุด
SELECT 
    ri.return_id,
    ri.return_code,
    ri.return_status,
    ri.product_name,
    ri.sku as original_sku,
    ri.new_sku,
    ri.new_barcode,
    ri.new_product_id,
    ri.return_qty,
    ri.restock_qty,
    ri.po_id,
    ri.po_number,
    ri.defect_notes,
    ri.created_at
FROM returned_items ri
WHERE ri.reason_id = 8  -- สินค้าชำรุดบางส่วน
    AND ri.return_status = 'completed'
    AND ri.new_sku LIKE 'ตำหนิ-%'
ORDER BY ri.created_at DESC
LIMIT 10;

-- 7. ตรวจสอบ stock ของสินค้าชำรุด (คำนวณจาก receive_items)
-- Note: ตาราง products ไม่มี column 'stock' ต้องคำนวณจาก receive_items
SELECT 
    p.product_id,
    p.sku,
    p.name,
    p.barcode,
    COALESCE(SUM(ri.receive_qty), 0) as total_received,
    p.created_at
FROM products p
LEFT JOIN purchase_order_items poi ON p.product_id = poi.product_id
LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
WHERE p.sku LIKE 'ตำหนิ-%'
GROUP BY p.product_id, p.sku, p.name, p.barcode, p.created_at
ORDER BY p.created_at DESC;

-- 8. ล้างข้อมูล defect products สำหรับทดสอบใหม่ (ระวัง! จะลบข้อมูลทั้งหมด)
/*
-- ยกเลิก comment เพื่อใช้งาน
DELETE ri FROM receive_items ri
JOIN purchase_order_items poi ON ri.item_id = poi.item_id
JOIN products p ON poi.product_id = p.product_id
WHERE p.sku LIKE 'ตำหนิ-%';

DELETE FROM purchase_order_items
WHERE product_id IN (SELECT product_id FROM products WHERE sku LIKE 'ตำหนิ-%');

DELETE FROM products WHERE sku LIKE 'ตำหนิ-%';

UPDATE returned_items 
SET return_status = 'pending', 
    new_product_id = NULL,
    new_sku = NULL,
    new_barcode = NULL
WHERE reason_id = 8 AND new_sku LIKE 'ตำหนิ-%';
*/
