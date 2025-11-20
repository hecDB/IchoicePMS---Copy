# Summary: Per-Lot Expiry Date Implementation

## ✅ Changes Made

### Problem
- เมื่อรับสินค้าล็อตใหม่ (สินค้าเดียวกัน) → ระบบแสดงวันหมดอายุเดิม
- User อาจลืมเปลี่ยน → บันทึกข้อมูลผิด
- ไม่ชัดเจนว่านี่เป็นล็อตใหม่หรือเดิม

### Solution
**ทำให้ Expiry Date field ว่างเสมอเมื่อแก้ไข**

---

## 📝 Code Changes

### Change 1: ล้างวันหมดอายุเมื่อเปิด Modal
```javascript
// ✅ เปลี่ยนจาก:
$('#edit-expiry-date').val(expiry);

// ✅ เป็น:
$('#edit-expiry-date').val('');  // ล้างให้ว่าง
```
**Location:** `receive_items_view.php` Line 1100

### Change 2: ไม่อัปเดตจากค่า DB เดิม
```javascript
// ✅ ปิดการทำงานนี้:
// if (expiryFromAPI) {
//     $('#edit-expiry-date').val(expiryFromAPI);
// }
```
**Location:** `receive_items_view.php` Line 1147-1151

---

## 🔄 User Flow

```
Edit Receive Item
       ↓
Modal Opens
       ↓
Expiry Date: [_____]  ← ว่างเสมอ (ไม่มีค่าเดิม)
       ↓
User กรอกวันใหม่
       ↓
Click Save
       ↓
✅ Lot-specific expiry date saved
```

---

## 📊 Example

### Database (Before & After Save)
```
receive_id=42: product=A, expiry_date=2025-11-30 (Lot 1)
receive_id=43: product=A, expiry_date=2025-12-31 (Lot 2) ← ต่างล็อต
                                    ↑ กรอกข้อมูลใหม่
```

---

## ✨ Benefits

✅ ป้องกันข้อผิดพลาด  
✅ ชัดเจนว่าต่างล็อต  
✅ ทุกครั้งต้องกรอกใหม่  
✅ เก็บข้อมูลแบบล็อตอย่างถูกต้อง

---

## 🧪 Testing

```
1. Edit a receive item
2. Check: expiry_date field = empty ✓
3. Fill new date: 2025-12-31
4. Save
5. Verify DB: SELECT expiry_date FROM receive_items;
6. Result: Should show 2025-12-31 ✓
```

---

**Status:** ✅ Deployed  
**Files Modified:** 1 (receive_items_view.php)  
**Lines Changed:** 2 locations  
**Impact:** Low (UI only)
