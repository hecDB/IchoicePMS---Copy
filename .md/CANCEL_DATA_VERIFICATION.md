# ตรวจสอบและแก้ไขการบันทึกข้อมูลการยกเลิกสินค้า

## ปัญหาที่พบ

### 1. ❌ การยกเลิกสินค้าไม่ได้บันทึก cancel_qty ให้ถูกต้อง
**ไฟล์:** `receive/process_receive_po.php` (บรรทัด 313)
**สาเหตุ:** ส่งพารามิเตอร์ผิดลำดับใน prepared statement
```php
// ผิด:
$cancel_stmt->execute([
    $cancel_qty,
    $cancel_qty,        // ❌ ส่ง $cancel_qty ซ้ำสองครั้ง
    $user_id,
    $cancel_reason,
    $cancel_notes,
    $item_id
]);

// ถูก:
$cancel_stmt->execute([
    $cancel_qty,
    $user_id,           // ✓ ถูก
    $cancel_reason,
    $cancel_notes,
    $item_id
]);
```

### 2. ❌ ตาราง Modal ไม่แสดงจำนวนที่ยกเลิก
**ไฟล์:** `receive/receive_po_items.php`
**ปัญหา:** 
- ไม่มีคอลัมน์ "ยกเลิก" ในตาราง
- ไม่แสดงข้อมูล `cancel_qty` ให้ผู้ใช้เห็น

## การแก้ไขที่ทำ

### ✓ แก้ไขที่ 1: ตรวจสอบคอลัมน์ในตาราง purchase_order_items
ยืนยันว่ามีคอลัมน์ทั้งหมดดังต่อไปนี้:
- `is_cancelled` - flag สำหรับรายการที่ยกเลิก
- `is_partially_cancelled` - flag สำหรับรายการที่ยกเลิกบางส่วน
- `cancel_qty` - จำนวนที่ยกเลิก ✓
- `cancelled_by` - user_id ของผู้ยกเลิก ✓
- `cancelled_at` - เวลาการยกเลิก ✓
- `cancel_reason` - เหตุผลการยกเลิก ✓
- `cancel_notes` - หมายเหตุเพิ่มเติม ✓

### ✓ แก้ไขที่ 2: แก้ไขพารามิเตอร์ใน cancel_item (partial)
ไฟล์: `receive/process_receive_po.php` (บรรทัด 313)
- เปลี่ยนจาก: `[$cancel_qty, $cancel_qty, ...]` 
- เป็น: `[$cancel_qty, $user_id, ...]`

### ✓ แก้ไขที่ 3: เพิ่มคอลัมน์แสดง "ยกเลิก" ในตาราง
ไฟล์: `receive/receive_po_items.php`
- เพิ่มคอลัมน์ table header "ยกเลิก" (width 7%)
- แสดงข้อมูล `cancel_qty` ในแต่ละแถว
- แสดงเหตุผลการยกเลิกเมื่อ hover ด้วย title attribute
- ทำให้แถวของรายการที่ยกเลิก มีสีแดงหรี่ (background #fee2e2)

### ✓ แก้ไขที่ 4: API ดึงข้อมูลให้ถูกต้อง
ยืนยันว่า `api/get_po_items.php` ดึง cancel_qty อย่างถูกต้อง:
```php
COALESCE(poi.cancel_qty, 0) as cancel_qty,
poi.cancel_reason,
poi.cancel_notes,
```

## ตัวอย่างการทำงานที่ถูกต้อง

### Scenario 1: ยกเลิกสินค้าทั้งหมด
```
สินค้า: กล่องกระดาษ A4
จำนวนสั่ง: 100
รับแล้ว: 0
ยกเลิก: 100 (เหตุผล: supplier_cancel)
--> สถานะ: สมบูรณ์ (0 + 100 >= 100 ✓)
```

### Scenario 2: ยกเลิกบางส่วน
```
สินค้า: กล่องกระดาษ A4
จำนวนสั่ง: 100
รับแล้ว: 50
ยกเลิก: 30
คงเหลือ: 20
--> สถานะ: บางส่วน (50 + 30 = 80, < 100)
--> สามารถรับเพิ่ม: 20
```

### Scenario 3: สมบูรณ์ (รับ + ยกเลิก)
```
สินค้า: กล่องกระดาษ A4
จำนวนสั่ง: 100
รับแล้ว: 70
ยกเลิก: 30
--> สถานะ: สมบูรณ์ (70 + 30 >= 100 ✓)
```

## การตรวจสอบข้อมูล

เรียก URL: `/test_cancel_data.php`
หรือในเทอร์มินัล:
```bash
php test_cancel_data.php
```

มันจะแสดง:
1. ✓ ข้อมูล purchase_order_items ที่ยกเลิก (มี cancel_qty)
2. ✓ ตรวจสอบว่า receive_items ไม่มีรายการที่ยกเลิก
3. ✓ สถานะการรับสินค้า (received + cancelled >= ordered)

## ที่ต้องทำต่อ

- [ ] ทดสอบ UI โดยการยกเลิกสินค้าจากหน้า receive
- [ ] ตรวจสอบว่าข้อมูล cancel_qty ปรากฏในตาราง modal
- [ ] ตรวจสอบสถานะปรับปรุงตามสูตร (received + cancelled >= ordered)
- [ ] ทดสอบรับสินค้าหลังจากยกเลิกบางส่วน

