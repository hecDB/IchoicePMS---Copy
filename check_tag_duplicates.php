<?php
/**
 * ตรวจสอบการซ้ำของรูปแบบเลขแท็ค
 */

session_start();
require 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('❌ กรุณาเข้าสู่ระบบก่อน');
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบการซ้ำของเลขแท็ค</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f8f9fa; }
        .container { margin-top: 2rem; }
        .duplicate-group { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
        .pattern-row { background: white; padding: 0.75rem; border-left: 4px solid #ffc107; margin: 0.5rem 0; }
        .no-duplicate { background: #d4edda; border: 1px solid #28a745; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .regex-code { font-family: monospace; background: #f1f5f9; padding: 0.5rem; border-radius: 4px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4">🔍 ตรวจสอบการซ้ำของรูปแบบเลขแท็ค</h1>

<?php

try {
    // ค้นหา regex_pattern ที่ซ้ำกัน
    $sql = "
        SELECT 
            regex_pattern,
            COUNT(*) as count,
            GROUP_CONCAT(CONCAT(pattern_id, ' | ', platform, ' | ', pattern_name) SEPARATOR '\n') as details
        FROM tag_patterns
        GROUP BY regex_pattern
        HAVING COUNT(*) > 1
        ORDER BY COUNT(*) DESC
    ";
    
    $stmt = $pdo->query($sql);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo '<div class="no-duplicate">
                <h3><span class="material-icons align-middle me-2" style="font-size: 2rem; color: #28a745;">check_circle</span>ยินดีด้วย!</h3>
                <p class="mb-0">ไม่พบการซ้ำกันของรูปแบบเลขแท็ค ทุกรูปแบบมีเอกลักษณ์เฉพาะตัว</p>
            </div>';
    } else {
        echo '<div class="alert alert-warning mb-4">
                <h5><span class="material-icons align-middle me-2">warning</span>พบการซ้ำกัน ' . count($duplicates) . ' รูปแบบ</h5>
                <p class="mb-0">รูปแบบเหล่านี้ใช้ regex_pattern เดียวกันแต่มีชื่อแตกต่างกัน</p>
            </div>';
        
        foreach ($duplicates as $dup) {
            echo '<div class="duplicate-group">
                    <h5 class="mb-3">
                        <span class="badge bg-warning">' . $dup['count'] . ' รูปแบบ</span>
                        ใช้ regex เดียวกัน
                    </h5>
                    
                    <div class="regex-code mb-3">
                        <strong>Regular Expression:</strong><br>
                        <code style="word-break: break-all;">' . htmlspecialchars($dup['regex_pattern']) . '</code>
                    </div>
                    
                    <h6 class="mb-2">รูปแบบที่ซ้ำกัน:</h6>
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #dc3545;">';
            
            $details = explode('\n', $dup['details']);
            foreach ($details as $detail) {
                list($id, $platform, $name) = explode(' | ', $detail);
                echo '<div class="pattern-row">
                        <div><strong>ID:</strong> ' . htmlspecialchars($id) . '</div>
                        <div><strong>แพลตฟอร์ม:</strong> ' . htmlspecialchars($platform) . '</div>
                        <div><strong>ชื่อ:</strong> ' . htmlspecialchars($name) . '</div>
                    </div>';
            }
            
            echo '    </div>
                </div>';
        }
    }
    
    // แสดงสรุปสถิติ
    echo '<hr class="my-4">';
    echo '<h3 class="mb-3">📊 สรุปสถิติ</h3>';
    
    $statsHtml = '';
    
    // รวมทั้งหมด
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM tag_patterns");
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ไม่ซ้ำ (unique)
    $uniqueStmt = $pdo->query("SELECT COUNT(DISTINCT regex_pattern) as unique_count FROM tag_patterns");
    $unique = $uniqueStmt->fetch(PDO::FETCH_ASSOC)['unique_count'];
    
    // เปิดใช้งาน
    $activeStmt = $pdo->query("SELECT COUNT(*) as active FROM tag_patterns WHERE is_active = 1");
    $active = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    // แต่ละแพลตฟอร์ม
    $platformStmt = $pdo->query("SELECT platform, COUNT(*) as count FROM tag_patterns GROUP BY platform");
    $platforms = $platformStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="row">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary">' . $total . '</h4>
                        <small class="text-muted">รูปแบบทั้งหมด</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success">' . $unique . '</h4>
                        <small class="text-muted">รูปแบบ Unique</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info">' . $active . '</h4>
                        <small class="text-muted">เปิดใช้งาน</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning">' . count($duplicates) . '</h4>
                        <small class="text-muted">กลุ่มที่ซ้ำ</small>
                    </div>
                </div>
            </div>
        </div>';
    
    echo '<h4 class="mt-4 mb-3">📱 จำนวนตามแพลตฟอร์ม</h4>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>แพลตฟอร์ม</th>
                        <th>จำนวนรูปแบบ</th>
                        <th>เปอร์เซนต์</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($platforms as $plat) {
        $percentage = ($plat['count'] / $total) * 100;
        echo '<tr>
                <td>' . htmlspecialchars($plat['platform']) . '</td>
                <td>' . $plat['count'] . '</td>
                <td>' . round($percentage, 1) . '%</td>
            </tr>';
    }
    
    echo '        </tbody>
            </table>
        </div>';
    
    // แนะนำ
    if (!empty($duplicates)) {
        echo '<div class="alert alert-info mt-4">
                <h5><span class="material-icons align-middle me-2">lightbulb</span>แนะนำ</h5>
                <ul class="mb-0">
                    <li>รูปแบบที่ซ้ำกันนี้จะใช้งานได้ แต่อาจทำให้เกิดความสับสน</li>
                    <li>พิจารณาเปลี่ยนชื่อรูปแบบให้แตกต่างกัน หรือ</li>
                    <li>รวมรูปแบบที่ซ้ำกันไว้เป็นหนึ่งรูปแบบ เพื่อความชัดเจน</li>
                    <li>ตัวอย่าง: "Flash Express" อาจใช้เป็นชื่อเดียวสำหรับทั้ง Shopee, Lazada และ TikTok</li>
                </ul>
            </div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">❌ เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

?>

    <div class="mt-4 mb-4">
        <a href="sales/tag_management.php" class="btn btn-primary">
            <span class="material-icons align-middle me-2">arrow_back</span>
            กลับไปยังการจัดการแท็ค
        </a>
    </div>
</div>
</body>
</html>
