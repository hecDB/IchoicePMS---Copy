# การปรับปรุงเงื่อนไขสถานะสต็อก

## วันที่: 13 ตุลาคม 2025

## สรุปการเปลี่ยนแปลง
ปรับปรุงเงื่อนไขการแสดงสถานะสต็อกในหน้า "สินค้าคงคลังทั้งหมด" ให้เข้มงวดมากขึ้น

## เงื่อนไขเดิม vs เงื่อนไขใหม่

### เงื่อนไขเดิม:
- **สต็อกเพียงพอ**: มากกว่า 100 ชิ้น 
- **สต็อกปานกลาง**: 20-100 ชิ้น
- **สต็อกต่ำ**: 1-19 ชิ้น
- **หมด**: 0 ชิ้น

### เงื่อนไขใหม่:
- **สต็อกเพียงพอ**: มากกว่า 10 ชิ้น
- **สต็อกปานกลาง**: 2-10 ชิ้น  
- **สต็อกต่ำ**: เหลือ 1 ชิ้น
- **หมด**: 0 ชิ้น

## ไฟล์ที่ถูกแก้ไข

### 1. `all_stock.php`

#### ส่วน SQL Query - stock_status CASE statement:
```sql
-- เดิม
CASE 
    WHEN COALESCE(SUM(ri.receive_qty), 0) > 100 THEN 'high'
    WHEN COALESCE(SUM(ri.receive_qty), 0) >= 20 THEN 'medium' 
    WHEN COALESCE(SUM(ri.receive_qty), 0) > 0 THEN 'low'
    ELSE 'out'
END as stock_status

-- ใหม่
CASE 
    WHEN COALESCE(SUM(ri.receive_qty), 0) > 10 THEN 'high'
    WHEN COALESCE(SUM(ri.receive_qty), 0) BETWEEN 2 AND 10 THEN 'medium' 
    WHEN COALESCE(SUM(ri.receive_qty), 0) = 1 THEN 'low'
    ELSE 'out'
END as stock_status
```

#### ส่วน Stats SQL Query:
```sql
-- เดิม
SUM(CASE WHEN total_stock > 100 THEN 1 ELSE 0 END) as high_stock,
SUM(CASE WHEN total_stock BETWEEN 20 AND 100 THEN 1 ELSE 0 END) as medium_stock,  
SUM(CASE WHEN total_stock BETWEEN 1 AND 19 THEN 1 ELSE 0 END) as low_stock,

-- ใหม่
SUM(CASE WHEN total_stock > 10 THEN 1 ELSE 0 END) as high_stock,
SUM(CASE WHEN total_stock BETWEEN 2 AND 10 THEN 1 ELSE 0 END) as medium_stock,  
SUM(CASE WHEN total_stock = 1 THEN 1 ELSE 0 END) as low_stock,
```

#### ส่วน Stats Cards Display Text:
```html
<!-- เดิม -->
<div class="stats-subtitle">มากกว่า 100 ชิ้น</div>
<div class="stats-subtitle">20-100 ชิ้น</div>
<div class="stats-subtitle">น้อยกว่า 20 ชิ้น</div>

<!-- ใหม่ -->
<div class="stats-subtitle">มากกว่า 10 ชิ้น</div>
<div class="stats-subtitle">2-10 ชิ้น</div>
<div class="stats-subtitle">เหลือ 1 ชิ้น</div>
```

## ผลกระทบจากการเปลี่ยนแปลง

### 1. การแสดงผลในตาราง:
- สินค้าที่มี 11+ ชิ้น จะแสดงเป็น "สต็อกเพียงพอ" (สีเขียว)
- สินค้าที่มี 2-10 ชิ้น จะแสดงเป็น "สต็อกปานกลาง" (สีเหลือง)
- สินค้าที่มี 1 ชิ้น จะแสดงเป็น "สต็อกต่ำ" (สีแดง)

### 2. การนับสถิติ:
- สถิติแต่ละประเภทจะเปลี่ยนไปตามเงื่อนไขใหม่
- การ์ดสถิติจะแสดงจำนวนที่ถูกต้องตามเงื่อนไขใหม่

### 3. ข้อควรระวัง:
- สินค้าที่เคยแสดงเป็น "สต็อกเพียงพอ" (เช่น 50 ชิ้น) ยังคงแสดงเป็น "สต็อกเพียงพอ"
- สินค้าที่เคยแสดงเป็น "สต็อกปานกลาง" (เช่น 25 ชิ้น) จะเปลี่ยนเป็น "สต็อกเพียงพอ"
- สินค้าที่เคยแสดงเป็น "สต็อกต่ำ" (เช่น 15 ชิ้น) จะเปลี่ยนเป็น "สต็อกเพียงพอ"
- สินค้าที่เคยแสดงเป็น "สต็อกต่ำ" (เช่น 5 ชิ้น) จะเปลี่ยนเป็น "สต็อกปานกลาง"

## การทดสอบ
- ✅ PHP Syntax Check: ผ่าน
- ✅ Web Page Load: ผ่าน
- ✅ Stats Cards Display: ปรับข้อความเรียบร้อย
- ✅ Table Status Display: ใช้เงื่อนไขใหม่

## หมายเหตุ
การเปลี่ยนแปลงนี้ทำให้ระบบมีความเข้มงวดในการแจ้งเตือนสถานะสต็อกมากขึ้น ช่วยให้ผู้ใช้สามารถจัดการสต็อกได้อย่างมีประสิทธิภาพมากขึ้น