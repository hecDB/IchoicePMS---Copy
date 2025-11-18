# Location Components Modal & Excel Export Fixes

## วันที่: Phase 30
## การแก้ไข: การแยกตำแหน่งในโปแกรมแก้ไข และแก้ไขปัญหา Excel

---

## 1. ✅ Popup แก้ไข - แยกตำแหน่งออกเป็น 3 ส่วน

### ปัญหา
- ก่อนหน้านี้ modal ใช้ dropdown เลือกตำแหน่งแบบรวม (location_id)
- ไม่สามารถแก้ไขแต่ละส่วนได้อย่างเป็นอิสระ

### วิธีแก้ไข

#### 1.1 อัปเดต Modal Form (product_management.php)
**ลบ:** Location dropdown (single select)
```php
<select id="productLocation" name="location_id">
    <option value="">-- เลือกตำแหน่งจัดเก็บ --</option>
    ...
</select>
```

**เพิ่ม:** 3 input fields แยกกันในการจัดวาง Grid
```php
<div class="form-group-custom">
    <label>ตำแหน่งที่จัดเก็บสินค้า</label>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
        <div>
            <label style="font-size: 0.85rem; color: #6b7280;">แถว (Row)</label>
            <input type="text" id="productRowCode" name="row_code" placeholder="เช่น A">
        </div>
        <div>
            <label style="font-size: 0.85rem; color: #6b7280;">ล็อค (Bin)</label>
            <input type="number" id="productBin" name="bin" placeholder="1-10">
        </div>
        <div>
            <label style="font-size: 0.85rem; color: #6b7280;">ชั้น (Shelf)</label>
            <input type="number" id="productShelf" name="shelf" placeholder="1-10">
        </div>
    </div>
    <input type="hidden" id="productLocation" name="location_id">
</div>
```

**ผลลัพธ์:** User สามารถระบุแต่ละส่วนได้อิสระ (A, 2, 3)

#### 1.2 อัปเดต editProduct Function
**เพิ่ม 3 บรรทัด** เพื่อเติมค่า location components จาก API:
```javascript
document.getElementById('productRowCode').value = p.row_code || '';
document.getElementById('productBin').value = p.bin || '';
document.getElementById('productShelf').value = p.shelf || '';
```

#### 1.3 อัปเดต API (product_management_api.php)
**Logic สำหรับการบันทึก:**
1. ตรวจรับ row_code, bin, shelf จาก form
2. ค้นหา location ที่ตรงกับค่าทั้ง 3 ส่วน
3. เชื่อม product กับ location นั้น

```php
// ค้นหา location_id ที่ตรงกับ row_code, bin, shelf
$find_loc_sql = "SELECT location_id FROM locations 
                 WHERE row_code = ? AND bin = ? AND shelf = ?";
$find_loc_stmt = $pdo->prepare($find_loc_sql);
$find_loc_stmt->execute([$row_code, $bin, $shelf]);
$loc_result = $find_loc_stmt->fetch(PDO::FETCH_ASSOC);

if ($loc_result) {
    $location_id = $loc_result['location_id'];
    // INSERT INTO product_location
}
```

---

## 2. ✅ แก้ไขปัญหา Excel Load - Location Data ไม่แสดง

### ปัญหา
- เมื่อ export products ไปยัง Excel และนำเข้า Excel ตำแหน่งไม่แสดง
- Export format ไม่ตรงกับ import template

### วิธีแก้ไข

#### 2.1 ปัญหาหลัก: Export Format Mismatch
**Export (จาก product_management):**
```
ลำดับ | ชื่อสินค้า | SKU | Barcode | หน่วย | หมวดหมู่ | หมายเหตุสี | แบ่งขาย | แถว | ล็อค | ชั้น | สถานะ
```

**Import Template (สำหรับ import_excel.php):**
```
SKU | Barcode | Name | ภาพ | หน่วย | แถว | ล็อค | ชั้น | จำนวน | ราคา | ราคาขาย | สกุลเงิน | EXP | สี | แบ่งขาย | หมายเหตุ
```

#### 2.2 วิธีแก้ไข: สร้าง Export Function ใหม่
**เพิ่ม: `exportForImport()` function**
- ส่งออกเป็น CSV format ที่ตรงกับ import template
- คอลัมน์อยู่ในลำดับเดียวกัน
- ทำให้ export ไปแล้ว import กลับมาได้ปกติ

```javascript
function exportForImport() {
    // Format: SKU, Barcode, Name, Image, Unit, Row, Bin, Shelf, ...
    // ตรงกับ import_excel.php template
}
```

#### 2.3 เพิ่ม UI Button
- เพิ่ม 2 buttons ในการ export:
  - **"ส่งออก Excel (รายงาน)"** - รูปแบบตรวจสอบ/รายงาน
  - **"ส่งออก Excel (นำเข้า)"** - รูปแบบนำเข้ากลับไปใช้ import

---

## 3. ทดสอบการใช้งาน

### Workflow แก้ไขสินค้า
1. คลิก "แก้ไข" สินค้า
2. Modal แสดง 3 fields แยก: แถว, ล็อค, ชั้น
3. แก้ไขค่าใด ๆ ก็ได้
4. บันทึก → System ค้นหา location ที่ตรงกัน

### Workflow Export/Import
1. เลือกสินค้า → คลิก "ส่งออก Excel (นำเข้า)"
2. File นั้นมี location components (แถว, ล็อค, ชั้น)
3. Upload ไป import_excel.php → location data โหลดถูกต้อง

---

## 4. ไฟล์ที่แก้ไข

| ไฟล์ | การแก้ไข | บรรทัด |
|------|----------|--------|
| `stock/product_management.php` | Modal form, editProduct(), exportForImport() | 595-610, 710-712, 1117-1165 |
| `api/product_management_api.php` | Location logic create/update | 155-179, 234-266 |

---

## 5. ข้อมูล Location Components

### Database Fields
- `locations.row_code` - VARCHAR(10) - แถว (A-Z)
- `locations.bin` - INT - ล็อค (1-10)
- `locations.shelf` - INT - ชั้น (1-10)

### Display Format
- **Table:** 3 color-coded badges (primary/info/success)
- **Modal:** 3 separate input fields in grid layout
- **Export:** Column order matches import template

### Empty State Handling
- ถ้าส่วนใดว่าง → UI ไม่แสดง badge
- Modal fields สามารถเว้นว่างได้ (optional)
- Export เติม empty string สำหรับส่วนว่าง

---

## 6. Notes สำหรับการใช้งาน

✅ **แยกส่วนตำแหน่ง:** User สามารถกำหนด แถว/ล็อค/ชั้น ในแต่ละสินค้า
✅ **Export Import:** Export แล้วสามารถนำเข้าใหม่ได้โดยข้อมูลไม่หายไป
✅ **API Support:** product_detail_api.php คืนค่า row_code, bin, shelf แยก

⚠️ **สำคัญ:** ตำแหน่งต้องมีอยู่ใน locations table ก่อน เพื่อให้ system หา location_id
⚠️ **Database:** เมื่อแก้ไข row/bin/shelf ต้องหมั่นตรวจสอบว่ามี location ที่ตรงกัน

---

## 7. SQL Reference

### Query ค้นหา Location
```sql
SELECT location_id FROM locations 
WHERE row_code = 'A' AND bin = 2 AND shelf = 3;
```

### Query ดูสินค้า + Location
```sql
SELECT p.*, l.row_code, l.bin, l.shelf, l.description
FROM products p
LEFT JOIN product_location pl ON p.product_id = pl.product_id
LEFT JOIN locations l ON pl.location_id = l.location_id;
```

---

## ✨ สรุป
- **Popup แก้ไข:** ✅ แยก location ออกเป็น 3 fields
- **Excel Load:** ✅ เพิ่ม export function ที่ตรงกับ import template
- **Data Integrity:** ✅ Location data แสดง/โหลดถูกต้อง
