<?php
session_start();
require 'config/db_connect.php';

echo "<h2>Setup Cancel Item Feature</h2>";
echo "<pre>";

// 1. Check if purchase_order_items has the required columns
echo "Checking purchase_order_items table structure...\n";

$check_columns = "SHOW COLUMNS FROM purchase_order_items";
$stmt = $pdo->query($check_columns);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$column_names = array_column($columns, 'Field');

$required_columns = [
    'is_cancelled' => "ALTER TABLE purchase_order_items ADD COLUMN is_cancelled TINYINT(1) DEFAULT 0 AFTER qty",
    'cancelled_by' => "ALTER TABLE purchase_order_items ADD COLUMN cancelled_by INT AFTER is_cancelled",
    'cancelled_at' => "ALTER TABLE purchase_order_items ADD COLUMN cancelled_at DATETIME AFTER cancelled_by",
    'cancel_reason' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_reason VARCHAR(100) AFTER cancelled_at",
    'cancel_notes' => "ALTER TABLE purchase_order_items ADD COLUMN cancel_notes TEXT AFTER cancel_reason"
];

foreach ($required_columns as $col_name => $alter_sql) {
    if (in_array($col_name, $column_names)) {
        echo "✓ Column '{$col_name}' already exists\n";
    } else {
        try {
            $pdo->exec($alter_sql);
            echo "✓ Column '{$col_name}' added successfully\n";
        } catch (Exception $e) {
            echo "✗ Error adding column '{$col_name}': " . $e->getMessage() . "\n";
        }
    }
}

// 2. Check and create activity_logs table if not exists
echo "\nChecking activity_logs table...\n";
$check_activity_logs = "SHOW TABLES LIKE 'activity_logs'";
$stmt = $pdo->query($check_activity_logs);
$table_exists = $stmt->rowCount() > 0;

if ($table_exists) {
    echo "✓ activity_logs table already exists\n";
} else {
    try {
        $create_activity_logs = "
        CREATE TABLE activity_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100),
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )
        ";
        $pdo->exec($create_activity_logs);
        echo "✓ activity_logs table created successfully\n";
    } catch (Exception $e) {
        echo "✗ Error creating activity_logs table: " . $e->getMessage() . "\n";
    }
}

// 3. Display the updated structure
echo "\n\nUpdated purchase_order_items structure:\n";
$check_columns = "DESCRIBE purchase_order_items";
$stmt = $pdo->query($check_columns);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n✓ Setup completed successfully!\n";
echo "</pre>";

?>
