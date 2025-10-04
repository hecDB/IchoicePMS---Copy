<?php
// suppliers_api.php - API for getting suppliers list
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config/db_connect.php';

try {
    $stmt = $pdo->prepare("SELECT supplier_id, name, phone, email, address FROM suppliers ORDER BY name ASC");
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($suppliers, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>