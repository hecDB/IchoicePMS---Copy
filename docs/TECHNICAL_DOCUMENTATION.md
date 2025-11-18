# ระบบสร้าง PO สำหรับสินค้าใหม่ - เอกสารเทคนิค

## 🔧 ข้อมูลเทคนิค

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend Layer                          │
├─────────────────────────────────────────────────────────────┤
│  purchase_order_create_new_product.php                       │
│  └─ HTML5 Form (enctype=multipart/form-data)               │
│  └─ JavaScript (FormData API)                               │
│  └─ CSS Grid Layout                                         │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ AJAX POST
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                     API Layer                               │
├─────────────────────────────────────────────────────────────┤
│  purchase_order_new_product_api.php                         │
│  └─ File Processing (MIME, Size validation)                │
│  └─ Base64 Encoding                                         │
│  └─ Database Transaction                                    │
│                                                              │
│  generate_po_number_api.php                                 │
│  └─ Query MAX PO Number                                     │
│  └─ Generate Next Number                                    │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ PDO Prepared Statements
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                    Data Layer                               │
├─────────────────────────────────────────────────────────────┤
│  MySQL Database                                              │
│  └─ temp_products (product_image LONGBLOB)                 │
│  └─ purchase_orders                                         │
│  └─ purchase_order_items                                    │
└─────────────────────────────────────────────────────────────┘
```

## 📋 API Endpoints

### 1. Generate PO Number
**Endpoint:** `api/generate_po_number_api.php`

**Method:** POST

**Request:**
```
POST /api/generate_po_number_api.php
```

**Response (Success - 200):**
```json
{
  "success": true,
  "po_number": "PO-2025-00001"
}
```

**Response (Error - 400):**
```json
{
  "success": false,
  "message": "เกิดข้อผิดพลาด: [error message]"
}
```

**Session Required:**
- `$_SESSION['user_role']` = 'admin' หรือ 'manager'

---

### 2. Create PO with New Products
**Endpoint:** `api/purchase_order_new_product_api.php`

**Method:** POST

**Headers:**
```
Content-Type: multipart/form-data
```

**POST Parameters:**
```
supplier_id         (int)      - ซัพพลายเยอร์ ID [บังคับ]
order_date          (date)     - วันที่สั่งซื้อ [บังคับ]
currency_id         (int)      - สกุลเงิน ID [บังคับ]
po_remark           (string)   - หมายเหตุ (optional)
po_number           (string)   - เลขที่ PO (optional, auto-generated if empty)

product_name[]      (string)   - ชื่อสินค้า [บังคับ]
category[]          (string)   - ประเภทสินค้า [บังคับ]
product_image[]     (file)     - รูปภาพสินค้า (optional)
unit[]              (string)   - หน่วยนับ [บังคับ]
quantity[]          (float)    - จำนวน [บังคับ]
unit_price[]        (float)    - ราคา/หน่วย [บังคับ]
discount[]          (float)    - ส่วนลด % (default: 0)
```

**Response (Success - 200):**
```json
{
  "success": true,
  "message": "สร้างใบ PO สำเร็จ",
  "po_id": 123,
  "po_number": "PO-2025-00001"
}
```

**Response (Error - 400):**
```json
{
  "success": false,
  "message": "กรุณากรอกข้อมูลเบื้องต้น"
}
```

**File Validation:**
- MIME Types: image/jpeg, image/png, image/gif, image/webp
- Max Size: 5 MB (5242880 bytes)
- Encoding: Base64

---

## 🗄️ Database Schema

### temp_products Table

```sql
CREATE TABLE `temp_products` (
  `temp_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(100) NOT NULL COMMENT 'ชื่อสินค้าเบื้องต้น',
  `product_category` varchar(100) DEFAULT NULL COMMENT 'ประเภทสินค้า',
  `product_image` longblob DEFAULT NULL COMMENT 'รูปภาพสินค้า (Base64 encoded)',
  `provisional_sku` varchar(255) DEFAULT NULL COMMENT 'SKU ชั่วคราว',
  `provisional_barcode` varchar(50) DEFAULT NULL COMMENT 'Barcode ชั่วคราว',
  `unit` varchar(20) DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
  `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
  `status` enum('draft','pending_approval','approved','rejected','converted') DEFAULT 'draft',
  `po_id` int(11) NOT NULL COMMENT 'ใบ PO ที่อ้างอิง',
  `created_by` int(11) NOT NULL COMMENT 'สร้างโดย user_id',
  `approved_by` int(11) DEFAULT NULL COMMENT 'อนุมัติโดย user_id',
  `created_at` timestamp DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`temp_product_id`),
  KEY `fk_po_id` (`po_id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`product_category`),
  CONSTRAINT `fk_po_id` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Column Details

| Column Name | Type | Size | Nullable | Default | Notes |
|-------------|------|------|----------|---------|-------|
| temp_product_id | INT | 11 | NO | AI | Primary Key |
| product_name | VARCHAR | 100 | NO | - | ชื่อสินค้า |
| product_category | VARCHAR | 100 | YES | NULL | ประเภทสินค้า |
| product_image | LONGBLOB | - | YES | NULL | Base64 encoded image |
| provisional_sku | VARCHAR | 255 | YES | NULL | SKU ชั่วคราว |
| provisional_barcode | VARCHAR | 50 | YES | NULL | Barcode ชั่วคราว |
| unit | VARCHAR | 20 | YES | 'ชิ้น' | หน่วยนับ |
| remark | TEXT | - | YES | NULL | หมายเหตุ |
| status | ENUM | - | NO | 'draft' | สถานะ |
| po_id | INT | 11 | NO | - | Foreign Key |
| created_by | INT | 11 | NO | - | สร้างโดย |
| approved_by | INT | 11 | YES | NULL | อนุมัติโดย |
| created_at | TIMESTAMP | - | NO | NOW() | วันที่สร้าง |
| approved_at | TIMESTAMP | - | YES | NULL | วันที่อนุมัติ |

---

## 💾 Database Queries

### Query 1: Insert Temp Product
```sql
INSERT INTO temp_products 
(product_name, product_category, product_image, unit, status, po_id, created_by) 
VALUES (?, ?, ?, ?, ?, ?, ?)
```

**Parameters:**
1. product_name (string)
2. product_category (string)
3. product_image (binary - Base64 encoded)
4. unit (string)
5. status (string - 'pending_approval')
6. po_id (int)
7. created_by (int)

### Query 2: Find Max PO Number for Year
```sql
SELECT MAX(CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED)) as max_num
FROM purchase_orders 
WHERE po_number LIKE CONCAT('PO-', ?, '-%')
```

**Parameters:**
1. year (string - '2025')

### Query 3: Update Temp Product Status
```sql
UPDATE temp_products 
SET status = ?, approved_by = ?, approved_at = NOW() 
WHERE temp_product_id = ?
```

**Parameters:**
1. status (string - 'converted')
2. approved_by (int)
3. temp_product_id (int)

---

## 🎨 JavaScript Functions

### generatePONumber()
```javascript
async function generatePONumber() {
  try {
    const response = await fetch('../api/generate_po_number_api.php', {
      method: 'POST'
    });
    const result = await response.json();
    
    if (result.success) {
      document.getElementById('po_number').value = result.po_number;
      // Show success message
    }
  } catch (error) {
    // Handle error
  }
}
```

### previewImage(input, imagePreviewId)
```javascript
function previewImage(input, imagePreviewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById(imagePreviewId);
      img.src = e.target.result;
      img.style.display = 'block';
      
      const uploadBtn = input.previousElementSibling;
      uploadBtn.style.display = 'none';
    }
    reader.readAsDataURL(input.files[0]);
  }
}
```

### addItemRow()
```javascript
function addItemRow() {
  itemCount++;
  const container = document.getElementById('itemsContainer');
  const imageUploadId = `imageUpload_${itemCount}`;
  const imagePreviewId = `imagePreview_${itemCount}`;
  
  // Create HTML for new row with image upload and category select
  // ...
}
```

---

## 🔒 Security Features

### 1. File Upload Validation
```php
// Check MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file);
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_types)) {
  throw new Exception('ประเภทรูปภาพไม่รองรับ');
}

// Check file size
if (filesize($file) > 5 * 1024 * 1024) {
  throw new Exception('ขนาดรูปภาพใหญ่เกินไป');
}
```

### 2. Session Verification
```php
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
if ($user_role !== 'admin' && $user_role !== 'manager') {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Access Denied']);
  exit;
}
```

### 3. Database Transaction
```php
$pdo->beginTransaction();
try {
  // Database operations
  $pdo->commit();
} catch (Exception $e) {
  $pdo->rollBack();
  throw $e;
}
```

---

## 📊 Performance Considerations

### 1. LONGBLOB Storage
- Base64 encoded images increase DB size by ~33%
- Example: 100 products × 500KB images = 75MB+ DB increase
- Consider archiving old records

### 2. Query Optimization
```sql
-- Add index for category queries
CREATE INDEX idx_category ON temp_products(product_category);

-- Add index for status queries
CREATE INDEX idx_status ON temp_products(status);
```

### 3. File Upload Limits
- PHP: `upload_max_filesize` = 10MB minimum recommended
- PHP: `post_max_size` >= `upload_max_filesize`
- Set timeout for large uploads

---

## 🧪 Testing Checklist

### Unit Tests
- [ ] Test PO number generation
- [ ] Test file validation (MIME type, size)
- [ ] Test Base64 encoding/decoding
- [ ] Test database transaction rollback

### Integration Tests
- [ ] Create PO with images
- [ ] Create PO without images
- [ ] Approve and convert products
- [ ] Reject products
- [ ] Query temp products

### Security Tests
- [ ] Upload non-image files
- [ ] Upload files > 5MB
- [ ] Test access control (non-admin users)
- [ ] Test SQL injection prevention
- [ ] Test XSS prevention

### UI Tests
- [ ] Image preview display
- [ ] Category dropdown selection
- [ ] PO number generation button
- [ ] Approve modal display
- [ ] Image display in modal

---

## 🐛 Troubleshooting

### Issue 1: "ขนาดรูปภาพใหญ่เกินไป"
**Solution:** Increase PHP settings:
```ini
upload_max_filesize = 10M
post_max_size = 10M
```

### Issue 2: Image not displaying
**Solution:** Check MIME type detection:
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file);
// Should return: image/jpeg, image/png, etc.
```

### Issue 3: Database transaction fails
**Solution:** Check PDO connection:
```php
try {
  $pdo->beginTransaction();
  // operations
  $pdo->commit();
} catch (PDOException $e) {
  $pdo->rollBack();
  error_log($e->getMessage());
}
```

### Issue 4: AJAX request timeout
**Solution:** Increase timeout in JavaScript:
```javascript
fetch(url, {
  method: 'POST',
  body: formData,
  timeout: 30000 // 30 seconds
});
```

---

## 📚 Code Review Checklist

- [ ] All variables initialized before use
- [ ] All user inputs sanitized/validated
- [ ] All database queries use prepared statements
- [ ] All responses include proper HTTP status codes
- [ ] All error messages user-friendly
- [ ] All images properly encoded/decoded
- [ ] Transaction handling correct
- [ ] Session checks implemented
- [ ] No hardcoded values
- [ ] Comments for complex logic

---

## 🚀 Deployment

### Pre-deployment
1. Run database migration
2. Test in staging environment
3. Verify file permissions (644 for files, 755 for directories)
4. Check PHP settings (upload limits, timeout)
5. Clear any cache

### Post-deployment
1. Verify API endpoints accessible
2. Test create PO functionality
3. Test approve functionality
4. Monitor database size
5. Check error logs

---

**Document Version:** 1.0
**Last Updated:** Nov 16, 2025
**Status:** Complete ✅
