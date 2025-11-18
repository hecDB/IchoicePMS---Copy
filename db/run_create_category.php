<?php
include 'config/db_connect.php';

$sql = file_get_contents('db/create_product_category_table.sql');
$statements = array_filter(explode(';', $sql), fn($s) => trim($s));

foreach($statements as $stmt) {
    if(trim($stmt)) {
        try {
            $pdo->exec($stmt);
            echo "✓ Statement executed successfully\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "✓ สร้างตาราง product_category สำเร็จ\n";
?>
