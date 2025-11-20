<?php
require 'config/db_connect.php';

echo "<h2>Check product_category Table</h2>";
echo "<pre>";

echo "product_category fields:\n";
$sql = "DESCRIBE product_category";
$stmt = $pdo->query($sql);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($fields as $f) {
    echo "  - " . $f['Field'] . " (" . $f['Type'] . ")\n";
}

echo "\n";

echo "Sample data from product_category:\n";
$sql_data = "SELECT * FROM product_category LIMIT 5";
$stmt_data = $pdo->query($sql_data);
$rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "  ";
    foreach ($row as $k => $v) {
        echo $k . "=" . $v . " | ";
    }
    echo "\n";
}

echo "</pre>";

?>
