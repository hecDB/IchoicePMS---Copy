# ✅ แก้ไขข้อผิดพลาด: Unknown column 'u.firstname'

## ❌ ปัญหาที่พบ
```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 
Unknown column 'u.firstname' in 'field list' in cancelled_items.php:45
```

## 🔍 สาเหตุ
ตาราง `users` ใช้คอลัมน์ `name` เพียงอย่างเดียว ไม่ใช่ `firstname` และ `lastname`

## ✅ การแก้ไข

### SQL Query
**เปลี่ยนจาก:**
```sql
u.firstname as cancelled_by_firstname,
u.lastname as cancelled_by_lastname,
```

**เปลี่ยนเป็น:**
```sql
COALESCE(u.name, '-') as cancelled_by_name,
```

### HTML Template
**เปลี่ยนจาก:**
```php
<?php echo htmlspecialchars(($item['cancelled_by_firstname'] ?? '') . ' ' . ($item['cancelled_by_lastname'] ?? '')); ?>
```

**เปลี่ยนเป็น:**
```php
<?php echo htmlspecialchars($item['cancelled_by_name'] ?? '-'); ?>
```

## 📊 ผลลัพธ์
✅ ไม่มี SQL Error  
✅ แสดงชื่อผู้ยกเลิก ได้ถูกต้อง  
✅ หน้า `cancelled_items.php` ทำงานได้อย่างสมบูรณ์
