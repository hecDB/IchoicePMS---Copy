<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simulate the exact request being sent by the browser

// Set up session with user
session_start();
$_SESSION['user_id'] = 1;

echo "Session user_id: " . ($_SESSION['user_id'] ?? 'N/A') . "\n";

// Create test payload
$testData = [
    'action' => 'process_damaged_inspection',
    'inspection_id' => 8,
    'disposition' => 'sellable',
    'new_sku' => 'ตำหนิ-prd005',
    'restock_qty' => 1.0,
    'inspection_notes' => 'test',
    'cost_price' => 100.0,
    'sale_price' => 200.0,
    'expiry_date' => '2027-03-31'
];

$json = json_encode($testData);
echo "Test Payload: " . $json . "\n\n";

// Mock php://input
$GLOBALS['_stdin_content'] = $json;
$GLOBALS['_read_stdin'] = true;

// Override file_get_contents for php://input
function myFileGetContents($path) {
    if ($path === 'php://input' && isset($GLOBALS['_read_stdin'])) {
        return $GLOBALS['_stdin_content'];
    }
    return \file_get_contents($path);
}

// Can't easily override file_get_contents globally, so let's just check if the API includes work
// by checking if required files exist

echo "Checking if all required files exist:\n";
echo "config/db_connect.php: " . (file_exists('config/db_connect.php') ? "✓" : "✗") . "\n";

// Try to include just the beginning of the API to see if there are any syntax errors
$apiStart = file_get_contents('api/returned_items_api.php', false, null, 0, 500);
echo "\nFirst 500 bytes of API:\n";
echo substr($apiStart, 0, 300) . "...\n";
?>
