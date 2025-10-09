<?php
/**
 * Create expiry_notifications table automatically
 * Run this file once to setup the database table
 */
require 'config/db_connect.php';

echo "<h2>สร้างตาราง expiry_notifications</h2>";

try {
    // Create the table
    $sql = "
    CREATE TABLE IF NOT EXISTS expiry_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        notification_date DATE NOT NULL,
        acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_date (user_id, notification_date),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ ตาราง 'expiry_notifications' ถูกสร้างเรียบร้อยแล้ว<br>";
    
    // Create index for better performance
    $index_sql = "CREATE INDEX IF NOT EXISTS idx_user_notification_date ON expiry_notifications (user_id, notification_date)";
    $pdo->exec($index_sql);
    echo "✅ สร้าง Index เรียบร้อยแล้ว<br>";
    
    // Check table structure
    $check_sql = "DESCRIBE expiry_notifications";
    $stmt = $pdo->query($check_sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>โครงสร้างตาราง:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>สถานะ:</h3>";
    echo "🎉 <strong>ระบบแจ้งเตือนสินค้าหมดอายุพร้อมใช้งาน!</strong><br>";
    echo "📱 คุณสามารถเข้า <a href='dashboard.php'>dashboard.php</a> เพื่อทดสอบระบบแจ้งเตือน<br>";
    echo "⚙️ ตารางนี้จะเก็บข้อมูลการรับทราบของแต่ละ user แยกตามวัน<br>";
    
} catch (PDOException $e) {
    echo "❌ <strong>เกิดข้อผิดพลาด:</strong> " . $e->getMessage() . "<br>";
    echo "<br>💡 <strong>วิธีแก้ไข:</strong><br>";
    echo "1. ตรวจสอบว่าฐานข้อมูลเชื่อมต่อได้<br>";
    echo "2. ตรวจสอบว่า user มีสิทธิ์ CREATE TABLE<br>";
    echo "3. ตรวจสอบว่าตาราง 'users' มีอยู่จริง (สำหรับ Foreign Key)<br>";
}
?>