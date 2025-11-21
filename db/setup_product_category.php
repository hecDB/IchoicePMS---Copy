<?php
/**
 * Script สำหรับเพิ่มคอลัมน์ประเภทสินค้าลงในตาราง products
 * รัน script นี้จาก browser เพื่อสร้างคอลัมน์
 */

session_start();
include 'config/db_connect.php';

// ตรวจสอบสิทธิ์ (admin เท่านั้น)
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access Denied - Admin only');
}

try {
    echo "<h2>🔧 ปรับปรุงฐานข้อมูล</h2>";
    
    // 1. ตรวจสอบว่ามี product_category_id หรือไม่
    $result = $pdo->query("SHOW COLUMNS FROM products LIKE 'product_category_id'");
    if ($result->rowCount() === 0) {
        echo "<p>⏳ กำลังเพิ่มคอลัมน์ product_category_id...</p>";
        
        $pdo->exec("
            ALTER TABLE products 
            ADD COLUMN product_category_id INT DEFAULT NULL,
            ADD CONSTRAINT fk_products_category 
            FOREIGN KEY (product_category_id) 
            REFERENCES product_category(category_id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
        ");
        
        echo "<p>✅ เพิ่มคอลัมน์ product_category_id สำเร็จ</p>";
    } else {
        echo "<p>ℹ️ คอลัมน์ product_category_id มีอยู่แล้ว</p>";
    }
    
    // 2. ตรวจสอบว่ามี category_name หรือไม่
    $result = $pdo->query("SHOW COLUMNS FROM products LIKE 'category_name'");
    if ($result->rowCount() === 0) {
        echo "<p>⏳ กำลังเพิ่มคอลัมน์ category_name...</p>";
        
        $pdo->exec("
            ALTER TABLE products 
            ADD COLUMN category_name VARCHAR(100) COMMENT 'ชื่อประเภท' DEFAULT NULL
        ");
        
        echo "<p>✅ เพิ่มคอลัมน์ category_name สำเร็จ</p>";
    } else {
        echo "<p>ℹ️ คอลัมน์ category_name มีอยู่แล้ว</p>";
    }
    
    // 3. สร้าง index
    $result = $pdo->query("SHOW INDEX FROM products WHERE Key_name='idx_products_category'");
    if ($result->rowCount() === 0) {
        echo "<p>⏳ กำลังสร้าง index...</p>";
        
        $pdo->exec("CREATE INDEX idx_products_category ON products(product_category_id)");
        
        echo "<p>✅ สร้าง index สำเร็จ</p>";
    } else {
        echo "<p>ℹ️ index มีอยู่แล้ว</p>";
    }
    
    // 4. ตรวจสอบตาราง product_category
    $result = $pdo->query("SELECT COUNT(*) as count FROM product_category");
    $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p>📊 ข้อมูลประเภทสินค้า: $count รายการ</p>";
    
    echo "<div style='margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 5px;'>";
    echo "<h3 style='color: #2e7d32;'>✨ ปรับปรุงสำเร็จ!</h3>";
    echo "<p>ระบบพร้อมใช้งานแล้ว</p>";
    echo "<p><a href='products/import_product.php' style='color: #1976d2; text-decoration: none;'>→ ไปยังหน้านำเข้าสินค้า</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 15px; background: #ffebee; border-radius: 5px;'>";
    echo "<h3 style='color: #c62828;'>❌ เกิดข้อผิดพลาด</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
