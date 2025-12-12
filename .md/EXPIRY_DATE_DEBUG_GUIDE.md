# การแก้ไขปัญหา: วันหมดอายุไม่บันทึก (Expiry Date Not Saving)

## ปัญหา
เมื่อแก้ไขรายการรับสินค้าครั้งที่ 2 ขึ้นไป กรอกวันหมดอายุแล้ว แต่ไม่ได้บันทึกลงฐานข้อมูล

## การแก้ไขที่ทำแล้ว

### 1. แก้ไข Function Signature (receive_edit.php)
**ปัญหา:** Function `handleQuantitySplit()` มีพารามิเตอร์เกินที่ไม่ได้ใช้
**วิธีแก้:** ลบพารามิเตอร์ `$expiry_date` ออก (บรรทัดที่ 146)

### 2. เพิ่ม Debug Logging (receive_edit.php)
**วัตถุประสงค์:** ติดตามการไหลของข้อมูลจากฟอร์มไปฐานข้อมูล

**ข้อมูลที่ log:**
- ค่า expiry_date ที่รับเข้ามา (raw, length, type)
- All POST keys และ full $_POST array
- Split data JSON parsing
- UPDATE/INSERT query results

### 3. เพิ่ม Client-side Logging (receive_items_view.php)
**วัตถุประสงค์:** ติดตามการส่งข้อมูลจาก JavaScript form

**ข้อมูลที่ log:**
- Form serialized data
- ค่า expiry_date field
- Field existence check
- Split data (ถ้ามี)

## วิธีทำการตรวจสอบ

### ขั้นตอนที่ 1: เปิด Developer Tools
```
F12 → Console tab
```

### ขั้นตอนที่ 2: แก้ไขรายการรับสินค้า
1. ไปยังหน้า Receive Items
2. คลิก Edit บนรายการที่ต้องการ
3. กรอก/ตรวจสอบ "วันหมดอายุ"
4. คลิก "บันทึก"

### ขั้นตอนที่ 3: ตรวจ Console Output
ที่ Console ของ browser ควรเห็น log:
```
=== FORM DATA BEING SENT ===
Form serialized data: receive_id=43&remark=...&expiry_date=2025-11-30&...
Expiry date field value: 2025-11-30
Expiry date field exists: true
Expiry date field name attr: expiry_date
```

**ถ้าเห็น:**
- `Expiry date field value: [empty]` → Form field ว่าง
- `Expiry date field exists: false` → Field ไม่มี (HTML issue)
- `expiry_date=` ในฟอร์ม → ค่าไม่ถูกส่ง

### ขั้นตอนที่ 4: ตรวจสอบ PHP Error Log
ตำแหน่ง log file (Windows):
- `php_errors.log` - ที่เดียวกับ `php.ini`
- หรือเช็ก phpinfo() ที่ `error_log` directive

ใน XAMPP:
```
C:\xampp\apache\logs\error.log
```

ใน Server Linux:
```
/var/log/php_errors.log
```

**ค้นหา log ด้วย:**
```bash
# Linux
grep "RECEIVE_EDIT START" /path/to/error.log | tail -20

# Windows - เปิด error log file ใน text editor
```

### ขั้นตอนที่ 5: วิเคราะห์ Log Output

**ตัวอย่าง Output ที่ดี:**
```
=== RECEIVE_EDIT START ===
receive_id: 43
expiry_date raw: 'string' (24) "2025-11-30"
expiry_date length: 10
expiry_date is_null: no
expiry_date is_empty_string: no
receive_qty: 5
remark: รับสินค้า PO
POST keys: receive_id, remark, price_per_unit, sale_price, receive_qty, expiry_date, row_code, bin, shelf, po_id, item_id, location_desc
POST expiry_date key exists: yes
FULL POST: {"receive_id":"43","remark":"...","expiry_date":"2025-11-30",...}
Normal update executed. Expiry_date: 'string' (24) "2025-11-30", Rows affected: 1
```

**ปัญหา: expiry_date เป็น empty string**
```
expiry_date raw: 'string' (0) ""
expiry_date is_empty_string: yes
```
→ Form field ว่าง หรือไม่ได้กรอก

**ปัญหา: expiry_date key ไม่มี**
```
POST expiry_date key exists: no
POST keys: receive_id, remark, price_per_unit, ...
```
→ Form serialize ไม่ได้เอาค่า (jQuery issue)

## การทำงานของระบบ (Flow)

```
User Input (Form)
        ↓
JavaScript serialize form ($('#edit-form').serialize())
        ↓
POST to receive_edit.php
        ↓
PHP receives $_POST['expiry_date']
        ↓
UPDATE query: SET expiry_date=? 
        ↓
Database: receive_items.expiry_date
```

## ข้อความทั่วไป

### Q: ทำไม receive_id 42 มีวันหมดอายุแต่ 43 ไม่มี?
**A:** ระบบไม่ได้บันทึกค่า NULL เมื่อแก้ไข receive_id 43 ครั้งที่ 2

### Q: วันหมดอายุแสดงในตารางแต่ไม่บันทึก?
**A:** UI แสดงจากตาราง HTML ใช้ JavaScript extract ค่า (ไม่ได้มาจาก DB จริง)

### Q: จะทำการสำรองข้อมูลได้ไหม?
**A:** SQL query เพื่อ check current state:
```sql
SELECT receive_id, item_id, po_id, receive_qty, expiry_date, created_at 
FROM receive_items 
WHERE receive_id IN (42, 43);
```

## File ที่แก้ไข

1. **receive_edit.php**
   - บรรทัดที่ 10-30: Detailed debug logging
   - บรรทัดที่ 62-74: Split data logging
   - บรรทัดที่ 83: Normal update logging
   - บรรทัดที่ 146: Function signature fix
   - บรรทัดที่ 176-180: Split update logging
   - บรรทัดที่ 209-211: Additional PO logging

2. **receive_items_view.php**
   - บรรทัดที่ 1304-1312: Client-side form data logging

## ลิงก์ที่เกี่ยวข้อง

- verify_expiry.php - Script ตรวจสอบข้อมูลในฐานข้อมูล
- DEBUG_LOGGING_GUIDE.md - Guide เดิม (อ้างอิง)

## ขั้นตอนต่อไป

1. **Test** - ลองแก้ไขรายการและกรอกวันหมดอายุ
2. **Collect** - ส่วนต่างออกมาจาก Console และ Error Log
3. **Report** - บอกผลลัพธ์
4. **Fix** - ทำการแก้ไขตามที่ log บอก
