<?php
session_start();
require 'config/db_connect.php';

// ตรวจสอบการเข้าสู่ระบบ (optional - ลบออกได้ถ้าต้องการให้ใครก็เรียกได้)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: auth/combined_login_register.php');
//     exit;
// }

echo "<h1>Setup Cancel Item Feature - Database Schema</h1>";
echo "<hr>";

$results = [];

try {
    echo "<h2>1. Adding Columns to purchase_order_items</h2>";
    echo "<pre>";
    
    // Get existing columns
    $columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='purchase_order_items' AND TABLE_SCHEMA=DATABASE()";
    $stmt = $pdo->query($columns_sql);
    $existing_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    
    $columns_to_add = [
        'is_cancelled' => "ALTER TABLE purchase_order_items ADD COLUMN is_cancelled TINYINT(1) DEFAULT 0 AFTER unit_price",
        'cancelled_by' => "ALTER TABLE purchase_order_items ADD COLUMN cancelled_by INT AFTER is_cancelled",
        'cancelled_at' => "ALTER TABLE purchase_order_items ADD COLUMN cancelled_at DATETIME AFTER cancelled_by",
        'cancel_reason' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_reason VARCHAR(100) AFTER cancelled_at",
        'cancel_notes' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_notes TEXT AFTER cancel_reason"
    ];
    
    foreach ($columns_to_add as $col_name => $sql) {
        if (in_array($col_name, $existing_columns)) {
            echo "✓ Column '{$col_name}' already exists\n";
        } else {
            try {
                $pdo->exec($sql);
                echo "✓ Column '{$col_name}' added successfully\n";
                $results[] = "✓ Added {$col_name}";
            } catch (PDOException $e) {
                echo "✗ Error adding '{$col_name}': " . $e->getMessage() . "\n";
                $results[] = "✗ Error adding {$col_name}";
            }
        }
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    $results[] = "✗ " . $e->getMessage();
}

try {
    echo "<h2>2. Creating activity_logs Table</h2>";
    echo "<pre>";
    
    // Check if activity_logs exists
    $check_sql = "SHOW TABLES LIKE 'activity_logs'";
    $stmt = $pdo->query($check_sql);
    
    if ($stmt->rowCount() > 0) {
        echo "✓ activity_logs table already exists\n";
    } else {
        try {
            $create_sql = "CREATE TABLE activity_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(100),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $pdo->exec($create_sql);
            echo "✓ activity_logs table created successfully\n";
            $results[] = "✓ Created activity_logs table";
        } catch (PDOException $e) {
            echo "✗ Error creating activity_logs: " . $e->getMessage() . "\n";
            $results[] = "✗ Error creating activity_logs";
        }
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

try {
    echo "<h2>3. Final Schema Check</h2>";
    echo "<pre>";
    
    $check_sql = "DESCRIBE purchase_order_items";
    $stmt = $pdo->query($check_sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current purchase_order_items columns:\n";
    $col_names = [];
    foreach ($columns as $col) {
        $col_names[] = $col['Field'];
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Check cancel columns
    echo "\nCancel-related columns status:\n";
    $cancel_cols = ['is_cancelled', 'cancelled_by', 'cancelled_at', 'cancel_reason', 'cancel_notes'];
    $all_present = true;
    foreach ($cancel_cols as $col) {
        $exists = in_array($col, $col_names);
        echo ($exists ? "✓" : "✗") . " {$col}\n";
        if (!$exists) $all_present = false;
    }
    
    if ($all_present) {
        echo "\n✓ All cancel columns are present!\n";
        $results[] = "✓ All cancel columns verified";
    } else {
        echo "\n✗ Some cancel columns are missing\n";
        $results[] = "✗ Some cancel columns still missing";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Summary</h2>";
echo "<ul style='font-size: 16px; line-height: 2;'>";
foreach ($results as $result) {
    $style = strpos($result, '✓') === 0 ? 'color: green;' : 'color: red;';
    echo "<li style='{$style}'>$result</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><a href='receive/receive_po_items.php'>← Back to Receive Items</a></p>";

?>
