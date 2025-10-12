# การแบ่งจำนวนสินค้าใน PO - คู่มือการใช้งาน

## ภาพรวมของคุณสมบัติ

ระบบการแบ่งจำนวนสินค้าได้รับการพัฒนาเพื่อจัดการกรณีที่ต้องการแก้ไข PO ของรายการรับสินค้า แต่ PO ปลายทางมีจำนวนคงเหลือไม่เพียงพอ ระบบจะช่วยแบ่งจำนวนสินค้าไปยัง PO หลายตัวได้อัตโนมัติ และบันทึกรายการความเคลื่อนไหวในฐานข้อมูลได้อย่างสมบูรณ์

## คุณสมบัติหลัก

### 1. การตรวจสอบจำนวนอัตโนมัติ
- ระบบจะตรวจสอบจำนวนคงเหลือใน PO ปลายทางเมื่อผู้ใช้เลือกเปลี่ยน PO
- หากจำนวนที่ต้องการรับมากกว่าจำนวนคงเหลือ ระบบจะแสดง Modal สำหรับแบ่งจำนวน

### 2. UI/UX ที่เป็นมิตรกับผู้ใช้
- Modal แสดงข้อมูลสรุปการแบ่งจำนวนแบบ Real-time
- แสดงสถานะการคำนวณ (จำนวนรวม, จำนวนที่จัดสรรแล้ว, จำนวนคงเหลือ)
- ปุ่มเพิ่ม PO เพิ่มเติมพร้อมการเลือกจากรายการที่มีอยู่

### 3. การบันทึกข้อมูลที่สมบูรณ์
- รายการรับสินค้าเดิมจะถูกอัปเดตเป็น PO หลักด้วยจำนวนที่แบ่งให้
- สร้างรายการรับสินค้าใหม่สำหรับแต่ละ PO เพิ่มเติม
- รักษาข้อมูลผู้สร้าง วันที่ และข้อมูลอื่นๆ ไว้ครบถ้วน

## ไฟล์ที่เกี่ยวข้อง

### Backend Files
- `receive/receive_edit.php` - จัดการการบันทึกข้อมูลการแบ่งจำนวน
- `api/get_po_for_product.php` - API สำหรับดึงรายการ PO ที่เกี่ยวข้องกับสินค้า

### Frontend Files
- `receive/receive_items_view.php` - หน้าหลักที่มี Modal และ JavaScript สำหรับการแบ่งจำนวน

### Database Files
- `db/check_quantity_split_results.sql` - SQL script สำหรับตรวจสอบผลลัพธ์

### Test Files
- `receive/test_quantity_split.html` - หน้าทดสอบและเอกสารการใช้งาน

## การทำงานของระบบ

### 1. Frontend Process
```javascript
// เมื่อผู้ใช้เลือก PO ที่จำนวนไม่เพียงพอ
if (Math.abs(currentReceiveQty) > remainingQty && remainingQty > 0) {
    showQuantitySplitModal(poId, itemId, poNumber, unitCost, remainingQty, Math.abs(currentReceiveQty));
}

// การเตรียมข้อมูลสำหรับส่งไป Backend
const splitData = {
    mainPoId: mainSplit.poId,
    mainItemId: mainSplit.itemId,
    mainQty: mainSplit.quantity,
    additionalPOs: [...] // รายการ PO เพิ่มเติม
};
```

### 2. Backend Process
```php
// ใน receive_edit.php
if ($is_split && $split_data) {
    $splitInfo = json_decode($split_data, true);
    handleQuantitySplit($pdo, $receive_id, $originalData, $splitInfo, ...);
}

// ใน handleQuantitySplit()
// 1. ตรวจสอบความถูกต้องของข้อมูล
// 2. อัปเดตรายการเดิม
// 3. สร้างรายการใหม่สำหรับ PO เพิ่มเติม
```

### 3. Database Changes
```sql
-- รายการเดิมจะถูกอัปเดต
UPDATE receive_items SET 
    receive_qty = [จำนวนที่แบ่งให้ PO หลัก],
    po_id = [ID ของ PO หลัก],
    item_id = [ID ของรายการใน PO หลัก]
WHERE receive_id = [ID เดิม];

-- สร้างรายการใหม่สำหรับแต่ละ PO เพิ่มเติม
INSERT INTO receive_items (po_id, item_id, receive_qty, remark, created_by, created_at)
VALUES ([PO ID], [Item ID], [จำนวน], '[หมายเหตุ] (แบ่งจาก PO เดิม)', [ผู้สร้างเดิม], NOW());
```

## วิธีการทดสอบ

### 1. ขั้นตอนการทดสอบพื้นฐาน
1. เปิดหน้า `receive_items_view.php`
2. คลิกปุ่มแก้ไขรายการรับสินค้าใดๆ
3. คลิกปุ่ม "เปลี่ยน" ข้างช่องเลข PO
4. เลือก PO ที่มีจำนวนคงเหลือน้อยกว่าจำนวนที่ต้องการรับ
5. ระบบจะแสดง Modal แบ่งจำนวน
6. กรอกจำนวนที่ต้องการแบ่งให้แต่ละ PO
7. คลิก "ยืนยันการแบ่ง" และ "บันทึก"

### 2. การตรวจสอบผลลัพธ์
```sql
-- ดูรายการที่แบ่งจาก PO เดิม
SELECT * FROM receive_items 
WHERE remark LIKE '%แบ่งจาก PO เดิม%' 
ORDER BY created_at DESC;
```

### 3. การตรวจสอบความถูกต้อง
- ใช้ SQL script ใน `db/check_quantity_split_results.sql`
- ตรวจสอบผลรวมจำนวนก่อนและหลังการแบ่ง
- ตรวจสอบข้อมูล PO และผู้สร้างรายการ

## การจัดการข้อผิดพลาด

### 1. Validation
- ตรวจสอบจำนวนรวมที่แบ่งต้องเท่ากับจำนวนเดิม
- ตรวจสอบความครบถ้วนของข้อมูล PO
- ตรวจสอบสิทธิ์การเข้าถึง

### 2. Transaction Safety
- ใช้ Database Transaction เพื่อความปลอดภัย
- Rollback อัตโนมัติเมื่อเกิดข้อผิดพลาด
- Logging เพื่อการ Debug

### 3. Error Messages
- ข้อความแจ้งเตือนเป็นภาษาไทยที่เข้าใจง่าย
- แสดงรายละเอียดข้อผิดพลาดใน Console (สำหรับ Developer)

## ข้อมูลเทคนิค

### Dependencies
- **Frontend:** Bootstrap 5, jQuery, SweetAlert2
- **Backend:** PHP 7.4+, PDO, MySQL
- **Database:** ตาราง receive_items, purchase_orders, purchase_order_items

### Browser Support
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

### Performance
- ใช้ AJAX แบบ Asynchronous
- Database Transaction สำหรับการบันทึกที่ปลอดภัย
- Real-time calculation โดยไม่ต้องโหลดหน้าใหม่

## การบำรุงรักษา

### 1. Log Files
- PHP Error Log: การทำงานของ Backend
- Browser Console: การทำงานของ Frontend
- Database Query Log: การเปลี่ยนแปลงข้อมูล

### 2. Monitoring
- ตรวจสอบรายการที่แบ่งผิดปกติ (จำนวนไม่สมเหตุสมผล)
- ตรวจสอบ Transaction ที่ค้างหรือล้มเหลว
- ตรวจสอบการใช้งานของผู้ใช้

### 3. Backup Strategy
- สำรองข้อมูลก่อนการอัปเดตใหญ่
- เก็บ Log การเปลี่ยนแปลงไว้อย่างน้อย 30 วัน
- ทดสอบการ Restore เป็นประจำ

---

## ติดต่อสำหรับการสนับสนุน
หากพบปัญหาหรือต้องการข้อมูลเพิ่มเติม กรุณาติดต่อทีมพัฒนา พร้อมแนบ:
1. ขั้นตอนการทำซ้ำปัญหา
2. Screenshot หรือ Error Message
3. ข้อมูล Browser และ Environment