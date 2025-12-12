# 🎯 สรุปการเปลี่ยนแปลง - ระบบ PO สินค้าใหม่

## 📦 ไฟล์ที่สร้างใหม่

### 1. Database Layer
- **`db/add_temp_products_table.sql`** ✨ NEW
  - สร้างตาราง `temp_products` (14 คอลัมน์)
  - เพิ่มคอลัมน์ `temp_product_id` ใน `purchase_order_items`
  - สร้าง Indexes สำหรับการค้นหาและ Foreign Keys

### 2. Frontend - User Interface

- **`orders/purchase_order_create_new_product.php`** ✨ NEW
  - หน้าสร้าง PO สำหรับสินค้าใหม่
  - UI ที่ทันสมัย พร้อม Responsive Design
  - ฟังก์ชัน dynamic item addition/removal
  - Real-time calculation ของยอดรวม
  - ตรวจสอบสิทธิ์ (Admin/Manager only)

- **`products/convert_temp_to_product.php`** ✨ NEW
  - หน้าอนุมัติและแปลงสินค้า temp เป็นถาวร
  - แสดงรายการสินค้าที่รอการอนุมัติ
  - Modal สำหรับอนุมัติ/ปฏิเสธ
  - ตรวจสอบ SKU/Barcode unique
  - ตรวจสอบสิทธิ์ (Admin only)

### 3. Backend - API

- **`api/purchase_order_new_product_api.php`** ✨ NEW
  - รับข้อมูล POST จากฟอร์มสร้าง PO
  - สร้าง PO record ใหม่
  - บันทึกสินค้าชั่วคราวใน `temp_products`
  - เชื่อมต่อ `purchase_order_items` กับ temp products
  - Transaction support (commit/rollback)
  - ตรวจสอบ Supplier และ Currency validation

### 4. Documentation

- **`PO_NEW_PRODUCT_GUIDE.md`** ✨ NEW
  - คำแนะนำการใช้งานฉบับสมบูรณ์
  - ตารางโครงสร้างฐานข้อมูล
  - ขั้นตอนการใช้งาน (Step-by-step)
  - Status flow diagram
  - Permission matrix
  - API Documentation

---

## 🔧 ไฟล์ที่แก้ไข

### `orders/purchase_orders.php`
**การเปลี่ยนแปลง:**
- เพิ่มปุ่ม "🆕 สร้าง PO - สินค้าใหม่" (สีเขียว) ข้างปุ่มเดิม
- ปุ่มเดิม "สร้าง PO - สินค้าเดิม" ยังคงใช้ได้

```php
// Before:
<a href="../orders/purchase_order_create.php" class="btn btn-primary">
    สร้างใบสั่งซื้อใหม่
</a>

// After:
<div style="display: flex; gap: 10px;">
    <a href="../orders/purchase_order_create.php" class="btn btn-primary">
        สร้าง PO - สินค้าเดิม
    </a>
    <a href="../orders/purchase_order_create_new_product.php" class="btn btn-primary" 
       style="background-color: #27ae60;">
        สร้าง PO - สินค้าใหม่
    </a>
</div>
```

---

## 📊 Database Schema

### ตาราง `temp_products` (สร้างใหม่)

```
temp_product_id (INT, PK, Auto Increment)
product_name (VARCHAR 100, NOT NULL)
provisional_sku (VARCHAR 255, NULL)
provisional_barcode (VARCHAR 50, NULL)
unit (VARCHAR 20, DEFAULT 'ชิ้น')
remark (TEXT, NULL)
status (ENUM: draft, pending_approval, approved, rejected, converted)
po_id (INT, FK to purchase_orders, NOT NULL)
created_by (INT, FK to users, NOT NULL)
approved_by (INT, FK to users, NULL)
created_at (TIMESTAMP, DEFAULT current_timestamp)
approved_at (TIMESTAMP, NULL)
```

### ตาราง `purchase_order_items` (แก้ไข)

**เพิ่มคอลัมน์:**
- `temp_product_id` (INT, NULL) - ลิงก์ไปยัง temp_products
- Index: `idx_temp_product_id`

---

## 🔐 Security & Permissions

### Role-based Access Control

| Feature | Admin | Manager | Stock Staff | Guest |
|---------|-------|---------|-------------|-------|
| ดู PO ทั้งหมด | ✓ | ✓ | ✓ | ✗ |
| สร้าง PO - สินค้าเดิม | ✓ | ✓ | ✗ | ✗ |
| สร้าง PO - สินค้าใหม่ | ✓ | ✓ | ✗ | ✗ |
| ดูรายการรอการอนุมัติ | ✓ | ✓ | ✗ | ✗ |
| อนุมัติสินค้า | ✓ | ✗ | ✗ | ✗ |
| ปฏิเสธสินค้า | ✓ | ✗ | ✗ | ✗ |

### Validation Checks

✅ Database Level:
- Foreign Key constraints on `po_id`, `created_by`
- AUTO_INCREMENT on `temp_product_id`
- NOT NULL constraints

✅ Application Level:
- Session & role verification
- Supplier existence check
- Currency existence check
- Duplicate SKU/Barcode check (on approval)
- Quantity & price validation (> 0)

✅ Data Integrity:
- Transaction management (BEGIN/COMMIT/ROLLBACK)
- Atomic operations for multi-step processes

---

## 🚀 Installation Steps

### 1. Run Database Migration
```bash
# Option 1: phpMyAdmin
# Upload db/add_temp_products_table.sql through phpMyAdmin

# Option 2: Command Line
mysql -u ichoice_admin -p ichoice_ < db/add_temp_products_table.sql

# Option 3: Direct MySQL Client
# Copy & paste queries from add_temp_products_table.sql
```

### 2. Verify Installation
```sql
-- Check if temp_products table exists
SHOW TABLES LIKE 'temp_products';

-- Check columns in purchase_order_items
DESC purchase_order_items;
-- Should see temp_product_id column
```

### 3. Update User Role (if needed)
Ensure your users have the correct roles set:
- Admin = `admin`
- Manager = `manager`
- Stock Staff = `staff`

### 4. Test the System
1. Login as Admin/Manager
2. Go to Purchase Orders
3. Click "🆕 สร้าง PO - สินค้าใหม่" (green button)
4. Fill in form and submit
5. Check `temp_products` table
6. Go to "✅ อนุมัติและแปลงสินค้าใหม่"
7. Approve the product
8. Verify in `products` table

---

## 📝 Usage Flow Diagram

```
┌─────────────────────────────────────┐
│   Purchase Orders Page              │
│  (orders/purchase_orders.php)        │
└──────────────┬──────────────────────┘
               │
       ┌───────┴────────┐
       │                │
  [สินค้าเดิม]      [🆕 สินค้าใหม่]
       │                │
       │       ┌────────────────────────────────┐
       │       │  Create PO Form                │
       │       │  (purchase_order_create_new...)│
       │       │  Input: Product Details        │
       │       │  Button: บันทึกใบ PO           │
       │       └────────────┬───────────────────┘
       │                    │
       │       ┌────────────▼───────────────────┐
       │       │  API: purchase_order_new...    │
       │       │  - Create PO                   │
       │       │  - Save temp_products          │
       │       │  - Link to PO items            │
       │       │  Return: PO Number ✓           │
       │       └────────────┬───────────────────┘
       │                    │
       │       ┌────────────▼───────────────────┐
       │       │  Admin Review                  │
       │       │  (convert_temp_to_product.php) │
       │       │  Status: pending_approval      │
       │       └────────────┬───────────────────┘
       │                    │
       │       ┌────────────┴────────────┐
       │       │                         │
       │   [✓ Approve]              [✗ Reject]
       │       │                         │
  [Process]┌──┴──────────┐         ┌────┴─────┐
       │   │  Input SKU/ │         │  Reason  │
       │   │  Barcode    │         │          │
       │   └──┬──────────┘         └────┬─────┘
       │      │                         │
       │  [Create in products] ✓    [Mark rejected]
       │      │                         │
       └──────┴──────────┬──────────────┘
                         │
               ┌─────────▼─────────┐
               │  Status Updated   │
               │  Status: converted│
               │  or: rejected     │
               └───────────────────┘
```

---

## 🐛 Troubleshooting

### Issue: "Access Denied" when trying to create PO
**Solution:** Check that your user role is set to `admin` or `manager` in the `users` table

### Issue: "ซัพพลายเยอร์ไม่มีอยู่"
**Solution:** Ensure the supplier exists and has `is_active = 1` in `suppliers` table

### Issue: "SKU นี้มีอยู่แล้ว"
**Solution:** The SKU you entered already exists in `products` table. Use a different SKU or leave blank

### Issue: Table `temp_products` doesn't exist
**Solution:** Run the SQL migration file: `db/add_temp_products_table.sql`

### Issue: API returns error "ไม่สามารถสร้างใบ PO"
**Solution:** Check browser console for error details and database logs

---

## 📋 Testing Checklist

- [ ] Database migration ran successfully
- [ ] temp_products table exists
- [ ] purchase_order_items has temp_product_id column
- [ ] Can see both PO creation buttons
- [ ] Can fill and submit new product PO form
- [ ] Data saved in temp_products table
- [ ] Admin can see pending products
- [ ] Admin can approve and convert
- [ ] Product appears in products table
- [ ] purchase_order_items linked correctly

---

## 🔄 Future Enhancements

Possible improvements for future versions:
- [ ] Bulk import of temp products
- [ ] Email notifications on approval/rejection
- [ ] Product category auto-assignment
- [ ] Barcode generation integration
- [ ] Integration with supplier catalog
- [ ] Approval workflow with multiple levels
- [ ] SKU auto-generation based on pattern
- [ ] History/audit trail for changes

---

## 📞 Support

For questions or issues:
1. Check `PO_NEW_PRODUCT_GUIDE.md` for detailed documentation
2. Review SQL queries in `db/add_temp_products_table.sql`
3. Check browser console (F12) for JavaScript errors
4. Check server logs for PHP errors
5. Contact system administrator

---

**Created:** Nov 16, 2025
**Version:** 1.0
**Status:** Ready for Production ✓
