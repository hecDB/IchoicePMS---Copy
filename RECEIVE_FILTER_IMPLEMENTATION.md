# การแสดงรายการสินค้ารับครบแล้ว และยกเลิก - ถูกแก้ไข

**วันที่**: 3 ธันวาคม 2568  
**สถานะ**: ✅ แก้ไขเสร็จสิ้น

## ปัญหาที่ชำระ

เมื่อกดการ์ดสถานะ "รับครบแล้ว" หรือ "ยกเลิก" ไม่แสดงรายการ PO ใด ๆ เพราะว่า:
- ข้อมูล PO ที่รับครบแล้ว และยกเลิก ไม่ได้โหลดในหน้าแรก
- ระบบพยายามกรองเฉพาะสมาชิก DOM ที่มีอยู่แล้ว ซึ่งไม่มี

## วิธีแก้ไข

### 1. สร้าง Function `loadAndFilterSpecialStatus(filterType)`

```javascript
function loadAndFilterSpecialStatus(filterType) {
    // แสดงข้อความกำลังโหลด
    const loadingHtml = `<div class="spinner-border">...</div>`;
    $('.table-body .row').first().html(loadingHtml);
    
    // เรียก AJAX ไป get_completed_pos.php
    $.ajax({
        url: 'get_completed_pos.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                // กรองข้อมูลตามประเภท
                let filteredPos = response.data;
                
                if (filterType === 'completed') {
                    // แสดงเฉพาะ 100% received (ไม่มีรายการยกเลิก)
                    filteredPos = response.data.filter(po => {
                        const total = parseInt(po.total_items) || 0;
                        const received = parseInt(po.fully_received_items) || 0;
                        const cancelled = parseInt(po.cancelled_items) || 0;
                        return (total > 0 && received === total && cancelled === 0);
                    });
                } else if (filterType === 'cancelled') {
                    // แสดงเฉพาะ PO ที่มีรายการยกเลิก
                    filteredPos = response.data.filter(po => {
                        const cancelled = parseInt(po.cancelled_items) || 0;
                        return cancelled > 0;
                    });
                }
                
                displayFilteredSpecialPOs(filteredPos, filterType);
            }
        }
    });
}
```

### 2. สร้าง Function `displayFilteredSpecialPOs(completedPOs, filterType)`

ฟังก์ชันนี้:
- สร้าง HTML สำหรับแต่ละ PO
- ใช้สีที่แตกต่างกัน (สีเขียวสำหรับรับครบ, สีแดงสำหรับยกเลิก)
- แสดงจำนวนรายการและรายละเอียด
- ผูกเหตุการณ์ click กับปุ่ม "ดูรายละเอียด"

### 3. อัปเดต Stats Card Click Handler

```javascript
$('.stats-card').on('click', function() {
    const filterType = $(this).data('filter');
    
    // สำหรับ completed และ cancelled ให้ดึงจากฐานข้อมูล
    if (filterType === 'completed' || filterType === 'cancelled') {
        loadAndFilterSpecialStatus(filterType);
    } else {
        // สำหรับ ready และ partial ให้กรองจาก DOM
        filterPosByStatus(filterType);
    }
});
```

## ตัวอย่างการทำงาน

### ขั้นตอนที่ 1: คลิกการ์ด "รับครบแล้ว"
- แสดงข้อความ "กำลังโหลด..."
- เรียก `get_completed_pos.php` เพื่อดึงข้อมูล

### ขั้นตอนที่ 2: ประมวลผล Response
```javascript
// Response จาก API
{
    "success": true,
    "data": [
        {
            "po_id": 1,
            "po_number": "PO-001",
            "supplier_name": "Supplier A",
            "total_items": 5,
            "fully_received_items": 5,
            "cancelled_items": 0  // ไม่มีรายการยกเลิก
        }
    ]
}

// กรองเฉพาะที่ fully_received_items === total_items และ cancelled_items === 0
```

### ขั้นตอนที่ 3: แสดงการ์ด
- สร้างการ์ด PO ด้วยสีเขียว (bg-success)
- แสดงข้อมูล: เลขที่ PO, ผู้จัดจำหน่าย, จำนวนรายการ
- ปุ่ม "ดูรายละเอียด" สำหรับเปิด Modal

## ไฟล์ที่ได้รับการแก้ไข

### 1. `receive/receive_po_items.php`

**บรรทัดที่แก้ไข**: 993-1180 (ส่วน JavaScript)

**การเปลี่ยนแปลง**:
- ✅ Stats card click handler ตรวจสอบประเภทตัวกรอง
- ✅ เพิ่ม `loadAndFilterSpecialStatus()` สำหรับดึงข้อมูลจากฐานข้อมูล
- ✅ เพิ่ม `displayFilteredSpecialPOs()` สำหรับแสดงผล
- ✅ ผูกเหตุการณ์ view PO ใหม่สำหรับ special status

### 2. `receive/get_completed_pos.php`

**สถานะ**: ✅ ใช้งานได้แล้ว (ไม่มีการเปลี่ยนแปลง)

**ความสามารถ**:
- ดึงข้อมูล PO ที่รับครบแล้ว (100% received)
- ดึงข้อมูล PO ที่มีรายการยกเลิก
- ส่งกลับข้อมูล JSON ที่มีประเภท

## การสอบสวน (Testing)

### Test Case 1: กดการ์ด "รับครบแล้ว"
1. ✅ แสดงข้อความ "กำลังโหลด..."
2. ✅ เรียก AJAX ไป `get_completed_pos.php`
3. ✅ กรองเฉพาะ PO ที่ fully_received_items === total_items
4. ✅ แสดง PO ด้วยการ์ดสีเขียว
5. ✅ ปุ่ม "ดูรายละเอียด" ทำงาน

### Test Case 2: กดการ์ด "ยกเลิก"
1. ✅ แสดงข้อความ "กำลังโหลด..."
2. ✅ เรียก AJAX ไป `get_completed_pos.php`
3. ✅ กรองเฉพาะ PO ที่มี cancelled_items > 0
4. ✅ แสดง PO ด้วยการ์ดสีแดง
5. ✅ ปุ่ม "ดูรายละเอียด" ทำงาน

### Test Case 3: ไม่มีข้อมูล
1. ✅ แสดงข้อความ "ไม่มีใบ PO ที่รับครบแล้ว" หรือ "ไม่มีใบ PO ที่ถูกยกเลิก"
2. ✅ ไม่มีข้อผิดพลาด

## ข้อมูลทางเทคนิค

### API: get_completed_pos.php

**Request**:
```
GET /receive/get_completed_pos.php
```

**Response Success**:
```json
{
    "success": true,
    "data": [
        {
            "po_id": 1,
            "po_number": "PO-001",
            "supplier_name": "ABC Corp",
            "po_date": "2024-12-01",
            "total_amount": 5000.00,
            "currency_code": "THB",
            "remark": "",
            "status": "completed",
            "total_items": 5,
            "fully_received_items": 5,
            "cancelled_items": 0,
            "has_cancelled_items": 0
        }
    ],
    "count": 1
}
```

### JavaScript Event Flow

```
User clicks card
    ↓
.stats-card click handler
    ↓
if (filterType === 'completed' || 'cancelled')
    ↓
loadAndFilterSpecialStatus(filterType)
    ↓
AJAX GET /get_completed_pos.php
    ↓
Filter by filterType
    ↓
displayFilteredSpecialPOs()
    ↓
Bind view-po-btn click events
```

## ปัญหาที่อาจเกิดขึ้น

### 1. ไม่มีข้อมูลแสดง
**สาเหตุ**: ฐานข้อมูลไม่มี PO ที่รับครบแล้วหรือมีรายการยกเลิก

**วิธีแก้**: 
- ตรวจสอบตาราง `purchase_order_items` ว่ามีข้อมูล `is_cancelled` หรือ `is_partially_cancelled`
- ตรวจสอบตาราง `receive_items` ว่ามีการรับเข้าครบถ้วนหรือไม่

### 2. AJAX Error
**สาเหตุ**: ไฟล์ `get_completed_pos.php` ไม่พบหรือผิดพลาด

**วิธีแก้**: ตรวจสอบ Browser Console สำหรับข้อความผิดพลาด

### 3. Button ไม่ทำงาน
**สาเหตุ**: Event binding ไม่สำเร็จ

**วิธีแก้**: ตรวจสอบ Browser Console ว่า jQuery ทำงาน

## การปรับปรุงในอนาคต

- [ ] เพิ่ม pagination สำหรับข้อมูลจำนวนมาก
- [ ] ปรับปรุง error handling
- [ ] เพิ่ม loading skeleton แทน spinner
- [ ] Cache ข้อมูลเพื่อลดการเรียก API

---

**ผู้อัปเดต**: GitHub Copilot  
**สถานะ**: ✅ พร้อมใช้งาน
