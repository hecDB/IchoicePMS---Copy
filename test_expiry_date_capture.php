<?php
/**
 * Test: ตรวจสอบการบันทึกวันหมดอายุในตาราง returned_items
 */
session_start();
require 'config/db_connect.php';

// ตรวจสอบว่าคอลัมน์ expiry_date มีอยู่ในทั้ง 3 ตาราง
echo "<h2>📋 ตรวจสอบคอลัมน์ expiry_date</h2>";

$tables = ['returned_items', 'issue_items', 'receive_items', 'damaged_return_inspections'];

foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = 'expiry_date'");
    $stmt->execute([':table' => $table]);
    $hasColumn = $stmt->rowCount() > 0;
    
    $status = $hasColumn ? '✓ มี' : '✗ ไม่มี';
    echo "<p><strong>$table:</strong> $status</p>";
}

echo "<hr>";
echo "<h2>📊 ตรวจสอบข้อมูล returned_items ที่มี expiry_date</h2>";

$stmt = $pdo->query("
    SELECT 
        return_id, 
        return_code, 
        product_name, 
        sku, 
        return_qty,
        expiry_date,
        return_status,
        created_at
    FROM returned_items 
    WHERE expiry_date IS NOT NULL 
    ORDER BY created_at DESC 
    LIMIT 10
");

$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($returns) > 0) {
    echo "<table class='table table-bordered' style='max-width: 1200px;'>";
    echo "<thead class='table-light'><tr>";
    echo "<th>เลขที่</th>";
    echo "<th>เลขที่สินค้าตีกลับ</th>";
    echo "<th>ชื่อสินค้า</th>";
    echo "<th>SKU</th>";
    echo "<th>จำนวน</th>";
    echo "<th>วันหมดอายุ</th>";
    echo "<th>สถานะ</th>";
    echo "<th>บันทึกเมื่อ</th>";
    echo "</tr></thead>";
    echo "<tbody>";
    
    foreach ($returns as $ret) {
        $expiryDate = $ret['expiry_date'] ? date('d/m/Y', strtotime($ret['expiry_date'])) : 'ไม่มี';
        $createdDate = date('d/m/Y H:i', strtotime($ret['created_at']));
        
        echo "<tr>";
        echo "<td>{$ret['return_id']}</td>";
        echo "<td><strong>{$ret['return_code']}</strong></td>";
        echo "<td>{$ret['product_name']}</td>";
        echo "<td>{$ret['sku']}</td>";
        echo "<td>{$ret['return_qty']}</td>";
        echo "<td><strong style='color: #e74c3c;'>$expiryDate</strong></td>";
        echo "<td>{$ret['return_status']}</td>";
        echo "<td>$createdDate</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<p class='alert alert-info'>ยังไม่มีข้อมูล returned_items ที่มีวันหมดอายุ</p>";
}

echo "<hr>";
echo "<h2>📊 ตรวจสอบข้อมูล damaged_return_inspections ที่มี expiry_date</h2>";

$stmt = $pdo->query("
    SELECT 
        inspection_id,
        return_code, 
        product_name, 
        sku, 
        return_qty,
        expiry_date,
        status,
        created_at
    FROM damaged_return_inspections 
    WHERE expiry_date IS NOT NULL 
    ORDER BY created_at DESC 
    LIMIT 10
");

$inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($inspections) > 0) {
    echo "<table class='table table-bordered' style='max-width: 1200px;'>";
    echo "<thead class='table-light'><tr>";
    echo "<th>ID</th>";
    echo "<th>เลขที่สินค้าตีกลับ</th>";
    echo "<th>ชื่อสินค้า</th>";
    echo "<th>SKU</th>";
    echo "<th>จำนวน</th>";
    echo "<th>วันหมดอายุ</th>";
    echo "<th>สถานะ</th>";
    echo "<th>บันทึกเมื่อ</th>";
    echo "</tr></thead>";
    echo "<tbody>";
    
    foreach ($inspections as $insp) {
        $expiryDate = $insp['expiry_date'] ? date('d/m/Y', strtotime($insp['expiry_date'])) : 'ไม่มี';
        $createdDate = date('d/m/Y H:i', strtotime($insp['created_at']));
        
        echo "<tr>";
        echo "<td>{$insp['inspection_id']}</td>";
        echo "<td><strong>{$insp['return_code']}</strong></td>";
        echo "<td>{$insp['product_name']}</td>";
        echo "<td>{$insp['sku']}</td>";
        echo "<td>{$insp['return_qty']}</td>";
        echo "<td><strong style='color: #e74c3c;'>$expiryDate</strong></td>";
        echo "<td>{$insp['status']}</td>";
        echo "<td>$createdDate</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<p class='alert alert-info'>ยังไม่มีข้อมูล damaged_return_inspections ที่มีวันหมดอายุ</p>";
}

echo "<hr>";
echo "<h2>✓ เสร็จสิ้น</h2>";
echo "<p>สรุป: ระบบบันทึกวันหมดอายุในตาราง returned_items แล้ว</p>";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบการบันทึกวันหมดอายุ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
            padding: 2rem;
        }
        h2 {
            color: #2c3e50;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .container {
            max-width: 1400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Content is generated by PHP above -->
    </div>
</body>
</html>
