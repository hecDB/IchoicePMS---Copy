# Testing Guide - Data Refresh Fix

## ทดสอบการดึงข้อมูล

### 1. บันทึกผู้สั่งซื้อ (Edit User)
**ขั้นตอน:**
1. เปิด PO ที่มีข้อมูลแล้ว
2. คลิก "แก้ไข" ที่ส่วนผู้สั่งซื้อ
3. เปลี่ยนผู้สั่งซื้อเป็นคนอื่น
4. คลิก "บันทึก"

**ผลที่คาดหวัง:**
- ✅ Popup ยังเปิดอยู่
- ✅ ชื่อผู้สั่งซื้อเปลี่ยน
- ✅ รายการสินค้า ผู้จำหน่าย ทั้งหมดแสดงผล
- ✅ Success alert ปรากฏ
- ✅ ปิด popup → เปิดใหม่ → ข้อมูลถูกต้อง

---

### 2. บันทึกผู้จำหน่าย (Edit Supplier)
**ขั้นตอน:**
1. เปิด PO 
2. คลิก "แก้ไข" ที่ส่วนผู้จำหน่าย
3. เปลี่ยนผู้จำหน่าย
4. คลิก "บันทึก"

**ผลที่คาดหวัง:**
- ✅ ชื่อผู้จำหน่ายเปลี่ยน
- ✅ บันทึกแล้ว! alert แสดง
- ✅ Popup ปิดเอง (optional)
- ✅ เปิด PO ใหม่ → ผู้จำหน่ายถูกต้อง

---

### 3. แก้ไขรายการสินค้า (Edit Item)
**ขั้นตอน:**
1. เปิด PO → คลิก "แก้ไขรายการ"
2. เปลี่ยนจำนวน: 5 → 10
3. เปลี่ยนราคา: 100 → 150
4. คลิก "บันทึก" (ไอคอนถูก)

**ผลที่คาดหวัง:**
- ✅ โต๊ะยังแสดง edit mode
- ✅ แล้วหลังจากเซิร์ฟเวอร์ตอบกลับ (1-2 วินาที)
- ✅ จำนวนเปลี่ยนเป็น 10
- ✅ ราคาเปลี่ยนเป็น 150
- ✅ **รวมทั้งหมด = 10 × 150 = 1500** ← ตรวจสอบ
- ✅ บันทึกแล้ว! toast alert (มุมขวาบน)

---

### 4. เพิ่มรายการสินค้าใหม่ (Add New Item)
**ขั้นตอน:**
1. เปิด PO → คลิก "เพิ่มสินค้า"
2. พิมพ์ชื่อสินค้า (ใช้ autocomplete)
3. ใส่จำนวน: 5
4. ใส่ราคา: 200
5. คลิก "เพิ่ม"

**ผลที่คาดหวัง:**
- ✅ Dialog ยังเปิดสักครู่
- ✅ เพิ่มแล้ว! alert แสดง 2 วินาที
- ✅ รายการใหม่ปรากฏในตาราทันที
- ✅ **รวมทั้งหมด = รวมเก่า + 5 × 200** ← ตรวจสอบ

---

### 5. ลบรายการสินค้า (Delete Item)
**ขั้นตอน:**
1. เปิด PO → คลิก "แก้ไขรายการ"
2. คลิก "ลบ" ที่รายการใดๆ
3. ยืนยันการลบ

**ผลที่คาดหวัง:**
- ✅ รายการหายจากตาราทันที
- ✅ **รวมทั้งหมด = รวมเก่า - (จำนวน × ราคา)** ← ตรวจสอบ
- ✅ ยุบกลับ popup ← จะแสดง PO ใหม่ที่อัปเดต

---

### 6. เปลี่ยนสกุลเงิน (Currency)
**ขั้นตอน:**
1. เปิด PO (บันทึกสินค้าพร้อม)
2. คลิก "แก้ไขรายการ"
3. เปลี่ยนสกุลเงินของรายการ
4. บันทึก

**ผลที่คาดหวัง:**
- ✅ ราคาคำนวณใหม่ตามอัตราแลกเปลี่ยน
- ✅ รวมทั้งหมด = รวมแรก × exchange_rate ← ตรวจสอบ
- ✅ โต้แลกเปลี่ยนแสดงในสกุลเงินใหม่

---

## Troubleshooting

### ❌ โปรแกรมยังแสดงข้อมูลเก่า
**สาเหตุ:** Cache ของเบราว์เซอร์
**วิธีแก้:** 
- กด Ctrl + F5 (Hard Refresh)
- หรือ Ctrl + Shift + Delete (Clear Cache)
- เปิด popup ใหม่

### ❌ Alert ไม่แสดง Success
**สาเหตุ:** API ล้มเหลว
**ตรวจสอบ:**
- เปิด Developer Tools (F12)
- ดู Network tab → ตรวจสอบ API response
- ดู Console tab → error message
- ดู server logs: `logs/api_errors.log`

### ❌ Popup ไม่ยุบกลับ
**สาเหตุ:** ผลลัพธ์ HTTP error หรือ JSON parsing fail
**วิธีแก้:**
- ปิด popup ด้วยเอง (ปุ่ม X)
- ตรวจสอบ browser console (F12)
- ล้างข้อมูล cache

### ❌ ข้อมูลหายไปหรือเผิด
**สาเหตุ:** Database transaction fail
**ตรวจสอบ:**
- ตรวจ SQL logs ใน phpMyAdmin
- ตรวจสอบสิทธิ์ในตาราฐานข้อมูล
- รีสตาร์ท MySQL service

---

## API Response Inspection

ถ้าต้องการดู raw data ที่เซิร์ฟเวอร์ส่งกลับ:

```javascript
// เปิด Browser Console (F12)
// เข้า Network tab → ดูคำขอไปยัง purchase_order_api.php
// ดูใน Response tab
```

**ข้อมูลที่คาดหวัง:**
```json
{
  "order": {
    "po_id": 1,
    "po_number": "PO-2025-00001",
    "order_date": "2025-11-16",
    "total_amount": "1500.00",
    "status": "pending",
    "supplier_id": 5,
    "ordered_by": 3,
    "currency_id": 1
  },
  "items": [
    {
      "item_id": 1,
      "qty": 10,
      "price_per_unit": 150,
      "total": 1500
    }
  ],
  "user": {
    "user_id": 3,
    "name": "สมชาย"
  },
  "supplier": {
    "supplier_id": 5,
    "name": "บริษัท ABC"
  },
  "currencies": [...]
}
```

ถ้าขาด field ใด → ไม่จะ render ได้

---

## Database Verification

เพื่อยืนยันว่าข้อมูลบันทึกถูกต้องในฐานข้อมูล:

```sql
-- ตรวจสอบ purchase_orders
SELECT po_id, po_number, supplier_id, ordered_by, total_amount 
FROM purchase_orders 
WHERE po_id = ? 
ORDER BY po_id DESC LIMIT 1;

-- ตรวจสอบ purchase_order_items
SELECT item_id, qty, price_per_unit, total 
FROM purchase_order_items 
WHERE po_id = ? 
ORDER BY item_id ASC;
```

---

## Success Criteria ✅

- [x] ทุกการบันทึก ข้อมูลทั้งหมดอัปเดตอัตโนมัติ
- [x] ไม่เห็นข้อมูลเก่าจาก client-side
- [x] Alert ปรากฏเพียงครั้งเดียว
- [x] Popup สามารถปิด/เปิดใหม่ได้ดี
- [x] ข้อมูลหลังปิด popup ถูกต้อง

---

**Status**: Ready for QA Testing ✅
