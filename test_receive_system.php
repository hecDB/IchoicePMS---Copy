<?php
session_start();
require 'config/db_connect.php';

// ทดสอบการเชื่อมต่อฐานข้อมูล
echo "<h2>Test Database Connection</h2>";
echo "<pre>";

try {
    // ทดสอบ PO
    $sql = "SELECT po_id, po_number, supplier_id FROM purchase_orders LIMIT 1";
    $stmt = $pdo->query($sql);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($po) {
        echo "✓ Database connected successfully\n";
        echo "Test PO: " . json_encode($po, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "✗ No PO found\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// ทดสอบ API
echo "\n<h2>Test API Endpoints</h2>";
echo "<pre>";

// ทดสอบ get_po_items.php
echo "Testing get_po_items.php API...\n";
if ($po) {
    $url = 'http://localhost/IchoicePMS---Copy/api/get_po_items.php?po_id=' . $po['po_id'];
    echo "URL: {$url}\n";
    
    // Try direct curl call
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            echo "Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "✗ Curl error: {$error}\n";
        }
    } else {
        $response = @file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            echo "Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "✗ Failed to call API (file_get_contents disabled or network error)\n";
        }
    }
}

echo "</pre>";

// ทดสอบ purchase_order_items schema
echo "\n<h2>Check purchase_order_items Schema</h2>";
echo "<pre>";

try {
    $columns_sql = "DESCRIBE purchase_order_items";
    $stmt = $pdo->query($columns_sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in purchase_order_items:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // ตรวจสอบ cancel columns
    $cancel_cols = ['is_cancelled', 'cancelled_by', 'cancelled_at', 'cancel_reason', 'cancel_notes'];
    echo "\nCancel-related columns:\n";
    $existing_cols = array_column($columns, 'Field');
    foreach ($cancel_cols as $col) {
        $exists = in_array($col, $existing_cols) ? '✓' : '✗';
        echo "  $exists $col\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// ทดสอบ activity_logs table
echo "\n<h2>Check activity_logs Table</h2>";
echo "<pre>";

try {
    $check_sql = "SHOW TABLES LIKE 'activity_logs'";
    $stmt = $pdo->query($check_sql);
    
    if ($stmt->rowCount() > 0) {
        echo "✓ activity_logs table exists\n";
        
        $describe_sql = "DESCRIBE activity_logs";
        $stmt = $pdo->query($describe_sql);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Columns:\n";
        foreach ($cols as $col) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } else {
        echo "✗ activity_logs table does not exist\n";
        echo "Run: http://localhost/IchoicePMS---Copy/setup_cancel_columns.php\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "\n<hr>";
echo "<h2>Setup Instructions</h2>";
echo "<p>If you see ✗ marks above, run this setup script:</p>";
echo "<p><strong><a href='setup_cancel_columns.php' target='_blank'>Click here to setup database schema</a></strong></p>";

?>

