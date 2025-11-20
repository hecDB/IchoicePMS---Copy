# ✅ Checklist สำหรับแก้ไขปัญหา Expiry Date

## การแก้ไขที่ทำแล้ว (Completed)

✅ **Fixed Function Signature**
- ลบพารามิเตอร์ `$expiry_date` ออกจาก `handleQuantitySplit()`
- ฟังก์ชันจะใช้ `mainExpiryDate` จาก `$splitInfo` แทน

✅ **Added Detailed Backend Logging**
- Log ค่า expiry_date ที่รับเข้า
- Log ทุก POST keys
- Log UPDATE/INSERT results

✅ **Added Client-side Logging**
- Log form serialized data
- Log field value, existence, name
- Log split data

---

## วิธีการทดสอบ

### 1. ⬜ เปิด Browser Console
```
F12 → Console
```

### 2. ⬜ ไปหน้า Receive Items
```
URL: .../receive/receive_items_view.php
```

### 3. ⬜ คลิก Edit บนรายการ
```
เลือกรายการที่ expiry_date = NULL
เช่น receive_id 43
```

### 4. ⬜ กรอกวันหมดอายุ
```
เลือกวันที่ (เช่น 2025-12-31)
```

### 5. ⬜ กดบันทึก
```
คลิก "บันทึก"
```

### 6. ⬜ ตรวจ Console Output
```
ดูหน้าต่าง Console ของ browser
ค้นหา: "=== FORM DATA BEING SENT ==="
```

### 7. ⬜ ตรวจ PHP Error Log
```
XAMPP: C:\xampp\apache\logs\error.log
ค้นหา: "=== RECEIVE_EDIT START ==="
```

### 8. ⬜ ตรวจฐานข้อมูล
```sql
SELECT * FROM receive_items WHERE receive_id = 43;
```

### 9. ⬜ บอกผลลัพธ์
```
ส่ง screenshot หรือ output ของ:
1. Browser console
2. PHP error log
3. Database query result
```

---

## ผลลัพธ์ที่คาดหวัง ✅

### Browser Console
```
=== FORM DATA BEING SENT ===
Form serialized data: receive_id=43&remark=...&expiry_date=2025-12-31&...
Expiry date field value: 2025-12-31
Expiry date field exists: true
Expiry date field name attr: expiry_date
```

### PHP Error Log
```
=== RECEIVE_EDIT START ===
receive_id: 43
expiry_date raw: 'string' (10) "2025-12-31"
expiry_date is_empty_string: no
Normal update executed. Expiry_date: 'string' (10) "2025-12-31", Rows affected: 1
```

### Database
```
receive_id: 43
expiry_date: 2025-12-31 (NOT NULL)
```

---

## ถ้าปัญหายังมี ❌

**บอกข้อมูล:**
1. Console output (copy ทั้งหมด)
2. Error log entries (copy ทั้งหมด)
3. Database query result (screenshot หรือ text)
4. Exact steps ที่ทำ

**พร้อมรายละเอียด:**
- ใช้ค่า expiry_date เท่าไร
- ฐานข้อมูลเป็น MySQL ไหน
- PHP version เท่าไร

---

## ลิงค์ที่เกี่ยวข้อง

📄 `SUMMARY_EXPIRY_FIX.md` - Summary สมบูรณ์  
📄 `EXPIRY_DATE_DEBUG_GUIDE.md` - Debug guide ละเอียด  
📄 `verify_expiry.php` - Script ตรวจสอบข้อมูล  

---

**Status:** Ready for Testing ✅  
**Last Updated:** 2025-11-20

---

### ข้อมูลเพิ่มเติม

**ฐาน:** MySQL `receive_items` table  
**Column:** `expiry_date` (DATE or NULL)  
**Type:** User input → Form → POST → Database  

**Function Flow:**
- Normal update: UPDATE receive_items SET expiry_date=? 
- Split update: UPDATE main PO + INSERT additional POs (each with own expiry_date)

**Latest Changes:**
- handleQuantitySplit() signature: 8 parameters (was 9)
- Logging: 11 debug points total
- Validation: Form field serialization check
