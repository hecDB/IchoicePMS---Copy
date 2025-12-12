# ระบบบันทึกสินค้าสูญหาย (Missing Products System)

## ประกาศ
สร้างระบบบันทึกและติดตามสินค้าที่สูญหายหรือหาไม่เจอในคลัง

## ขั้นตอนการใช้งาน

### 1. บันทึกสินค้าสูญหาย (`/stock/missing_products.php`)

#### ขั้นตอน 1: แสกนบาร์โค้ด/พิมพ์ SKU
- กดแล่ตรงช่องค้นหา หรือ กด `/` ได้ด้วย (Hotkey)
- พิมพ์บาร์โค้ด SKU หรือชื่อสินค้า
- ระบบจะแสดงรายการสินค้าที่ตรงกัน

#### ขั้นตอน 2: เลือกสินค้า
- คลิกไปที่สินค้าที่ต้องการจากรายการผลลัพธ์

#### ขั้นตอน 3: กรอกจำนวนสูญหาย
- กรอกจำนวนที่สูญหายหรือหาไม่เจอ
- (ทางเลือก) เพิ่มหมายเหตุ เช่น: "หาไม่เจอในตู้เก็บ", "ชำรุด", ฯลฯ

#### ขั้นตอน 4: บันทึก
- คลิก "บันทึกสินค้าสูญหาย"
- ระบบจะ:
  - บันทึกในตาราง `missing_products`
  - สร้าง transaction record ในตาราง `receive_items` (ลบออกจากสต็อก)
  - ปรับปรุง stock count อัตโนมัติ

### 2. ดูรายการสินค้าสูญหาย (`/stock/missing_products_list.php`)

- ดูรายการสินค้าสูญหายทั้งหมด
- สถิติจำนวนรายการ (ทั้งหมด, วันนี้, สัปดาห์นี้, จำนวนรวม)
- ลบรายการได้

## ฐานข้อมูล

### ตาราง: `missing_products`
| Column | Type | Description |
|--------|------|-------------|
| missing_id | INT | ID หลัก (Auto Increment) |
| product_id | INT | รหัสสินค้า |
| sku | VARCHAR(50) | SKU สินค้า |
| barcode | VARCHAR(100) | บาร์โค้ด |
| product_name | VARCHAR(255) | ชื่อสินค้า |
| quantity_missing | DECIMAL(10,2) | จำนวนสูญหาย |
| remark | TEXT | หมายเหตุ |
| reported_by | INT | ผู้บันทึก (user_id) |
| created_at | TIMESTAMP | วันที่บันทึก |
| updated_at | TIMESTAMP | วันที่อัปเดต |

### ตาราง: `receive_items` (ใช้สำหรับ transaction tracking)
เมื่อบันทึกสินค้าสูญหาย จะสร้าง record ดังนี้:
- `item_id`: ID สินค้า
- `po_id`: 0 (ไม่มี PO)
- `receive_qty`: -จำนวนสูญหาย (ลบออกจากสต็อก)
- `remark`: "สินค้าสูญหาย (Missing ID: XXX) - หมายเหตุ"
- `created_by`: ผู้บันทึก

## API Endpoints

### 1. ค้นหาสินค้า
**GET** `/api/missing_product_search_api.php?q=search_term`

Response:
```json
{
  "success": true,
  "message": "พบสินค้า X รายการ",
  "results": [
    {
      "product_id": 1,
      "sku": "SKU001",
      "barcode": "1234567890123",
      "product_name": "ชื่อสินค้า",
      "image": "image.jpg",
      "unit_cost": 100.00,
      "sale_price": 150.00
    }
  ]
}
```

### 2. บันทึกสินค้าสูญหาย
**POST** `/api/record_missing_product_api.php`

Parameters:
- `product_id`: ID สินค้า
- `quantity_missing`: จำนวนที่สูญหาย
- `remark`: หมายเหตุ (optional)
- `reported_by`: ID ผู้บันทึก

Response:
```json
{
  "success": true,
  "message": "บันทึกสินค้าสูญหายสำเร็จ",
  "missing_id": 1,
  "product_id": 1,
  "product_name": "ชื่อสินค้า",
  "quantity": 5,
  "receive_id": 123
}
```

### 3. ดูรายการสินค้าสูญหาย
**GET** `/api/get_missing_products_api.php?date=YYYY-MM-DD&limit=50`

Response:
```json
{
  "success": true,
  "count": 5,
  "date": "2025-11-20",
  "data": [
    {
      "missing_id": 1,
      "product_id": 1,
      "product_name": "ชื่อสินค้า",
      "quantity_missing": 5,
      "remark": "หมายเหตุ",
      "created_at": "2025-11-20 14:30:00",
      "created_by_name": "Username"
    }
  ]
}
```

### 4. ลบรายการสินค้าสูญหาย
**POST** `/api/delete_missing_product_api.php`

Parameters:
- `missing_id`: ID ของรายการ

Response:
```json
{
  "success": true,
  "message": "ลบรายการสูญหายสำเร็จ"
}
```

## ความสามารถ

✅ แสกนบาร์โค้ดและค้นหาสินค้า  
✅ ค้นหาด้วย SKU หรือชื่อสินค้า  
✅ เลือกสินค้าจากรายการผลลัพธ์  
✅ กรอกจำนวนและหมายเหตุ  
✅ บันทึกอัตโนมัติกับการเคลื่อนไหว (receive_items)  
✅ ลบรายการสูญหาย  
✅ ดูรายการสินค้าสูญหาย  
✅ สถิติรายการสูญหาย (วันนี้, สัปดาห์นี้, ทั้งหมด)  

## ไฟล์ที่สร้าง

### Frontend
- `/stock/missing_products.php` - หน้าบันทึกสินค้าสูญหาย
- `/stock/missing_products_list.php` - หน้าดูรายการสินค้าสูญหาย

### API
- `/api/missing_product_search_api.php` - ค้นหาสินค้า
- `/api/record_missing_product_api.php` - บันทึกสินค้าสูญหาย
- `/api/get_missing_products_api.php` - ดูรายการ
- `/api/delete_missing_product_api.php` - ลบรายการ

### Setup
- `/setup_missing_products_table.php` - สร้างตาราง
- `/db/create_missing_products_table.sql` - SQL schema

## ขั้นตอนการติดตั้ง

1. รันไฟล์ PHP เพื่อสร้างตาราง:
   ```
   php setup_missing_products_table.php
   ```
   หรือ import SQL file:
   ```
   mysql -u root -p ichoice_ < db/create_missing_products_table.sql
   ```

2. เข้าใช้ระบบและไปที่ `/stock/missing_products.php`

3. เริ่มบันทึกสินค้าสูญหาย

## โปรแกรม Integration

เมื่อสินค้าถูกบันทึกว่าสูญหาย:
1. ระบบลดจำนวนสต็อกของสินค้านั้นโดยอัตโนมัติ
2. สร้างรายการ movement ในตาราง `receive_items` ด้วย remark ที่ชัดเจน
3. บันทึกผู้ที่รายงาน และเวลาของการบันทึก
4. สามารถดูประวัติการเคลื่อนไหวของสินค้าได้ในหน้า Stock Movement

## หมายเหตุ

- ระบบใช้ transaction ของ database เพื่อให้มั่นใจว่าข้อมูลสอดคล้องกัน
- เมื่อลบรายการสูญหาย จะลบ transaction record ที่เกี่ยวข้องด้วย
- สามารถติดตามสินค้าสูญหายเป็นรายบุคคล ตามวันที่ และตามประเภท
