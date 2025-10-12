<?php
// Simple test file to isolate syntax issues
session_start();
require '../config/db_connect.php';

// Simple query to test basic functionality
$sql = "SELECT r.receive_id, p.sku, p.name AS product_name 
        FROM receive_items r
        LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
        LEFT JOIN products p ON poi.product_id = p.product_id
        LIMIT 5";
        
try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
    echo "Database error: " . $e->getMessage();
}

function getImagePath($imageName) {
    return empty($imageName) ? '../images/noimg.png' : '../images/' . $imageName;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทดสอบ Receive Items</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h2>ทดสอบการแสดงข้อมูล</h2>
        
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>SKU</th>
                    <th>ชื่อสินค้า</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="3">ไม่มีข้อมูล</td>
                </tr>
                <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['receive_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['sku'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['product_name'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log('Basic test script loaded successfully');
        
        // Test template literals
        const testMessage = `Test message with variable: ${new Date().toLocaleString()}`;
        console.log(testMessage);
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded successfully');
        });
    </script>
</body>
</html>