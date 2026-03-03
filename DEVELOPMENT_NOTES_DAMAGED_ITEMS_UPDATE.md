# 🔧 แก้ไข: บันทึกสินค้าชำรุดเข้า temp_products แทน receive_items

## 📁 ไฟล์ที่แก้ไข

### 1. `/api/returned_items_api.php`

#### ✨ เพิ่มฟังก์ชันใหม่: `insertTempProductFromDamagedInspection()`
```php
/**
 * บันทึกสินค้าชำรุดบางส่วนที่ขายได้ลงตาราง temp_products
 * แทนการสร้าง PO และการรับเข้าสต๊อก
 */
function insertTempProductFromDamagedInspection(
    PDO $pdo,
    array $inspection,
    string $newSku,
    float $restockQty,
    ?float $costPrice,
    ?float $salePrice,
    ?string $defectNotes,
    int $userId
): ?int
```

**ข้อมูลที่บันทึก:**
- `product_name`: ชื่อสินค้า
- `product_category`: หมวดหมู่
- `product_image`: รูปภาพ
- `unit`: หน่วยนับ
- `provisional_sku`: SKU ใหม่ (เช่น "ตำหนิ-OLD-SKU")
- `provisional_barcode`: barcode เดิม
- `remark`: หมายเหตุพร้อมรหัส return
- `status`: "pending_approval"
- `po_id`: PO ต้นทาง (ถ้ามี)

#### 🔄 แก้ไข: `process_damaged_inspection` Action

**เปลี่ยนแปลง:**
1. **ลบ**: `ensureDamagedReturnPo()`, `ensureDamagedPurchaseOrderItem()`, `insertDamagedReceiveMovement()`
2. **เพิ่ม**: เรียก `insertTempProductFromDamagedInspection()` เมื่อ `disposition = 'sellable'`
3. **ปรับปรุง**: update SQL ให้ไม่บันทึก `po_id`, `po_number` ลงการบันทึก

**Logic:**
```
if (isSellable) {
    → บันทึกลง temp_products
} else {
    → ปิดงาน (ไม่สร้าง PO)
}
```

#### 📊 Database Changes
```sql
-- ข้อมูลถูกบันทึกลง temp_products แทน receive_items
INSERT INTO temp_products (
    product_name, product_category, product_image, unit,
    provisional_sku, provisional_barcode, remark,
    status, po_id, created_by, created_at
) VALUES (...)

-- ไม่มี INSERT/UPDATE เข้า receive_items อีกต่อไป
-- ไม่สร้าง purchase_orders ใหม่อีกต่อไป
```

---

### 2. `/returns/damaged_return_inspections.php`

#### 🎨 UI ที่ปรับปรุง

**หัวข้อหน้า:**
```
ตรวจสอบสินค้าชำรุดบางส่วน
เลือกสินค้าที่ตีกลับด้วยเหตุผล "สินค้าชำรุดบางส่วน" เพื่อเปลี่ยน SKU ใหม่ 
(ขายได้: บันทึก temp_products, ทิ้ง: ปิดงาน)
```

**ส่วนเลือกสถานะสินค้า:**
```html
<option value="sellable">ขายได้ - บันทึกเข้า temp_products เพื่ออนุมัติ SKU ใหม่</option>
<option value="discard">ทิ้ง / ใช้ไม่ได้ - ไม่บันทึกลงสต๊อก</option>
```

**ปุ่มยืนยัน:**
- ขายได้: "ยืนยันสินค้ามีตำหนิและบันทึก temp_products"
- ทิ้ง: "ยืนยันจัดประเมินสินค้าและปิดงาน"

#### 🔧 JavaScript ที่เพิ่ม

**อัปเดตข้อความปุ่มตามสถานะ:**
```javascript
document.getElementById('dispositionSelect').addEventListener('change', function() {
    const submitBtn = document.getElementById('submitBtnText');
    if (this.value === 'sellable') {
        submitBtn.textContent = 'ยืนยันสินค้ามีตำหนิและบันทึก temp_products';
    } else {
        submitBtn.textContent = 'ยืนยันจัดประเมินสินค้าและปิดงาน';
    }
});
```

**ปรับปรุง Success Message:**
```javascript
const successMessage = disposition === 'sellable' 
    ? 'บันทึกการตรวจสอบเรียบร้อย - สินค้ารอการอนุมัติ SKU ใหม่'
    : 'บันทึกการตรวจสอบเรียบร้อย - สินค้าจัดประเมินว่าไม่สามารถใช้ได้';
```

---

## ✅ การทดสอบ

### Test Case 1: สินค้าขายได้
```
1. เลือกสินค้าชำรุด
2. ตั้ง disposition = "sellable"
3. กรอกข้อมูล
4. คลิกยืนยัน
5. ✅ ตรวจสอบ: temp_products มีบันทึกใหม่
6. ✅ ตรวจสอบ: returned_items.is_returnable = 1
7. ✅ ตรวจสอบ: damaged_return_inspections.status = "completed"
```

### Test Case 2: สินค้าทิ้ง
```
1. เลือกสินค้าชำรุด
2. ตั้ง disposition = "discard"
3. กรอกข้อมูล
4. คลิกยืนยัน
5. ✅ ตรวจสอบ: temp_products ไม่มีบันทึก
6. ✅ ตรวจสอบ: returned_items.is_returnable = 0
7. ✅ ตรวจสอบ: damaged_return_inspections.status = "completed"
```

### Database Queries

**ตรวจสอบ temp_products:**
```sql
SELECT * FROM temp_products 
WHERE status = 'pending_approval' 
AND provisional_sku LIKE 'ตำหนิ-%'
ORDER BY created_at DESC;
```

**ตรวจสอบ returned_items:**
```sql
SELECT * FROM returned_items 
WHERE return_status = 'completed' 
AND reason_name = 'สินค้าชำรุดบางส่วน'
ORDER BY updated_at DESC;
```

**ตรวจสอบ damaged_return_inspections:**
```sql
SELECT * FROM damaged_return_inspections 
WHERE status = 'completed'
ORDER BY inspected_at DESC;
```

---

## 📊 ผลกระทบ

### ✅ ประโยชน์
- ✓ ลดการสร้าง PO หลากหลาย
- ✓ กระบวนการอนุมัติชัดเจน
- ✓ ติดตาม SKU ใหม่ในตารางเดียว (temp_products)
- ✓ สะดวกในการสร้างสินค้าใน products

### ⚠️ ข้อสังเกต
- ต้องการ Admin อนุมัติหลังจากตรวจสอบ
- ไม่สามารถสร้างสินค้าโดยอัตโนมัติ
- ต้องทำซ้ำขั้นตอนสำหรับแต่ละสินค้าชำรุด

---

## 📝 หมายเหตุการพัฒนา

- **Version**: 2.0 (Damaged Items with temp_products)
- **Date**: 27 มีนาคม 2568
- **Status**: ✅ Ready for Testing
- **Related Files**: 
  - `/api/returned_items_api.php`
  - `/returns/damaged_return_inspections.php`
  - `/api/approve_temp_product.php` (existing)
