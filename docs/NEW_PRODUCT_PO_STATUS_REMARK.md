# ✅ New Product PO Status & Remark - Auto Assignment

## 🎯 Update Summary

Modified the new product PO creation to automatically set status and remark fields when adding new products.

---

## 📝 Changes Made

### File: `purchase_order_new_product_api.php`

**What Changed:**
```php
// ❌ BEFORE
$po_status = 'draft';
$stmt->execute([$supplier_id, $order_date, $po_status, $created_by, $po_remark, $currency_id]);

// ✅ AFTER
$po_status = 'pending';
$final_remark = 'ซื้อสินค้ามาใหม่';
// ถ้ามีหมายเหตุเพิ่มเติมจากผู้ใช้ ให้เพิ่มเข้าไป
if (!empty($po_remark)) {
    $final_remark .= ' (' . trim($po_remark) . ')';
}
$stmt->execute([$supplier_id, $order_date, $po_status, $created_by, $final_remark, $currency_id]);
```

---

## 🔍 How It Works

### Logic Flow:

1. **Set Default Status**: `status = 'pending'`
   - New product POs start with pending status instead of draft
   - Ready for approval process

2. **Set Default Remark**: `remark = 'ซื้อสินค้ามาใหม่'`
   - Automatically marks the PO as new product purchase

3. **Append User Input** (if provided):
   - If user enters additional remark → Add it in parentheses
   - Format: `ซื้อสินค้ามาใหม่ (user's additional remark)`

### Examples:

| User Input | Database Remark |
|-----------|-----------------|
| (empty) | `ซื้อสินค้ามาใหม่` |
| `นำเข้าจากต่างประเทศ` | `ซื้อสินค้ามาใหม่ (นำเข้าจากต่างประเทศ)` |
| `ทดลองจำหน่ายใหม่` | `ซื้อสินค้ามาใหม่ (ทดลองจำหน่ายใหม่)` |

---

## 📊 Database Impact

### Before:
```
purchase_orders
├── po_id: 1
├── po_number: PO-2025-00001
├── status: draft
└── remark: (user input or empty)
```

### After:
```
purchase_orders
├── po_id: 1
├── po_number: PO-2025-00001
├── status: pending ← Changed from 'draft'
└── remark: ซื้อสินค้ามาใหม่ ← Auto-set
```

---

## ✨ Benefits

✅ **Clear Status** - New product POs immediately show as 'pending'
✅ **Automatic Tracking** - All new product purchases marked with remark
✅ **Flexible Notes** - Can still add additional remarks
✅ **Easy Filtering** - Can search POs with remark containing 'ซื้อสินค้ามาใหม่'
✅ **Audit Trail** - Clear indication of new product vs. regular products

---

## 🧪 Testing

### Test Case 1: Create PO Without Additional Remark
```
1. Create new product PO
2. Leave "หมายเหตุ" field empty
3. Submit
4. Check database/view PO
5. ✅ status = 'pending'
6. ✅ remark = 'ซื้อสินค้ามาใหม่'
```

### Test Case 2: Create PO With Additional Remark
```
1. Create new product PO
2. Fill "หมายเหตุ" with "นำเข้าจากต่างประเทศ"
3. Submit
4. Check database/view PO
5. ✅ status = 'pending'
6. ✅ remark = 'ซื้อสินค้ามาใหม่ (นำเข้าจากต่างประเทศ)'
```

### Test Case 3: Regular PO Still Works
```
1. Create regular product PO (existing flow)
2. Should not be affected
3. ✅ Regular POs work as before
```

---

## 📋 Related Fields

### Status Values:
- `draft` - Draft (old, no longer used for new products)
- `pending` - ← **New default for new product POs**
- `partial` - Partial receipt
- `completed` - Completed
- `cancel` - Cancelled

### Remark Field:
- Now includes auto-prefix: `ซื้อสินค้ามาใหม่`
- User can add additional context in parentheses
- Max length: 255 characters

---

## 🔧 Code Details

### Location:
- File: `api/purchase_order_new_product_api.php`
- Lines: 54-68

### Key Variables:
- `$po_status = 'pending'` - New status
- `$final_remark` - Combined remark with user input
- `if (!empty($po_remark))` - Checks if user added remark

---

## ✅ Validation

- [x] No syntax errors
- [x] Database transaction intact
- [x] User input is trimmed
- [x] Works with empty and filled remarks
- [x] No breaking changes to existing code
- [x] Ready for deployment

---

## 📞 Notes

- This change only applies to **new product POs** created via `purchase_order_create_new_product.php`
- Regular product POs are not affected
- The status can still be changed manually via the UI if needed
- Remarks can be updated later if required

---

**Status**: ✅ READY
**Date**: November 16, 2025
**Version**: 1.0
