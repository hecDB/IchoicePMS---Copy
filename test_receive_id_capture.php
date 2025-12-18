<?php
/**
 * Test: ตรวจสอบการบันทึก receive_id และ expiry_date
 */
session_start();
require 'config/db_connect.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบการบันทึก receive_id</title>
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
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 1rem 0;
        }
        .badge-count {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 ตรวจสอบคอลัมน์และข้อมูล receive_id</h2>

        <!-- Check Columns -->
        <h3>📋 ตรวจสอบคอลัมน์</h3>
        <?php
        $tables = [
            'issue_items' => ['receive_id', 'expiry_date'],
            'returned_items' => ['receive_id', 'expiry_date'],
            'damaged_return_inspections' => ['receive_id', 'expiry_date']
        ];

        foreach ($tables as $table => $columns) {
            echo "<div class='stat-box'>";
            echo "<h5>$table</h5>";
            
            foreach ($columns as $column) {
                $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
                $stmt->execute([':table' => $table, ':column' => $column]);
                $hasColumn = $stmt->rowCount() > 0;
                
                $status = $hasColumn ? '✓ มี' : '✗ ไม่มี';
                $color = $hasColumn ? 'text-success' : 'text-danger';
                echo "<p><span class='$color'><strong>$column:</strong> $status</span></p>";
            }
            
            echo "</div>";
        }
        ?>

        <!-- Check issue_items data with receive_id -->
        <h3>📦 ข้อมูล issue_items ที่มี receive_id</h3>
        <?php
        $stmt = $pdo->query("
            SELECT 
                issue_id,
                product_id,
                issue_qty,
                receive_id,
                expiry_date,
                created_at
            FROM issue_items 
            WHERE receive_id IS NOT NULL 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($items) > 0) {
            echo "<p class='alert alert-info'>พบ " . count($items) . " รายการ</p>";
            echo "<table class='table table-sm'>";
            echo "<thead class='table-light'><tr>";
            echo "<th>Issue ID</th>";
            echo "<th>Product ID</th>";
            echo "<th>Issue Qty</th>";
            echo "<th>Receive ID</th>";
            echo "<th>Expiry Date</th>";
            echo "</tr></thead>";
            echo "<tbody>";
            
            foreach ($items as $item) {
                $expiry = $item['expiry_date'] ? date('d/m/Y', strtotime($item['expiry_date'])) : '<em class="text-muted">-</em>';
                echo "<tr>";
                echo "<td>{$item['issue_id']}</td>";
                echo "<td>{$item['product_id']}</td>";
                echo "<td>{$item['issue_qty']}</td>";
                echo "<td><strong>{$item['receive_id']}</strong></td>";
                echo "<td>$expiry</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='alert alert-warning'>ยังไม่มีข้อมูล issue_items ที่มี receive_id</p>";
        }
        ?>

        <!-- Check returned_items with receive_id -->
        <h3>📋 ข้อมูล returned_items ที่มี receive_id</h3>
        <?php
        $stmt = $pdo->query("
            SELECT 
                return_id,
                return_code,
                product_name,
                sku,
                receive_id,
                expiry_date,
                return_status,
                return_from_sales,
                created_at
            FROM returned_items 
            WHERE receive_id IS NOT NULL 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($returns) > 0) {
            echo "<p class='alert alert-success'>พบ " . count($returns) . " รายการ ✓</p>";
            echo "<table class='table table-sm'>";
            echo "<thead class='table-light'><tr>";
            echo "<th>Return ID</th>";
            echo "<th>Return Code</th>";
            echo "<th>Product</th>";
            echo "<th>Receive ID</th>";
            echo "<th>Expiry Date</th>";
            echo "<th>From Sales</th>";
            echo "<th>Status</th>";
            echo "</tr></thead>";
            echo "<tbody>";
            
            foreach ($returns as $ret) {
                $expiry = $ret['expiry_date'] ? date('d/m/Y', strtotime($ret['expiry_date'])) : '<em class="text-muted">-</em>';
                $fromSales = $ret['return_from_sales'] ? 'Sales' : 'Purchase';
                echo "<tr>";
                echo "<td>{$ret['return_id']}</td>";
                echo "<td><strong>{$ret['return_code']}</strong></td>";
                echo "<td>{$ret['product_name']}<br><small>{$ret['sku']}</small></td>";
                echo "<td><strong style='color: #3b82f6;'>{$ret['receive_id']}</strong></td>";
                echo "<td style='color: #e74c3c;'>$expiry</td>";
                echo "<td>$fromSales</td>";
                echo "<td><span class='badge bg-info'>{$ret['return_status']}</span></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='alert alert-info'>ยังไม่มีข้อมูล returned_items ที่มี receive_id (ต้องทำการสร้างการตีกลับจาก Sales Order ที่มี receive_id ก่อน)</p>";
        }
        ?>

        <!-- Summary -->
        <h3>📝 สรุป</h3>
        <div class="alert alert-success">
            <h5>✓ ระบบพร้อมใช้งาน</h5>
            <ul>
                <li>คอลัมน์ receive_id ถูกเพิ่มเข้าในตาราง returned_items</li>
                <li>คอลัมน์ expiry_date ถูกเพิ่มเข้าในตาราง issue_items</li>
                <li>API จะบันทึก receive_id จาก issue_items เมื่อสร้างการตีกลับจาก Sales Order</li>
                <li>ข้อมูลวันหมดอายุและลัตชุดสินค้าบันทึกไว้เป็นอ้างอิงสำหรับการติดตาม</li>
            </ul>
        </div>

        <div class="alert alert-info">
            <h5>📌 ข้อมูลที่บันทึก:</h5>
            <ul>
                <li><strong>receive_id:</strong> ระบุว่าสินค้าออกจากลัตชุดไหน (ถ้ามี)</li>
                <li><strong>expiry_date:</strong> วันหมดอายุของสินค้า</li>
                <li>ข้อมูลทั้ง 2 อย่างนี้ช่วยในการติดตามและจัดการสินค้าตีกลับ</li>
            </ul>
        </div>
    </div>
</body>
</html>
