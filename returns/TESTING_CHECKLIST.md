# ✅ Testing Checklist - ระบบสินค้าตีกลับ

## 📋 Checklist การทดสอบระบบ

### Phase 1: Database & Setup ✅

- [ ] เข้า `setup_return_items_table.php`
- [ ] ตรวจสอบการสร้างตาราง `return_reasons` สำเร็จ
- [ ] ตรวจสอบการสร้างตาราง `returned_items` สำเร็จ
- [ ] ตรวจสอบการ insert ข้อมูลเหตุผลเริ่มต้น (7 เหตุผล) สำเร็จ
- [ ] เข้า Database ผ่าน MySQL หรือ phpMyAdmin เพื่อตรวจสอบตาราง

**SQL Query to Verify:**
```sql
SELECT * FROM return_reasons;
SELECT COUNT(*) FROM returned_items;
```

---

### Phase 2: API Testing ✅

#### Test 1: Get Return Reasons
```
GET: /api/returned_items_api.php?action=get_reasons
```
- [ ] ได้รับ HTTP 200 OK
- [ ] Response มี 7 reasons
- [ ] Reasons แบ่งออกเป็น 2 category (returnable, non-returnable)

**Expected Response:**
```json
{
  "status": "success",
  "data": [
    {
      "reason_id": 1,
      "reason_code": "001",
      "reason_name": "จัดส่งไม่สำเร็จ",
      "is_returnable": 1,
      "category": "returnable"
    },
    ...
  ]
}
```

#### Test 2: Search PO
```
GET: /api/returned_items_api.php?action=search_po&keyword=PO-XXXX
```
- [ ] สามารถค้นหา PO ได้
- [ ] Response มี po_id, po_number, supplier_name, total_items
- [ ] ทำงานกับเลขที่ PO ที่มีอยู่จริง

#### Test 3: Get PO Items
```
GET: /api/returned_items_api.php?action=get_po_items&po_id=1
```
- [ ] ได้รับรายการสินค้าใน PO
- [ ] มี available_qty สำหรับการตีกลับ
- [ ] มี image, sku, barcode, product_name

#### Test 4: Create Return
```
POST: /api/returned_items_api.php
Body: {
  "action": "create_return",
  "po_id": 1,
  "item_id": 1,
  "product_id": 1,
  "return_qty": 5,
  "reason_id": 1,
  "notes": "Test note"
}
```
- [ ] HTTP 200 OK
- [ ] Response มี return_id และ return_code
- [ ] Return code format ถูกต้อง (RET-YYYY-MM-DD-XXXX)
- [ ] ข้อมูลถูกเก็บในฐานข้อมูล

#### Test 5: Get Returns List
```
GET: /api/returned_items_api.php?action=get_returns
```
- [ ] ได้รับรายการตีกลับ
- [ ] Filter by status ทำงาน
- [ ] Filter by is_returnable ทำงาน
- [ ] Pagination (limit/offset) ทำงาน

#### Test 6: Get Return Detail
```
GET: /api/returned_items_api.php?action=get_return&return_id=1
```
- [ ] ได้รับข้อมูลรายละเอียดการตีกลับ
- [ ] มี created_by_name, approved_by_name

#### Test 7: Approve Return
```
POST: /api/returned_items_api.php
Body: {
  "action": "approve_return",
  "return_id": 1
}
```
- [ ] Status เปลี่ยนเป็น "approved"
- [ ] approved_by และ approved_at ถูกตั้ง
- [ ] HTTP 200 OK

#### Test 8: Reject Return
```
POST: /api/returned_items_api.php
Body: {
  "action": "reject_return",
  "return_id": 1,
  "reason": "Test rejection"
}
```
- [ ] Status เปลี่ยนเป็น "rejected"
- [ ] หมายเหตุเพิ่มเติมใน notes
- [ ] HTTP 200 OK

---

### Phase 3: UI - Return Items Page ✅

#### Navigation & Layout
- [ ] หน้า `returns/return_items.php` โหลดได้
- [ ] แสดง 2 tabs: "บันทึกสินค้าตีกลับ" และ "รายการตีกลับ"
- [ ] ค้นหา PO box ชัดเจน
- [ ] Form สำหรับกรอกข้อมูลตีกลับ

#### Search PO Functionality
- [ ] ค้นหา PO ตามเลขที่ PO
- [ ] ได้รับผลลัพธ์จาก API
- [ ] สามารถเลือก PO ได้
- [ ] แสดงรายละเอียด PO ที่เลือก

#### Select Product
- [ ] แสดงรายการสินค้าใน PO ที่เลือก
- [ ] สามารถเลือกสินค้าได้
- [ ] Form เต็มไปด้วยข้อมูลสินค้า
- [ ] เน้นจำนวนที่สามารถตีกลับได้

#### Fill Return Form
- [ ] กรอกจำนวนตีกลับได้
- [ ] ตรวจสอบว่าจำนวนตีกลับ ≤ จำนวนที่สามารถตีกลับได้
- [ ] ค้นหาและเลือกเหตุผลได้
- [ ] แสดง badge เหตุผล (returnable/non-returnable)
- [ ] กรอกหมายเหตุได้

#### Submit Return
- [ ] คลิก "บันทึกสินค้าตีกลับ" ได้
- [ ] ได้รับ success message พร้อมเลขที่ตีกลับ
- [ ] Form reset หลังบันทึก
- [ ] ข้อมูลปรากฏในตาราง "รายการตีกลับ"

#### View Returns List
- [ ] Tab "รายการตีกลับ" แสดงตารางสินค้าตีกลับ
- [ ] Filter by status ทำงาน
- [ ] Filter by type (returnable/non-returnable) ทำงาน
- [ ] เรียงลำดับตามวันที่ล่าสุด

---

### Phase 4: UI - Return Dashboard ✅

#### Navigation & Layout
- [ ] หน้า `returns/return_dashboard.php` โหลดได้
- [ ] แสดง 4 stat cards
- [ ] ปุ่ม "บันทึกสินค้าตีกลับ" และ "รีเฟรช"
- [ ] ตาราง "รายการสินค้าตีกลับ"

#### Stat Cards
- [ ] Pending count ถูกต้อง
- [ ] Approved count ถูกต้อง
- [ ] Returnable count ถูกต้อง
- [ ] Non-returnable count ถูกต้อง

#### Returns Table
- [ ] แสดงรายการตีกลับทั้งหมด
- [ ] สามารถคลิกดูรายละเอียดได้
- [ ] Badge สถานะแสดงถูกต้อง
- [ ] Badge ประเภท (returnable/non-returnable) แสดงถูกต้อง

#### Detail Modal
- [ ] เปิด Modal รายละเอียดได้
- [ ] แสดงข้อมูลทั้งหมด
- [ ] หากสถานะ pending มีปุ่ม "อนุมัติ" และ "ปฏิเสธ"

#### Approve Action
- [ ] คลิก "อนุมัติ" ได้
- [ ] ต้อง confirm ก่อน
- [ ] Status เปลี่ยนเป็น "approved" ใน table
- [ ] ปุ่ม action หายไป

#### Reject Action
- [ ] คลิก "ปฏิเสธ" ได้
- [ ] ขึ้น prompt ให้ใส่เหตุผล
- [ ] Status เปลี่ยนเป็น "rejected" ใน table
- [ ] ปุ่ม action หายไป

#### Auto-Refresh
- [ ] Dashboard auto-refresh ทุก 30 วินาที (ถ้าหากตั้งค่าไว้)
- [ ] ข้อมูล refresh ใหม่
- [ ] ไม่มี error ใน Console

---

### Phase 5: Documentation ✅

- [ ] `QUICKSTART.php` โหลดได้
- [ ] `RETURN_SYSTEM_DOCUMENTATION.md` อ่านได้
- [ ] `README.md` มีข้อมูลครบครัน
- [ ] `RETURN_ITEMS_CENTER.php` เป็นศูนย์รวมเชื่อมโยง

---

### Phase 6: Security & Validation ✅

- [ ] ต้องเข้าสู่ระบบจึงเข้าได้
- [ ] ตรวจสอบ session user_id
- [ ] SQL Injection protection ✓ (ใช้ prepared statement)
- [ ] XSS protection ✓ (ใช้ htmlspecialchars)
- [ ] CSRF protection ✓ (ใช้ session)

---

### Phase 7: Error Handling ✅

#### Invalid Input
- [ ] ส่ง invalid po_id ได้ error
- [ ] ส่ง invalid item_id ได้ error
- [ ] ส่ง return_qty > available_qty ได้ error
- [ ] ส่ง missing required fields ได้ error

#### Database Errors
- [ ] Database disconnect ได้ error message
- [ ] Invalid SQL ได้ error message

#### Network Errors
- [ ] Connection timeout แสดง error
- [ ] API offline แสดง error

---

### Phase 8: Browser Compatibility ✅

- [ ] Chrome - ทดสอบได้
- [ ] Firefox - ทดสอบได้
- [ ] Safari - ทดสอบได้
- [ ] Edge - ทดสอบได้

---

### Phase 9: Performance ✅

- [ ] Page load time < 2 seconds
- [ ] API response time < 1 second
- [ ] Table การแสดง 100 rows ราบรื่น
- [ ] Auto-refresh ไม่ใช้ memory มาก

---

### Phase 10: Data Integrity ✅

#### Database
- [ ] Foreign key constraints ทำงาน
- [ ] Duplicate return codes ไม่เกิด
- [ ] Timestamps ถูกต้อง

#### Return Workflow
- [ ] Cannot approve non-pending return
- [ ] Cannot reject non-pending return
- [ ] Cannot create return with qty > available

---

## 🐛 Bug Report Template

หากพบ bug ให้บันทึก:

```
[ ] Timestamp: ________________
[ ] Severity: [ ] Critical [ ] High [ ] Medium [ ] Low
[ ] Component: [ ] Setup [ ] API [ ] UI [ ] Doc
[ ] Browser: ________________
[ ] URL: ________________
[ ] Step to Reproduce:
  1. ________________
  2. ________________
  3. ________________

[ ] Expected Result: ________________
[ ] Actual Result: ________________
[ ] Error Message: ________________
[ ] Console Error: ________________
[ ] Network Error: ________________
```

---

## ✅ Sign-off Checklist

- [ ] ทั้งหมด API endpoints ทำงาน
- [ ] ทั้งหมด UI pages ทำงาน
- [ ] ทั้งหมด user interactions ทำงาน
- [ ] ไม่มี console errors
- [ ] ไม่มี bugs ที่สำคัญ
- [ ] สามารถใช้งานจริงได้
- [ ] เอกสารครบครัน

---

**Testing Date:** ________________  
**Tested By:** ________________  
**Status:** [ ] PASS [ ] FAIL [ ] PARTIAL

---

**Notes:**
```
_________________________________________________
_________________________________________________
_________________________________________________
```

---

🎉 **ขอบคุณที่ทดสอบระบบสินค้าตีกลับ!**
