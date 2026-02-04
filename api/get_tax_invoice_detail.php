<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/db_connect.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing invoice id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tax_invoices WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $itemStmt = $pdo->prepare("SELECT item_no, name, qty, unit, price, line_total FROM tax_invoice_items WHERE invoice_id = :id ORDER BY item_no ASC");
    $itemStmt->execute([':id' => $id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'invoice' => $invoice,
        'items' => $items
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
