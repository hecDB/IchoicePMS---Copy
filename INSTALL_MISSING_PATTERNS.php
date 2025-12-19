<?php
session_start();
require 'config/db_connect.php';

$_SESSION['user_id'] = 1;

// ตรวจสอบ patterns ในระบบตอนนี้
$sql = "SELECT platform, pattern_name, COUNT(*) as count FROM tag_patterns WHERE regex_pattern = '^TH[0-9]{6}[A-Z0-9]{5,7}$' GROUP BY platform, pattern_name";
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Current Flash Express Patterns:</h2>";
foreach ($results as $r) {
    echo "<p>" . $r['platform'] . " - " . $r['pattern_name'] . " (Count: " . $r['count'] . ")</p>";
}

echo "<h2>Inserting missing patterns...</h2>";

// Pattern ที่จะเพิ่ม
$patterns = [
    [
        'platform' => 'Shopee',
        'pattern_name' => 'Shopee-TH-Flash Express',
        'description' => 'TH + ตัวเลข 6 หลัก + ตัวอักษร/ตัวเลข 5-7 ตัว',
        'regex_pattern' => '^TH[0-9]{6}[A-Z0-9]{5,7}$',
        'example_tags' => 'TH123456ABCDE, TH654321XYZ12, TH000000FLASH1',
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

foreach ($patterns as $p) {
    // ตรวจสอบว่ามีอยู่แล้วหรือไม่
    $checkSql = "SELECT pattern_id FROM tag_patterns WHERE platform = ? AND pattern_name = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$p['platform'], $p['pattern_name']]);
    
    if ($checkStmt->rowCount() > 0) {
        echo "<p>✓ Skipped: " . $p['platform'] . " - " . $p['pattern_name'] . " (already exists)</p>";
    } else {
        $insertSql = "INSERT INTO tag_patterns (platform, pattern_name, description, regex_pattern, example_tags, is_active, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insertStmt = $pdo->prepare($insertSql);
        $result = $insertStmt->execute([$p['platform'], $p['pattern_name'], $p['description'], $p['regex_pattern'], $p['example_tags'], $p['is_active']]);
        
        if ($result) {
            echo "<p>✓ Inserted: " . $p['platform'] . " - " . $p['pattern_name'] . "</p>";
        } else {
            echo "<p>✗ Failed to insert: " . $p['platform'] . " - " . $p['pattern_name'] . "</p>";
        }
    }
}

// Verify final result
echo "<h2>Final Status:</h2>";
$finalSql = "SELECT platform, pattern_name FROM tag_patterns WHERE regex_pattern = '^TH[0-9]{6}[A-Z0-9]{5,7}$' ORDER BY platform";
$finalStmt = $pdo->query($finalSql);
$finalResults = $finalStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Total matches: " . count($finalResults) . "</strong></p>";
foreach ($finalResults as $f) {
    echo "<p>✓ " . $f['platform'] . " - " . $f['pattern_name'] . "</p>";
}

echo "<h3>✅ Complete! Test TH520482U7BU3A again</h3>";
?>
