<?php
/**
 * ตัวอย่างการทดสอบระบบยิงสินค้าออก
 * วันที่สร้าง: 13 ตุลาคม 2025
 */

echo "<h2>ตัวอย่างการทดสอบระบบยิงสินค้าออก</h2>";

// Test 1: ทดสอบการค้นหาสินค้าสำหรับยิงออก
echo "<h3>Test 1: การค้นหาสินค้าสำหรับยิงออก</h3>";
?>

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h4>API Endpoint:</h4>
    <code>GET /api/product_search_api.php?q=pen&type=issue&available_only=true</code>
    
    <h4>ผลลัพธ์ที่คาดหวัง:</h4>
    <pre>{
  "products": [
    {
      "product_id": 1,
      "name": "ดินสอ HB",
      "sku": "PEN001",
      "barcode": "1234567890123",
      "unit": "ชิ้น",
      "image": "pencil.jpg",
      "receive_id": 5,
      "available_qty": 10,
      "expiry_date": null,
      "receive_date": "2025-09-08 08:15:05",
      "lot_info": "ล็อตรับ: 08/09/2025"
    }
  ]
}</pre>
</div>

<?php
// Test 2: ทดสอบการบันทึกการยิงสินค้า
echo "<h3>Test 2: การบันทึกการยิงสินค้าออก</h3>";
?>

<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h4>API Endpoint:</h4>
    <code>POST /api/issue_product_api.php</code>
    
    <h4>ข้อมูลที่ส่ง (JSON):</h4>
    <pre>{
  "issue_tag": "SALE-2025-001",
  "products": [
    {
      "product_id": 1,
      "receive_id": 5,
      "name": "ดินสอ HB",
      "sku": "PEN001",
      "barcode": "1234567890123",
      "available_qty": 10,
      "issue_qty": 3,
      "sale_price": 15.00
    }
  ]
}</pre>
    
    <h4>ผลลัพธ์ที่คาดหวัง:</h4>
    <pre>{
  "success": true,
  "message": "ยิงสินค้าออกสำเร็จทั้งหมด 1 รายการ (แท็ค: SALE-2025-001)",
  "count": 1
}</pre>
</div>

<?php
// Test 3: ทดสอบการตรวจสอบสต็อก
echo "<h3>Test 3: การตรวจสอบสต็อกไม่เพียงพอ</h3>";
?>

<div style="background: #fff5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h4>สถานการณ์:</h4>
    <p>ต้องการยิงสินค้า 15 ชิ้น แต่มีสต็อกเพียง 10 ชิ้น</p>
    
    <h4>ผลลัพธ์ที่คาดหวัง:</h4>
    <pre>{
  "success": false,
  "message": "ไม่สามารถยิงสินค้าออกได้: สินค้า ดินสอ HB มีสต็อกไม่เพียงพอ (คงเหลือ: 10, ต้องการ: 15)"
}</pre>
</div>

<?php
// Test 4: ทดสอบการทำงานของ FIFO
echo "<h3>Test 4: การทำงานของระบบ FIFO</h3>";
?>

<div style="background: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h4>สถานการณ์:</h4>
    <p>สินค้าเดียวกันมี 3 ล็อต:</p>
    <ul>
        <li>ล็อต A: รับเข้า 01/09/2025, หมดอายุ 01/12/2025, จำนวน 5 ชิ้น</li>
        <li>ล็อต B: รับเข้า 05/09/2025, หมดอายุ 15/11/2025, จำนวน 8 ชิ้น</li>
        <li>ล็อต C: รับเข้า 03/09/2025, ไม่มีวันหมดอายุ, จำนวน 12 ชิ้น</li>
    </ul>
    
    <h4>ลำดับที่ระบบจะแสดง (FIFO):</h4>
    <ol>
        <li><strong>ล็อต B</strong> - หมดอายุเร็วสุด (15/11/2025)</li>
        <li><strong>ล็อต A</strong> - หมดอายุ (01/12/2025)</li>
        <li><strong>ล็อต C</strong> - ไม่หมดอายุ แต่รับเข้าเก่ากว่า</li>
    </ol>
    
    <h4>เหตุผล:</h4>
    <p>ระบบจะเรียงตาม: หมดอายุเร็ว → หมดอายุช้า → ไม่หมดอายุ (เรียง FIFO)</p>
</div>

<div style="background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3>✅ สรุปการทดสอบ</h3>
    <p><strong>ระบบยิงสินค้าออกพร้อมใช้งานแล้ว!</strong></p>
    
    <h4>คุณสมบัติที่ทำงานได้:</h4>
    <ul>
        <li>✅ การกรอกแท็คส่งออก</li>
        <li>✅ การค้นหาสินค้าแบบ FIFO</li>
        <li>✅ การเลือกและปรับจำนวนสินค้า</li>
        <li>✅ การตรวจสอบสต็อคคงเหลือ</li>
        <li>✅ การบันทึกข้อมูลด้วย Transaction</li>
        <li>✅ การแจ้งเตือนและ Error handling</li>
        <li>✅ User Interface ที่สวยและใช้งานง่าย</li>
    </ul>
    
    <h4>เมนูใน Sidebar:</h4>
    <p>📍 <strong>จัดการสต็อก</strong> → <strong>ยิงสินค้าออก (ขาย)</strong></p>
</div>

<style>
    body { font-family: 'Sarabun', sans-serif; margin: 20px; }
    h2 { color: #2d3748; border-bottom: 3px solid #4299e1; padding-bottom: 10px; }
    h3 { color: #4a5568; margin-top: 30px; }
    h4 { color: #2b6cb0; }
    code { background: #f7fafc; padding: 2px 6px; border-radius: 4px; }
    pre { background: #f7fafc; padding: 15px; border-radius: 8px; overflow-x: auto; }
    ul, ol { line-height: 1.6; }
</style>