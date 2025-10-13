# ระบบยิงสินค้าออก (การขายสินค้า) - IchoicePMS

## วันที่สร้าง: 13 ตุลาคม 2025

## ภาพรวมระบบ
ระบบยิงสินค้าออกเป็นฟีเจอร์สำหรับการจัดการการขายสินค้าและการออกสินค้าจากคลัง โดยใช้หลักการ FIFO (First In, First Out) ในการเลือกสินค้าที่จะออกก่อน

## คุณสมบัติหลัก

### 1. การกรอกแท็คส่งออก
- รองรับการสแกนหรือพิมพ์เลขแท็คส่งออก
- ใช้สำหรับติดตามและจัดกลุ่มการขายแต่ละครั้ง

### 2. การค้นหาและเลือกสินค้า
- สแกนบาร์โค้ดหรือพิมพ์ SKU บางส่วน
- แสดงรายการสินค้าที่มีสต็อกพร้อมข้อมูลล็อต
- เรียงลำดับตามหลัก FIFO (ล็อตเก่าก่อน, หมดอายุเร็วก่อน)

### 3. การจัดการจำนวน
- กรอกจำนวนที่ต้องการยิงออก
- ตรวจสอบสต็อกคงเหลือแบบ Real-time
- ป้องกันการยิงเกินจำนวนที่มีอยู่

### 4. การบันทึกข้อมูล
- บันทึกลงตาราง `issue_items`
- อัปเดตสต็อกในตาราง `receive_items`
- ระบบ Transaction เพื่อความปลอดภัยของข้อมูล

## โครงสร้างไฟล์

### หน้าหลัก
- **`issue/issue_product.php`** - หน้าหลักระบบยิงสินค้าออก

### API Endpoints
- **`api/product_search_api.php`** - ค้นหาสินค้าพร้อมข้อมูล FIFO
- **`api/issue_product_api.php`** - บันทึกการยิงสินค้าออก

### ตารางฐานข้อมูล
- **`issue_items`** - บันทึกรายการยิงสินค้าออก
- **`receive_items`** - ข้อมูลสต็อกที่ได้รับเข้ามา (จะถูกหักออก)

## โครงสร้างตาราง issue_items

```sql
CREATE TABLE `issue_items` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `receive_id` int(11) DEFAULT NULL,
  `issue_qty` int(11) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `issued_by` int(11) NOT NULL,
  `remark` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## การทำงานของระบบ FIFO

### 1. การค้นหาสินค้า
```sql
-- เรียงลำดับตาม FIFO
ORDER BY 
    p.name ASC,                -- ชื่อสินค้า
    ri.expiry_date ASC,        -- หมดอายุเร็วก่อน
    ri.created_at ASC          -- รับเข้าเก่าก่อน (FIFO)
```

### 2. การแสดงข้อมูลล็อต
- **ล็อตรับ**: วันที่รับเข้าสินค้า
- **วันหมดอายุ**: ถ้ามีข้อมูล
- **จำนวนคงเหลือ**: สต็อกที่สามารถยิงออกได้

### 3. การตรวจสอบสต็อก
- ตรวจสอบสต็อกคงเหลือก่อนยิงออก
- แจ้งเตือนหากจำนวนที่ต้องการเกินสต็อก
- ป้องกันการยิงสินค้าที่ไม่มีสต็อก

## ขั้นตอนการใช้งาน

### 1. กรอกแท็คส่งออก
```javascript
// เมื่อกด Enter จะเปิดส่วนสแกนสินค้า
$('#issue-tag').on('keypress', function(e) {
    if (e.which === 13) {
        // เริ่มกระบวนการยิงสินค้า
    }
});
```

### 2. ค้นหาและเลือกสินค้า
```javascript
// ค้นหาสินค้าแบบ Real-time
searchProducts(query);

// เลือกสินค้าจากรายการ
selectProduct(productId, name, sku, barcode, availableQty, receiveId, expiryDate, lotInfo);
```

### 3. ปรับจำนวนและยืนยัน
```javascript
// อัปเดตจำนวน
updateQuantity(index, newQty);

// ประมวลผลการยิงสินค้า
processIssue();
```

## การทำงานของ API

### Product Search API (`product_search_api.php`)
```php
// พารามิเตอร์พิเศษสำหรับยิงสินค้า
$type = 'issue';
$available_only = true;

// Query แบบ FIFO
SELECT p.product_id, ri.receive_id, ri.receive_qty as available_qty
FROM products p
INNER JOIN purchase_order_items poi ON poi.product_id = p.product_id
INNER JOIN receive_items ri ON ri.item_id = poi.item_id
WHERE ri.receive_qty > 0
ORDER BY ri.expiry_date ASC, ri.created_at ASC
```

### Issue Product API (`issue_product_api.php`)
```php
// Transaction เพื่อความปลอดภัย
$pdo->beginTransaction();

// 1. ตรวจสอบสต็อก
// 2. บันทึกใน issue_items
// 3. หักสต็อกใน receive_items
// 4. Commit หรือ Rollback

$pdo->commit();
```

## ระบบรักษาความปลอดภัย

### 1. Authentication
- ตรวจสอบ Session ก่อนเข้าใช้
- บันทึก User ID ผู้ที่ทำการยิงสินค้า

### 2. Data Validation
- ตรวจสอบข้อมูลทุกฟิลด์ก่อนบันทึก
- ป้องกัน SQL Injection ด้วย Prepared Statements
- ตรวจสอบจำนวนกับสต็อกที่มีอยู่

### 3. Transaction Safety
- ใช้ Database Transaction
- Rollback หากเกิดข้อผิดพลาด
- Log การทำงานเพื่อ Debug

## การจัดการข้อผิดพลาด

### 1. Validation Errors
```javascript
// ตรวจสอบข้อมูลก่อนส่ง
if (!currentIssueTag) {
    Swal.fire('แจ้งเตือน', 'กรุณากรอกแท็คส่งออก', 'warning');
    return;
}
```

### 2. API Errors
```php
// ส่งข้อความแจ้งข้อผิดพลาดที่เข้าใจง่าย
if ($available['receive_qty'] < $issue_qty) {
    $error_messages[] = "สินค้า {$available['name']} มีสต็อกไม่เพียงพอ";
}
```

### 3. Database Errors
```php
// Rollback และ Log ข้อผิดพลาด
catch (PDOException $e) {
    $pdo->rollBack();
    error_log('PDO Error: ' . $e->getMessage());
}
```

## User Interface Design

### 1. Responsive Design
- ใช้ Bootstrap 5 Grid System
- รองรับหน้าจอขนาดต่างๆ

### 2. Interactive Elements
- Hover effects บนปุ่มและการ์ด
- Smooth transitions และ animations
- Real-time feedback

### 3. Color Coding
- **สีน้ำเงิน**: ระบบหลัก, ปุ่มหลัก
- **สีเขียว**: FIFO badge, ข้อมูลสต็อก
- **สีส้ม**: แท็คส่งออก
- **สีแดง**: ปุ่มลบ, ข้อผิดพลาด

## การ Maintenance และ Monitoring

### 1. Log Files
- `../logs/api_errors.log` - Log ข้อผิดพลาด API
- Database transaction logs

### 2. Performance Monitoring
- ตรวจสอบประสิทธิภาพ Query
- Monitor API response time

### 3. Data Integrity
- ตรวจสอบความสอดคล้องของข้อมูลสต็อก
- Backup ข้อมูลสำคัญ

## การพัฒนาต่อยอด

### 1. Features ที่อาจเพิ่มเติม
- รายงานการขาย
- การพิมพ์ใบเสร็จ
- การคืนสินค้า
- การจองสินค้า

### 2. การปรับปรุง UI/UX
- Mobile-first design
- Progressive Web App (PWA)
- Offline capability

### 3. การรวมระบบ
- เชื่อมต่อกับระบบบัญชี
- API สำหรับระบบภายนอก
- การส่งออกข้อมูล

## การแก้ไขปัญหาที่อาจเกิดขึ้น

### 1. สินค้าไม่แสดงในการค้นหา
- ตรวจสอบว่ามีสต็อกหรือไม่
- ตรวจสอบการเชื่อมต่อตาราง
- ตรวจสอบเงื่อนไข WHERE

### 2. การยิงสินค้าไม่สำเร็จ
- ตรวจสอบ Database connection
- ดู Log files เพื่อหาสาเหตุ
- ตรวจสอบ User permissions

### 3. ข้อมูลสต็อกไม่ถูกต้อง
- รัน Data integrity check
- ตรวจสอบ Transaction history
- ซิงค์ข้อมูลใหม่

## สรุป
ระบบยิงสินค้าออกเป็นระบบที่ครอบคลุมและปลอดภัย พร้อมใช้งานจริงในสภาพแวดล้อม Production ด้วยการออกแบบที่เน้นความสะดวกในการใช้งานและความแม่นยำของข้อมูล