<?php
/**
 * API สำหรับดึงรายการหมวดหมู่สินค้าจากตาราง product_category
 */
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน'
    ]);
    exit;
}

try {
    // ตรวจสอบว่าตาราง product_category มีคอลัมน์ is_active หรือไม่
    $checkColumn = $pdo->query("SHOW COLUMNS FROM product_category LIKE 'is_active'");
    $hasIsActive = $checkColumn->rowCount() > 0;
    
    // ดึงรายการหมวดหมู่ทั้งหมด
    if ($hasIsActive) {
        $stmt = $pdo->prepare("
            SELECT 
                category_id,
                category_name,
                description
            FROM product_category
            WHERE is_active = 1
            ORDER BY category_name ASC
        ");
    } else {
        // ถ้าไม่มี is_active column ให้ดึงทั้งหมด
        $stmt = $pdo->prepare("
            SELECT 
                category_id,
                category_name,
                description
            FROM product_category
            ORDER BY category_name ASC
        ");
    }
    
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('✅ Successfully fetched ' . count($categories) . ' categories');
    
    echo json_encode([
        'success' => true,
        'data' => $categories,
        'count' => count($categories)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log('❌ Error fetching product categories: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลหมวดหมู่: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('❌ Unexpected error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
