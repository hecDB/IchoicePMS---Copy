<?php
require 'config/db_connect.php';

echo "=== DAMAGED_RETURN_INSPECTIONS COLUMNS ===\n";
$stmt = $pdo->prepare('DESCRIBE damaged_return_inspections');
$stmt->execute();
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== RETURNED_ITEMS COLUMNS ===\n";
$stmt = $pdo->prepare('DESCRIBE returned_items');
$stmt->execute();
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

// Check for records with damaged reason
echo "\n=== CHECK DATA IN returned_items (DAMAGED RECORDS) ===\n";
$stmt = $pdo->prepare("
    SELECT ri.return_id, ri.return_code, ri.reason_id, r.reason_name, ri.return_status, ri.created_at
    FROM returned_items ri
    LEFT JOIN return_reasons r ON ri.reason_id = r.reason_id
    WHERE r.reason_name LIKE '%ชำรุด%'
    LIMIT 5
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== CHECK DATA IN damaged_return_inspections ===\n";
$stmt = $pdo->prepare("
    SELECT inspection_id, return_id, return_code, product_name, status, created_at
    FROM damaged_return_inspections
    LIMIT 5
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Check relationships
echo "\n=== FOREIGN KEY RELATIONSHIP ===\n";
$stmt = $pdo->prepare("
    SELECT COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'damaged_return_inspections'
    AND CONSTRAINT_NAME != 'PRIMARY'
");
$stmt->execute();
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($fks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Check if there are duplicated data
echo "\n=== CHECK IF SAME DATA EXISTS IN BOTH TABLES ===\n";
$stmt = $pdo->prepare("
    SELECT di.inspection_id, di.return_id, di.return_code, 
           ri.return_id as returned_items_id, ri.return_code as ri_return_code
    FROM damaged_return_inspections di
    LEFT JOIN returned_items ri ON di.return_id = ri.return_id
    LIMIT 5
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
?>
