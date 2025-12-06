<?php
session_start();
require '../config/db_connect.php';

// Check user session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Get cancelled items count
try {
    $sql = "
        SELECT COUNT(DISTINCT poi.po_id) as cancelled_count
        FROM purchase_order_items poi
        LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
        WHERE (poi.is_cancelled = 1 OR poi.is_partially_cancelled = 1)
        AND po.status IN ('pending', 'partial', 'completed')
    ";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => $result['cancelled_count'] ?? 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
