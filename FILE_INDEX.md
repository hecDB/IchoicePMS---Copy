# IchoicePMS — คู่มือไฟล์ระบบ (File Index)

แสดงไฟล์ทั้งหมดในระบบ แยกตามเมนูการทำงาน

---

## 1. แดชบอร์ด (Dashboard)

| ไฟล์ | หน้าที่ |
|------|---------|
| `index.php` | หน้าแรก — redirect ไปยัง login หรือ dashboard |
| `dashboard.php` | หน้า dashboard หลัก — แสดงรายการรออนุมัติและสินค้า |

---

## 2. จัดการคำสั่งซื้อ (Purchase Orders)

### หน้าหลัก

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `orders/purchase_orders.php` | รายการใบสั่งซื้อ | แสดงรายการ PO ทั้งหมด พร้อมสถานะ pending/partial/complete |
| `orders/purchase_order_create.php` | สร้างใบสั่งซื้อใหม่ | ฟอร์มสร้าง PO สำหรับสินค้าที่มีอยู่แล้ว |
| `orders/purchase_order_create_new_product.php` | ซื้อสินค้าใหม่ | สร้าง PO พร้อมสินค้าใหม่ (เฉพาะ admin/manager) รองรับสกุลเงิน |
| `orders/purchase_order_update.php` | — | แก้ไข PO header และรายการสินค้า |
| `orders/purchase_order_save.php` | — | API บันทึก PO ใหม่ลงฐานข้อมูล |
| `orders/purchase_order_delete.php` | — | API ลบ PO พร้อม cascade |
| `receive/receive_po_items.php` | รับเข้าสินค้า | หน้ารับสินค้า — แสดง PO ที่รอรับพร้อม progress |
| `receive/quick_receive.php` | รับสินค้าด่วน Scan | สแกน barcode รับสินค้าเร็ว |
| `receive/receive_po_detail.php` | — | รายละเอียด PO สำหรับรับสินค้าทีละรายการ |
| `receive/process_receive_po.php` | — | API บันทึกสินค้าที่รับเข้า |
| `receive/receive_edit.php` | — | API แก้ไขรายการรับสินค้า (qty, ราคา, ตำแหน่ง, วันหมดอายุ) |
| `receive/receive_product.php` | — | หน้ารับสินค้า legacy (barcode input) |

### API Purchase Order

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/purchase_order_api.php` | ดึงข้อมูล PO พร้อมรายการสินค้า ราคา สกุลเงิน |
| `api/purchase_order_new_product_api.php` | สร้าง PO สำหรับสินค้าใหม่ |
| `api/get_po_for_product.php` | หา PO ที่มีสินค้าที่ระบุ |
| `api/get_po_items.php` | รายการสินค้าใน PO |
| `api/get_po_items_new_product.php` | รายการ temp products ใน PO |
| `api/generate_po_number_api.php` | สร้างเลข PO ถัดไป |
| `api/generate_po_new_number_api.php` | สร้างเลข PO ใหม่ (logic ต่างออกไป) |
| `api/update_po_section.php` | อัปเดต section/header ของ PO |
| `api/get_cancelled_items_count.php` | นับรายการ PO ที่ถูกยกเลิก |

---

## 3. อัปโหลดสินค้า (Import/Upload Products)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `imports/import_excel.php` | อัปโหลด Excel | นำเข้าสินค้าจาก Excel (ใช้ PhpSpreadsheet) |
| `products/import_product.php` | — | UI นำเข้าสินค้า พร้อม debug log |
| `products/import_product_scannable.php` | — | UI สแกน barcode สำหรับนำเข้าสินค้า |
| `products/convert_temp_to_product.php` | — | แปลง temp_products เป็นสินค้าถาวร (เฉพาะ admin) |
| `templates/download_template.php` | — | ดาวน์โหลด Excel template สำหรับนำเข้าสินค้า |

---

## 4. จัดการสต็อก (Stock Management)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `receive/receive_items_view.php` | ความเคลื่อนไหวสินค้า | แสดงสินค้าที่รับเข้าทั้งหมด พร้อมตำแหน่ง สต็อก ราคา |
| `receive/transaction_view_separated.php` | สินค้าซื้อใหม่ | รายการสินค้าที่ซื้อใหม่ แยกมุมมอง |
| `stock/low_stock.php` | สินค้าใกล้หมด | สินค้าที่มีสต็อก ≤ 5 ชิ้น (critical/low/normal) |
| `stock/product_management.php` | จัดการสินค้า | รายการสินค้าครบถ้วน — category, ตำแหน่ง, สต็อก |
| `stock/expiring_soon.php` | สินค้าใกล้หมดอายุ | สินค้าหมดอายุภายใน 300 วัน พร้อมสถานะ |
| `returns/damaged_return_inspections.php` | สินค้าชำรุดบางส่วน | ฟอร์มตรวจสอบสินค้าชำรุด/บกพร่องระหว่างรับ |
| `stock/all_stock.php` | — | สินค้าในสต็อกทั้งหมด พร้อมตำแหน่ง (row-bin-shelf) |
| `receive/cancelled_items.php` | — | แสดงรายการ PO ที่ถูกยกเลิกทั้งหมด |
| `products/product_activity.php` | — | บันทึกการเคลื่อนไหวสินค้า |
| `products/product_activity_search.php` | — | API ค้นหา/กรองกิจกรรมสินค้า |

### API Stock/Receiving

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/receive_position_api.php` | ตำแหน่งจัดเก็บสินค้าที่รับเข้า |
| `api/approve_temp_product.php` | อนุมัติ temp product |
| `api/update_temp_product.php` | อัปเดตข้อมูล temp product |
| `api/get_damaged_unsellable_items.php` | รายการสินค้าชำรุดที่ขายไม่ได้ |
| `api/get_damaged_unsellable_by_po.php` | สินค้าชำรุดกรองตาม PO |
| `api/get_latest_product_price.php` | ราคาล่าสุดจาก PO ล่าสุด |
| `api/delete_product_holding.php` | ลบ product holding record |

---

## 5. สินค้านำออก (Borrowed/Taken Out Items)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `borrow/borrow_items.php` | สินค้าออกกรณีอื่นๆ | จัดการการยืม/นำออก — สถิติ active/overdue/returned |
| `stock/missing_products.php` | สินค้าออกกรณีอื่นๆ | บันทึกสินค้าสูญหาย/ชำรุด — สร้าง negative inventory |

### API Borrow/Missing

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/borrow_api.php` | จัดการการยืมสินค้า |
| `api/record_missing_product_api.php` | บันทึกสินค้าสูญหาย |
| `api/update_missing_product_api.php` | อัปเดตสินค้าสูญหาย |
| `api/get_missing_products_api.php` | รายการสินค้าสูญหายทั้งหมด |
| `api/get_missing_products_stats_api.php` | สถิติสินค้าสูญหาย |
| `api/missing_product_search_api.php` | ค้นหาสินค้าสูญหาย |
| `api/delete_missing_product_api.php` | ลบ record สินค้าสูญหาย |
| `api/return_missing_product_api.php` | บันทึกการคืนสินค้าที่สูญหาย |

---

## 6. ขายสินค้า (Sales / Issue Products)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `issue/issue_product.php` | ยิงสินค้าออก (ขาย) | หน้าขาย/จ่ายสินค้า พร้อม barcode scanning |
| `sales/sales_orders.php` | รายการขาย | รายการ sales orders ทั้งหมด — เลขแท็ก, วันที่, จำนวน |
| `sales/tag_management.php` | จัดการเลขแท็ก | จัดการ tag patterns สำหรับ Shopee และ Lazada |

### API Sales

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/issue_product_api.php` | จ่ายสินค้าขาย — ตรวจสอบ tag patterns |
| `api/sales_orders_api.php` | สร้าง/ดึง sales orders |
| `api/sales_dashboard_api.php` | สถิติและ dashboard ยอดขาย |
| `api/tag_management_api.php` | สร้าง/อัปเดต tag patterns |
| `api/get_next_invoice_number.php` | สร้างเลข invoice ถัดไป |
| `api/create_promotion_clearance.php` | สร้างโปรโมชั่น clearance sale |

---

## 7. รายงาน (Reports)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `reports/category_report.php` | รายงานประเภทสินค้า | สินค้าแยกตาม category พร้อม date range filter (max 90 วัน) |
| `reports/order_report.php` | รายงานคำสั่งซื้อ | วิเคราะห์ purchase order พร้อม date filter |
| `reports/product_report.php` | รายงานสินค้า | รายงาน inventory สินค้าโดยละเอียด |

---

## 8. ใบกำกับภาษี (Tax Invoices)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `reports/tax_invoice.php` | ใบกำกับภาษี | สร้าง/ออกใบกำกับภาษี — ข้อมูลผู้ซื้อ/ผู้ขาย คำนวณภาษี |
| `reports/tax_invoice_list.php` | ใบกำกับภาษีที่บันทึก | รายการใบกำกับภาษีทั้งหมด พร้อม export Excel |

### API Tax Invoice

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/save_tax_invoice.php` | บันทึกใบกำกับภาษี |
| `api/get_tax_invoice_detail.php` | ดึงรายละเอียดใบกำกับภาษี |
| `api/list_tax_invoices.php` | รายการใบกำกับภาษีพร้อม filter |

---

## 9. สินค้าตีกลับ (Returns)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `returns/return_items.php` | บันทึกสินค้าตีกลับ | ฟอร์มบันทึกสินค้าตีกลับ |
| `returns/return_dashboard.php` | จัดการสินค้าตีกลับ | dashboard รายการสินค้าตีกลับพร้อมสถานะ |
| `returns/damaged_return_inspections.php` | (ดูหัวข้อสต็อก) | ตรวจสอบสินค้าชำรุด — จัดหมวดหมู่การตีกลับ |

### API Returns

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/returned_items_api.php` | จัดการสินค้าตีกลับจากการขาย |

---

## 10. ผู้ดูแลระบบ / Admin (Admin Only)

| ไฟล์ | เมนู | หน้าที่ |
|------|------|---------|
| `admin/admin_users.php` | จัดการผู้ใช้งาน | รายการผู้ใช้รออนุมัติ/อนุมัติแล้ว — approve/reject/delete |
| `admin/admin_password_reset.php` | — | รีเซ็ตรหัสผ่าน สร้าง temporary password |
| `admin/admin_reset_requests.php` | — | แสดงคำขอรีเซ็ตรหัสผ่านที่รอดำเนินการ |
| `admin/delete_user.php` | — | API ลบผู้ใช้ |
| `admin/update_user.php` | — | API อัปเดตข้อมูลผู้ใช้ (ชื่อ, email, แผนก, role) |
| `admin/admin_password_reset_backup.php` | — | สำรอง password reset handler |
| `settings/currency_management.php` | จัดการสกุลเงิน | จัดการสกุลเงิน อัตราแลกเปลี่ยน กำหนด base currency |

### API Admin/User

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/users_api.php` | จัดการผู้ใช้ผ่าน API |

---

## 11. ระบบ Authentication

| ไฟล์ | หน้าที่ |
|------|---------|
| `auth/combined_login_register.php` | หน้า login/register — workflow อนุมัติผู้ใช้ใหม่ |
| `auth/auth_check.php` | Include ตรวจสอบ session — redirect ถ้าไม่ได้ login |
| `auth/force_change_password.php` | บังคับเปลี่ยนรหัสผ่านครั้งแรก (8+ ตัว, ตัวพิมพ์ใหญ่/เล็ก, ตัวเลข, อักขระพิเศษ) |
| `auth/forgot_password.php` | ลืมรหัสผ่าน — สร้างคำขอใน password_reset_requests |
| `auth/process_reset.php` | ยืนยันและประมวลผลการรีเซ็ตรหัสผ่าน |
| `auth/logout.php` | ลบ session และ redirect ไปยัง login |
| `auth/hash_pass.php` | Utility สำหรับ hash รหัสผ่าน |

---

## 12. API ทั่วไป

| ไฟล์ | หน้าที่ |
|------|---------|
| `api/product_search_api.php` | ค้นหาสินค้า — barcode/SKU/ชื่อ พร้อม filter |
| `api/product_detail_api.php` | ดึงรายละเอียดสินค้า — ราคา สต็อก ตำแหน่ง |
| `api/product_management_api.php` | สร้าง/แก้ไข/ลบสินค้า |
| `api/barcode_search_api.php` | ค้นหาสินค้าด้วย barcode |
| `api/get_product_categories.php` | รายการ category ทั้งหมด |
| `api/get_product_location.php` | ตำแหน่งจัดเก็บสินค้า (row, bin, shelf) |
| `api/suppliers_api.php` | รายการ supplier พร้อมข้อมูลติดต่อ |
| `api/supplier_add_api.php` | เพิ่ม supplier ใหม่ |
| `api/currency_api.php` | รายการสกุลเงินพร้อมอัตราแลกเปลี่ยน |
| `api/get_exchange_rates.php` | อัตราแลกเปลี่ยนปัจจุบัน |
| `api/get_locations_list.php` | รายการตำแหน่งจัดเก็บ |
| `api/acknowledge_expiry_api.php` | ยืนยันรับทราบการแจ้งเตือนหมดอายุ |
| `api/expiry_notification_api.php` | สร้าง/ดึงการแจ้งเตือนหมดอายุ |

---

## 13. Infrastructure / Config

| ไฟล์ | หน้าที่ |
|------|---------|
| `config/db_connect.php` | เชื่อมต่อฐานข้อมูล PDO — โหลด .env |
| `config/env_loader.php` | โหลด environment variables |
| `config/test_connection.php` | ทดสอบการเชื่อมต่อฐานข้อมูล |
| `templates/sidebar.php` | เมนู navigation หลัก — role-based visibility, pending counts |
| `includes/tag_validator.php` | ตรวจสอบ tag patterns สำหรับ Shopee/Lazada |
| `composer.json` / `composer.lock` | PHP dependencies (PhpSpreadsheet, etc.) |
| `vendor/` | PHP libraries (autoload) |

---

## 14. SQL Migrations (db/)

| ไฟล์ | หน้าที่ |
|------|---------|
| `db/001_create_temp_products_table.sql` | สร้างตาราง temp_products |
| `db/002_add_missing_columns_to_po_items.sql` | เพิ่มคอลัมน์ currency ใน purchase_order_items |
| `db/003_fix_sale_price_default.sql` | แก้ค่า default ของ sale price |
| `db/generate_locations.php` | สร้าง location records |
| `db/run_migration.php` | รัน migration scripts |

---

## 15. Assets / Frontend

| ไฟล์ | หน้าที่ |
|------|---------|
| `assets/base.css` | สไตล์พื้นฐาน |
| `assets/sidebar.css` | สไตล์ sidebar |
| `assets/components.css` | สไตล์ component |
| `assets/style.css` | stylesheet ทั่วไป |
| `assets/purchase_order.css` | สไตล์เฉพาะหน้า PO |
| `assets/modern-table.js` | UI ตารางสมัยใหม่ |
| `assets/tax-invoice-print.js` | ฟังก์ชันพิมพ์ใบกำกับภาษี |
| `js/main.js` | JavaScript utilities หลัก |
| `js/barcode_scanner.js` | ระบบสแกน barcode |

---

## สรุปจำนวนไฟล์ตามโฟลเดอร์

| โฟลเดอร์ | จำนวน | หน้าที่ |
|-----------|--------|---------|
| `admin/` | 6 | จัดการผู้ใช้, รีเซ็ตรหัสผ่าน |
| `api/` | ~48 | JSON APIs ทั้งหมด |
| `auth/` | 7 | Authentication, login, password |
| `borrow/` | 1 | จัดการการยืมสินค้า |
| `config/` | 3 | Database config, environment |
| `db/` | ~10 | SQL migrations |
| `imports/` | 1 | นำเข้า Excel |
| `includes/` | 1 | Tag validation |
| `issue/` | 1 | ออกสินค้า/ขาย |
| `js/` | 2 | JavaScript |
| `orders/` | 7 | Purchase order management |
| `products/` | 4 | จัดการสินค้า, import |
| `receive/` | 8 | รับสินค้า, ดูสต็อก |
| `reports/` | 5 | รายงาน, ใบกำกับภาษี |
| `returns/` | 3 | สินค้าตีกลับ, ชำรุด |
| `sales/` | 2 | ขาย, จัดการแท็ก |
| `settings/` | 1 | จัดการสกุลเงิน |
| `stock/` | 4 | สต็อก, สินค้าใกล้หมด |
| `templates/` | 2 | Sidebar, templates |
| Root | 3 | index, dashboard, composer |
