<?php
session_start();
require_once '../config/db_connect.php';

// ตั้งค่า header สำหรับ JSON response
header('Content-Type: application/json');

try {
    // ดึงข้อมูลอัตราแลกเปลี่ยนจากฐานข้อมูล
    $stmt = $pdo->prepare("
        SELECT currency_code, exchange_rate_to_thb 
        FROM currencies 
        WHERE is_active = 1 
        ORDER BY currency_code
    ");
    $stmt->execute();
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดรูปแบบข้อมูลให้เหมาะสมกับ JavaScript
    $rates = [];
    foreach ($currencies as $currency) {
        $rates[$currency['currency_code']] = floatval($currency['exchange_rate_to_thb']);
    }
    
    // ตรวจสอบว่ามีข้อมูลหรือไม่
    if (empty($rates)) {
        // ถ้าไม่มีข้อมูลในฐานข้อมูล ใช้ค่าเริ่มต้น
        $rates = [
            'THB' => 1.00,
            'USD' => 35.50
        ];
    }
    
    // ส่งข้อมูลกลับ
    echo json_encode([
        'success' => true,
        'rates' => $rates,
        'message' => 'อัตราแลกเปลี่ยนโหลดสำเร็จ',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // จัดการข้อผิดพลาด
    echo json_encode([
        'success' => false,
        'rates' => [
            'THB' => 1.00,
            'USD' => 35.50
        ],
        'message' => 'ไม่สามารถโหลดอัตราแลกเปลี่ยนได้: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>