# สรุปการแก้ไขปัญหา: วันหมดอายุไม่บันทึก (Expiry Date Not Saving)

**วันที่:** November 20, 2025  
**ปัญหา:** เมื่อแก้ไขรับสินค้าครั้งที่ 2 และกรอกวันหมดอายุ ไม่ได้บันทึกลงฐานข้อมูล  
**สาเหตุ:** Function parameter mismatch และการไหลของข้อมูลไม่ชัดเจน

---

## การแก้ไขที่ทำแล้ว

### 1. ✅ แก้ไข Function Signature (`receive_edit.php`)

**ไฟล์:** `receive/receive_edit.php` (บรรทัด 151)

**ปัญหา:**
```php
// ❌ ผิด - Parameter ไม่ตรง
function handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $expiry_date, $row_code, $bin, $shelf)
// แต่เรียก:
handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $row_code, $bin, $shelf);
```

**แก้ไข:**
```php
// ✅ ถูก - Parameter ตรงกับการเรียก
function handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, $remark, $row_code, $bin, $shelf)
```

### 2. ✅ เพิ่ม Detailed Debug Logging (`receive_edit.php`)

**ตำแหน่ง:** บรรทัด 20-32

**ติดตาม:**
- ค่า `expiry_date` ที่รับเข้ามา
- ความยาวและประเภท
- ว่าเป็น NULL หรือ empty string
- POST keys ที่ได้รับ
- Full POST array

**เพิ่มเติม:**
- Split data parsing (บรรทัด 62-74)
- Query execution results (บรรทัด 83, 180-184, 215)

### 3. ✅ เพิ่ม Client-side Logging (`receive_items_view.php`)

**ตำแหน่ง:** บรรทัด 1310-1320

**ติดตาม:**
- Form serialized data
- ค่า expiry_date field
- Field existence
- Split data (ถ้ามี)

---

## วิธีตรวจสอบ

### ขั้นที่ 1: ทำการแก้ไขรายการ
1. ไปยังหน้า **Receive Items**
2. คลิก **Edit** บนรายการที่ต้องการ (เลือกรายการที่ `expiry_date = NULL`)
3. กรอก/เปลี่ยน **วันหมดอายุ** (เช่น 2025-12-31)
4. คลิก **บันทึก**

### ขั้นที่ 2: ตรวจ Browser Console
```
F12 → Console tab → ดูผลลัพธ์
```

**ควรเห็น:**
```
=== FORM DATA BEING SENT ===
Form serialized data: receive_id=43&remark=...&expiry_date=2025-12-31&...
Expiry date field value: 2025-12-31
Expiry date field exists: true
Expiry date field name attr: expiry_date
```

**ถ้าเห็นปัญหา:**
- `Expiry date field value: ` (ว่าง) → Form ไม่มีค่า
- `expiry_date=` (ไม่มีค่า) → Serialize ไม่ได้ข้อมูล

### ขั้นที่ 3: ตรวจ PHP Error Log
ที่ XAMPP:
```
C:\xampp\apache\logs\error.log
```

**ค้นหา:**
```
=== RECEIVE_EDIT START ===
```

**ควรเห็น:**
```
=== RECEIVE_EDIT START ===
receive_id: 43
expiry_date raw: 'string' (10) "2025-12-31"
expiry_date length: 10
expiry_date is_null: no
expiry_date is_empty_string: no
POST expiry_date key exists: yes
FULL POST: {..., "expiry_date":"2025-12-31", ...}
Normal update executed. Expiry_date: 'string' (10) "2025-12-31", Rows affected: 1
```

### ขั้นที่ 4: ตรวจฐานข้อมูล
```sql
SELECT receive_id, item_id, po_id, receive_qty, expiry_date, created_at 
FROM receive_items 
WHERE receive_id = 43;
```

**ควรเห็น:**
```
receive_id: 43
expiry_date: 2025-12-31  (NOT NULL)
```

---

## การวิเคราะห์ผลลัพธ์

### สถานการณ์ 1: ✅ ทุกอย่างถูกต้อง
- Console: `expiry_date=2025-12-31`
- Error log: `expiry_date is_empty_string: no`
- Database: `expiry_date: 2025-12-31`
- **ผลลัพธ์:** ✅ ระบบกำลังทำงานได้แล้ว

### สถานการณ์ 2: ❌ Form field ว่าง
- Console: `Expiry date field value: ` (ว่าง)
- Error log: `expiry_date is_empty_string: yes`
- **ปัญหา:** User ไม่ได้กรอกข้อมูล
- **วิธีแก้:** ตรวจสอบว่าค่า expiry_date มี default value จากฟอร์ม

### สถานการณ์ 3: ❌ POST ไม่ได้รับค่า
- Console: `expiry_date=` (ไม่มี value)
- Error log: `POST expiry_date key exists: no`
- **ปัญหา:** jQuery serialize() ไม่เอาค่า field
- **วิธีแก้:** ตรวจสอบ form HTML structure

### สถานการณ์ 4: ❌ POST มีค่า แต่ DB เป็น NULL
- Console: `expiry_date=2025-12-31`
- Error log: `expiry_date is_empty_string: no`
- Database: `expiry_date: NULL`
- **ปัญหา:** SQL query ไม่ทำงาน หรือ Transaction rollback
- **วิธีแก้:** ตรวจสอบ MySQL error และ transaction handling

---

## File ที่สร้าง/แก้ไข

### 1. `receive_edit.php` ✅
- ✅ เพิ่ม detailed debug logging (บรรทัด 20-32)
- ✅ แก้ไข function signature (บรรทัด 151)
- ✅ เพิ่ม logging for split operations
- ✅ เพิ่ม logging for UPDATE queries

### 2. `receive_items_view.php` ✅
- ✅ เพิ่ม client-side console logging (บรรทัด 1310-1320)

### 3. `verify_expiry.php` ✅
- ✅ สร้าง diagnostic script

### 4. `EXPIRY_DATE_DEBUG_GUIDE.md` ✅
- ✅ Document รายละเอียด (ภาษาไทย)

### 5. `SUMMARY_EXPIRY_FIX.md` (นี่) ✅
- ✅ Summary เร็ว

---

## ขั้นตอนต่อไป

1. **ทำการทดสอบ** → ปฏิบัติตามขั้นตอนตรวจสอบ
2. **เก็บ Output** → Copy console output และ error log
3. **บอกผล** → ส่งผลลัพธ์มา
4. **แก้ปัญหาต่อไป** → ถ้า log แสดงปัญหา จะเป็นตำแหน่งแก้ไขต่อไป

---

## ข้อมูลเพิ่มเติม

**Query ตรวจสอบปัญจุบัน:**
```sql
-- เช็ค last 10 receive items
SELECT receive_id, item_id, po_id, receive_qty, expiry_date, 
       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
FROM receive_items 
ORDER BY receive_id DESC 
LIMIT 10;

-- เช็ค receive_id 43
SELECT * FROM receive_items WHERE receive_id = 43;
```

**File เก่า (สำหรับอ้างอิง):**
- `DEBUG_LOGGING_GUIDE.md` - Guide ก่อนหน้า

**Related Issues:**
- Image upload fix (ก่อนหน้า)
- Per-PO expiry date in splits (ก่อนหน้า)

---

**Status:** ✅ Ready for Testing

**ติดต่อ:** [เมื่อเสร็จการทดสอบ ให้บอกผลลัพธ์]
