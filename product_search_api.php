<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 1);

include 'db_connect.php'; // เชื่อมฐานข้อมูล

// รับ query จาก URL
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if($q === '') {
    echo json_encode([]);
    exit;
}

// เตรียมคำค้นแบบ %keyword%
$like = "%$q%";

try {
    $stmt = $pdo->prepare("
    SELECT product_id, name, sku, barcode, unit, image
    FROM products
    WHERE name LIKE ? 
       OR sku LIKE ?
       OR barcode LIKE ?
    ORDER BY 
        CASE 
            WHEN name LIKE ? THEN 1 
            WHEN sku LIKE ? THEN 2
            WHEN barcode LIKE ? THEN 3
            ELSE 4 
        END,
        name ASC
    LIMIT 10
");
    // bind parameter ทั้ง 6 ช่อง
    $stmt->execute([$like, $like, $like, $like, $like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
