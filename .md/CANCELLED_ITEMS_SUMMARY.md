# 📋 การแก้ไขการแสดงรายการสินค้าที่ถูกยกเลิก

## 🎯 วัตถุประสงค์

แยกการแสดงรายการสินค้าที่ถูกยกเลิกเป็นหน้าแยกต่างหาก เพื่อให้:
- ☑️ อ่านโค้ดง่าย (ใช้ Include แยกส่วน)
- ☑️ จัดการง่าย (ไฟล์ template แยก)
- ☑️ ไม่งง (มีหน้าแยกสำหรับรายการยกเลิก)

---

## 📁 ไฟล์ที่สร้าง/แก้ไข

### ✅ ไฟล์ใหม่

| ไฟล์ | คำอธิบาย |
|-----|---------|
| `receive/cancelled_items.php` | หน้าแยกสำหรับแสดงรายการสินค้าที่ยกเลิก |
| `receive/templates_stats_cards.php` | Template stats cards (Include ใน receive_po_items.php) |
| `receive/templates_filter_results.php` | Template สำหรับแสดงผลลัพธ์การกรอง (เตรียมไว้ขั้นสูง) |
| `api/get_cancelled_items_count.php` | API ดึงจำนวนสินค้าที่ยกเลิก |
| `CANCELLED_ITEMS_FEATURE.md` | เอกสารอธิบายรายละเอียด |

### ✏️ ไฟล์ที่แก้ไข

| ไฟล์ | การเปลี่ยนแปลง |
|-----|--------------|
| `receive/receive_po_items.php` | - แทนที่ stats cards ด้วย Include<br>- อัปเดต JS ให้กดการ์ด "ยกเลิก" ไปหน้า cancelled_items.php |

---

## 🎨 คุณสมบัติ

### หน้า `cancelled_items.php`

#### Header
```
┌─────────────────────────────────────────┐
│ ← กลับไปรับสินค้า                        │
│                                         │
│ ❌ สินค้าที่ถูกยกเลิก                   │
│    รายละเอียดสินค้าที่ยกเลิกจากใบ PO   │
└─────────────────────────────────────────┘
```

#### Stats Cards
```
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ รวม      │ │ ยกเลิก   │ │ บางจำนวน │ │ใบ PO    │
│ 5 รายการ │ │ 3 รายการ │ │ 2 รายการ │ │ 2 ใบ    │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

#### จัดกลุ่มตาม PO
```
┌─ PO-001 | บริษัท ABC ──────────────────┐
│  - สินค้า 1 (ยกเลิกทั้งหมด)           │
│    จำนวนยกเลิก: 10 units              │
│    เหตุผล: ไม่ได้ตามคุณภาพ             │
│    หมายเหตุ: ...                       │
│                                        │
│  - สินค้า 2 (ยกเลิกบางจำนวน)          │
│    จำนวนยกเลิก: 5 units               │
│    เหตุผล: มีตำหนิ                    │
│    หมายเหตุ: ...                       │
└────────────────────────────────────────┘
```

### หน้า `receive_po_items.php` (ที่แก้ไข)

#### Stats Cards (Include)
```php
<?php include 'templates_stats_cards.php'; ?>
```

#### การกดการ์ด
- **ยกเลิก** → ไปหน้า `cancelled_items.php` โดยอัตโนมัติ
- **พร้อมรับ** → กรอง PO ที่พร้อมรับ
- **รับบางส่วน** → กรอง PO ที่รับบางส่วน
- **รับครบแล้ว** → โหลดจากฐานข้อมูลและแสดงผล

---

## 🔄 Workflow

### 1️⃣ ดูรายการยกเลิก
```
หน้า receive_po_items.php
         ↓
  กดการ์ด "ยกเลิก"
         ↓
  cancelled_items.php
  (แสดงรายละเอียด)
```

### 2️⃣ โครงสร้างไฟล์
```
receive/
  ├── receive_po_items.php          (แก้ไข)
  ├── cancelled_items.php            (✨ ใหม่)
  ├── templates_stats_cards.php      (✨ ใหม่)
  └── templates_filter_results.php   (✨ ใหม่)

api/
  └── get_cancelled_items_count.php  (✨ ใหม่)
```

---

## 🛠️ รายละเอียดทางเทคนิค

### Database Query (cancelled_items.php)
```sql
SELECT 
    poi.item_id, poi.po_id,
    po.po_number, s.name as supplier_name,
    p.name as product_name, p.code as product_code,
    poi.qty as ordered_qty,
    SUM(ri.receive_qty) as received_qty,
    poi.cancel_qty, poi.is_cancelled, 
    poi.is_partially_cancelled,
    poi.cancel_reason, poi.cancel_notes,
    poi.cancelled_at, u.firstname, u.lastname,
    un.name as unit_name, poi.unit_price,
    c.code as currency_code
FROM purchase_order_items poi
LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
...
WHERE poi.is_cancelled = 1 OR poi.is_partially_cancelled = 1
GROUP BY poi.item_id
ORDER BY poi.cancelled_at DESC
```

### JavaScript (receive_po_items.php)
```javascript
// Stats card click handler
$('.stats-card').on('click', function() {
    const filterType = $(this).data('filter');
    
    // ถ้ากดการ์ด "ยกเลิก" → ไปหน้า cancelled_items.php
    if (filterType === 'cancelled') {
        window.location.href = 'cancelled_items.php';
        return;
    }
    
    // สำหรับการ์ดอื่น → กรองข้อมูลในหน้าเดียวกัน
    ...
});
```

---

## 🎯 ข้อดี

| ด้าน | ข้อดี |
|-----|-----|
| **ความสะอาด** | โค้ดแยกตามความหมาย |
| **ประสิทธิภาพ** | ใช้ Include เพื่อหลีกเลี่ยงการซ้ำซ้อน |
| **ความง่าย** | เข้าใจและแก้ไขได้ง่าย |
| **ความยืดหยุ่น** | เตรียมไว้สำหรับเพิ่มฟีเจอร์ในอนาคต |

---

## ✨ ฟีเจอร์ที่อาจเพิ่มในอนาคต

- 🔍 ตัวกรองตามวันที่ยกเลิก
- 📊 ตัวกรองตามเหตุผล
- 📤 ส่งออก Excel
- 🔄 ยกเลิกการยกเลิก (ถ้าอนุญาต)
- 📧 ส่งอีเมล็อยประกาศ

---

## ✅ ตรวจสอบเสร็จสิ้น

- ✅ สร้างหน้า `cancelled_items.php`
- ✅ สร้าง template stats cards
- ✅ สร้าง template filter results
- ✅ สร้าง API ดึงจำนวนยกเลิก
- ✅ แก้ไข `receive_po_items.php` ให้ใช้ Include
- ✅ อัปเดต JavaScript ให้ redirect ไปหน้า cancelled items
- ✅ เอกสารอธิบาย
