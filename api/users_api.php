<?php
// users_api.php - API for getting users list
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config/db_connect.php';

try {
    $stmt = $pdo->prepare("SELECT user_id, name, department FROM users WHERE status = 'approved' ORDER BY name ASC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>