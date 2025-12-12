# ขั้นตอนการตั้งค่าฟังก์ชั่นยกเลิกสินค้า

## อธิบาย
เพื่อให้ฟังก์ชั่นยกเลิกสินค้าทำงานได้อย่างถูกต้อง จำเป็นต้องเพิ่มคอลัมน์ใหม่ในตาราง `purchase_order_items`

## ขั้นตอนการติดตั้ง

### 1. เพิ่มคอลัมน์ในตาราง purchase_order_items

```sql
-- เพิ่มคอลัมน์สำหรับบันทึกการยกเลิก
ALTER TABLE purchase_order_items ADD COLUMN is_cancelled TINYINT(1) DEFAULT 0 AFTER qty;
ALTER TABLE purchase_order_items ADD COLUMN cancelled_by INT AFTER is_cancelled;
ALTER TABLE purchase_order_items ADD COLUMN cancelled_at DATETIME AFTER cancelled_by;
ALTER TABLE purchase_order_items ADD COLUMN cancel_reason VARCHAR(100) AFTER cancelled_at;
ALTER TABLE purchase_order_items ADD COLUMN cancel_notes TEXT AFTER cancel_reason;

-- เพิ่ม Foreign Key สำหรับ cancelled_by
ALTER TABLE purchase_order_items 
ADD CONSTRAINT fk_cancelled_by 
FOREIGN KEY (cancelled_by) REFERENCES users(user_id) ON DELETE SET NULL;
```

### 2. สร้างตาราง activity_logs (ถ้ายังไม่มี)

```sql
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100),
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

### 3. ตรวจสอบความเรียบร้อย

```sql
-- ตรวจสอบคอลัมน์ใหม่
DESCRIBE purchase_order_items;

-- ตรวจสอบว่าสามารถบันทึกข้อมูลได้หรือไม่
SELECT is_cancelled, cancelled_by, cancelled_at, cancel_reason, cancel_notes 
FROM purchase_order_items 
LIMIT 1;
```

## ฟีเจอร์ที่เพิ่มเข้ามา

### 1. UI Changes (ใน receive_po_items.php)
- เพิ่มปุ่ม "Clear" (ยกเลิก) ในคอลัมน์รับเข้าสินค้า
- เพิ่ม Modal สำหรับยกเลิกสินค้าพร้อมฟิลด์เหตุผล

### 2. Backend Changes (ใน process_receive_po.php)
- เพิ่ม action `cancel_item` เพื่อจัดการการยกเลิก
- บันทึกข้อมูลการยกเลิกลงในตาราง purchase_order_items
- บันทึก log ไปยังตาราง activity_logs

### 3. เหตุผลการยกเลิกที่มี
1. supplier_shortage - สินค้าไม่ครบตามจำนวนที่สั่ง
2. supplier_unavailable - ผู้จำหน่ายไม่มีสินค้า
3. product_discontinued - สินค้าถูกยกเลิก
4. wrong_order - ผิดข้อมูลสั่งซื้อ
5. customer_request - ตามคำขอของลูกค้า
6. other - อื่นๆ

## การใช้งาน

1. เปิดหน้าระบบรับเข้าสินค้า (receive_po_items.php)
2. คลิกปุ่ม "รับเข้า" หรือ "ดู" เพื่อเปิด modal
3. คลิกปุ่ม "Clear" (เครื่องหมาย ✕) เพื่อยกเลิกสินค้า
4. เลือกเหตุผลและระบุหมายเหตุเพิ่มเติม
5. คลิก "ยืนยันการยกเลิก" เพื่อบันทึก

## หมายเหตุ
- การยกเลิกจะทำเครื่องหมายให้สินค้าเป็น "cancelled"
- ไม่สามารถเปลี่ยนกลับได้หลังจากยกเลิก
- ทั้งหมดจะถูกบันทึกพร้อมชื่อผู้ใช้ที่ทำการยกเลิก
