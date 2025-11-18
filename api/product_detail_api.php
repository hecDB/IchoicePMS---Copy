<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require '../config/db_connect.php';

$product_id = $_GET['product_id'] ?? '';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID สินค้า']);
    exit;
}

try {
    $sql = "SELECT p.*, pl.location_id, l.row_code, l.bin, l.shelf, l.description as location_description
            FROM products p
            LEFT JOIN product_location pl ON p.product_id = pl.product_id
            LEFT JOIN locations l ON pl.location_id = l.location_id
            WHERE p.product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('ไม่พบข้อมูลสินค้า');
    }
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
