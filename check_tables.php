<?php
require 'config/db_connect.php';

echo "<h2>Check Database Tables</h2>";
echo "<pre>";

// ตรวจสอบทุกตารางที่เกี่ยวกับ category
$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'ichoice_' AND TABLE_NAME LIKE '%categor%'";
$stmt = $pdo->query($sql);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Tables with 'category' in name:\n";
if (empty($tables)) {
    echo "  ❌ None found\n\n";
} else {
    foreach ($tables as $t) {
        echo "  ✓ " . $t['TABLE_NAME'] . "\n";
    }
    echo "\n";
}

// ตรวจสอบทุกตารางใน database
echo "All tables in database:\n";
$sql_all = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'ichoice_' ORDER BY TABLE_NAME";
$stmt_all = $pdo->query($sql_all);
$all_tables = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_tables as $t) {
    echo "  - " . $t['TABLE_NAME'] . "\n";
}

echo "\n";

// ตรวจสอบ temp_products fields
echo "temp_products fields:\n";
$sql_desc = "DESCRIBE temp_products";
$stmt_desc = $pdo->query($sql_desc);
$fields = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);

foreach ($fields as $f) {
    echo "  - " . $f['Field'] . " (" . $f['Type'] . ")\n";
}

echo "\n";

// ตรวจสอบ products fields
echo "products fields:\n";
$sql_prod = "DESCRIBE products";
$stmt_prod = $pdo->query($sql_prod);
$prod_fields = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

foreach ($prod_fields as $f) {
    echo "  - " . $f['Field'] . " (" . $f['Type'] . ")\n";
}

echo "</pre>";

?>
