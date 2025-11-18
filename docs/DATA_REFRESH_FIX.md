# Data Refresh Fix - Purchase Orders

## ปัญหา (Problems Found)

ข้อมูลไม่ถูกดึงมาแบบอัตโนมัติเต็มที่เมื่อมีการแก้ไข PO มี 4 ปัญหาหลัก:

### 1. **saveUserSection() - User Section Only Refreshed**
- **ปัญหา**: หลังบันทึกผู้สั่งซื้อ เรียก `renderUserSection()` เท่านั้น 
- **ผล**: ไม่ยุบกลับ popup อัตโนมัติ ไม่อัปเดตรายการสินค้า ไม่อัปเดตผู้จำหน่าย
- **แก้ไข**: เรียก `renderPoView()` แทน เพื่ออัปเดตข้อมูลทั้งหมด

### 2. **saveSupplierSection() - Supplier Section Only Refreshed**
- **ปัญหา**: หลังบันทึกผู้จำหน่าย เรียก `renderSupplierSection()` เท่านั้น
- **ผล**: ไม่ยุบกลับ popup อัตโนมัติ ไม่อัปเดตรายการสินค้า
- **แก้ไข**: เรียก `renderPoView()` แทน เพื่ออัปเดตข้อมูลทั้งหมด

### 3. **saveItemRow() - Renders Local Data First, Then Fetches**
- **ปัญหา**: 
  ```javascript
  // Old: ใช้ข้อมูลเก่า (updateData) ที่อาจไม่ถูกต้องจากการคำนวณด้านไคลเอ็นต์
  currentPoData.items[index] = { ...updateData };
  renderItemsTable();  // render ข้อมูลเก่า
  
  // จากนั้นค่อยหลังจากความล่าช้า 1 วินาที ค่อยรีเฟรชจากเซิร์ฟเวอร์
  setTimeout(() => { fetch API }, 1000);
  ```
- **ผล**: ข้อมูลในตาราแสดงค่าชั่วคราวที่อาจไม่ตรงกัน
- **แก้ไข**: ดึงข้อมูลจากเซิร์ฟเวอร์ทันที แล้วค่อย render

### 4. **addNewItem() - Shows Success Before Data Renders**
- **ปัญหา**:
  ```javascript
  // Old: แสดง success ก่อน
  Swal.fire({ title: 'เพิ่มแล้ว!' });
  
  // รอ 0.5 วินาทีค่อยรีเฟรช
  setTimeout(() => { fetch API }, 500);
  ```
- **ผล**: Alert ปิดเร็วเกินไป ผู้ใช้ไม่เห็นความเปลี่ยนแปลง
- **แก้ไข**: ดึงข้อมูลก่อน แล้วค่อยแสดง Success ที่ท้าย

### 5. **Missing Error Handling**
- **ปัญหา**: 
  - ไม่ตรวจสอบ HTTP response status
  - ไม่ตรวจสอบ error ในข้อมูล JSON
  - ข้อมูลอาจส่งมาไม่ครบถ้วน
- **แก้ไข**: เพิ่มการตรวจสอบ response validation ทั้งหมด

---

## การแก้ไข (Fixes Applied)

### ✅ saveUserSection()
```javascript
// Before: renderUserSection() - แสดงเฉพาะผู้สั่งซื้อ
// After: renderPoView() - แสดงข้อมูลทั้งหมด (ผู้สั่งซื้อ, ผู้จำหน่าย, รายการสินค้า, สกุลเงิน)

// เพิ่มตรวจสอบ HTTP response
.then(res => {
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
})

// เพิ่มตรวจสอบข้อมูล JSON
.then(updatedData => {
    if (updatedData.error) throw new Error(updatedData.error);
    currentPoData = updatedData;
    renderPoView(updatedData);
})

// เพิ่มข้อความเตือนหากเกิดข้อผิดพลาด
.catch(err => {
    console.error('Error refreshing data:', err);
    Swal.fire('เตือน', 'ข้อมูลอาจไม่ปรากฏให้เห็นทั้งหมด...', 'warning');
});
```

### ✅ saveSupplierSection()
```javascript
// Before: ไม่มี error handling, ไม่แสดง popup ใหม่
// After: error handling ครบถ้วน และเรียก renderPoView()
```

### ✅ saveItemRow()
```javascript
// Before: 
if (data.success) {
    currentPoData.items[index] = { ...updateData };
    renderItemsTable();  // render ข้อมูลเก่า
    setTimeout(() => { fetch API }, 1000);
}

// After:
if (data.success) {
    fetch('../api/purchase_order_api.php?id=' + poId)
        .then(...) // ดึงข้อมูลใหม่ทันที
        .then(refreshedData => {
            currentPoData = refreshedData;
            renderPoView(refreshedData);  // render ข้อมูลใหม่
        });
}
```

### ✅ addNewItem()
```javascript
// Before:
Swal.fire({ title: 'เพิ่มแล้ว!' });
setTimeout(() => { fetch API }, 500);  // ช้า

// After:
fetch('../api/purchase_order_api.php?id=' + poId)
    .then(...) // ดึงข้อมูลใหม่ทันที
    .then(refreshedData => {
        currentPoData = refreshedData;
        renderPoView(refreshedData);  // render ข้อมูลใหม่
        Swal.fire({ title: 'เพิ่มแล้ว!' });  // แสดง success ท้ายสุด
    });
```

---

## วิธีการทำงานใหม่ (New Workflow)

### ลำดับการทำงาน:
1. ผู้ใช้คลิก "บันทึก"
2. ส่งข้อมูล → API endpoint
3. API บันทึกข้อมูล → Database
4. **ดึงข้อมูลใหม่ทั้งหมดจากเซิร์ฟเวอร์** ← NEW
5. Render UI ด้วยข้อมูลใหม่
6. แสดง Success message
7. Popup ยุบกลับอัตโนมัติ

### ข้อมูลที่ดึงมา (purchase_order_api.php):
```
{
  "order": { po_id, po_number, order_date, status, supplier_id, ... },
  "items": [ { item_id, product_id, qty, price_per_unit, total, ... } ],
  "user": { user_id, name, department },
  "supplier": { supplier_id, name, phone, email, address },
  "currencies": [ { currency_id, code, symbol, exchange_rate } ]
}
```

---

## ประโยชน์ (Benefits)

| ด้าน | ก่อน | หลัง |
|------|------|------|
| ความถูกต้อง | ข้อมูลชั่วคราวจาก client-side | ข้อมูลจริงจากฐานข้อมูล |
| ความเร็ว | รอ 0.5-1 วินาที | ดึงข้อมูลทันที |
| ประสบการณ์ | Alert แสดงเร็ว แต่ข้อมูลอัปเดตล่าช้า | Alert แสดงเมื่อข้อมูลพร้อม |
| Error handling | ไม่มี | ครบถ้วน (HTTP + JSON validation) |
| Coverage | ส่วนเดียว (user/supplier) | ทั้งหมด (popup ทั้งหน้า) |

---

## Testing Checklist

- [ ] บันทึกผู้สั่งซื้อ → ดูรายการสินค้า, ผู้จำหน่าย, สกุลเงิน ทั้งหมดอัปเดตจริง
- [ ] บันทึกผู้จำหน่าย → ทุกส่วนอัปเดตจริง
- [ ] แก้ไขรายการสินค้า → จำนวน, ราคา, รวมทั้งหมดอัปเดตจริง
- [ ] เพิ่มรายการสินค้าใหม่ → ปรากฏในตารางทันที
- [ ] ลบรายการสินค้า → หายจากตารางและคำนวณรวมใหม่
- [ ] เปลี่ยนสกุลเงิน → รวมทั้งหมด และอัตราแลกเปลี่ยน ถูกต้อง
- [ ] ปิด popup → ข้อมูลใน popup ครั้งต่อไปแสดงค่าล่าสุด

---

## Files Modified

- ✅ `orders/purchase_orders.php` (4 functions)
  - `saveUserSection()` - Line 1298+
  - `saveSupplierSection()` - Line 1403+
  - `saveItemRow()` - Line 1644+
  - `addNewItem()` - Line 1880+

---

## Version Info

- **Date**: November 16, 2025
- **Files Changed**: 1
- **Functions Updated**: 4
- **Status**: ✅ Ready for Testing
