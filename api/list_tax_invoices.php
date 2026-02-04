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

try {
    $where = [];
    $params = [];

    $q = trim($_GET['q'] ?? '');
    $invNo = trim($_GET['inv_no'] ?? '');
    $customer = trim($_GET['customer'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $platform = trim($_GET['platform'] ?? '');
    $payment = trim($_GET['payment_method'] ?? '');

    if ($q !== '') {
        $where[] = '(inv_no LIKE :q OR customer LIKE :q OR ref_no LIKE :q)';
        $params[':q'] = "%$q%";
    }
    if ($invNo !== '') {
        $where[] = 'inv_no LIKE :inv_no';
        $params[':inv_no'] = "%$invNo%";
    }
    if ($customer !== '') {
        $where[] = 'customer LIKE :customer';
        $params[':customer'] = "%$customer%";
    }
    if ($dateFrom !== '') {
        $where[] = 'inv_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'inv_date <= :date_to';
        $params[':date_to'] = $dateTo;
    }
    if ($platform !== '') {
        $where[] = 'platform = :platform';
        $params[':platform'] = $platform;
    }
    if ($payment !== '') {
        $where[] = 'payment_method = :payment_method';
        $params[':payment_method'] = $payment;
    }

    $sql = "SELECT id, inv_no, inv_date, customer, platform, payment_method, grand_total, payable, ref_no, created_at
            FROM tax_invoices";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY inv_date DESC, id DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
