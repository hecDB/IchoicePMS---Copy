# ปรับปรุง: การระบุจำนวนสินค้าที่ยกเลิก

## ปัญหาที่พบ
- `cancel_qty` เก็บจำนวนทั้งหมดของสินค้าที่สั่ง แทนที่จะเก็บจำนวนที่ยกเลิกจริง
- ไม่มีการตรวจสอบจำนวนที่ยกเลิก กับจำนวนที่คงเหลือรับได้

## การแก้ไข

### 1. ✅ อัปเดต Cancel Item Modal UI
**ไฟล์:** `receive/receive_po_items.php`

เพิ่มช่องกรอกจำนวน:
```html
<label for="cancelQuantity" class="form-label fw-bold">จำนวนที่จะยกเลิก *</label>
<div class="input-group">
    <input type="number" 
           class="form-control" 
           id="cancelQuantity" 
           name="cancel_qty"
           min="0.01" 
           step="0.01"
           placeholder="0"
           required>
    <span class="input-group-text" id="cancelQtyUnit"></span>
</div>
```

แสดงข้อมูล:
- รับได้อีก: `${maxCancelQty.toLocaleString()} หน่วย`
- หน่วย: `${unit}`

### 2. ✅ เพิ่มการตรวจสอบ Input
**ฟังก์ชัน:** `showCancelItemModal()`

ตรวจสอบว่า:
- จำนวนที่กรอก > 0
- จำนวนที่กรอก ≤ จำนวนที่รับได้อีก
- แสดง validation message "✓ จำนวนถูกต้อง"

### 3. ✅ อัปเดต Backend Logic
**ไฟล์:** `receive/process_receive_po.php`

เปลี่ยนจาก:
```php
// เก่า: ส่ง cancel_qty แต่ไม่ใช้
$cancel_qty = (float)($_POST['cancel_qty'] ?? 0);

// ยกเลิกทั้งหมด = ordered_qty
if ($cancel_type === 'cancel_all') {
    $cancel_stmt->execute([
        $ordered_qty,  // ❌ เก็บจำนวนทั้งหมด
        ...
    ]);
}
```

เป็น:
```php
// ใหม่: ตรวจสอบ cancel_qty กับจำนวนคงเหลือ
$received_qty = (ได้จากระบบ);
$remaining_qty = $ordered_qty - $received_qty;

if ($cancel_type === 'cancel_partial') {
    // ตรวจสอบ: 0 < cancel_qty ≤ remaining_qty
    if ($cancel_qty <= 0) throw new Exception('จำนวนต้อง > 0');
    if ($cancel_qty > $remaining_qty) throw new Exception('เกินจำนวนที่คงเหลือ');
}

if ($cancel_type === 'cancel_all') {
    // ใช้ remaining_qty
    $cancel_qty = $remaining_qty;
}

// บันทึกจำนวนที่ถูกต้อง
$cancel_stmt->execute([
    $cancel_qty,  // ✅ จำนวนที่ยกเลิก จริง
    $user_id,
    $cancel_reason,
    $cancel_notes,
    $item_id
]);
```

### 4. ✅ อัปเดต Frontend Data Submission
เปลี่ยนจาก:
```javascript
// เก่า: ส่งเพื่อยกเลิกทั้งหมด
data: {
    action: 'cancel_item',
    cancel_type: 'cancel_all',  // ไม่ส่ง cancel_qty
    ...
}
```

เป็น:
```javascript
// ใหม่: ส่งจำนวนที่ยกเลิก
data: {
    action: 'cancel_item',
    cancel_type: 'cancel_partial',  // ส่งจำนวนที่กรอก
    cancel_qty: cancelQty,
    ...
}
```

## ตัวอย่างการทำงาน

### Scenario: ยกเลิกสินค้าบางส่วน
```
สินค้า: กล่องกระดาษ A4
จำนวนสั่ง: 100
รับแล้ว: 30
รับได้อีก: 70

ผู้ใช้:
1. กดปุ่ม "ยกเลิก" ✕
2. โมดัลแสดง:
   - รับได้อีก: 70 หน่วย
   - ช่องกรอก: [    ] หน่วย
3. กรอก: 20
4. ระบบตรวจสอบ: 0 < 20 ≤ 70 ✓
5. เลือกเหตุผล: "สินค้าเสียหาย"
6. กดยืนยัน
7. ระบบบันทึก: cancel_qty = 20

ผลลัพธ์:
- received_qty: 30
- cancel_qty: 20 ← ✅ จำนวนที่แท้จริง
- สถานะ: บางส่วน (30 + 20 = 50 < 100)
- สามารถรับเพิ่มได้: 50 หน่วย
```

## การตรวจสอบข้อมูล

### Query ตรวจสอบ
```sql
SELECT 
    poi.item_id,
    p.name as product_name,
    poi.qty as ordered_qty,
    COALESCE(SUM(ri.receive_qty), 0) as received_qty,
    poi.cancel_qty as cancelled_qty,
    (poi.qty - COALESCE(SUM(ri.receive_qty), 0) - poi.cancel_qty) as remaining_qty,
    poi.cancel_reason,
    poi.cancel_notes,
    poi.cancelled_at
FROM purchase_order_items poi
LEFT JOIN products p ON poi.product_id = p.product_id
LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
WHERE poi.cancel_qty > 0
GROUP BY poi.item_id
ORDER BY poi.cancelled_at DESC;
```

### ตรวจสอบความถูกต้อง
- ✓ `cancel_qty` ≤ `qty` (ไม่เกินจำนวนสั่ง)
- ✓ `received_qty + cancel_qty` ≤ `qty` (ไม่ซ้ำกัน)
- ✓ ไม่มีข้อมูลใน `receive_items` ที่ซ้ำกับ cancel

## API Response

### ก่อน (ผิด)
```json
{
    "success": true,
    "message": "ยกเลิกสินค้าทั้งหมดสำเร็จ"
}
// บันทึก: cancel_qty = 100 (จำนวนทั้งหมด) ❌
```

### หลังจาก (ถูก)
```json
{
    "success": true,
    "message": "ยกเลิกสินค้า 20 หน่วยสำเร็จ"
}
// บันทึก: cancel_qty = 20 (จำนวนจริง) ✓
```

## Activity Log
```
action: cancel_po_item
description: ยกเลิกสินค้าจาก PO 20250001 (cancel_partial): เหตุผล=damaged, จำนวน=20
```

## Files Changed
1. ✅ `receive/receive_po_items.php`
   - เพิ่ม cancel quantity input field
   - เพิ่ม validation logic
   - เพิ่ม unit display
   
2. ✅ `receive/process_receive_po.php`
   - ตรวจสอบ cancel_qty กับ remaining_qty
   - เก็บจำนวนที่ยกเลิก จริง
   - อัปเดต log description

