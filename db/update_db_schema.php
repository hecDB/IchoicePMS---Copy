<?php
include 'config/db_connect.php';

try {
    $sql = file_get_contents('db/create_sales_orders_table.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo 'Executed: ' . substr($statement, 0, 50) . '...' . PHP_EOL;
        }
    }
    echo 'Database schema updated successfully!' . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>