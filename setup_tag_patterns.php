<?php
/**
 * Setup Tag Patterns - เพิ่มเลขแท็คใหม่สำหรับระบบจัดการแท็ค
 * ใช้งานโดยเรียก: http://localhost/IchoicePMS---Copy/setup_tag_patterns.php
 */

session_start();
require 'config/db_connect.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id'])) {
    die('❌ กรุณาเข้าสู่ระบบก่อน');
}

// รายการรูปแบบแท็คที่ต้องเพิ่ม
$newPatterns = [
    // Lazada
    [
        'platform' => 'Lazada',
        'pattern_name' => 'Lazada-TH-Flash Express',
        'description' => 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว',
        'regex_pattern' => '^TH[0-9]{6}[A-Z0-9]{5,7}$',
        'example_tags' => 'TH123456ABCDE, TH654321XYZ12, TH000000FLASH1',
        'is_active' => 1
    ],
    [
        'platform' => 'Lazada',
        'pattern_name' => 'Lazada-TH-LEX TH (LEXPU)',
        'description' => 'LEXPU + ตัวเลข 10 หลัก',
        'regex_pattern' => '^LEXPU[0-9]{10}$',
        'example_tags' => 'LEXPU1234567890, LEXPU9876543210, LEXPU0000000000',
        'is_active' => 1
    ],
    [
        'platform' => 'Lazada',
        'pattern_name' => 'Lazada-TH-LEX TH (LEXDO)',
        'description' => 'LEXDO + ตัวเลข 10 หลัก',
        'regex_pattern' => '^LEXDO[0-9]{10}$',
        'example_tags' => 'LEXDO1234567890, LEXDO9876543210, LEXDO0000000000',
        'is_active' => 1
    ],

    // Requests 2026-02 Shopee / Lazada additions
    [
        'platform' => 'Shopee',
        'pattern_name' => 'ShopeeTP',
        'description' => 'WB หรือ EA นำหน้า ตามด้วยตัวเลข 9 หลัก และลงท้าย TH (Thai Post)',
        'regex_pattern' => '^(WB|EA)[0-9]{9}TH$',
        'example_tags' => 'WB123456789TH, EA987654321TH, WB000000000TH',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'ShopeeFlash',
        'description' => 'TH ตามด้วยตัวอักษร/ตัวเลข 12-13 ตัว (Flash Express)',
        'regex_pattern' => '^TH[A-Z0-9]{12,13}$',
        'example_tags' => 'THA1B2C3D4E5F6, TH1234567890ABC, THZXCVBNM12345',
        'is_active' => 1
    ],
    [
        'platform' => 'Lazada',
        'pattern_name' => 'LazadaFlashBulky',
        'description' => 'TH + ตัวเลข 7 หลัก + อักษร/ตัวเลข 6 ตัว (Flash Bulky)',
        'regex_pattern' => '^TH[0-9]{7}[A-Z0-9]{6}$',
        'example_tags' => 'TH1234567ABCDEF, TH7654321ZXCVBN, TH0000000FLASH1',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'FlashRegular',
        'description' => 'TH ตามด้วยตัวอักษร/ตัวเลข 12-13 ตัว (Flash Regular)',
        'regex_pattern' => '^TH[A-Z0-9]{12,13}$',
        'example_tags' => 'THQWERTY123456, TH1234567890ZX, THFLASH1234567',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'DeliveryFood',
        'description' => 'ตัวเลขล้วน 19 หลัก (Food Delivery)',
        'regex_pattern' => '^[0-9]{19}$',
        'example_tags' => '1234567890123456789, 9876543210987654321, 5555555555555555555',
        'is_active' => 1
    ],
    
    // Shopee
    [
        'platform' => 'Shopee',
        'pattern_name' => 'Shopee-TH-EMS Thailand Post',
        'description' => 'ตัวอักษร 2 ตัว + ตัวเลข 9 หลัก + TH',
        'regex_pattern' => '^[A-Z]{2}[0-9]{9}TH$',
        'example_tags' => 'AA123456789TH, ZZ987654321TH, AB000000000TH',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'Shopee-TH-Express Delivery (SHP Food)',
        'description' => 'ตัวเลขล้วน 19 หลัก',
        'regex_pattern' => '^[0-9]{19}$',
        'example_tags' => '1234567890123456789, 9876543210987654321, 5555555555555555555',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'Shopee-TH-Flash Express',
        'description' => 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว',
        'regex_pattern' => '^TH[0-9]{6}[A-Z0-9]{5,7}$',
        'example_tags' => 'TH123456ABCDE, TH654321XYZ12, TH000000FLASH1',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'Shopee-TH-Instant Delivery (ส่งทันที)',
        'description' => 'ตัวเลขล้วน 19 หลัก',
        'regex_pattern' => '^[0-9]{19}$',
        'example_tags' => '1111111111111111111, 2222222222222222222, 9999999999999999999',
        'is_active' => 1
    ],
    [
        'platform' => 'Shopee',
        'pattern_name' => 'Shopee-TH-SPX Express',
        'description' => 'TH + ตัวเลข 12 หลัก + ตัวเลขหรือ A-Z 1 ตัว',
        'regex_pattern' => '^TH[0-9]{12}[A-Z0-9]$',
        'example_tags' => 'TH123456789012A, TH654321098765Z, TH000000000000X',
        'is_active' => 1
    ],
    
    // TikTok
    [
        'platform' => 'TikTok',
        'pattern_name' => 'TikTok-TH-J&T Express',
        'description' => 'ตัวเลขล้วน 12 หลัก',
        'regex_pattern' => '^[0-9]{12}$',
        'example_tags' => '123456789012, 987654321098, 000000000000',
        'is_active' => 1
    ],
    [
        'platform' => 'TikTok',
        'pattern_name' => 'TikTok-TH-Flash Express (Pickup)',
        'description' => 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว',
        'regex_pattern' => '^TH[0-9]{6}[A-Z0-9]{5,7}$',
        'example_tags' => 'TH123456ABCDE, TH654321XYZ12, TH000000PICKUP',
        'is_active' => 1
    ]
];

// เริ่มทำการเพิ่มข้อมูล
echo "<!DOCTYPE html>
<html lang='th'>
<head>
    <meta charset='UTF-8'>
    <title>Setup Tag Patterns</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f8f9fa; }
        .container { margin-top: 2rem; }
        .result-item { padding: 1rem; margin-bottom: 0.5rem; border-radius: 6px; }
        .result-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .result-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .result-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
<div class='container'>
    <h1 class='mb-4'>⚙️ ตั้งค่ารูปแบบเลขแท็ค</h1>
    <div id='results'>";

try {
    // ตรวจสอบว่า tag_patterns table มีอยู่
    $checkTableSql = "SHOW TABLES LIKE 'tag_patterns'";
    $checkStmt = $pdo->query($checkTableSql);
    if ($checkStmt->rowCount() == 0) {
        echo "<div class='result-item result-error'>❌ ตาราง tag_patterns ไม่มีอยู่ในฐานข้อมูล</div>";
        die();
    }

    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;

    foreach ($newPatterns as $pattern) {
        try {
            // ตรวจสอบว่าแพทเทิร์นนี้มีอยู่แล้วหรือไม่ (check platform + pattern_name)
            $checkSql = "SELECT pattern_id FROM tag_patterns WHERE platform = ? AND pattern_name = ? LIMIT 1";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$pattern['platform'], $pattern['pattern_name']]);

            if ($checkStmt->rowCount() > 0) {
                echo "<div class='result-item result-warning'>⏭️ ข้ามการเพิ่ม: {$pattern['pattern_name']} (มีอยู่แล้ว)</div>";
                $skipCount++;
                continue;
            }

            // เพิ่มรูปแบบใหม่
            $insertSql = "INSERT INTO tag_patterns 
                        (platform, pattern_name, description, regex_pattern, example_tags, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $insertStmt = $pdo->prepare($insertSql);
            $result = $insertStmt->execute([
                $pattern['platform'],
                $pattern['pattern_name'],
                $pattern['description'],
                $pattern['regex_pattern'],
                $pattern['example_tags'],
                $pattern['is_active']
            ]);

            if ($result) {
                $patternId = $pdo->lastInsertId();
                echo "<div class='result-item result-success'>✅ เพิ่มสำเร็จ: {$pattern['pattern_name']} (ID: {$patternId})</div>";
                $successCount++;
            } else {
                echo "<div class='result-item result-error'>❌ เพิ่มล้มเหลว: {$pattern['pattern_name']}</div>";
                $errorCount++;
            }
        } catch (Exception $e) {
            echo "<div class='result-item result-error'>❌ เพิ่มล้มเหลว: {$pattern['pattern_name']} - {$e->getMessage()}</div>";
            $errorCount++;
        }
    }

    echo "<hr>
    <div class='alert alert-info mt-4'>
        <h5>📊 สรุปผลการตั้งค่า</h5>
        <ul class='mb-0'>
            <li><strong>เพิ่มสำเร็จ:</strong> <span class='badge bg-success'>{$successCount}</span></li>
            <li><strong>ข้ามการเพิ่ม:</strong> <span class='badge bg-warning'>{$skipCount}</span></li>
            <li><strong>เพิ่มล้มเหลว:</strong> <span class='badge bg-danger'>{$errorCount}</span></li>
        </ul>
    </div>";

} catch (Exception $e) {
    echo "<div class='result-item result-error'>❌ เกิดข้อผิดพลาด: {$e->getMessage()}</div>";
}

echo "</div>
    <div class='mt-4'>
        <a href='sales/tag_management.php' class='btn btn-primary'>
            <i class='material-icons' style='vertical-align: middle;'>arrow_back</i>
            กลับไปยังการจัดการแท็ค
        </a>
    </div>
</div>
</body>
</html>";
?>
