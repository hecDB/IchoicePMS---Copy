<?php
// ทดสอบ database และ Position API
session_start();
require '../config/db_connect.php';

echo "<h2>ทดสอบ Database และ Position API</h2>";

// ทดสอบ receive_items
echo "<h3>1. ตรวจสอบ receive_items</h3>";
try {
    $sql = "SELECT r.receive_id, p.sku, p.name AS product_name 
            FROM receive_items r
            LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
            LEFT JOIN products p ON poi.product_id = p.product_id
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>พบข้อมูล: " . count($rows) . " รายการ</p>";
    if (count($rows) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Receive ID</th><th>SKU</th><th>Product Name</th></tr>";
        foreach ($rows as $row) {
            echo "<tr><td>{$row['receive_id']}</td><td>{$row['sku']}</td><td>{$row['product_name']}</td></tr>";
        }
        echo "</table>";
        
        // ทดสอบ Position API กับ receive_id แรก
        $testReceiveId = $rows[0]['receive_id'];
        echo "<h3>2. ทดสอบ Position API กับ receive_id = $testReceiveId</h3>";
        
        // หา product_id
        $sql2 = "SELECT poi.product_id
                FROM receive_items r
                LEFT JOIN purchase_order_items poi ON r.item_id = poi.item_id
                WHERE r.receive_id = ? LIMIT 1";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$testReceiveId]);
        $product_id = $stmt2->fetchColumn();
        
        echo "<p>Product ID: $product_id</p>";
        
        if ($product_id) {
            // หา location
            $sql3 = "SELECT l.row_code, l.bin, l.shelf
                     FROM product_location pl
                     LEFT JOIN locations l ON pl.location_id = l.location_id
                     WHERE pl.product_id = ? LIMIT 1";
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([$product_id]);
            $location = $stmt3->fetch(PDO::FETCH_ASSOC);
            
            if ($location) {
                echo "<p>Location: Row={$location['row_code']}, Bin={$location['bin']}, Shelf={$location['shelf']}</p>";
            } else {
                echo "<p>ไม่พบข้อมูล location</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// ทดสอบ API โดยตรง
echo "<h3>3. ทดสอบ Position API โดยตรง</h3>";
if (isset($testReceiveId)) {
    $apiUrl = "http://localhost/IchoicePMS---Copy/api/receive_position_api.php?receive_id=$testReceiveId";
    echo "<p>API URL: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";
    
    // ใช้ curl ทดสอบ
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>HTTP Code: $httpCode</p>";
    echo "<p>Response: <pre>$response</pre></p>";
}
?>

<p><a href="receive_items_view.php">กลับไปหน้าหลัก</a></p>