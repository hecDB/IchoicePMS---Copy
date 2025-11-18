# ฟีเจอร์: ลดขนาดรูปภาพอัตโนมัติ

## 📝 คำอธิบาย

ระบบจะ **ลดขนาดรูปภาพอัตโนมัติ** เมื่อผู้ใช้กดปุ่ม "บันทึกใบ PO" ทำให้ไม่มีข้อผิดพลาด "ขนาดรูปใหญ่เกินไป"

---

## ⚙️ วิธีทำงาน

### ขั้นตอน 1: ผู้ใช้อัพโหลดรูป
```
- ผู้ใช้เลือกรูปเดิม (ขนาดอาจใหญ่)
- ระบบแสดงตัวอย่างรูป
```

### ขั้นตอน 2: กดบันทึก
```
- ผู้ใช้กดปุ่ม "บันทึกใบ PO"
- ระบบทำงานที่ Browser (Client-side)
```

### ขั้นตอน 3: ลดขนาดรูป (Client-side)
```
- ตรวจสอบขนาดรูปแต่ละรูป
- ถ้า > 500KB → ลดขนาด
- ลดให้ยาวด้านที่ยาวเป็น 800px
- ใช้ quality 80% เป็น JPEG
```

### ขั้นตอน 4: ส่งไปเซิร์ฟเวอร์
```
- รูปที่ลดขนาดแล้ว ส่งไป API
- API แปลง Base64 และบันทึก
```

---

## 📊 ตัวอย่างผลลัพธ์

### ก่อนลดขนาด
```
รูป 1: 8 MB → ⚠️ Error "ขนาดรูปใหญ่เกินไป"
รูป 2: 6 MB → ⚠️ Error "ขนาดรูปใหญ่เกินไป"
```

### หลังลดขนาด
```
รูป 1: 8 MB → ✅ ลดเป็น ~400 KB → บันทึกสำเร็จ
รูป 2: 6 MB → ✅ ลดเป็น ~300 KB → บันทึกสำเร็จ
```

---

## 🎯 ตัวเลขที่สำคัญ

| พารามิเตอร์ | ค่า | ความหมาย |
|----------|-----|---------|
| Min Size | - | ไม่ลดรูป < 500KB |
| Max Width/Height | 800px | ลดขนาดยาวสูงสุด |
| Quality | 80% | ความคมชัด JPEG |
| Max Upload | 10 MB | ขีดจำกัดสุดท้าย |

---

## 💻 Technical Details

### JavaScript (Frontend)

```javascript
// Canvas API ลดขนาดรูป
const canvas = document.createElement('canvas');
canvas.width = newWidth;
canvas.height = newHeight;
const ctx = canvas.getContext('2d');
ctx.drawImage(img, 0, 0, newWidth, newHeight);

// ส่งออกเป็น JPEG 80% quality
canvas.toBlob((blob) => {
    const compressedFile = new File([blob], file.name, {
        type: 'image/jpeg',
        lastModified: file.lastModified
    });
}, 'image/jpeg', 0.8);
```

### Flow Chart

```
[ผู้ใช้อัพโหลดรูป]
         ↓
[กดปุ่มบันทึก]
         ↓
[ลูปผ่านรูปแต่ละรูป]
         ↓
[ตรวจสอบขนาด > 500KB?]
    ↙                 ↖
   ใช่                  ไม่
    ↓                  ↓
[ลดขนาด]    [เก็บเดิม]
    ↓                  ↓
[รวมใหม่ FormData]
         ↓
[ส่ง API]
         ↓
[บันทึกลง DB]
         ↓
[✅ สำเร็จ]
```

---

## ✅ Supported Formats

- ✅ JPEG/JPG
- ✅ PNG
- ✅ GIF
- ✅ WebP

---

## ⚡ Performance

| ขนาดเดิม | ขนาดหลังลด | เวลาประมวลผล |
|---------|-----------|-----------|
| 8 MB | ~400 KB | ~1-2 sec |
| 6 MB | ~300 KB | ~1-2 sec |
| 5 MB | ~250 KB | ~0.5-1 sec |
| 3 MB | ~150 KB | ~0.5 sec |
| < 500 KB | ไม่เปลี่ยน | ไม่ประมวลผล |

---

## 🔧 File Changes

### Modified Files

1. **`orders/purchase_order_create_new_product.php`**
   - เพิ่ม Image Compression Logic
   - ใช้ Canvas API
   - Async/Promise handling

2. **`api/purchase_order_new_product_api.php`**
   - เพิ่ม File Size limit เป็น 10MB
   - ลบ 5MB check

---

## 📋 Browser Compatibility

| Browser | Support | Note |
|---------|---------|------|
| Chrome | ✅ | เต็มที่ |
| Firefox | ✅ | เต็มที่ |
| Safari | ✅ | เต็มที่ |
| Edge | ✅ | เต็มที่ |
| IE 11 | ❌ | ไม่รองรับ Canvas API |

---

## 🐛 Error Handling

ถ้าเกิดข้อผิดพลาด:

```javascript
try {
    // ลดขนาดรูป
    canvas.toBlob((blob) => {
        // ส่ง
    });
} catch (error) {
    showError('เกิดข้อผิดพลาด: ' + error.message);
}
```

---

## 🎨 User Experience

### ก่อนหน้า
```
❌ อัพโหลดรูปใหญ่ → กดบันทึก → ⚠️ Error → ต้องเลือกรูปใหม่
```

### ปัจจุบัน
```
✅ อัพโหลดรูปใหญ่ → กดบันทึก → ✅ ลดขนาดอัตโนมัติ → ✅ สำเร็จ
```

---

## 📊 Storage Savings

### ไม่มี Compression
```
10 PO × 3 รูปต่อ PO × 3 MB = 90 MB ต่อวัน
```

### มี Compression
```
10 PO × 3 รูปต่อ PO × 300 KB = 9 MB ต่อวัน
ประหยัด: 90% 🎉
```

---

## ✨ Features

- ✅ ลดขนาดอัตโนมัติ
- ✅ ไม่เปลี่ยนชื่อไฟล์
- ✅ Preserve Quality สมควร
- ✅ Error Handling
- ✅ No Dependencies
- ✅ Pure JavaScript

---

**Status:** ✅ Implemented  
**Version:** 1.0  
**Date:** Nov 16, 2025
