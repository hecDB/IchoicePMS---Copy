<?php
/**
 * Migration Runner for New Product PO System
 * Run this file once to create the required database tables
 * 
 * Usage: Access via web browser: http://yoursite.com/db/run_migration.php
 */

require_once '../config/db_connect.php';
require_once '../auth/auth_check.php';

// Check if user is admin
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
if ($user_role !== 'admin') {
    die('Access Denied - Admin only');
}

// Define migration SQL
$migrations = [
    "CREATE TABLE IF NOT EXISTS `temp_products` (
        `temp_product_id` int(11) NOT NULL AUTO_INCREMENT,
        `product_name` varchar(100) NOT NULL COMMENT 'ชื่อสินค้าเบื้องต้น',
        `product_category` varchar(100) DEFAULT NULL COMMENT 'ประเภทสินค้า',
        `product_image` longblob DEFAULT NULL COMMENT 'รูปภาพสินค้า (Base64 encoded)',
        `provisional_sku` varchar(255) DEFAULT NULL COMMENT 'SKU ชั่วคราว',
        `provisional_barcode` varchar(50) DEFAULT NULL COMMENT 'Barcode ชั่วคราว',
        `unit` varchar(20) DEFAULT 'ชิ้น' COMMENT 'หน่วยนับ',
        `remark` text DEFAULT NULL COMMENT 'หมายเหตุเพิ่มเติม',
        `status` enum('draft','pending_approval','approved','rejected','converted') DEFAULT 'draft' COMMENT 'สถานะ',
        `po_id` int(11) NOT NULL COMMENT 'ใบ PO ที่อ้างอิง',
        `created_by` int(11) NOT NULL COMMENT 'สร้างโดย user_id',
        `approved_by` int(11) DEFAULT NULL COMMENT 'อนุมัติโดย user_id',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สร้าง',
        `approved_at` timestamp NULL DEFAULT NULL COMMENT 'วันที่อนุมัติ',
        PRIMARY KEY (`temp_product_id`),
        KEY `fk_po_id` (`po_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_by` (`created_by`),
        KEY `idx_category` (`product_category`),
        CONSTRAINT `fk_po_id` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางสินค้าชั่วคราวสำหรับ PO ใหม่'",
    
    "ALTER TABLE `purchase_order_items` ADD COLUMN IF NOT EXISTS `temp_product_id` int(11) DEFAULT NULL COMMENT 'ลิงก์ไปยัง temp_products'",
    
    "ALTER TABLE `purchase_order_items` ADD COLUMN IF NOT EXISTS `quantity` decimal(10,2) DEFAULT NULL COMMENT 'จำนวน (alias for qty)'",
    
    "ALTER TABLE `purchase_order_items` ADD COLUMN IF NOT EXISTS `unit_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคา/หน่วย (alias for price_per_unit)'",
    
    "ALTER TABLE `purchase_order_items` ADD COLUMN IF NOT EXISTS `unit` varchar(20) DEFAULT NULL COMMENT 'หน่วยนับ'",
    
    "ALTER TABLE `purchase_order_items` ADD COLUMN IF NOT EXISTS `po_item_amount` decimal(12,2) DEFAULT NULL COMMENT 'ยอดรวมรายการ'",
];

$results = [];
$has_error = false;

try {
    foreach ($migrations as $index => $sql) {
        try {
            $pdo->exec($sql);
            $results[] = [
                'success' => true,
                'message' => 'Migration ' . ($index + 1) . ' executed successfully'
            ];
        } catch (PDOException $e) {
            // Check if error is about already existing
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                $results[] = [
                    'success' => true,
                    'message' => 'Migration ' . ($index + 1) . ' skipped (already exists)'
                ];
            } else {
                $results[] = [
                    'success' => false,
                    'message' => 'Migration ' . ($index + 1) . ' failed: ' . $e->getMessage()
                ];
                $has_error = true;
            }
        }
    }
} catch (Exception $e) {
    $results[] = [
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage()
    ];
    $has_error = true;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <style>
        body {
            font-family: 'Prompt', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .result {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #e7f3ff;
            border-radius: 4px;
            border-left: 4px solid #0066cc;
        }
        .summary.error {
            background: #ffe7e7;
            border-left-color: #cc0000;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0052a3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Migration - New Product PO System</h1>
        
        <?php foreach ($results as $result): ?>
            <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($result['message']); ?>
            </div>
        <?php endforeach; ?>
        
        <div class="summary <?php echo $has_error ? 'error' : ''; ?>">
            <h3><?php echo $has_error ? '❌ Migration Failed' : '✅ Migration Completed Successfully'; ?></h3>
            <p>
                <?php if ($has_error): ?>
                    One or more migrations failed. Please check the errors above and try again.
                <?php else: ?>
                    All migrations completed successfully! The database is now ready for the new product PO system.
                    <br><br>
                    <strong>Tables created/modified:</strong>
                    <ul>
                        <li>✓ temp_products - สำหรับเก็บข้อมูลสินค้าชั่วคราว</li>
                        <li>✓ purchase_order_items - เพิ่มคอลัมน์ temp_product_id</li>
                    </ul>
                <?php endif; ?>
            </p>
        </div>
        
        <a href="../orders/purchase_orders.php" class="btn">Go to Purchase Orders</a>
    </div>
</body>
</html>
