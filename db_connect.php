<?php
// db_connect.php

// ข้อมูลการเชื่อมต่อ
$host = 'localhost';
$port = '3307';
$db   = 'ichoice_PMS';
$user = 'khing_IT';        // กรณีใช้งาน xampp/lampp มักใช้ root (หรือชื่อ user ของคุณ)
$pass = 'Khing_IchoicePMS99';            // รหัสผ่าน (ใส่ให้ตรง)

try {
    $pdo = new PDO(
        "mysql:host=$host; port=$port; dbname=$db;charset=utf8mb4", 
        $user, 
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Error แบบ Exception
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // ผลลัพธ์เป็น associative array
            PDO::ATTR_EMULATE_PREPARES => false,    // ป้องกัน SQL Injection
        ]
    );
    // echo "เชื่อมต่อฐานข้อมูลสำเร็จ!"; // ใช้เทส
} catch (PDOException $e) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}
?>