# ระบบยืมสินค้า (Borrow Item System)

## 📋 สรุป
สร้างระบบยืมสินค้าแบบ standalone ที่ไม่เกี่ยวกับใบ PO สำหรับใช้งาน:
- การตรวจสอบสินค้า (QC/Inspection)
- การโฆษณา (Marketing/Demo)
- การวิจัยพัฒนา (Research)
- อื่นๆ

## 🗄️ โครงสร้างฐานข้อมูล

### 1. ตาราง `borrow_categories` (หมวดหมู่การยืม)
```sql
- category_id: รหัสหมวดหมู่
- category_name: ชื่อหมวดหมู่ (เช่น โฆษณา, ตรวจสอบ, ฯลฯ)
- description: คำอธิบาย
- created_at: วันที่สร้าง
```

### 2. ตาราง `item_borrows` (บันทึกการยืม)
```sql
- borrow_id: รหัสการยืม (PK)
- borrow_number: หมายเลขการยืม (BRW-2025-000001)
- borrow_date: วันที่ยืม
- category_id: หมวดหมู่การยืม (FK)
- borrower_name: ชื่อผู้ยืม
- borrower_phone: เบอร์โทรผู้ยืม
- borrower_email: อีเมลผู้ยืม
- purpose: วัตถุประสงค์การยืม
- expected_return_date: วันที่คาดว่าจะคืน
- actual_return_date: วันที่คืนจริง
- status: สถานะ (active, returned, overdue, cancelled)
- notes: หมายเหตุ
- created_by: ผู้สร้างรายการ (FK)
- created_at, updated_at: timestamp
```

### 3. ตาราง `borrow_items` (รายการสินค้าที่ยืม)
```sql
- borrow_item_id: รหัสรายการยืม (PK)
- borrow_id: รหัสการยืม (FK)
- product_id: รหัสสินค้า (FK) - nullable
- product_name: ชื่อสินค้า
- sku: รหัสสินค้า
- qty: จำนวน
- unit: หน่วย (ชิ้น, กล่อง, ม้วน ฯลฯ)
- image: รูปสินค้า
- notes: หมายเหตุ
- created_at: วันที่สร้าง
```

## 📁 ไฟล์ที่สร้าง

### 1. Database Migration
- **ไฟล์**: `db/create_borrow_table.sql`
- **เนื้อหา**: 
  - CREATE TABLE statements
  - Default categories insertion
  - Stored procedure สำหรับสร้างหมายเลข

### 2. API Endpoints
- **ไฟล์**: `api/borrow_api.php`
- **Methods**:
  - `action=list` - ดึงรายการยืม (สามารถ filter ตาม status)
  - `action=get&id={id}` - ดึงรายละเอียดการยืม (พร้อม items)
  - `action=create` - สร้างการยืมใหม่
  - `action=return` - บันทึกการคืนสินค้า
  - `action=categories` - ดึงรายชื่อหมวดหมู่

### 3. UI Frontend
- **ไฟล์**: `borrow/borrow_items.php`
- **ฟีเจอร์**:
  - Dashboard stats (total, active, overdue, returned)
  - Filter by status
  - DataTable listing
  - Modal form สำหรับสร้างการยืม
  - View detail popup
  - Return item form
  - Real-time item addition/removal

### 4. Menu Integration
- **ไฟล์**: `templates/sidebar.php` (อัปเดต)
- **เพิ่มเมนู**: "ยืมสินค้า" ในเมนูหลัก
- **ไอคอน**: card_giftcard

## 🎯 หมวดหมู่การยืมตั้งต้น

1. โฆษณา / Marketing
2. ตรวจสอบ / QC
3. เปรียบเทียบ / Demo
4. วิจัย / Research
5. อื่นๆ

## 🔄 Workflow

### การยืมสินค้า
1. คลิก "เพิ่มการยืมใหม่"
2. ป้อนข้อมูลผู้ยืม (ชื่อ, เบอร์โทร, อีเมล)
3. เลือกหมวดหมู่
4. ป้อนเป้าประสงค์
5. ตั้งวันที่คาดว่าจะคืน
6. เพิ่มรายการสินค้า (จำนวนและหน่วย)
7. บันทึก → ระบบสร้างหมายเลขอัตโนมัติ

### การคืนสินค้า
1. ไปที่ "รายการยืมสินค้า"
2. ค้นหารายการที่ยิม (status = active)
3. คลิก "คืน"
4. ป้อนหมายเหตุการคืน (ถ้ามี)
5. ยืนยัน → ระบบอัปเดต status เป็น "returned"

## 📊 Status ของการยืม

- **active** - กำลังยืมอยู่
- **returned** - คืนแล้ว
- **overdue** - เกินกำหนดการคืน (ต้องตั้งค่าแยก)
- **cancelled** - ยกเลิกการยืม

## 🎨 UI Elements

### Statistics Cards
- Background gradient
- Total borrows
- Active borrows
- Overdue items
- Returned items

### Table
- Borrow number
- Borrower name
- Category
- Borrow date
- Expected return date
- Status badge
- Item count
- Action buttons

### Modal Form
- Responsive grid layout
- Form validation
- Item management (add/remove)
- Real-time updates

## 🔒 Security

- ✅ User authentication required
- ✅ Created by tracking (user_id)
- ✅ Input validation
- ✅ Prepared statements (SQL Injection protection)
- ✅ Transaction support

## 🚀 วิธีการใช้งาน

### 1. นำเข้าฐานข้อมูล
```bash
mysql -u username -p database_name < db/create_borrow_table.sql
```

### 2. เข้าถึงระบบ
- ผ่าน menu: ยืมสินค้า → รายการยืมสินค้า
- URL: `/borrow/borrow_items.php`

### 3. API Usage
```php
// List all active borrows
GET /api/borrow_api.php?action=list&status=active

// Get borrow details
GET /api/borrow_api.php?action=get&id=1

// Create new borrow
POST /api/borrow_api.php
Parameters: action=create, borrower_name, category_id, items (JSON), etc.

// Return items
POST /api/borrow_api.php
Parameters: action=return, borrow_id, actual_return_date, return_notes

// Get categories
GET /api/borrow_api.php?action=categories
```

## 📝 Notes

- หมายเลขการยืมสร้างอัตโนมัติในรูป BRW-YYYY-XXXXXX
- สินค้าสามารถเพิ่มได้โดยไม่ต้องผูกกับ PO
- รองรับการติดตามสินค้าหลายสภาพ (image, SKU, unit)
- สามารถ filter และ search ได้อย่างอิสระ
- มี Dashboard เพื่อติดตามสถานะการยืม

## ✅ Features Ready

- ✅ Database schema complete
- ✅ API endpoints functional
- ✅ UI frontend complete
- ✅ Menu integration done
- ✅ Statistics dashboard
- ✅ Form validation
- ✅ Real-time item management
- ✅ Status tracking
- ✅ Return management
- ✅ DataTable integration
