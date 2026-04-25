<?php
// db_connect.php - Optimized & Secure Database Connection

// ใช้ Static Variable เพื่อ reuse connection ใน request เดียวกัน
static $pdo = null;

if ($pdo !== null) {
    return $pdo;
}

// โหลด environment variables จากไฟล์ .env ถ้ามี (ไม่บังคับ)
if (file_exists(__DIR__ . '/env_loader.php')) {
    require_once __DIR__ . '/env_loader.php';
}

// ข้อมูลการเชื่อมต่อ
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'ichoice_';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// ลอง MAMP (8889) และ XAMPP (3306) - แต่ปรับปรุงให้เร็วขึ้น
$ports = [
    getenv('DB_PORT') ?: '3306',  // ลอง XAMPP/Standard ก่อน (ใช้บ่อยกว่า)
    '8889',                        // แล้วค่อยลอง MAMP
];

$lastError = null;

foreach ($ports as $port) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                // ⚡ Performance Optimization
                PDO::ATTR_PERSISTENT => true,              // ใช้ persistent connection - เร็วขึ้นมาก
                PDO::ATTR_EMULATE_PREPARES => false,       // ใช้ real prepared statements
                PDO::ATTR_TIMEOUT => 1,                    // ลด timeout ให้ล้มเหลวเร็วๆ ถ้าพอร์ตผิด
                
                // 🔒 Security
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES => false,      // ป้องกัน type juggling
                
                // 📊 Additional Settings
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]
        );
        
        // เชื่อมต่อสำเร็จ - หยุดลองพอร์ตถัดไป
        $lastError = null;
        
        // 🚀 Additional Performance Tweaks (เฉพาะเมื่อเชื่อมต่อสำเร็จ)
        try {
            $pdo->exec("SET SESSION sql_mode='TRADITIONAL'");
        } catch (Exception $e) {
            // Ignore if sql_mode setting fails
        }
        
        break;
        
    } catch (PDOException $e) {
        $lastError = "[$host:$port] " . $e->getMessage();
        continue; // ลองพอร์ตถัดไป
    }
}

// ถ้าเชื่อมต่อไม่สำเร็จทุกพอร์ต
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Log error
    error_log("Database Connection Error: " . $lastError);
    
    // แสดง error message
    $env = getenv('APP_ENV') ?: 'development';
    if ($env === 'development') {
        die("❌ ไม่สามารถเชื่อมต่อฐานข้อมูล<br><br><strong>รายละเอียด:</strong> " . htmlspecialchars($lastError) . "<br><br><strong>แนะนำ:</strong><ul><li>ตรวจสอบว่า MySQL/MariaDB ทำงานอยู่</li><li>XAMPP ใช้ port 3306</li><li>MAMP ใช้ port 8889</li><li>ตรวจสอบ username/password</li></ul>");
    } else {
        die("❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ");
    }
}

return $pdo;
?>