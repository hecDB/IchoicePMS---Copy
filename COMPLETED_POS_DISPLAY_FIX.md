# ปรับปรุง: แสดงการ์ด PO ที่รับครบแล้ว

## ปัญหาที่พบ
- ปุ่ม "รับครบแล้ว" ไม่แสดงข้อมูลการ์ด PO ที่สินค้ารับครบแล้ว
- ข้อมูล API ส่งกลับมาแต่ property names ไม่ตรงกับที่ UI ใช้

## การแก้ไข

### 1. ✅ อัปเดต `get_completed_pos.php`
**ไฟล์:** `receive/get_completed_pos.php`

เปลี่ยนจาก:
- Query ซับซ้อนหลายรอบ
- Property names ไม่สอดคล้อง

เป็น:
- Query เดี่ยว SQL ที่เหมาะสม
- Property names ชัดเจน:
  - `total_ordered_qty` - จำนวนสั่ง
  - `total_received_qty` - จำนวนรับแล้ว
  - `total_cancelled_qty` - จำนวนยกเลิก
  - `completion_rate` - เปอร์เซ็นต์สมบูรณ์

### 2. ✅ อัปเดต `displayCompletedPOs()` function
**ไฟล์:** `receive/receive_po_items.php`

เปลี่ยนจาก:
```javascript
// อ้างอิง property ที่ไม่มีอยู่
${po.received_items}  // ❌ ไม่มี
```

เป็น:
```javascript
// ใช้ property ที่ส่งมาจาก API
${po.total_received_qty}      // ✓
${po.total_cancelled_qty}     // ✓
${po.total_ordered_qty}       // ✓
${totalFulfilled}             // ✓ (received + cancelled)
```

### 3. ✅ เพิ่มข้อมูล `remark` ในการ์ด
เพิ่ม `data-remark="${escapeHtml(po.remark || '')}"` เพื่อให้รู้ว่าเป็น New Product Purchase หรือไม่

## UI Elements ที่อัปเดต

```html
<!-- แสดง 4 คอลัมน์ -->
<div class="col-6">
  <div class="small text-muted">จำนวนสั่ง</div>
  <div class="fw-bold">${total_ordered_qty}</div>
</div>

<div class="col-6">
  <div class="small text-muted">รับแล้ว</div>
  <div class="fw-bold text-success">${total_received_qty}</div>
</div>

<div class="col-6">
  <div class="small text-muted">ยกเลิก</div>
  <div class="fw-bold text-danger">${total_cancelled_qty}</div>
</div>

<div class="col-6">
  <div class="small text-muted">รวม</div>
  <div class="fw-bold text-info">${totalFulfilled}</div>
</div>
```

## ตัวอย่าง Response API

```json
{
  "success": true,
  "data": [
    {
      "po_id": 5,
      "po_number": "PO-20250001",
      "supplier_name": "บริษัท ABC",
      "po_date": "2024-12-01",
      "total_amount": 50000,
      "currency_code": "THB",
      "total_ordered_qty": 100,
      "total_received_qty": 70,
      "total_cancelled_qty": 30,
      "completion_rate": 100
    }
  ],
  "count": 1
}
```

## Workflow

1. **ผู้ใช้กดปุ่ม "รับครบแล้ว"**
   ↓
2. **Call AJAX → get_completed_pos.php**
   ↓
3. **API ส่งข้อมูล PO status='completed'**
   ↓
4. **displayCompletedPOs() แสดงการ์ด**
   - แสดงจำนวนสั่ง / รับแล้ว / ยกเลิก / รวม
   - ใช้สี: สีเขียวแสดงรับครบ
   - Progress circle: 100%
   ↓
5. **ผู้ใช้กดปุ่ม "ดูรายการ" เพื่อดูรายละเอียด**

## Status Filter Updates

- ✅ "พร้อมรับสินค้า" (ready): received=0 AND cancelled=0
- ✅ "รับบางส่วน" (partial): 0 < (received+cancelled) < ordered
- ✅ "รับครบแล้ว" (complete): (received+cancelled) >= ordered

## Files Changed
1. ✅ `receive/get_completed_pos.php` - Fix API response format
2. ✅ `receive/receive_po_items.php` - Fix displayCompletedPOs function

