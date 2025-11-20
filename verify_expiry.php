<?php
// Simple script to verify expiry_date data in database
header('Content-Type: application/json');
require 'config/db_connect.php';

$receive_id = isset($_GET['receive_id']) ? intval($_GET['receive_id']) : 0;

if ($receive_id <= 0) {
    // Show last 10 receive items
    $sql = "SELECT receive_id, item_id, po_id, receive_qty, expiry_date, remark, created_at 
            FROM receive_items 
            ORDER BY receive_id DESC 
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Show specific receive_id
    $sql = "SELECT receive_id, item_id, po_id, receive_qty, expiry_date, remark, created_at 
            FROM receive_items 
            WHERE receive_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$receive_id]);
    $results = $stmt->fetch(PDO::FETCH_ASSOC);
}

echo json_encode([
    'success' => true,
    'data' => $results,
    'query_time' => date('Y-m-d H:i:s')
]);
