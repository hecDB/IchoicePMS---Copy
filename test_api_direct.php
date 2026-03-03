<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock the POST request with JSON payload
$testPayload = json_encode([
    'action' => 'process_damaged_inspection',
    'inspection_id' => 8,
    'disposition' => 'sellable',
    'new_sku' => 'ตำหนิ-prd005',
    'restock_qty' => 1,
    'inspection_notes' => 'test inspection',
    'cost_price' => 100,
    'sale_price' => 200,
    'expiry_date' => '2027-03-31'
]);

// Create a stream wrapper for php://input
$f = fopen('php://memory', 'r+');
fwrite($f, $testPayload);
rewind($f);

// Override superglobal
$GLOBALS['_POST'] = json_decode($testPayload, true);

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['CONTENT_LENGTH'] = strlen($testPayload);

// Mock session
$_SESSION['user_id'] = 1;

// Capture all output
ob_start();

try {
    // Temporarily replace php://input
    $php_input_backup = file_get_contents('php://input');
    
    // Include API - this should trigger the action
    if (true) {
        include 'api/returned_items_api.php';
    }
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

$output = ob_get_clean();
echo $output;
?>
