<?php
// db_connect.php

// ข้อมูลการเชื่อมต่อ (MAMP บน Windows โดยทั่วไปใช้ 127.0.0.1:8889)
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'ichoice_';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

// ลอง MAMP ก่อน แล้ว fallback ไป XAMPP หากยังไม่เจอ service
$ports = [
    getenv('DB_PORT') ?: '8889',
    '3306',
];

$lastError = null;

foreach ($ports as $port) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ]
        );

        // เชื่อมต่อสำเร็จ หยุดลองพอร์ตถัดไป
        $lastError = null;
        break;
    } catch (PDOException $e) {
        $lastError = "[$host:$port] " . $e->getMessage();
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $lastError);
}
?>