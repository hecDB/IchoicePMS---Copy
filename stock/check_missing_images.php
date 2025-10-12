<?php
/**
 * สคริปต์ตรวจสอบรูปภาพที่หายไป
 * วันที่สร้าง: 13 ตุลาคม 2025
 * วัตถุประสงค์: ตรวจสอบและรายงานไฟล์รูปภาพที่บันทึกใน database แต่ไม่มีไฟล์จริง
 */

require_once '../config/db_connect.php';

echo "<h2>รายงานการตรวจสอบรูปภาพที่หายไป</h2>\n";
echo "<p><strong>วันที่ตรวจสอบ:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// ดึงข้อมูลสินค้าที่มีรูปภาพ
$stmt = $pdo->query("
    SELECT product_id, name, sku, image 
    FROM products 
    WHERE image IS NOT NULL AND image != '' 
    ORDER BY product_id
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$missing_images = [];
$found_images = [];
$total_products = count($products);

echo "<h3>ผลการตรวจสอบ:</h3>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr style='background: #f0f0f0;'>
        <th>Product ID</th>
        <th>ชื่อสินค้า</th>
        <th>SKU</th>
        <th>ชื่อไฟล์รูปภาพ</th>
        <th>สถานะ</th>
        <th>Path ที่พบ</th>
      </tr>\n";

foreach ($products as $product) {
    $image_file = $product['image'];
    
    // ลองหา path ต่าง ๆ
    $possible_paths = [
        "../images/{$image_file}",
        "../{$image_file}"
    ];
    
    // ถ้ามี images/ prefix แล้ว
    if (strpos($image_file, 'images/') === 0) {
        $possible_paths[] = "../{$image_file}";
    }
    
    $found_path = null;
    $status = '❌ ไม่พบ';
    $status_color = 'color: red;';
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $found_path = $path;
            $status = '✅ พบแล้ว';
            $status_color = 'color: green;';
            $found_images[] = $product;
            break;
        }
    }
    
    if (!$found_path) {
        $missing_images[] = $product;
    }
    
    echo "<tr>\n";
    echo "<td>{$product['product_id']}</td>\n";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>\n";
    echo "<td>" . htmlspecialchars($product['sku']) . "</td>\n";
    echo "<td>" . htmlspecialchars($image_file) . "</td>\n";
    echo "<td style='{$status_color}'><strong>{$status}</strong></td>\n";
    echo "<td>" . htmlspecialchars($found_path ?: 'ไม่พบในทุก path') . "</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

// สรุปผล
$missing_count = count($missing_images);
$found_count = count($found_images);

echo "<h3>สรุปผลการตรวจสอบ:</h3>\n";
echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px;'>\n";
echo "<p><strong>สินค้าทั้งหมดที่มีรูปภาพ:</strong> {$total_products} รายการ</p>\n";
echo "<p style='color: green;'><strong>รูปภาพที่พบ:</strong> {$found_count} ไฟล์</p>\n";
echo "<p style='color: red;'><strong>รูปภาพที่หายไป:</strong> {$missing_count} ไฟล์</p>\n";
echo "<p><strong>เปอร์เซ็นต์ความสำเร็จ:</strong> " . 
     round(($found_count / $total_products) * 100, 2) . "%</p>\n";
echo "</div>\n";

// รายการไฟล์ที่หายไป
if (!empty($missing_images)) {
    echo "<h3>รายการไฟล์ที่หายไป (สำหรับการแก้ไข):</h3>\n";
    echo "<div style='background: #fff5f5; padding: 15px; border: 1px solid #fecaca; border-radius: 5px;'>\n";
    echo "<p><strong>คำแนะนำ:</strong> คุณสามารถ:</p>\n";
    echo "<ul>\n";
    echo "<li>อัปโหลดไฟล์รูปภาพที่หายไปไปยังโฟลเดอร์ <code>images/</code></li>\n";
    echo "<li>หรือเปลี่ยนค่าในฐานข้อมูลเป็น <code>NULL</code> เพื่อใช้รูป default</li>\n";
    echo "</ul>\n";
    
    echo "<h4>SQL สำหรับเคลียร์ข้อมูลรูปภาพที่หายไป:</h4>\n";
    echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 3px;'>";
    echo "-- ใช้คำสั่งนี้เพื่อเคลียร์ข้อมูลรูปภาพที่หายไป\n";
    echo "UPDATE products SET image = NULL WHERE product_id IN (";
    $missing_ids = array_column($missing_images, 'product_id');
    echo implode(', ', $missing_ids);
    echo ");\n";
    echo "</pre>\n";
    echo "</div>\n";
}

// ข้อมูลเพิ่มเติม
echo "<h3>ข้อมูลเพิ่มเติม:</h3>\n";
echo "<div style='background: #f0f9ff; padding: 15px; border: 1px solid #bae6fd; border-radius: 5px;'>\n";
echo "<p><strong>โฟลเดอร์ที่ตรวจสอบ:</strong></p>\n";
echo "<ul>\n";
echo "<li><code>../images/</code> - โฟลเดอร์รูปภาพหลัก</li>\n";
echo "<li><code>../</code> - โฟลเดอร์ root (สำหรับไฟล์ที่มี path เต็ม)</li>\n";
echo "</ul>\n";

// แสดงไฟล์ที่มีในโฟลเดอร์ images
$images_dir = '../images/';
if (is_dir($images_dir)) {
    $actual_files = array_diff(scandir($images_dir), ['.', '..']);
    echo "<p><strong>ไฟล์ที่มีอยู่จริงในโฟลเดอร์ images:</strong> " . count($actual_files) . " ไฟล์</p>\n";
    echo "<details>\n";
    echo "<summary>คลิกเพื่อดูรายการไฟล์ทั้งหมด</summary>\n";
    echo "<ul>\n";
    foreach ($actual_files as $file) {
        echo "<li>" . htmlspecialchars($file) . "</li>\n";
    }
    echo "</ul>\n";
    echo "</details>\n";
}

echo "</div>\n";

echo "<hr>\n";
echo "<p><em>สคริปต์นี้ใช้สำหรับการตรวจสอบเท่านั้น ไม่ได้แก้ไขข้อมูลในฐานข้อมูล</em></p>\n";
?>