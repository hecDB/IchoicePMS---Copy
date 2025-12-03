# 🚀 Quick Start Guide - ทดสอบปุ่ม "รับสินค้า"

## 📋 Checklist การแก้ไข (ทำสำเร็จแล้ว ✅)

- [x] แก้ไข `api/get_po_items.php` - เปลี่ยนให้ส่งข้อมูลตัวเลขแทน string
- [x] แก้ไข `receive/receive_po_items.php` - ปรับปรุง error handling
- [x] สร้าง `test_receive_button.php` - ไฟล์ทดสอบเฉพาะ
- [x] สร้างเอกสาร troubleshooting

---

## ⚡ 3 ขั้นตอนทดสอบ (5 นาที)

### 1️⃣ ทดสอบ API โดยตรง (2 นาที)

**เปิด URL นี้ในเบราว์เซอร์:**
```
http://localhost/IchoicePMS---Copy/api/get_po_items.php?po_id=1
```

**ควรเห็น:**
```json
{
  "success": true,
  "items": [
    {
      "item_id": 1,
      "product_name": "Product A",
      "order_qty": 100,
      "unit_price": 150.5,
      ...
    }
  ]
}
```

**ถ้าเห็น:**
- ✅ `"order_qty": 100` (ตัวเลขไม่มี quote) → **ถูกต้อง**
- ❌ `"order_qty": "100"` (มี quote) → **ยังไม่แก้**
- ❌ Error message → **ตรวจสอบ Console**

---

### 2️⃣ ทดสอบปุ่มด้วยหน้าทดสอบ (2 นาที)

**เปิด URL นี้:**
```
http://localhost/IchoicePMS---Copy/test_receive_button.php
```

**ทีหน้าทดสอบ:**
1. ตรวจสอบ "Test 2: Database Connection" → ต้องเป็น ✓
2. ตรวจสอบ "Test 3: API Endpoint" → ต้องเป็น ✓
3. **คลิกปุ่ม "รับสินค้า (Test Button)"**
4. ดู Console Output (ต้อง scroll down)

**ควรเห็น:**
```
=== Button Clicked ===
Button data: {poId: 1, poNumber: "PO-2025-00001", ...}
Modal shown
Calling API: api/get_po_items.php?po_id=1
✓ API Success Response
✓ Items loaded: 3
```

---

### 3️⃣ ทดสอบในระบบจริง (1 นาที)

**เปิด:**
```
http://localhost/IchoicePMS---Copy/receive/receive_po_items.php
```

**ทำการทดสอบ:**
1. ค้นหาช่องสี่เหลี่ยมสีน้ำเงิน ที่มีปุ่ม "รับสินค้า"
2. **คลิกปุ่ม "รับสินค้า"** (สีน้ำเงิน)
3. ตรวจสอบ:
   - ✅ Modal ขึ้นมา
   - ✅ ตารางแสดงสินค้า
   - ✅ สามารถกรอกจำนวน

**ถ้าไม่ทำงาน:**
- เปิด F12 → Console tab
- ค้นหา error messages (สีแดง)
- ดูรายละเอียด error

---

## 🎯 Expected Result (ผลลัพธ์ที่คาดหวัง)

```
┌─ Browser ─────────────────────────────┐
│                                       │
│  PO-2025-00013                        │
│  target.com        ✓ พร้อมรับสินค้า   │
│  received: 0%      ✓                  │
│  [    รับสินค้า   ] [  👁️ ดู  ]      │
│                                       │
└───────────────────────────────────────┘
         ↓ (click)
┌─ Modal ───────────────────────────────┐
│  PO-2025-00013                        │
│ ┌────────────────────────────────────┐│
│ │ No │ Product Name │ Qty │ Received ││
│ ├────────────────────────────────────┤│
│ │ 1  │ Product A    │100  │ 0        ││
│ │ 2  │ Product B    │ 50  │ 0        ││
│ │ 3  │ Product C    │200  │ 0        ││
│ └────────────────────────────────────┘│
│                                       │
│     [  บันทึก  ]    [  ปิด  ]         │
└───────────────────────────────────────┘
```

---

## ✅ Verification Checklist

| Test | Expected | Actual | ✓/✗ |
|------|----------|--------|-----|
| API returns JSON | Yes | ? | ? |
| Numbers are numeric | Yes | ? | ? |
| Modal opens | Yes | ? | ? |
| Table shows items | Yes | ? | ? |
| Quick Receive works | Yes | ? | ? |
| Cancel Item works | Yes | ? | ? |
| Save works | Yes | ? | ? |

---

## 🆘 If Something Goes Wrong

### Error: "ไฟล์ API ไม่พบ (404)"
```
สาเหตุ: ไฟล์ api/get_po_items.php ไม่มีหรือ path ผิด
วิธีแก้:
1. ตรวจสอบว่าไฟล์มีอยู่ที่: api/get_po_items.php
2. ตรวจสอบสิทธิ์ (permissions)
3. ดูไฟล์ error logs ของเซิร์ฟเวอร์
```

### Error: "เกิดข้อผิดพลาดเซิร์ฟเวอร์ (500)"
```
สาเหตุ: PHP error ในไฟล์ API
วิธีแก้:
1. เปิด Developer Console (F12)
2. ดู error message ที่ปรากฏ
3. ตรวจสอบไฟล์ logs ของ Apache
```

### Error: "API ส่งคืนข้อมูลผิดประเภท"
```
สาเหตุ: API ส่งกลับ HTML แทน JSON
วิธีแก้:
1. ตรวจสอบไฟล์ api/get_po_items.php ว่าถูกต้องหรือไม่
2. ตรวจสอบว่า header ถูกต้อง: header('Content-Type: application/json');
3. ตรวจสอบ PHP syntax errors
```

### Modal opens but no items show
```
สาเหตุ: Database query ไม่ส่งข้อมูล
วิจัย:
1. เปิด F12 → Console
2. ตรวจสอบ "✓ API Success Response" หรือ error
3. ดู Network tab → get_po_items.php → Response
4. ตรวจสอบฐานข้อมูลว่ามีสินค้าในใบ PO นั้นหรือไม่
```

---

## 📞 Support

ถ้ายังติดปัญหา:

1. **เปิด Developer Console:** F12
2. **Copy ทั้ง error message**
3. **ตรวจสอบ Network tab:**
   - ดูคำขอ (request) ที่ถูกส่ง
   - ดูการตอบสนอง (response) จาก server
4. **ดูไฟล์ logs:**
   - Apache error logs
   - PHP error logs
   - Browser console logs

---

**Created:** 2025-12-03  
**Version:** 1.0 - Quick Start Guide
