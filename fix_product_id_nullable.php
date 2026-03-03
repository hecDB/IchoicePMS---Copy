<?php
/**
 * Fix: Make product_id nullable in returned_items and damaged_return_inspections tables
 * This allows new products (without product_id) to be recorded as damaged items
 */

session_start();
require './config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die('❌ กรุณา login ก่อน');
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไข product_id Constraint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .code-block { background: #f5f5f5; padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-wrap; overflow-x: auto; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <h1 class="mb-4">🔧 แก้ไข product_id ให้เป็น Nullable</h1>
            
            <div class="alert alert-info">
                <strong>ปัญหา:</strong> เมื่อส่งสินค้าใหม่ไปที่ชำรุดบางส่วน ระบบจะขึ้นข้อผิดพลาด 
                "Column 'product_id' cannot be null" เพราะ product_id ต้องมีค่าเสมอ
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">SQL ที่จะรัน</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">จะแก้ไข 2 ตาราง:</p>
                    <ul>
                        <li><strong>returned_items</strong> - ตาราง return สินค้า</li>
                        <li><strong>damaged_return_inspections</strong> - ตาราง inspect damage</li>
                    </ul>
                    
                    <h6 class="mt-4 mb-2">SQL Command:</h6>
                    <div class="code-block">-- 1. Alter returned_items table
ALTER TABLE returned_items 
MODIFY COLUMN product_id INT NULL COMMENT 'Product ID - NULL for new products';

-- 2. Alter damaged_return_inspections table  
ALTER TABLE damaged_return_inspections
MODIFY COLUMN product_id INT NULL COMMENT 'Product ID - NULL for new products';

-- Verify changes
DESCRIBE returned_items;
DESCRIBE damaged_return_inspections;</div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">🚀 ขั้นตอนการแก้ไข</h5>
                </div>
                <div class="card-body">
                    <h6>วิธี 1: ใช้ phpMyAdmin (ง่ายที่สุด)</h6>
                    <ol>
                        <li>เปิด phpMyAdmin</li>
                        <li>ไปที่ฐานข้อมูล IchoicePMS</li>
                        <li>ไปที่ตาราง <strong>returned_items</strong></li>
                        <li>คลิก "Structure" แล้วคลิก ✏️ ที่คอลัมน์ <strong>product_id</strong></li>
                        <li>ยกเลิก "NOT NULL" โดยเอา checklist ออก</li>
                        <li>คลิก "Save"</li>
                        <li>ทำซ้ำ step 3-6 สำหรับตาราง <strong>damaged_return_inspections</strong></li>
                    </ol>

                    <hr>

                    <h6>วิธี 2: ใช้ Terminal/Command Line</h6>
                    <ol>
                        <li>เปิด MySQL command line หรือ Terminal</li>
                        <li>วิ่ง SQL commands ข้างบน</li>
                    </ol>

                    <hr>

                    <h6>วิธี 3: ใช้ปุ่มด้านล่าง (Automatic - ถ้าคุณมี admin access)</h6>
                    <p>คลิกปุ่มด้านล่างเพื่อให้ระบบแก้ไขโดยอัตโนมัติ</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">⚠️ สิ่งที่จะเปลี่ยนแปลง</h5>
                </div>
                <div class="card-body">
                    <ul>
                        <li>✅ <strong>returned_items.product_id</strong> จะเป็น NULL ได้ สำหรับสินค้าใหม่</li>
                        <li>✅ <strong>damaged_return_inspections.product_id</strong> จะเป็น NULL ได้ สำหรับสินค้าใหม่</li>
                        <li>✅ ข้อมูลเดิมจะไม่มีการเปลี่ยนแปลง</li>
                        <li>✅ สินค้าใหม่สามารถส่งไปที่ชำรุดบางส่วนได้แล้ว</li>
                    </ul>
                </div>
            </div>

            <div class="text-center mb-4">
                <form method="POST" action="">
                    <button type="submit" name="action" value="fix" class="btn btn-success btn-lg">
                        <span class="material-icons align-middle me-2">build</span>
                        แก้ไขโดยอัตโนมัติ
                    </button>
                    <a href="../receive/receive_po_items.php" class="btn btn-secondary btn-lg ms-2">
                        <span class="material-icons align-middle me-2">arrow_back</span>
                        กลับไป
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php
// Process the fix if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fix') {
    try {
        // Verify user is admin or has permission
        $user_check = $pdo->prepare("SELECT role FROM users WHERE user_id = :user_id");
        $user_check->execute([':user_id' => $user_id]);
        $user = $user_check->fetch();
        
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            throw new Exception('❌ คุณไม่มีสิทธิ์ในการแก้ไขระบบ');
        }

        // 1. Alter returned_items table
        $sql1 = "ALTER TABLE returned_items MODIFY COLUMN product_id INT NULL COMMENT 'Product ID - NULL for new products'";
        $pdo->exec($sql1);
        echo "✅ แก้ไข returned_items.product_id สำเร็จ<br>";

        // 2. Alter damaged_return_inspections table
        $sql2 = "ALTER TABLE damaged_return_inspections MODIFY COLUMN product_id INT NULL COMMENT 'Product ID - NULL for new products'";
        $pdo->exec($sql2);
        echo "✅ แก้ไข damaged_return_inspections.product_id สำเร็จ<br>";

        // Verify changes
        $result1 = $pdo->query("DESCRIBE returned_items")->fetchAll();
        $result2 = $pdo->query("DESCRIBE damaged_return_inspections")->fetchAll();

        echo "<div class='alert alert-success mt-4'>";
        echo "<h4>✅ แก้ไขสำเร็จ!</h4>";
        echo "<p>ตอนนี้สามารถส่งสินค้าใหม่ไปที่ชำรุดบางส่วนได้แล้ว</p>";
        echo "<p><a href='../receive/receive_po_items.php' class='btn btn-primary'>กลับไปที่รับเข้าสินค้า</a></p>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='alert alert-danger mt-4'>";
        echo "<h4>❌ เกิดข้อผิดพลาด</h4>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    exit;
}
?>
