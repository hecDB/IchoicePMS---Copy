<?php
/**
 * Fix: เพิ่ม reason "สินค้าชำรุดบางส่วน" ลงในตาราง return_reasons
 * เมื่อค่าไม่มีในฐานข้อมูล
 */

require 'config/db_connect.php';

try {
    // ตรวจสอบว่า reason นี้มีอยู่แล้วหรือไม่
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM return_reasons 
        WHERE reason_name = 'สินค้าชำรุดบางส่วน'
    ");
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['cnt'] > 0) {
        echo "✓ reason 'สินค้าชำรุดบางส่วน' มีอยู่แล้วในฐานข้อมูล<br>";
        echo "ข้อมูลที่มีอยู่:<br>";
        
        $detailStmt = $pdo->prepare("
            SELECT reason_id, reason_code, reason_name, is_returnable, category 
            FROM return_reasons 
            WHERE reason_name = 'สินค้าชำรุดบางส่วน'
        ");
        $detailStmt->execute();
        $detail = $detailStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($detail);
        echo "</pre>";
    } else {
        echo "⚠️ reason 'สินค้าชำรุดบางส่วน' ไม่พบในฐานข้อมูล<br>";
        echo "กำลังเพิ่ม...<br>";
        
        // เพิ่ม reason ใหม่
        $insertStmt = $pdo->prepare("
            INSERT INTO return_reasons 
            (reason_code, reason_name, is_returnable, category, description, is_active) 
            VALUES 
            (:reason_code, :reason_name, :is_returnable, :category, :description, :is_active)
        ");
        
        $insertStmt->execute([
            ':reason_code' => 'DMG-PARTIAL',
            ':reason_name' => 'สินค้าชำรุดบางส่วน',
            ':is_returnable' => 0, // ไม่สามารถคืนสต็อก
            ':category' => 'non-returnable',
            ':description' => 'สินค้าชำรุดบางส่วน ต้องเข้าตรวจสอบก่อนตัดสินใจ',
            ':is_active' => 1
        ]);
        
        $newReasonId = $pdo->lastInsertId();
        echo "✓ เพิ่ม reason สำเร็จ (reason_id: " . $newReasonId . ")<br>";
        
        // ตรวจสอบการเพิ่มสำเร็จ
        $verifyStmt = $pdo->prepare("
            SELECT reason_id, reason_code, reason_name, is_returnable, category 
            FROM return_reasons 
            WHERE reason_id = :reason_id
        ");
        $verifyStmt->execute([':reason_id' => $newReasonId]);
        $verify = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($verify);
        echo "</pre>";
    }
    
    // รวมทั้งหมด
    echo "<h3>🔍 รวมทั้งหมด return_reasons ที่มี is_returnable = 0:</h3>";
    $allStmt = $pdo->prepare("
        SELECT reason_id, reason_code, reason_name, is_returnable, category 
        FROM return_reasons 
        WHERE is_returnable = 0
        ORDER BY reason_code ASC
    ");
    $allStmt->execute();
    $allReasons = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>reason_id</th><th>reason_code</th><th>reason_name</th><th>is_returnable</th><th>category</th></tr>";
    foreach ($allReasons as $reason) {
        echo "<tr>";
        echo "<td>" . $reason['reason_id'] . "</td>";
        echo "<td>" . $reason['reason_code'] . "</td>";
        echo "<td>" . $reason['reason_name'] . "</td>";
        echo "<td>" . $reason['is_returnable'] . "</td>";
        echo "<td>" . $reason['category'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ ข้อผิดพลาด: " . $e->getMessage();
}
?>
