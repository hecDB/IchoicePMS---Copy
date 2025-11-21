<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db_connect.php';
require_once '../auth/auth_check.php';

// Check permission
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
if ($user_role !== 'admin' && $user_role !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Generate next PO number with format: PO-New-YYYY-NNNNN
    // Example: PO-New-2025-00001
    // This will be used as temporary PO number; the actual format will be determined when saved
    
    $year = date('Y');
    
    // Find the highest PO number with PO-New prefix for current year
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(po_number, LOCATE('-', po_number, 8) + 1) AS UNSIGNED)) as max_num
        FROM purchase_orders 
        WHERE po_number LIKE CONCAT('PO-New-', ?, '-%')
    ");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_num = ($result['max_num'] ?? 0) + 1;
    $po_number = 'PO-New-' . $year . '-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
    
    // Check if PO number already exists (shouldn't happen, but just in case)
    $stmt_check = $pdo->prepare("SELECT po_id FROM purchase_orders WHERE po_number = ?");
    $stmt_check->execute([$po_number]);
    
    if ($stmt_check->rowCount() > 0) {
        // Retry with a higher number
        $next_num++;
        $po_number = 'PO-New-' . $year . '-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
    }
    
    echo json_encode([
        'success' => true,
        'po_number' => $po_number
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
