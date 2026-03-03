<?php
session_start();
require '../config/db_connect.php';

// Get the last 100 lines of error log
$error_log_file = ini_get('error_log');
if (!$error_log_file) {
    $error_log_file = '/var/log/php_errors.log';
}

echo "=== PHP Error Log Location ===\n";
echo "Error log: " . ($error_log_file ?: 'None configured') . "\n\n";

// Try to read error log
if ($error_log_file && file_exists($error_log_file)) {
    $lines = file($error_log_file);
    echo "=== Last 50 lines of error log ===\n";
    $last_lines = array_slice($lines, -50);
    foreach ($last_lines as $line) {
        echo $line;
    }
} else {
    echo "⚠️ Error log file not found or not configured\n\n";
}

// Check database connections and damaged_return_inspections table
echo "\n=== Database Check ===\n";
try {
    // Check if table exists
    $result = $pdo->query("SELECT COUNT(*) FROM damaged_return_inspections");
    $count = $result->fetchColumn();
    echo "✅ damaged_return_inspections table exists\n";
    echo "📊 Total records: $count\n\n";
    
    // Get sample records
    $samples = $pdo->query("
        SELECT inspection_id, return_id, return_code, status 
        FROM damaged_return_inspections 
        ORDER BY inspection_id DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Sample records:\n";
    var_dump($samples);
    
    // Check returns_items table
    echo "\n\n=== Check returned_items table ===\n";
    $returns = $pdo->query("
        SELECT return_id, return_code, reason_name 
        FROM returned_items 
        WHERE reason_name = 'สินค้าชำรุดบางส่วน'
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Returns with 'สินค้าชำรุดบางส่วน':\n";
    var_dump($returns);
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Test API directly
echo "\n\n=== Test API Call ===\n";

// Get first inspection_id
try {
    $result = $pdo->query("SELECT inspection_id FROM damaged_return_inspections WHERE status = 'pending' LIMIT 1");
    $first_inspection = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($first_inspection) {
        $inspection_id = $first_inspection['inspection_id'];
        echo "Testing with inspection_id: $inspection_id\n\n";
        
        // Simulate the API call
        $payload = [
            'action' => 'process_damaged_inspection',
            'inspection_id' => $inspection_id,
            'disposition' => 'sellable',
            'new_sku' => 'TEST-SKU',
            'restock_qty' => 10,
            'inspection_notes' => 'Test note',
            'cost_price' => 100,
            'sale_price' => 200
        ];
        
        echo "📤 Payload: " . json_encode($payload) . "\n\n";
        
        // Make the actual request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/api/returned_items_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "📥 HTTP Code: $http_code\n";
        echo "📥 Response:\n" . $response . "\n";
        
        // Try to decode
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo "\n✅ Valid JSON response:\n";
            var_dump($decoded);
        } else {
            echo "\n❌ Invalid JSON response - likely HTML error\n";
            echo "First 500 chars: " . substr($response, 0, 500) . "\n";
        }
    } else {
        echo "❌ No pending inspections found\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
