<?php
// Execute SQL to add category columns
require_once 'config/db_connect.php';

try {
    $pdo->beginTransaction();
    
    // 1. Create product_category table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_category (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    echo "✓ Created product_category table\n";
    
    // 2. Check if product_category_id column already exists
    $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='products' AND COLUMN_NAME='product_category_id'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN product_category_id INT DEFAULT NULL");
        echo "✓ Added product_category_id column\n";
    } else {
        echo "✓ product_category_id column already exists\n";
    }
    
    // 3. Check if foreign key already exists
    $result = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='products' AND CONSTRAINT_NAME='fk_products_category'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (product_category_id) REFERENCES product_category(category_id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✓ Added foreign key constraint\n";
    } else {
        echo "✓ Foreign key constraint already exists\n";
    }
    
    // 4. Check if category_name column already exists
    $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='products' AND COLUMN_NAME='category_name'");
    if ($result->rowCount() === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category_name VARCHAR(100) COMMENT 'ชื่อประเภท' DEFAULT NULL");
        echo "✓ Added category_name column\n";
    } else {
        echo "✓ category_name column already exists\n";
    }
    
    // 5. Check if index already exists
    $result = $pdo->query("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME='products' AND INDEX_NAME='idx_products_category'");
    if ($result->rowCount() === 0) {
        $pdo->exec("CREATE INDEX idx_products_category ON products(product_category_id)");
        echo "✓ Created index idx_products_category\n";
    } else {
        echo "✓ Index idx_products_category already exists\n";
    }
    
    $pdo->commit();
    echo "\n✅ All database updates completed successfully!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
