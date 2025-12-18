<?php
/**
 * ตรวจสอบข้อมูล expiry_date ในตารางต้นทาง
 */
session_start();
require 'config/db_connect.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบข้อมูล Expiry Date ที่มีอยู่</title>
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
        h3 {
            color: #34495e;
            margin-top: 1.5rem;
            font-weight: 500;
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
        <h2>📊 ตรวจสอบข้อมูล Expiry Date ที่มีอยู่</h2>

        <!-- issue_items -->
        <h3>📦 ข้อมูล issue_items (Sales Order Items)</h3>
        <?php
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_expiry,
                SUM(CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END) as without_expiry
            FROM issue_items
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <div class="stat-box">
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="badge-count text-primary"><?php echo $stats['total']; ?></div>
                        <div class="text-muted">รวมทั้งหมด</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="badge-count text-success"><?php echo $stats['with_expiry']; ?></div>
                        <div class="text-muted">มีวันหมดอายุ</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="badge-count text-warning"><?php echo $stats['without_expiry']; ?></div>
                        <div class="text-muted">ไม่มีวันหมดอายุ</div>
                    </div>
                </div>
            </div>
        </div>

        <h4>ตัวอย่าง 10 รายการแรก:</h4>
        <?php
        $stmt = $pdo->query("
            SELECT 
                issue_id,
                product_id,
                issue_qty,
                expiry_date,
                created_at
            FROM issue_items 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($items) > 0) {
            echo "<table class='table table-sm table-striped'>";
            echo "<thead class='table-light'><tr>";
            echo "<th>Issue ID</th>";
            echo "<th>Product ID</th>";
            echo "<th>Issue Qty</th>";
            echo "<th>Expiry Date</th>";
            echo "<th>Created</th>";
            echo "</tr></thead>";
            echo "<tbody>";
            
            foreach ($items as $item) {
                $expiry = $item['expiry_date'] ? date('d/m/Y', strtotime($item['expiry_date'])) : '<em class="text-muted">ไม่มี</em>';
                echo "<tr>";
                echo "<td>{$item['issue_id']}</td>";
                echo "<td>{$item['product_id']}</td>";
                echo "<td>{$item['issue_qty']}</td>";
                echo "<td>$expiry</td>";
                echo "<td>" . date('d/m/Y', strtotime($item['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='alert alert-info'>ไม่มีข้อมูล</p>";
        }
        ?>

        <!-- receive_items -->
        <h3>📦 ข้อมูล receive_items (Purchase Receive Items)</h3>
        <?php
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) as with_expiry,
                SUM(CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END) as without_expiry
            FROM receive_items
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <div class="stat-box">
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="badge-count text-primary"><?php echo $stats['total']; ?></div>
                        <div class="text-muted">รวมทั้งหมด</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="badge-count text-success"><?php echo $stats['with_expiry']; ?></div>
                        <div class="text-muted">มีวันหมดอายุ</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="badge-count text-warning"><?php echo $stats['without_expiry']; ?></div>
                        <div class="text-muted">ไม่มีวันหมดอายุ</div>
                    </div>
                </div>
            </div>
        </div>

        <h4>ตัวอย่าง 10 รายการแรก:</h4>
        <?php
        $stmt = $pdo->query("
            SELECT 
                receive_id,
                item_id,
                po_id,
                receive_qty,
                expiry_date,
                created_at
            FROM receive_items 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($items) > 0) {
            echo "<table class='table table-sm table-striped'>";
            echo "<thead class='table-light'><tr>";
            echo "<th>Receive ID</th>";
            echo "<th>Item ID</th>";
            echo "<th>PO ID</th>";
            echo "<th>Receive Qty</th>";
            echo "<th>Expiry Date</th>";
            echo "<th>Created</th>";
            echo "</tr></thead>";
            echo "<tbody>";
            
            foreach ($items as $item) {
                $expiry = $item['expiry_date'] ? date('d/m/Y', strtotime($item['expiry_date'])) : '<em class="text-muted">ไม่มี</em>';
                echo "<tr>";
                echo "<td>{$item['receive_id']}</td>";
                echo "<td>{$item['item_id']}</td>";
                echo "<td>{$item['po_id']}</td>";
                echo "<td>{$item['receive_qty']}</td>";
                echo "<td>$expiry</td>";
                echo "<td>" . date('d/m/Y', strtotime($item['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='alert alert-info'>ไม่มีข้อมูล</p>";
        }
        ?>

        <hr>
        <h3>📝 สรุป</h3>
        <div class="alert alert-info">
            <p><strong>ถ้ามีข้อมูล expiry_date ในตาราง issue_items หรือ receive_items:</strong></p>
            <ul>
                <li>ให้ทำการ สร้างการตีกลับสินค้า (Return) ผ่านหน้าแอปพลิเคชัน</li>
                <li>ระบบจะบันทึก expiry_date ลงในตาราง returned_items อัตโนมัติ</li>
                <li>ตรวจสอบผลลัพธ์ด้วยไฟล์ test_expiry_date_capture.php</li>
            </ul>
        </div>
    </div>
</body>
</html>
