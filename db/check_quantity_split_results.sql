-- SQL Script สำหรับตรวจสอบการทำงานของระบบแบ่งจำนวนสินค้า
-- ไฟล์: check_quantity_split_results.sql

-- 1. ตรวจสอบรายการรับสินค้าที่ถูกแบ่งจำนวน
SELECT 
    'รายการที่แบ่งจาก PO เดิม' as category,
    r.receive_id,
    r.receive_qty,
    r.remark,
    r.created_at,
    po.po_number,
    p.sku,
    p.name as product_name,
    CONCAT(u.name, ' (ID: ', r.created_by, ')') as created_by_info
FROM receive_items r
LEFT JOIN purchase_orders po ON r.po_id = po.po_id
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN products p ON poi.product_id = p.product_id
LEFT JOIN users u ON r.created_by = u.user_id
WHERE r.remark LIKE '%แบ่งจาก PO เดิม%'
ORDER BY r.created_at DESC
LIMIT 20;

-- 2. ตรวจสอบรายการรับสินค้าล่าสุด (รวมที่แบ่งและไม่แบ่ง)
SELECT 
    'รายการรับสินค้าล่าสุด' as category,
    r.receive_id,
    r.receive_qty,
    r.remark,
    r.created_at,
    po.po_number,
    p.sku,
    p.name as product_name,
    CASE 
        WHEN r.remark LIKE '%แบ่งจาก PO เดิม%' THEN 'แบ่งจาก PO เดิม'
        ELSE 'รายการปกติ'
    END as item_type
FROM receive_items r
LEFT JOIN purchase_orders po ON r.po_id = po.po_id
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN products p ON poi.product_id = p.product_id
ORDER BY r.created_at DESC
LIMIT 30;

-- 3. สถิติการแบ่งจำนวน
SELECT 
    'สถิติการแบ่งจำนวน' as category,
    COUNT(*) as total_split_items,
    SUM(ABS(r.receive_qty)) as total_split_quantity,
    COUNT(DISTINCT r.po_id) as affected_po_count,
    COUNT(DISTINCT poi.product_id) as affected_product_count,
    MIN(r.created_at) as first_split_time,
    MAX(r.created_at) as last_split_time
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
WHERE r.remark LIKE '%แบ่งจาก PO เดิม%';

-- 4. ตรวจสอบความสมบูรณ์ของข้อมูล PO ที่เกี่ยวข้อง
SELECT 
    'ข้อมูล PO ที่เกี่ยวข้องกับการแบ่งจำนวน' as category,
    po.po_id,
    po.po_number,
    po.supplier_id,
    s.name as supplier_name,
    COUNT(poi.item_id) as total_items,
    COUNT(r.receive_id) as received_items,
    SUM(poi.ordered_qty) as total_ordered,
    SUM(COALESCE(ri.total_received, 0)) as total_received_qty
FROM purchase_orders po
LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
LEFT JOIN receive_items r ON poi.item_id = r.item_id AND r.remark LIKE '%แบ่งจาก PO เดิม%'
LEFT JOIN (
    SELECT item_id, SUM(ABS(receive_qty)) as total_received 
    FROM receive_items 
    GROUP BY item_id
) ri ON poi.item_id = ri.item_id
WHERE po.po_id IN (
    SELECT DISTINCT r2.po_id 
    FROM receive_items r2 
    WHERE r2.remark LIKE '%แบ่งจาก PO เดิม%'
)
GROUP BY po.po_id, po.po_number, po.supplier_id, s.name
ORDER BY po.po_number;

-- 5. ตรวจสอบ transaction integrity (รายการที่แบ่งในเวลาเดียวกัน)
SELECT 
    'รายการที่แบ่งในเวลาใกล้เคียงกัน (ภายใน 1 นาที)' as category,
    DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:00') as time_group,
    COUNT(*) as items_count,
    GROUP_CONCAT(DISTINCT po.po_number ORDER BY po.po_number) as po_numbers,
    GROUP_CONCAT(DISTINCT p.sku ORDER BY p.sku) as product_skus,
    SUM(ABS(r.receive_qty)) as total_quantity
FROM receive_items r
LEFT JOIN purchase_orders po ON r.po_id = po.po_id
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN products p ON poi.product_id = p.product_id
WHERE r.remark LIKE '%แบ่งจาก PO เดิม%'
GROUP BY DATE_FORMAT(r.created_at, '%Y-%m-%d %H:%i:00')
HAVING COUNT(*) > 1
ORDER BY time_group DESC;

-- 6. ตรวจสอบความถูกต้องของจำนวน
SELECT 
    'ตรวจสอบความสมเหตุสมผลของจำนวน' as category,
    r.receive_id,
    r.receive_qty,
    poi.ordered_qty,
    CASE 
        WHEN ABS(r.receive_qty) > poi.ordered_qty THEN 'มีปัญหา: จำนวนรับมากกว่าที่สั่ง'
        WHEN r.receive_qty = 0 THEN 'มีปัญหา: จำนวนรับเป็น 0'
        ELSE 'ปกติ'
    END as status,
    po.po_number,
    p.sku,
    p.name as product_name
FROM receive_items r
LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
LEFT JOIN purchase_orders po ON r.po_id = po.po_id
LEFT JOIN products p ON poi.product_id = p.product_id
WHERE r.remark LIKE '%แบ่งจาก PO เดิม%'
  AND (ABS(r.receive_qty) > poi.ordered_qty OR r.receive_qty = 0)
ORDER BY r.created_at DESC;

-- 7. รายงานสรุป
SELECT 
    'รายงานสรุประบบแบ่งจำนวนสินค้า' as category,
    (SELECT COUNT(*) FROM receive_items WHERE remark LIKE '%แบ่งจาก PO เดิม%') as total_split_records,
    (SELECT COUNT(DISTINCT po_id) FROM receive_items WHERE remark LIKE '%แบ่งจาก PO เดิม%') as affected_po_count,
    (SELECT COUNT(DISTINCT created_by) FROM receive_items WHERE remark LIKE '%แบ่งจาก PO เดิม%') as users_used_feature,
    (SELECT DATE_FORMAT(MIN(created_at), '%Y-%m-%d %H:%i:%s') FROM receive_items WHERE remark LIKE '%แบ่งจาก PO เดิม%') as first_usage,
    (SELECT DATE_FORMAT(MAX(created_at), '%Y-%m-%d %H:%i:%s') FROM receive_items WHERE remark LIKE '%แบ่งจาก PO เดิม%') as last_usage
FROM dual;